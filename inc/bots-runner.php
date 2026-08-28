<?php
/**
 * Runner des bots — recherche assistée par OmniRoute.
 *
 * OmniRoute est une passerelle IA installée sur ce serveur (~/omniroute), qui
 * écoute en local sur le port 20128 et expose une API compatible OpenAI. Elle
 * n'est pas joignable depuis internet : PHP l'appelle sur 127.0.0.1, la machine
 * se parle à elle-même. Aucune clé ne circule, rien n'est exposé.
 *
 * Principe de conception : le modèle ne cherche pas, il LIT.
 *
 * Demander « trouve les subventions » à un modèle gratuit produit des dates
 * inventées. On télécharge donc nous-mêmes les pages officielles, et le modèle
 * n'a plus qu'à extraire ce qui s'y trouve. Il ne peut pas halluciner une
 * échéance absente du texte qu'on lui met sous les yeux.
 *
 * Corollaire : ce runner surveille une liste d'URL connues. Il ne découvre pas
 * un fonds créé l'an dernier dont personne ne lui a parlé — cette partie-là
 * reste du ressort d'une vraie recherche web.
 */

if ( ! defined( 'MD_OMNIROUTE_BASE' ) ) {
    define( 'MD_OMNIROUTE_BASE', 'http://127.0.0.1:20128/v1' );
}
if ( ! defined( 'MD_OMNIROUTE_MODEL' ) ) {
    define( 'MD_OMNIROUTE_MODEL', 'auto/best-reasoning' );
}
if ( ! defined( 'MD_OMNIROUTE_DIR' ) ) {
    define( 'MD_OMNIROUTE_DIR', '/home/clients/c0622062ac5c4b58ca02dc461b4fc240/omniroute' );
}
if ( ! defined( 'MD_NODE_BIN' ) ) {
    define( 'MD_NODE_BIN', '/home/clients/c0622062ac5c4b58ca02dc461b4fc240/opt/node/bin' );
}

// ==========================================================================
// Santé d'OmniRoute et veilleur
// ==========================================================================

/**
 * OmniRoute répond-il ?
 */
function md_omniroute_up() {
    $r = wp_remote_get( MD_OMNIROUTE_BASE . '/models', [ 'timeout' => 8 ] );

    return ! is_wp_error( $r ) && 200 === (int) wp_remote_retrieve_response_code( $r );
}

/**
 * Relance OmniRoute en arrière-plan.
 *
 * Détaché du processus PHP courant : sans nohup et sans redirection, il
 * mourrait avec la requête qui l'a lancé.
 */
function md_omniroute_start() {
    if ( ! function_exists( 'exec' ) ) {
        return false;
    }

    $cmd = sprintf(
        'cd %s && PATH=%s:$PATH nohup %s/npx omniroute > %s/omniroute.log 2>&1 &',
        escapeshellarg( MD_OMNIROUTE_DIR ),
        escapeshellarg( MD_NODE_BIN ),
        escapeshellarg( MD_NODE_BIN ),
        escapeshellarg( MD_OMNIROUTE_DIR )
    );

    exec( $cmd );

    // Le démarrage prend quelques secondes ; on laisse le temps de lier le port.
    for ( $i = 0; $i < 10; $i++ ) {
        sleep( 3 );
        if ( md_omniroute_up() ) {
            return true;
        }
    }

    return false;
}

/**
 * Veilleur horaire : relance OmniRoute s'il ne répond plus.
 *
 * Un bot mort en silence est pire qu'un bot absent : on découvrirait la panne
 * le jour où on a besoin du résultat. L'état est journalisé pour que la page
 * d'administration puisse l'afficher.
 */
function md_bots_watchdog() {
    if ( md_omniroute_up() ) {
        update_option( 'md_omniroute_status', [
            'etat'      => 'actif',
            'verifie_le' => current_time( 'mysql' ),
        ] );
        return;
    }

    $relance = md_omniroute_start();

    update_option( 'md_omniroute_status', [
        'etat'       => $relance ? 'relance' : 'hors service',
        'verifie_le' => current_time( 'mysql' ),
    ] );
}
add_action( 'md_bots_watchdog_event', 'md_bots_watchdog' );

/**
 * Planifie le veilleur.
 */
function md_bots_schedule_watchdog() {
    if ( ! wp_next_scheduled( 'md_bots_watchdog_event' ) ) {
        wp_schedule_event( time() + 300, 'hourly', 'md_bots_watchdog_event' );
    }
}
add_action( 'init', 'md_bots_schedule_watchdog' );

// ==========================================================================
// Appel du modèle
// ==========================================================================

/**
 * Interroge OmniRoute et renvoie le texte de la réponse.
 *
 * @return string|WP_Error
 */
function md_omniroute_complete( $system, $user, $timeout = 120 ) {
    $body = [
        'model'       => MD_OMNIROUTE_MODEL,
        'stream'      => false,
        'temperature' => 0,
        'messages'    => [
            [ 'role' => 'system', 'content' => $system ],
            [ 'role' => 'user', 'content' => $user ],
        ],
    ];

    // Les paliers gratuits refusent régulièrement (429). Une seule tentative
    // ferait passer une limite de débit momentanée pour une source illisible.
    $tentatives = 3;
    $r          = null;
    $code       = 0;

    for ( $i = 0; $i < $tentatives; $i++ ) {
        if ( $i > 0 ) {
            sleep( 5 * $i );
        }

        $r = wp_remote_post( MD_OMNIROUTE_BASE . '/chat/completions', [
            'timeout' => $timeout,
            'headers' => [ 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( $body ),
        ] );

        if ( is_wp_error( $r ) ) {
            return $r;
        }

        $code = (int) wp_remote_retrieve_response_code( $r );
        if ( 429 !== $code && 503 !== $code ) {
            break;
        }
    }

    if ( 200 !== $code ) {
        return new WP_Error(
            'omniroute_http',
            sprintf(
                429 === $code ? 'quota gratuit épuisé (429) après %2$d tentatives' : 'OmniRoute a répondu %1$d',
                $code,
                $tentatives
            )
        );
    }

    $data = json_decode( wp_remote_retrieve_body( $r ), true );
    if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
        return new WP_Error( 'omniroute_format', 'Réponse illisible d\'OmniRoute.' );
    }

    return (string) $data['choices'][0]['message']['content'];
}

/**
 * Extrait le premier objet JSON d'une réponse de modèle.
 *
 * Les modèles gratuits encadrent volontiers leur JSON de texte ou de balises
 * markdown. On récupère ce qui se trouve entre la première accolade ouvrante et
 * la dernière fermante plutôt que d'exiger une réponse parfaite.
 *
 * @return array|null
 */
