<?php
/**
 * Bots — pages d'administration réservées aux administrateurs.
 *
 * Les bots sont des agents de recherche exécutés hors du site (skills Claude
 * Code sur le poste de l'utilisateur). Ils déposent leurs résultats dans une
 * option WordPress via WP-CLI, et ce module les affiche dans wp-admin.
 *
 * Le site ne lance rien et n'appelle aucun service externe : il ne fait que
 * présenter des données déjà collectées. Rien n'est exposé publiquement — tout
 * passe par la capacité manage_options.
 *
 * Format d'une option de bot (JSON) :
 *   {
 *     "execute_le": "AAAA-MM-JJ",
 *     "resume": "phrase de synthèse",
 *     "entrees": [
 *       {
 *         "id": "slug-stable", "titre": "...", "sous_titre": "...",
 *         "url": "https://...", "echeance": "AAAA-MM-JJ" | null,
 *         "statut": "à déposer" | "à vérifier" | "hors critères",
 *         "motif": "...", "suivi": "...", "nouveau": true|false,
 *         "details": { "Libellé": "valeur" }, "notes": "..."
 *       }
 *     ]
 *   }
 */

if ( ! defined( 'MD_BOTS_CAP' ) ) {
    define( 'MD_BOTS_CAP', 'manage_options' );
}

/**
 * Bots déclarés. Ajouter une entrée suffit à créer sa page.
 *
 * @return array
 */
function md_bots_registry() {
    return [
        'subventions' => [
            'titre'    => 'Subventions',
            'menu'     => 'Subventions',
            'option'   => 'md_bot_subventions',
            'colonne2' => 'Échéance',
            'vide'     => 'Aucune recherche effectuée pour le moment. Clique sur « Lancer la recherche ».',
        ],
        'promo' => [
            'titre'    => 'Promo & premières',
            'menu'     => 'Promo & premières',
            'option'   => 'md_bot_promo',
            // null = colonne masquée. Ces médias n'ont pas de date limite ;
            // afficher vingt fois « non publiée » n'apprendrait rien.
            'colonne2' => null,
            'vide'     => 'Aucune recherche effectuée pour le moment. Clique sur « Lancer la recherche ».',
        ],
        'disquaires' => [
            'titre'    => 'Disquaires & distribution',
            'menu'     => 'Disquaires',
            'option'   => 'md_bot_disquaires',
            'colonne2' => null,
            'vide'     => 'Aucune recherche effectuée pour le moment. Clique sur « Lancer la recherche ».',
        ],
        'booking' => [
            'titre'    => 'Booking & visibilité',
            'menu'     => 'Booking',
            'option'   => 'md_bot_booking',
            'colonne2' => null,
            'vide'     => 'Aucune recherche effectuée pour le moment. Clique sur « Lancer la recherche ».',
        ],

        'contacts' => [
            'titre'    => 'Contacts',
            'menu'     => 'Contacts',
            // Pas d'option : cet onglet ne lance aucune recherche, il agrège
            // les adresses déjà trouvées par les autres bots.
            'option'   => '',
            'agrege'   => true,
            'colonne2' => null,
            'vide'     => 'Aucune adresse exploitable pour l\'instant. Lance les recherches des autres onglets.',
        ],

        // Le bot « Artistes émergents » n'est délibérément PAS déclaré ici.
        //
        // Son runner existe toujours (md_bots_run_artistes) mais il ne remonte
        // qu'un ou deux noms par exécution : les pages qui listent beaucoup
        // d'artistes n'indiquent pas leur genre, et celles qui décrivent un
        // genre ne couvrent qu'un artiste ou deux. Trois filtres successifs ont
        // buté sur cet écart.
        //
        // Un onglet affichant un seul artiste donnerait l'illusion d'une veille
        // qui n'a pas lieu — pire qu'un onglet absent. Le rétablir suppose de
        // descendre de deux niveaux (sélections → articles → artistes), au prix
        // d'une exécution bien plus longue.
    ];
}

/**
 * Menu « Bots » et une page par bot déclaré.
 */
function md_bots_menu() {
    add_menu_page(
        'Bots',
        'Bots',
        MD_BOTS_CAP,
        'md-bots',
        'md_bots_page_router',
        'dashicons-search',
        27
    );

    foreach ( md_bots_registry() as $slug => $bot ) {
        add_submenu_page(
            'md-bots',
            $bot['titre'],
            $bot['menu'],
            MD_BOTS_CAP,
            'md-bots-' . $slug,
            'md_bots_page_router'
        );
    }

    // Retire l'entrée dupliquée que WordPress crée pour la page parente.
    remove_submenu_page( 'md-bots', 'md-bots' );
}
add_action( 'admin_menu', 'md_bots_menu' );

