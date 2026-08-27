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
function md_bots_links_from( $url, $max = 4 ) {
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
    $mots = '~(soutien|subvention|bourse|encouragement|fonds|contribution|appel-a|candidat|aide-a-l)~i';

    // Deux familles de rejets, apprises à l'usage :
    // — les fichiers et les points d'entrée d'API, qui ne sont pas des pages ;
    // — les aides NON culturelles, que « aide » et « demande » attrapaient en
    //   masse sur les sites d'administration (logement, école, social).
    $exclus = '~(\.(pdf|jpe?g|png|gif|svg|css|js|zip|docx?)($|\?)'
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
function md_bots_expand_sources( array $sources ) {
    $final = [];

    foreach ( $sources as $src ) {
        if ( empty( $src['index'] ) ) {
            $final[] = $src;
            continue;
        }

        $liens = md_bots_links_from( $src['url'] );

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

function md_bots_handle_run() {
    if ( ! current_user_can( MD_BOTS_CAP ) ) {
        wp_die( esc_html__( 'Accès refusé.', 'mango-dragon' ) );
    }
    check_admin_referer( 'md_bots_run' );

    $res = md_bots_run_subventions();

    wp_safe_redirect( add_query_arg( [
        'page'       => 'md-bots-subventions',
        'md_bot_msg' => rawurlencode( $res['message'] ),
        'md_bot_ok'  => $res['ok'] ? '1' : '0',
    ], admin_url( 'admin.php' ) ) );
    exit;
}
add_action( 'admin_post_md_bots_run', 'md_bots_handle_run' );