function md_json_from_reply( $texte ) {
    $texte = preg_replace( '/^\s*```(?:json)?|```\s*$/mi', '', (string) $texte );

    $debut = strpos( $texte, '{' );
    $fin   = strrpos( $texte, '}' );

    if ( false === $debut || false === $fin || $fin <= $debut ) {
        return null;
    }

    $data = json_decode( substr( $texte, $debut, $fin - $debut + 1 ), true );

    return is_array( $data ) ? $data : null;
}

// ==========================================================================
// Sources et lecture des pages
// ==========================================================================

/**
 * Pages officielles surveillées pour les subventions.
 *
 * Chaque URL a été vérifiée : elle renvoie 200. Ce point mérite d'être dit,
 * parce que la première version de cette liste contenait des adresses
 * plausibles mais inventées, qui renvoyaient toutes 404 — une liste de sources
 * qui ne se charge pas produit un rapport vide qu'on prend pour « rien à
 * signaler ».
 *
 * À compléter au fil des découvertes. Toute URL ajoutée ici doit avoir été
 * ouverte et vérifiée, jamais déduite d'une logique d'arborescence.
 */
function md_bots_sources_subventions() {
    return [
        // 'index' => true : la page ne décrit pas de dispositif, elle liste des
        // liens vers les programmes. Le runner descend alors d'un niveau.
        [ 'nom' => 'Ville de Genève — soutien à la culture',        'url' => 'https://www.geneve.ch/fr/themes/culture/soutien-culture', 'index' => true ],
        [ 'nom' => 'Canton de Genève — organismes culturels',       'url' => 'https://www.ge.ch/soutien-aux-organismes-culturels-specialises' ],
        [ 'nom' => 'Canton de Genève — rayonnement culturel',       'url' => 'https://www.ge.ch/soutien-au-rayonnement-culturel' ],
        [ 'nom' => 'FCMA — Fondation romande musiques actuelles',   'url' => 'https://www.fcma.ch/soutiens/', 'index' => true ],
        [ 'nom' => 'Fondation SUISA — offres de soutien',           'url' => 'https://fondation-suisa.ch/fr/offres-de-soutien/', 'index' => true ],
        [ 'nom' => 'Pro Helvetia — domaines d\'encouragement',      'url' => 'https://prohelvetia.ch/fr/nos-domaines-dencouragement/', 'index' => true ],
        [ 'nom' => 'Fonds culturel Sud',                            'url' => 'https://www.fondsculturelsud.ch/', 'index' => true ],
    ];
}

/**
 * Liens de programmes trouvés sur une page d'index.
 *
 * Une page d'index ne décrit aucun dispositif : elle renvoie vers eux. En
 * l'extrayant telle quelle, on récolte des libellés de menu (« Bourses »,
 * « Subventions ») sans montant ni échéance — beaucoup de lignes, aucune
 * information. On descend donc d'un niveau pour lire les vraies pages.
 *
 * Aucune IA ici : filtrer des liens est de la manipulation de chaînes.
 *
 * @return array Liste d'URL absolues, même domaine, limitée à $max.
 */
function md_bots_links_from( $url, $max = 4, $mots = null ) {
    $r = wp_remote_get( $url, [
        'timeout'    => 30,
        'user-agent' => 'Mozilla/5.0 (compatible; MangoDragonBot/1.0; +https://mango-dragon.com)',
    ] );

    if ( is_wp_error( $r ) ) {
        return [];
    }

    $html = wp_remote_retrieve_body( $r );
    if ( ! preg_match_all( '~href=["\']([^"\']+)["\']~i', $html, $m ) ) {
        return [];
    }

    $base   = wp_parse_url( $url );
    $racine = ( $base['scheme'] ?? 'https' ) . '://' . ( $base['host'] ?? '' );
    // Les mots-clés dépendent du bot : ceux des subventions ne trouvent rien
    // sur un site de média musical, où l'information utile vit sous « contact »
    // ou « submit ». Une première version imposait la liste des subventions à
    // tous les bots — le bot Promo retombait alors sur les pages d'accueil et
    // n'en tirait qu'une fiche sur sept.
    if ( null === $mots ) {
        $mots = '~(soutien|subvention|bourse|encouragement|fonds|contribution|appel-a|candidat|aide-a-l)~i';
    }

    // Deux familles de rejets, apprises à l'usage :
    // — les fichiers et les points d'entrée d'API, qui ne sont pas des pages ;
    // — les aides NON culturelles, que « aide » et « demande » attrapaient en
    //   masse sur les sites d'administration (logement, école, social).
    $exclus = '~(\.(pdf|jpe?g|png|gif|svg|webp|avif|css|js|zip|docx?)($|\?)'
        . '|wp-json|/feed|oembed|/api/'
        . '|logement|scolaire|sociale?[-/]|chomage|assurance|impot|handicap|sante|petite-enfance)~i';

    $liens = [];

    foreach ( $m[1] as $href ) {
        $href = html_entity_decode( trim( $href ), ENT_QUOTES, 'UTF-8' );

        if ( '' === $href || '#' === $href[0] || preg_match( '~^(mailto:|tel:|javascript:)~i', $href ) ) {
            continue;
        }
        if ( preg_match( $exclus, $href ) || ! preg_match( $mots, $href ) ) {
            continue;
        }

        if ( 0 === strpos( $href, '//' ) ) {
            $abs = ( $base['scheme'] ?? 'https' ) . ':' . $href;
        } elseif ( 0 === strpos( $href, '/' ) ) {
            $abs = $racine . $href;
        } elseif ( preg_match( '~^https?://~i', $href ) ) {
            $abs = $href;
        } else {
            continue;
        }

        // On reste sur le domaine de la source : un lien sortant mène rarement
        // à un dispositif de cet organisme.
        $h = wp_parse_url( $abs, PHP_URL_HOST );
        if ( ! $h || false === strpos( $h, ltrim( (string) ( $base['host'] ?? '' ), 'w.' ) ) ) {
            continue;
        }

        $abs = strtok( $abs, '#' );
        if ( $abs !== $url && ! in_array( $abs, $liens, true ) ) {
            $liens[] = $abs;
        }

        if ( count( $liens ) >= $max ) {
            break;
        }
    }

    return $liens;
}

/**
 * Développe les pages d'index en pages de programmes.
 *
 * @return array Sources à lire réellement.
 */