/**
 * Route la page courante vers le bot correspondant.
 *
 * Le contrôle de capacité est refait ici : celui d'add_menu_page ne masque que
 * le lien du menu, il n'empêche pas d'atteindre la page par son URL.
 */
function md_bots_page_router() {
    if ( ! current_user_can( MD_BOTS_CAP ) ) {
        wp_die( esc_html__( 'Accès refusé.', 'mango-dragon' ) );
    }

    $page     = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
    $slug     = str_replace( 'md-bots-', '', $page );
    $registry = md_bots_registry();

    if ( ! isset( $registry[ $slug ] ) ) {
        $slug = array_key_first( $registry );
    }

    md_bots_render( $slug, $registry[ $slug ] );
}

/**
 * Décode l'option d'un bot en vérifiant sa forme.
 *
 * Les données viennent d'un processus externe : on ne présume rien de leur
 * structure et on ignore ce qui n'est pas conforme plutôt que d'émettre des
 * avertissements PHP dans l'administration.
 *
 * @return array
 */
function md_bots_data( $option_name ) {
    $vide = [
        'execute_le' => '',
        'resume'     => '',
        'entrees'    => [],
    ];

    $raw = get_option( $option_name );
    if ( empty( $raw ) ) {
        return $vide;
    }

    $data = is_array( $raw ) ? $raw : json_decode( (string) $raw, true );
    if ( ! is_array( $data ) ) {
        return $vide;
    }

    return [
        'execute_le' => isset( $data['execute_le'] ) ? (string) $data['execute_le'] : '',
        'resume'     => isset( $data['resume'] ) ? (string) $data['resume'] : '',
        'entrees'    => ( isset( $data['entrees'] ) && is_array( $data['entrees'] ) ) ? $data['entrees'] : [],
    ];
}

/**
 * Tri : échéance la plus proche d'abord, sans échéance ensuite,
 * hors critères en dernier.
 */
function md_bots_sort( array $entrees ) {
    usort( $entrees, function ( $a, $b ) {
        $hors_a = ( isset( $a['statut'] ) && 'hors critères' === $a['statut'] ) ? 1 : 0;
        $hors_b = ( isset( $b['statut'] ) && 'hors critères' === $b['statut'] ) ? 1 : 0;

        if ( $hors_a !== $hors_b ) {
            return $hors_a - $hors_b;
        }

        $ech_a = empty( $a['echeance'] ) ? '9999-99-99' : (string) $a['echeance'];
        $ech_b = empty( $b['echeance'] ) ? '9999-99-99' : (string) $b['echeance'];

        return strcmp( $ech_a, $ech_b );
    } );

    return $entrees;
}

/**
 * Nombre de jours avant une échéance, ou null si absente ou illisible.
 */
function md_bots_jours_restants( $echeance ) {
    if ( empty( $echeance ) ) {
        return null;
    }

    $ts = strtotime( (string) $echeance );
    if ( false === $ts ) {
        return null;
    }

    return (int) floor( ( $ts - current_time( 'timestamp' ) ) / DAY_IN_SECONDS );
}

/**
 * Rassemble les adresses e-mail exploitables trouvées par les autres bots.
 *
 * N'effectue aucune recherche : relit ce qui est déjà en base. Une adresse
 * n'est retenue que si is_email() la valide — beaucoup de sites masquent les
 * leurs (« [email protected] » de Cloudflare) ou les écrivent sans arobase,
 * et une adresse fausse dans une liste de démarchage se paie en messages
 * rejetés.
 *
 * @return array Entrées au format d'affichage, sans doublon d'adresse.
 */
function md_bots_collect_contacts() {
    $entrees = [];
    $vues    = [];

    foreach ( md_bots_registry() as $slug => $bot ) {
        if ( empty( $bot['option'] ) ) {
            continue;
        }

        $data = md_bots_data( $bot['option'] );

        foreach ( $data['entrees'] as $e ) {
            $matiere = wp_json_encode( $e['details'] ?? [] ) . ' ' . ( $e['notes'] ?? '' );

            if ( ! preg_match_all( '~[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}~', (string) $matiere, $m ) ) {
                continue;
            }

            foreach ( $m[0] as $mail ) {
                $mail = strtolower( trim( $mail, '.,;:' ) );

                // Cloudflare remplace l'adresse par ce libellé : le retenir
                // reviendrait à proposer une adresse qui n'existe pas.
                if ( ! is_email( $mail ) || false !== strpos( $mail, 'email-protected' ) ) {
                    continue;
                }
                if ( isset( $vues[ $mail ] ) ) {
                    continue;
                }
                $vues[ $mail ] = true;

                $entrees[] = [
                    'id'         => sanitize_title( $mail ),
                    'titre'      => $mail,
                    'sous_titre' => ( $e['titre'] ?? '' ) . ' — ' . $bot['titre'],
                    'url'        => $e['url'] ?? '',
                    'echeance'   => null,
                    'statut'     => $e['statut'] ?? '',
                    'motif'      => '',
                    'suivi'      => 'aucune',
                    'nouveau'    => false,
                    'details'    => array_intersect_key(
                        $e['details'] ?? [],
                        array_flip( [ 'Comment soumettre', 'Démarche', 'Premières', 'Genres couverts', 'Programmation', 'Conditions' ] )
                    ),
                    'notes'      => '',
                ];
            }
        }
    }

    return $entrees;
}