function md_bots_expand_sources( array $sources, $mots = null ) {
    $final = [];

    foreach ( $sources as $src ) {
        if ( empty( $src['index'] ) ) {
            $final[] = $src;
            continue;
        }

        $liens = md_bots_links_from( $src['url'], 4, $mots );

        if ( empty( $liens ) ) {
            // Aucun sous-lien : on lit la page d'index faute de mieux.
            $final[] = $src;
            continue;
        }

        foreach ( $liens as $lien ) {
            $final[] = [ 'nom' => $src['nom'], 'url' => $lien ];
        }
    }

    return $final;
}

/**
 * Télécharge une page et la réduit à du texte lisible.
 *
 * Le modèle n'a pas besoin du HTML, et le lui envoyer gaspillerait le contexte
 * disponible sur du balisage.
 *
 * @return string|WP_Error
 */
function md_bots_page_text( $url, $max = 18000 ) {
    $r = wp_remote_get( $url, [
        'timeout'    => 30,
        'user-agent' => 'Mozilla/5.0 (compatible; MangoDragonBot/1.0; +https://mango-dragon.com)',
    ] );

    if ( is_wp_error( $r ) ) {
        return $r;
    }

    $code = (int) wp_remote_retrieve_response_code( $r );
    if ( $code < 200 || $code >= 300 ) {
        return new WP_Error( 'fetch_http', sprintf( 'Page inaccessible (code %d)', $code ) );
    }

    $html = wp_remote_retrieve_body( $r );
    $html = preg_replace( '~<(script|style|nav|footer|svg)\b[^>]*>.*?</\1>~is', ' ', $html );
    $txt  = wp_strip_all_tags( $html );
    $txt  = html_entity_decode( $txt, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    $txt  = preg_replace( '/[ \t]+/', ' ', $txt );
    $txt  = preg_replace( '/\n{3,}/', "\n\n", $txt );
    $txt  = trim( $txt );

    if ( '' === $txt ) {
        return new WP_Error( 'fetch_empty', 'Page vide après nettoyage.' );
    }

    return mb_substr( $txt, 0, $max );
}

// ==========================================================================
// Extraction
// ==========================================================================

/**
 * Fait extraire par le modèle les dispositifs présents dans le texte fourni.
 *
 * @return array|WP_Error Liste d'entrées.
 */
function md_bots_extract_subventions( $source, $texte ) {
    $system = "Tu es un assistant qui EXTRAIT des informations d'un texte fourni. "
        . "Tu ne réponds jamais de mémoire et tu n'ajoutes aucune connaissance extérieure. "
        . "Si une information n'apparaît pas littéralement dans le texte, tu écris null. "
        . "Tu réponds uniquement par du JSON valide, sans commentaire ni balise markdown.";

    $user = "Voici le texte d'une page consacrée à des aides financières culturelles.\n\n"
        . "Contexte du demandeur : label associatif de musiques électroniques et expérimentales, "
        . "basé à Genève, une dizaine d'artistes, cherche à financer une sortie vinyle.\n\n"
        . "Extrais chaque dispositif d'aide DÉCRIT DANS LE TEXTE, au format :\n"
        . '{"dispositifs":[{"titre":"...","organisme":"...","montant":"... ou null",'
        . '"echeance":"AAAA-MM-JJ ou null","eligibilite":"... ou null","pertinent":true|false,'
        . '"motif":"pourquoi non pertinent, sinon null"}]}' . "\n\n"
        . "Règles strictes :\n"
        . "- echeance UNIQUEMENT si une date figure dans le texte. Sinon null. N'invente jamais de date.\n"
        . "- montant UNIQUEMENT si un chiffre figure dans le texte.\n"
        . "- pertinent = false si l'aide vise un autre domaine (jazz, classique, patrimoine, théâtre...).\n"
        . "- Aucun dispositif décrit ? Réponds {\"dispositifs\":[]}.\n\n"
        . "SOURCE : " . $source . "\n\n=== TEXTE ===\n" . $texte;

    $reponse = md_omniroute_complete( $system, $user );
    if ( is_wp_error( $reponse ) ) {
        return $reponse;
    }

    $data = md_json_from_reply( $reponse );
    if ( null === $data || ! isset( $data['dispositifs'] ) || ! is_array( $data['dispositifs'] ) ) {
        return new WP_Error( 'extract_format', 'Le modèle n\'a pas renvoyé de JSON exploitable.' );
    }

    return $data['dispositifs'];
}

// ==========================================================================
// Orchestration
// ==========================================================================

/**
 * Boucle d'exécution commune à tous les bots.
 *
 * Chaque bot fournit ses sources, son extracteur et son convertisseur en
 * entrées d'affichage ; le reste — budget de temps, pages en échec, garde-fou
 * contre la troncature silencieuse — est identique et vit ici.
 *
 * @param array    $sources    Sources déjà développées.
 * @param callable $extraire   fn( string $nom, string $texte ) => array|WP_Error
 * @param callable $convertir  fn( array $brut, array $src ) => array|null
 * @param string   $option     Option WordPress où écrire.
 * @param callable $resumer    fn( array $entrees, int $lues, int $vides ) => string
 * @return array
 */
function md_bots_run_generic( $sources, $extraire, $convertir, $option, $resumer ) {
    if ( ! md_omniroute_up() && ! md_omniroute_start() ) {
        return [ 'ok' => false, 'message' => 'OmniRoute ne répond pas et n\'a pas pu être relancé.' ];
    }

    $entrees = [];
    $echecs  = [];
    $vides   = 0;
    $debut   = time();

    // Depuis le navigateur, PHP coupe la requête : on garde une marge. En ligne
    // de commande ou par cron, aucune limite ne s'applique.
    $budget = ( ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() ) ? 900 : 240;

    foreach ( $sources as $src ) {
        if ( ( time() - $debut ) > $budget ) {
            $echecs[] = $src['nom'] . ' (temps imparti dépassé)';
            continue;
        }

        $texte = md_bots_page_text( $src['url'] );
        if ( is_wp_error( $texte ) ) {
            $echecs[] = $src['nom'] . ' (' . $texte->get_error_message() . ')';
            continue;
        }

        $bruts = call_user_func( $extraire, $src['nom'], $texte );
        if ( is_wp_error( $bruts ) ) {
            $echecs[] = $src['nom'] . ' (' . $bruts->get_error_message() . ')';
            continue;
        }

        foreach ( $bruts as $brut ) {
            $entree = call_user_func( $convertir, $brut, $src );
            if ( null === $entree ) {
                $vides++;
                continue;
            }
            $entrees[] = $entree;
        }
    }

    $resume = call_user_func( $resumer, $entrees, count( $sources ) - count( $echecs ), $vides );

    if ( $echecs ) {
        $resume .= ' Recherche INCOMPLÈTE — pages non lues : ' . implode( ' ; ', $echecs ) . '.';
    }

    update_option( $option, wp_json_encode( [
        'execute_le' => current_time( 'Y-m-d' ),
        'resume'     => $resume,
        'entrees'    => $entrees,
    ] ) );

    return [ 'ok' => true, 'message' => $resume ];
}

/**
 * Exécute la recherche complète et écrit le résultat dans l'option du bot.
 *
 * Rien n'est jamais tronqué en silence : toute source non lue apparaît dans le
 * résumé, pour qu'une liste courte ne passe pas pour une liste complète.
 *
 * @return array Compte rendu.
 */
function md_bots_run_subventions() {
    if ( ! md_omniroute_up() && ! md_omniroute_start() ) {
        return [ 'ok' => false, 'message' => 'OmniRoute ne répond pas et n\'a pas pu être relancé.' ];
    }

    $entrees = [];
    $echecs  = [];
    $vides   = 0;
    $debut   = time();

    // Depuis le navigateur, PHP coupe la requête : on garde une marge. En ligne
    // de commande ou par cron, aucune limite ne s'applique, on peut aller plus
    // loin et lire davantage de pages.
    $budget = ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron() ? 900 : 240;

    $sources = md_bots_expand_sources( md_bots_sources_subventions() );

    foreach ( $sources as $src ) {
        if ( ( time() - $debut ) > $budget ) {
            $echecs[] = $src['nom'] . ' (temps imparti dépassé)';
            continue;
        }

        $texte = md_bots_page_text( $src['url'] );
        if ( is_wp_error( $texte ) ) {
            $echecs[] = $src['nom'] . ' (' . $texte->get_error_message() . ')';
            continue;
        }

        $dispositifs = md_bots_extract_subventions( $src['nom'], $texte );
        if ( is_wp_error( $dispositifs ) ) {
            $echecs[] = $src['nom'] . ' (' . $dispositifs->get_error_message() . ')';
            continue;
        }

        foreach ( $dispositifs as $d ) {
            if ( empty( $d['titre'] ) ) {
                continue;
            }

            // Un dispositif réel annonce au moins un montant ou une date. Une
            // ligne qui n'a ni l'un ni l'autre est un libellé de rubrique
            // ramassé sur une page de navigation.
            //
            // Le critère ne regarde délibérément PAS l'éligibilité : le modèle
            // en produit une pour absolument tout, ne serait-ce qu'une phrase
            // générique. Une première version l'incluait dans le test, ce qui
            // rendait le filtre inopérant — 74 entrées retenues dont 2 avaient
            // une échéance.
            if ( empty( $d['montant'] ) && empty( $d['echeance'] ) ) {
                $vides++;
                continue;
            }

            $pertinent = ! isset( $d['pertinent'] ) || (bool) $d['pertinent'];

            $details = [];
            if ( ! empty( $d['montant'] ) ) {
                $details['Montant'] = (string) $d['montant'];
            }
            if ( ! empty( $d['eligibilite'] ) ) {
                $details['Éligibilité'] = (string) $d['eligibilite'];
            }
            $details['Source lue'] = $src['nom'];

            $entrees[] = [
                'id'         => sanitize_title( $d['titre'] ),
                'titre'      => (string) $d['titre'],
                'sous_titre' => ! empty( $d['organisme'] ) ? (string) $d['organisme'] : $src['nom'],
                'url'        => $src['url'],
                'echeance'   => ! empty( $d['echeance'] ) ? (string) $d['echeance'] : null,
                // Jamais « à déposer » : un modèle gratuit lit bien mais se
                // trompe sur les détails. La validation reste humaine.
                'statut'     => $pertinent ? 'à vérifier' : 'hors critères',
                'motif'      => ! empty( $d['motif'] ) ? (string) $d['motif'] : '',
                'suivi'      => 'aucune',
                'nouveau'    => true,
                'details'    => $details,
                'notes'      => '',
            ];
        }
    }

    $avec_date = 0;
    foreach ( $entrees as $e ) {
        if ( ! empty( $e['echeance'] ) ) {
            $avec_date++;
        }
    }

    $resume = sprintf(
        '%d dispositif(s) retenu(s) sur %d page(s) lue(s), dont %d avec une échéance datée.',
        count( $entrees ),
        count( $sources ) - count( $echecs ),
        $avec_date
    );

    if ( $vides ) {
        $resume .= sprintf( ' %d libellé(s) sans montant ni échéance écarté(s).', $vides );
    }

    if ( $echecs ) {
        $resume .= ' Recherche INCOMPLÈTE — pages non lues : ' . implode( ' ; ', $echecs ) . '.';
    }

    update_option( 'md_bot_subventions', wp_json_encode( [
        'execute_le' => current_time( 'Y-m-d' ),
        'resume'     => $resume,
        'entrees'    => $entrees,
    ] ) );

    return [ 'ok' => true, 'message' => $resume ];
}

// ==========================================================================
// Bouton « Lancer la recherche »
// ==========================================================================

// ==========================================================================
// Bot « Plateformes de promo et premières »
// ==========================================================================

/**
 * Médias susceptibles de relayer une sortie ou d'accueillir une première.
 *
 * URL vérifiées : chacune renvoie 200. Deux candidates ont été écartées après
 * test — resident advisor renvoie 403 aux robots, Data Transmission ne répond
 * plus. Les laisser aurait produit des échecs à chaque exécution.
 */
function md_bots_sources_promo() {
    return [
        [ 'nom' => 'UKF — bass music',            'url' => 'https://ukf.com/', 'index' => true ],
        [ 'nom' => 'Bandcamp Daily',              'url' => 'https://daily.bandcamp.com/', 'index' => true ],
        [ 'nom' => 'XLR8R',                       'url' => 'https://www.xlr8r.com/', 'index' => true ],
        [ 'nom' => 'Inverted Audio',              'url' => 'https://inverted-audio.com/contact/' ],
        [ 'nom' => 'Crack Magazine',              'url' => 'https://crackmagazine.net/', 'index' => true ],
        [ 'nom' => 'The Quietus',                 'url' => 'https://thequietus.com/', 'index' => true ],
        [ 'nom' => 'Stamp The Wax',               'url' => 'https://stampthewax.com/contact/' ],
        [ 'nom' => 'Mixmag',                      'url' => 'https://mixmag.net/', 'index' => true ],
    ];
}

/**
 * Extrait les modalités de soumission décrites dans le texte fourni.
 *
 * @return array|WP_Error
 */
function md_bots_extract_promo( $source, $texte ) {
    $system = "Tu es un assistant qui EXTRAIT des informations d'un texte fourni. "
        . "Tu ne réponds jamais de mémoire. Si une information n'apparaît pas dans le texte, tu écris null. "
        . "Tu réponds uniquement par du JSON valide, sans commentaire ni balise markdown.";

    $user = "Voici le texte d'une page d'un média musical.\n\n"
        . "Contexte : label associatif genevois de musiques électroniques et expérimentales "
        . "(drum & bass, jungle, dubstep, dub, bass music, ambient, noise). Il cherche des médias "
        . "qui relaient ses sorties ou accueillent des premières de titres.\n\n"
        . "Extrais UNIQUEMENT ce que le texte dit sur la façon de soumettre de la musique :\n"
        . '{"plateformes":[{"nom":"...","type":"blog|magazine|chaine|podcast|playlist|null",'
        . '"genres":"genres couverts, ou null","soumission":"comment soumettre : e-mail, formulaire, adresse — ou null",'
        . '"premieres":"ce que le texte dit des premières, ou null","pertinent":true|false,'
        . '"motif":"si non pertinent, pourquoi"}]}' . "\n\n"
        . "Règles strictes :\n"
        . "- soumission UNIQUEMENT si le texte explique réellement comment envoyer sa musique.\n"
        . "- N'invente aucune adresse e-mail : recopie celle du texte ou écris null.\n"
        . "- pertinent = false si le média ne couvre pas ces genres.\n"
        . "- Rien de tel dans le texte ? Réponds {\"plateformes\":[]}.\n\n"
        . "SOURCE : " . $source . "\n\n=== TEXTE ===\n" . $texte;

    $reponse = md_omniroute_complete( $system, $user );
    if ( is_wp_error( $reponse ) ) {
        return $reponse;
    }

    $data = md_json_from_reply( $reponse );
    if ( null === $data || ! isset( $data['plateformes'] ) || ! is_array( $data['plateformes'] ) ) {
        return new WP_Error( 'extract_format', 'Le modèle n\'a pas renvoyé de JSON exploitable.' );
    }

    return $data['plateformes'];
}

/**
 * Lance la recherche des plateformes de promo.
 */
function md_bots_run_promo() {
    // Sur un média, l'information utile est sous « contact », « submit »,
    // « demo » ou « about » — jamais sous les mots-clés des subventions.
    $mots = '~(contact|submit|submission|demo|promo|press|about|advertis|write-for|contribut)~i';

    return md_bots_run_generic(
        md_bots_expand_sources( md_bots_sources_promo(), $mots ),
        'md_bots_extract_promo',
        function ( $p, $src ) {
            if ( empty( $p['nom'] ) ) {
                return null;
            }

            // Sans modalité de soumission, la fiche ne sert à rien : savoir
            // qu'un média existe n'aide pas à lui envoyer un titre. Même
            // logique que le filtre montant/échéance du bot Subventions.
            if ( empty( $p['soumission'] ) ) {
                return null;
            }

            $details = [ 'Comment soumettre' => (string) $p['soumission'] ];
            if ( ! empty( $p['genres'] ) ) {
                $details['Genres couverts'] = (string) $p['genres'];
            }
            if ( ! empty( $p['premieres'] ) ) {
                $details['Premières'] = (string) $p['premieres'];
            }
            $details['Source lue'] = $src['nom'];

            $pertinent = ! isset( $p['pertinent'] ) || (bool) $p['pertinent'];

            return [
                'id'         => sanitize_title( $p['nom'] ),
                'titre'      => (string) $p['nom'],
                'sous_titre' => ! empty( $p['type'] ) ? (string) $p['type'] : $src['nom'],
                'url'        => $src['url'],
                'echeance'   => null,
                'statut'     => $pertinent ? 'à vérifier' : 'hors critères',
                'motif'      => ! empty( $p['motif'] ) ? (string) $p['motif'] : '',
                'suivi'      => 'aucune',
                'nouveau'    => true,
                'details'    => $details,
                'notes'      => '',
            ];
        },
        'md_bot_promo',
        function ( $entrees, $lues, $vides ) {
            $r = sprintf( '%d plateforme(s) retenue(s) sur %d page(s) lue(s).', count( $entrees ), $lues );
            if ( $vides ) {
                $r .= sprintf( ' %d mention(s) sans modalité de soumission écartée(s).', $vides );
            }
            return $r;
        }
    );
}

// ==========================================================================
// Bot « Disquaires & distribution »
// ==========================================================================

/**
 * Distributeurs et annuaires de disquaires pour une sortie vinyle.
 *
 * URL vérifiées : chacune renvoie 200. Trois candidates écartées après test —
 * VinylHub, Juno et Word and Sound renvoient 403 aux robots. Les garder aurait
 * produit un échec à chaque exécution, et un résumé « INCOMPLÈTE » permanent
 * qu'on finirait par ne plus lire.
 */
function md_bots_sources_disquaires() {
    return [
        [ 'nom' => 'Kudos Records — services labels', 'url' => 'https://www.kudosrecords.co.uk/label-services' ],
        [ 'nom' => 'Clone Distribution',              'url' => 'https://clone.nl/', 'index' => true ],
        [ 'nom' => 'Triple Vision',                   'url' => 'https://www.triplevision.nl/', 'index' => true ],
        [ 'nom' => 'Rush Hour',                       'url' => 'https://rushhour.nl/', 'index' => true ],
        [ 'nom' => 'Deejay.de',                       'url' => 'https://www.deejay.de/', 'index' => true ],
        [ 'nom' => 'Redeye Worldwide',                'url' => 'https://www.redeyeworldwide.com/', 'index' => true ],
        [ 'nom' => 'Record Stores Love — annuaire',   'url' => 'https://recordstores.love/', 'index' => true ],

        // PAS de disquaires suisses ici, et ce n'est pas un oubli.
        //
        // Bongo Joe (Genève), Plattfon (Bâle) et l'annuaire des disquaires
        // suisses ont été ajoutés puis retirés après mesure : les deux
        // boutiques tournent sur des sites dont le HTML servi ne contient que
        // la navigation et le bandeau cookies, et l'annuaire est une carte
        // entièrement JavaScript — 40 caractères extraits. Aucun n'a produit
        // la moindre fiche, tout en coûtant vingt secondes chacun.
        //
        // Pour les disquaires romands, un bot n'apporte rien : leurs conditions
        // de dépôt-vente ne sont pas publiées, elles se demandent de vive voix.
    ];
}

/**
 * Extrait les conditions de distribution décrites dans le texte fourni.
 *
 * @return array|WP_Error
 */
function md_bots_extract_disquaires( $source, $texte ) {
    $system = "Tu es un assistant qui EXTRAIT des informations d'un texte fourni. "
        . "Tu ne réponds jamais de mémoire. Si une information n'apparaît pas dans le texte, tu écris null. "
        . "Tu réponds uniquement par du JSON valide, sans commentaire ni balise markdown.";

    $user = "Voici le texte d'une page d'un distributeur de disques ou d'un disquaire.\n\n"
        . "Contexte : label associatif genevois de musiques électroniques et expérimentales "
        . "(drum & bass, jungle, dubstep, dub, bass music, ambient, noise). Il prépare sa première "
        . "sortie vinyle et cherche qui pourrait la distribuer ou la vendre.\n\n"
        . "Extrais ce que le texte dit sur la distribution :\n"
        . '{"structures":[{"nom":"...","role":"distributeur|disquaire|annuaire|null",'
        . '"territoire":"pays ou zone couverte, ou null","genres":"genres distribués, ou null",'
        . '"contact":"e-mail, formulaire ou page indiquée dans le texte, ou null",'
        . '"conditions":"ce que le texte dit des conditions d\'acceptation d\'un label, ou null",'
        . '"pertinent":true|false,"motif":"si non pertinent, pourquoi"}]}' . "\n\n"
        . "Règles strictes :\n"
        . "- N'invente aucune adresse e-mail : recopie celle du texte ou écris null.\n"
        . "- contact UNIQUEMENT si le texte indique réellement comment les joindre.\n"
        . "- pertinent = false si la structure ne distribue pas ces genres.\n"
        . "- Rien de tel dans le texte ? Réponds {\"structures\":[]}.\n\n"
        . "SOURCE : " . $source . "\n\n=== TEXTE ===\n" . $texte;

    $reponse = md_omniroute_complete( $system, $user );
    if ( is_wp_error( $reponse ) ) {
        return $reponse;
    }

    $data = md_json_from_reply( $reponse );
    if ( null === $data || ! isset( $data['structures'] ) || ! is_array( $data['structures'] ) ) {
        return new WP_Error( 'extract_format', 'Le modèle n\'a pas renvoyé de JSON exploitable.' );
    }

    return $data['structures'];
}

/**
 * Lance la recherche des disquaires et distributeurs.
 */
function md_bots_run_disquaires() {
    // Sur un site de distributeur, l'information utile vit sous « distribution »,
    // « label services » ou « contact ». « label » et « stock » ont été retirés
    // après essai : sur une boutique ils attrapent les fiches produit et les
    // classements de ventes, soit vingt secondes perdues par page inutile.
    $mots = '~(distribution|services|contact|about|submit|demo|wholesale)~i';

    return md_bots_run_generic(
        md_bots_expand_sources( md_bots_sources_disquaires(), $mots ),
        'md_bots_extract_disquaires',
        function ( $s, $src ) {
            if ( empty( $s['nom'] ) ) {
                return null;
            }

            // Sans contact ni conditions, la fiche n'aide pas à agir : savoir
            // qu'un distributeur existe ne dit pas comment le solliciter.
            if ( empty( $s['contact'] ) && empty( $s['conditions'] ) ) {
                return null;
            }

            $details = [];
            if ( ! empty( $s['contact'] ) ) {
                $details['Contact'] = (string) $s['contact'];
            }
            if ( ! empty( $s['conditions'] ) ) {
                $details['Conditions'] = (string) $s['conditions'];
            }
            if ( ! empty( $s['territoire'] ) ) {
                $details['Territoire'] = (string) $s['territoire'];
            }
            if ( ! empty( $s['genres'] ) ) {
                $details['Genres distribués'] = (string) $s['genres'];
            }
            $details['Source lue'] = $src['nom'];

            $pertinent = ! isset( $s['pertinent'] ) || (bool) $s['pertinent'];

            return [
                'id'         => sanitize_title( $s['nom'] ),
                'titre'      => (string) $s['nom'],
                'sous_titre' => ! empty( $s['role'] ) ? (string) $s['role'] : $src['nom'],
                'url'        => $src['url'],
                'echeance'   => null,
                'statut'     => $pertinent ? 'à vérifier' : 'hors critères',
                'motif'      => ! empty( $s['motif'] ) ? (string) $s['motif'] : '',
                'suivi'      => 'aucune',
                'nouveau'    => true,
                'details'    => $details,
                'notes'      => '',
            ];
        },
        'md_bot_disquaires',
        function ( $entrees, $lues, $vides ) {
            $r = sprintf( '%d structure(s) retenue(s) sur %d page(s) lue(s).', count( $entrees ), $lues );
            if ( $vides ) {
                $r .= sprintf( ' %d mention(s) sans contact ni conditions écartée(s).', $vides );
            }
            return $r;
        }
    );
}

// ==========================================================================
// Bot « Artistes émergents »
// ==========================================================================

/**
 * Sources de repérage d'artistes.
 *
 * Les pages Bandcamp par genre (bandcamp.com/tag/jungle et consorts) ont été
 * essayées puis écartées : elles se rendent entièrement en JavaScript et le
 * HTML servi ne contient que l'interface de filtres — 548 caractères, zéro
 * artiste. Un bot bâti dessus n'aurait jamais rien remonté.
 *
 * Restent des pages qui servent réellement du texte : les programmations de
 * festivals romands, le portail suisse Mx3, et l'éditorial de Bandcamp Daily.
 */
function md_bots_sources_artistes() {
    return [
        // Écartés après exécution réelle : Electron Festival renvoie 403 aux
        // robots, Les Digitales dépasse deux minutes sans répondre, et
        // daily.bandcamp.com/genre/electronic renvoie 404.
        //
        // Antigel est conservé mais ne donnera plus grand-chose : c'est un
        // festival pluridisciplinaire, sa programmation ne mentionne aucun
        // genre, et le filtre l'exige désormais. Ses 181 noms — Miossec,
        // Odezenne, Baxter Dury — étaient du bruit pur.
        [ 'nom' => 'Antigel — Genève',             'url' => 'https://www.antigel.ch/', 'index' => true ],
        [ 'nom' => 'Mx3 — portail suisse',         'url' => 'https://mx3.ch/genres' ],
        [ 'nom' => 'Bandcamp Daily — best of',     'url' => 'https://daily.bandcamp.com/best-electronic' ],
        [ 'nom' => 'Bandcamp Daily — sélections',  'url' => 'https://daily.bandcamp.com/lists', 'index' => true ],
    ];
}

/**
 * Extrait les artistes cités dans le texte fourni.
 *
 * @return array|WP_Error
 */
function md_bots_extract_artistes( $source, $texte ) {
    $system = "Tu es un assistant qui EXTRAIT des informations d'un texte fourni. "
        . "Tu ne réponds jamais de mémoire et tu n'ajoutes aucune connaissance extérieure sur les artistes. "
        . "Si une information n'apparaît pas dans le texte, tu écris null. "
        . "Tu réponds uniquement par du JSON valide, sans commentaire ni balise markdown.";

    $user = "Voici le texte d'une page de festival, de portail musical ou de magazine.\n\n"
        . "Contexte : label associatif genevois de musiques électroniques et expérimentales "
        . "(drum & bass, jungle, dubstep, dub, bass music, ambient, noise). Il repère des artistes "
        . "émergents qu'il pourrait signer ou programmer.\n\n"
        . "Extrais les ARTISTES ou GROUPES nommés dans le texte :\n"
        . '{"artistes":[{"nom":"...","genre":"genre indiqué, ou null","provenance":"ville ou pays, ou null",'
        . '"contexte":"ce que le texte en dit — programmé, chroniqué, sorti un disque...",'
        . '"pertinent":true|false,"motif":"si non pertinent, pourquoi"}]}' . "\n\n"
        . "Règles strictes :\n"
        . "- N'invente aucun genre ni aucune provenance : uniquement ce que le texte dit.\n"
        . "- N'invente pas d'artiste : seulement ceux nommés dans le texte.\n"
        . "- Ignore les noms de salles, de festivals, de sponsors et de rubriques.\n"
        . "- pertinent = false si l'artiste relève clairement d'un autre univers musical.\n"
        . "- Aucun artiste nommé ? Réponds {\"artistes\":[]}.\n\n"
        . "SOURCE : " . $source . "\n\n=== TEXTE ===\n" . $texte;

    $reponse = md_omniroute_complete( $system, $user );
    if ( is_wp_error( $reponse ) ) {
        return $reponse;
    }

    $data = md_json_from_reply( $reponse );
    if ( null === $data || ! isset( $data['artistes'] ) || ! is_array( $data['artistes'] ) ) {
        return new WP_Error( 'extract_format', 'Le modèle n\'a pas renvoyé de JSON exploitable.' );
    }

    return $data['artistes'];
}

/**
 * Lance le repérage d'artistes émergents.
 */
function md_bots_run_artistes() {
    // « 20[0-9]{2} » a ete retire : il matchait les dates dans les noms de
    // fichiers, et le bot telechargeait des images .webp comme des pages.
    $mots = '~(programm|line-?up|artistes?/|edition|festival|archive)~i';

    return md_bots_run_generic(
        md_bots_expand_sources( md_bots_sources_artistes(), $mots ),
        'md_bots_extract_artistes',
        function ( $a, $src ) {
            if ( empty( $a['nom'] ) ) {
                return null;
            }

            // Filtre sur les genres du label cherchés dans le texte, plutôt que
            // sur un champ « genre » rempli.
            //
            // Deux essais précédents ont échoué. Accepter genre OU provenance
            // OU contexte ne filtrait rien : le modèle remplit toujours le
            // contexte, et le bot recopiait la programmation entière d'Antigel
            // — 283 noms dont Miossec et Odezenne. Exiger un genre explicite
            // filtrait trop : une page qui liste vingt artistes indique
            // rarement le genre de chacun, et il ne restait qu'une fiche.
            //
            // Chercher les genres du label dans tout ce que le modèle rapporte
            // attrape « son disque de jungle » même quand le champ genre est
            // vide, et rejette la chanson française même quand il est rempli.
            $matiere = strtolower(
                ( $a['genre'] ?? '' ) . ' ' . ( $a['contexte'] ?? '' ) . ' ' . ( $a['nom'] ?? '' )
            );

            $genres = 'jungle|drum ?(and|&|n\'?) ?bass|d ?n ?b|dubstep|dub\b|bass music|breakbeat'
                . '|ambient|noise|experimental|expérimental|électronique|electronic|techno|breakcore|footwork|idm';

            if ( ! preg_match( '~' . $genres . '~i', $matiere ) ) {
                return null;
            }

            $details = [];
            if ( ! empty( $a['genre'] ) ) {
                $details['Genre'] = (string) $a['genre'];
            }
            if ( ! empty( $a['provenance'] ) ) {
                $details['Provenance'] = (string) $a['provenance'];
            }
            if ( ! empty( $a['contexte'] ) ) {
                $details['Repéré via'] = (string) $a['contexte'];
            }
            $details['Source lue'] = $src['nom'];

            $pertinent = ! isset( $a['pertinent'] ) || (bool) $a['pertinent'];

            return [
                'id'         => sanitize_title( $a['nom'] ),
                'titre'      => (string) $a['nom'],
                'sous_titre' => ! empty( $a['provenance'] ) ? (string) $a['provenance'] : $src['nom'],
                'url'        => $src['url'],
                'echeance'   => null,
                'statut'     => $pertinent ? 'à vérifier' : 'hors critères',
                'motif'      => ! empty( $a['motif'] ) ? (string) $a['motif'] : '',
                'suivi'      => 'aucune',
                'nouveau'    => true,
                'details'    => $details,
                'notes'      => '',
            ];
        },
        'md_bot_artistes',
        function ( $entrees, $lues, $vides ) {
            $r = sprintf( '%d artiste(s) repéré(s) sur %d page(s) lue(s).', count( $entrees ), $lues );
            if ( $vides ) {
                $r .= sprintf( ' %d nom(s) sans contexte écarté(s).', $vides );
            }
            return $r;
        }
    );
}

// ==========================================================================
// Bot « Booking & visibilité »
// ==========================================================================

/**
 * Salles et réseaux susceptibles de programmer les artistes du label.
 *
 * Texte réellement extrait vérifié avant de retenir chaque source — la leçon
 * du bot Artistes, où des pages entièrement rendues en JavaScript avaient été
 * choisies sans mesure. Bad Bonn a été écarté à ce titre : 69 caractères
 * servis, aucune information exploitable.
 *
 * Priorité au terrain atteignable : quatre salles romandes, deux alémaniques,
 * et la fédération suisse des clubs, qui en recense d'autres.
 */
function md_bots_sources_booking() {
    return [
        [ 'nom' => 'Cave12 — Genève',       'url' => 'https://cave12.org/', 'index' => true ],
        [ 'nom' => 'L\'Usine — Genève',     'url' => 'https://usine.ch/', 'index' => true ],
        [ 'nom' => 'Le Garage',             'url' => 'https://www.legarage.ch/', 'index' => true ],
        [ 'nom' => 'Dachstock — Berne',     'url' => 'https://www.dachstock.ch/', 'index' => true ],
        [ 'nom' => 'Rote Fabrik — Zurich',  'url' => 'https://rotefabrik.ch/', 'index' => true ],
        [ 'nom' => 'Petzi — clubs suisses', 'url' => 'https://www.petzi.ch/', 'index' => true ],
    ];
}

/**
 * Extrait les modalités de démarchage décrites dans le texte fourni.
 *
 * @return array|WP_Error
 */
function md_bots_extract_booking( $source, $texte ) {
    $system = "Tu es un assistant qui EXTRAIT des informations d'un texte fourni. "
        . "Tu ne réponds jamais de mémoire. Si une information n'apparaît pas dans le texte, tu écris null. "
        . "Tu réponds uniquement par du JSON valide, sans commentaire ni balise markdown.";

    $user = "Voici le texte d'une page de salle de concert, de club ou de réseau de lieux.\n\n"
        . "Contexte : label associatif genevois de musiques électroniques et expérimentales "
        . "(drum & bass, jungle, dubstep, dub, bass music, ambient, noise). Il cherche où faire jouer "
        . "ses artistes et à qui envoyer une proposition.\n\n"
        . "Extrais ce que le texte dit sur les lieux et le démarchage :\n"
        . '{"lieux":[{"nom":"...","ville":"ou null","programmation":"types de musique programmes, ou null",'
        . '"contact":"e-mail, formulaire ou page de contact indiquee dans le texte, ou null",'
        . '"demarche":"ce que le texte dit sur l\'envoi d\'une proposition, ou null",'
        . '"pertinent":true|false,"motif":"si non pertinent, pourquoi"}]}' . "\n\n"
        . "Règles strictes :\n"
        . "- N'invente aucune adresse e-mail : recopie celle du texte ou écris null.\n"
        . "- contact UNIQUEMENT si le texte indique réellement comment les joindre.\n"
        . "- pertinent = false si le lieu ne programme visiblement pas ces musiques.\n"
        . "- Rien de tel dans le texte ? Réponds {\"lieux\":[]}.\n\n"
        . "SOURCE : " . $source . "\n\n=== TEXTE ===\n" . $texte;

    $reponse = md_omniroute_complete( $system, $user );
    if ( is_wp_error( $reponse ) ) {
        return $reponse;
    }

    $data = md_json_from_reply( $reponse );
    if ( null === $data || ! isset( $data['lieux'] ) || ! is_array( $data['lieux'] ) ) {
        return new WP_Error( 'extract_format', 'Le modèle n\'a pas renvoyé de JSON exploitable.' );
    }

    return $data['lieux'];
}

/**
 * Lance la recherche de lieux et de contacts de booking.
 */
function md_bots_run_booking() {
    $mots = '~(contact|booking|programm|propos|about|kontakt|infos?)~i';

    return md_bots_run_generic(
        md_bots_expand_sources( md_bots_sources_booking(), $mots ),
        'md_bots_extract_booking',
        function ( $l, $src ) {
            if ( empty( $l['nom'] ) ) {
                return null;
            }

            // Sans contact ni démarche indiquée, la fiche ne permet pas d'agir.
            // Même critère que le bot Disquaires : connaître l'existence d'une
            // salle n'apprend pas à qui envoyer une proposition.
            if ( empty( $l['contact'] ) && empty( $l['demarche'] ) ) {
                return null;
            }

            $details = [];
            if ( ! empty( $l['contact'] ) ) {
                $details['Contact'] = (string) $l['contact'];
            }
            if ( ! empty( $l['demarche'] ) ) {
                $details['Démarche'] = (string) $l['demarche'];
            }
            if ( ! empty( $l['programmation'] ) ) {
                $details['Programmation'] = (string) $l['programmation'];
            }
            $details['Source lue'] = $src['nom'];

            $pertinent = ! isset( $l['pertinent'] ) || (bool) $l['pertinent'];

            return [
                'id'         => sanitize_title( $l['nom'] ),
                'titre'      => (string) $l['nom'],
                'sous_titre' => ! empty( $l['ville'] ) ? (string) $l['ville'] : $src['nom'],
                'url'        => $src['url'],
                'echeance'   => null,
                'statut'     => $pertinent ? 'à vérifier' : 'hors critères',
                'motif'      => ! empty( $l['motif'] ) ? (string) $l['motif'] : '',
                'suivi'      => 'aucune',
                'nouveau'    => true,
                'details'    => $details,
                'notes'      => '',
            ];
        },
        'md_bot_booking',
        function ( $entrees, $lues, $vides ) {
            $r = sprintf( '%d lieu(x) retenu(s) sur %d page(s) lue(s).', count( $entrees ), $lues );
            if ( $vides ) {
                $r .= sprintf( ' %d mention(s) sans contact ni démarche écartée(s).', $vides );
            }
            return $r;
        }
    );
}

function md_bots_handle_run() {
    if ( ! current_user_can( MD_BOTS_CAP ) ) {
        wp_die( esc_html__( 'Accès refusé.', 'mango-dragon' ) );
    }
    check_admin_referer( 'md_bots_run' );

    // Le bot à lancer est celui de la page d'où vient le formulaire.
    $slug     = isset( $_POST['md_bot'] ) ? sanitize_key( wp_unslash( $_POST['md_bot'] ) ) : 'subventions';
    $fonction = 'md_bots_run_' . $slug;

    if ( ! function_exists( $fonction ) ) {
        wp_die( esc_html__( 'Bot inconnu.', 'mango-dragon' ) );
    }

    $res = call_user_func( $fonction );

    wp_safe_redirect( add_query_arg( [
        'page'       => 'md-bots-' . $slug,
        'md_bot_msg' => rawurlencode( $res['message'] ),
        'md_bot_ok'  => $res['ok'] ? '1' : '0',
    ], admin_url( 'admin.php' ) ) );
    exit;
}
add_action( 'admin_post_md_bots_run', 'md_bots_handle_run' );