/**
 * Modèle de message, à personnaliser avant tout envoi.
 */
function md_bots_modele_message() {
    return "Objet : Mango Dragon International — [nom de la sortie]\n\n"
        . "Bonjour,\n\n"
        . "Je vous écris de Mango Dragon International, label associatif basé à Genève "
        . "(drum & bass, jungle, dubstep, dub, ambient, expérimental).\n\n"
        . "[Une phrase précise sur POURQUOI vous les contactez eux : un disque qu'ils ont "
        . "chroniqué, un artiste qu'ils ont programmé, une série où la sortie s'inscrirait.]\n\n"
        . "Notre prochaine sortie, [titre] de [artiste], paraît le [date].\n"
        . "Écoute : [lien privé]\n"
        . "Le label : https://mango-dragon.com\n\n"
        . "Merci de votre attention,\n"
        . "[votre nom] — Mango Dragon International\n"
        . "contact@mango-dragon.com";
}

/**
 * Rendu d'une page de bot.
 */
function md_bots_render( $slug, array $bot ) {
    if ( ! empty( $bot['agrege'] ) ) {
        $data = [
            'execute_le' => '',
            'resume'     => '',
            'entrees'    => md_bots_collect_contacts(),
        ];
        $data['resume'] = sprintf(
            '%d adresse(s) exploitable(s), rassemblée(s) depuis les autres onglets. '
                . 'Les adresses masquées par les sites (Cloudflare) ou mal formées sont écartées.',
            count( $data['entrees'] )
        );
    } else {
        $data = md_bots_data( $bot['option'] );
    }

    $entrees = md_bots_sort( $data['entrees'] );
    ?>
    <div class="wrap md-bots">
        <h1><?php echo esc_html( $bot['titre'] ); ?></h1>

        <?php
        // Compte rendu de la dernière exécution lancée depuis cette page.
        if ( isset( $_GET['md_bot_msg'] ) ) :
            $msg = sanitize_text_field( rawurldecode( wp_unslash( $_GET['md_bot_msg'] ) ) );
            $ok  = ! empty( $_GET['md_bot_ok'] );
            ?>
            <div class="notice <?php echo $ok ? 'notice-success' : 'notice-error'; ?> inline">
                <p><?php echo esc_html( $msg ); ?></p>
            </div>
        <?php endif; ?>

        <?php
        // Le bouton n'apparaît que pour les bots dotés d'un runner : la
        // convention md_bots_run_{slug} suffit à le savoir.
        if ( function_exists( 'md_bots_run_' . $slug ) ) :
            $etat = get_option( 'md_omniroute_status' );
            ?>
            <p>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
                    <input type="hidden" name="action" value="md_bots_run">
                    <input type="hidden" name="md_bot" value="<?php echo esc_attr( $slug ); ?>">
                    <?php wp_nonce_field( 'md_bots_run' ); ?>
                    <button type="submit" class="button button-primary">Lancer la recherche</button>
                </form>
                <span class="description" style="margin-left:10px">
                    Moteur :
                    <?php if ( is_array( $etat ) && ! empty( $etat['etat'] ) ) : ?>
                        <?php echo esc_html( $etat['etat'] ); ?>
                        (vérifié le <?php echo esc_html( $etat['verifie_le'] ); ?>)
                    <?php else : ?>
                        état inconnu, la surveillance n'a pas encore tourné
                    <?php endif; ?>
                </span>
            </p>
            <p class="description">
                La recherche lit des pages officielles et en fait extraire le contenu par un modèle
                gratuit. Elle prend une à trois minutes. Tout ressort en « à vérifier » : le modèle
                lit fidèlement mais se trompe sur les détails — la validation reste la tienne.
            </p>
        <?php endif; ?>

        <?php if ( '' !== $data['execute_le'] ) : ?>
            <p class="description">
                Dernière recherche : <strong><?php echo esc_html( $data['execute_le'] ); ?></strong>
                — <?php echo esc_html( (string) count( $entrees ) ); ?> résultat(s)
            </p>
        <?php endif; ?>

        <?php if ( '' !== $data['resume'] ) : ?>
            <div class="notice notice-info inline"><p><?php echo esc_html( $data['resume'] ); ?></p></div>
        <?php endif; ?>

        <?php if ( empty( $entrees ) ) : ?>
            <div class="notice notice-warning inline"><p><?php echo esc_html( $bot['vide'] ); ?></p></div>
        </div>
            <?php
            return;
        endif;
        ?>

        <?php if ( ! empty( $bot['agrege'] ) ) : ?>
            <h2>Modèle de message</h2>
            <p class="description">
                À <strong>personnaliser avant chaque envoi</strong>. Un message identique expédié à
                vingt destinataires se repère immédiatement et finit en indésirable — le passage
                entre crochets est le seul qui décide si on vous lit. Envoyez depuis votre propre
                boîte, un destinataire à la fois : rien n'est expédié depuis cette page.
            </p>
            <textarea readonly rows="16" style="width:100%;max-width:820px;font-family:monospace;font-size:12px"
                onclick="this.select()"><?php echo esc_textarea( md_bots_modele_message() ); ?></textarea>
            <h2>Adresses rassemblées</h2>
        <?php endif; ?>

        <?php
        // Colonne d'échéance affichée seulement pour les bots qui en ont une.
        $col2 = array_key_exists( 'colonne2', $bot ) ? $bot['colonne2'] : 'Échéance';
        ?>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th style="width:28%">Intitulé</th>
                    <?php if ( null !== $col2 ) : ?>
                        <th style="width:14%"><?php echo esc_html( $col2 ); ?></th>
                    <?php endif; ?>
                    <th style="width:12%">Statut</th>
                    <th>Détails</th>
                </tr>
            </thead>
            <tbody>
            <?php
            foreach ( $entrees as $e ) :
                $titre   = isset( $e['titre'] ) ? (string) $e['titre'] : '(sans titre)';
                $sous    = isset( $e['sous_titre'] ) ? (string) $e['sous_titre'] : '';
                $url     = isset( $e['url'] ) ? (string) $e['url'] : '';
                $statut  = isset( $e['statut'] ) ? (string) $e['statut'] : '';
                $echance = isset( $e['echeance'] ) ? $e['echeance'] : null;
                $jours   = md_bots_jours_restants( $echance );
                $details = ( isset( $e['details'] ) && is_array( $e['details'] ) ) ? $e['details'] : [];
                ?>
                <tr>
                    <td>
                        <strong>
                            <?php if ( '' !== $url ) : ?>
                                <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $titre ); ?></a>
                            <?php else : ?>
                                <?php echo esc_html( $titre ); ?>
                            <?php endif; ?>
                        </strong>
                        <?php if ( ! empty( $e['nouveau'] ) ) : ?>
                            <span class="md-bots__badge">nouveau</span>
                        <?php endif; ?>
                        <?php if ( '' !== $sous ) : ?>
                            <div class="description"><?php echo esc_html( $sous ); ?></div>
                        <?php endif; ?>
                    </td>
                    <?php if ( null !== $col2 ) : ?>
                    <td>
                        <?php if ( ! empty( $echance ) ) : ?>
                            <?php echo esc_html( (string) $echance ); ?>
                            <?php if ( null !== $jours ) : ?>
                                <div class="description">
                                    <?php
                                    echo $jours < 0
                                        ? esc_html( 'dépassée' )
                                        : esc_html( sprintf( 'dans %d jour(s)', $jours ) );
                                    ?>
                                </div>
                            <?php endif; ?>
                        <?php else : ?>
                            <span class="description">non publiée</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td>
                        <?php echo esc_html( $statut ); ?>
                        <?php if ( ! empty( $e['suivi'] ) && 'aucune' !== $e['suivi'] ) : ?>
                            <div class="description"><?php echo esc_html( (string) $e['suivi'] ); ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ( ! empty( $e['motif'] ) ) : ?>
                            <p><em><?php echo esc_html( (string) $e['motif'] ); ?></em></p>
                        <?php endif; ?>
                        <?php if ( ! empty( $details ) ) : ?>
                            <ul style="margin:0">
                                <?php foreach ( $details as $cle => $valeur ) : ?>
                                    <li>
                                        <strong><?php echo esc_html( (string) $cle ); ?> :</strong>
                                        <?php echo esc_html( is_scalar( $valeur ) ? (string) $valeur : (string) wp_json_encode( $valeur ) ); ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if ( ! empty( $e['notes'] ) ) : ?>
                            <p class="description"><?php echo esc_html( (string) $e['notes'] ); ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <style>
        .md-bots__badge {
            display: inline-block;
            margin-left: 6px;
            padding: 1px 6px;
            font-size: 11px;
            border-radius: 2px;
            background: #135e96;
            color: #fff;
        }
    </style>
    <?php
}
