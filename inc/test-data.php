<?php
/**
 * Mango Dragon — Test Data Generator
 *
 * Adds an admin page under Appearance to create sample content.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function md_test_data_menu() {
    add_theme_page(
        'Données de test',
        'Données de test',
        'manage_options',
        'md-test-data',
        'md_test_data_page'
    );
}
add_action( 'admin_menu', 'md_test_data_menu' );

function md_test_data_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( isset( $_POST['md_generate'] ) && check_admin_referer( 'md_test_data' ) ) {
        $result = md_generate_test_data();
        echo '<div class="notice notice-success"><p>' . esc_html( $result ) . '</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>Données de test — Mango Dragon</h1>
        <p>Cliquez sur le bouton pour créer des artistes, releases, termes et pages de démonstration.</p>
        <form method="post">
            <?php wp_nonce_field( 'md_test_data' ); ?>
            <p><input type="submit" name="md_generate" class="button button-primary" value="Générer les données de test"></p>
        </form>
    </div>
    <?php
}

/* =================================================================
   Generator
   ================================================================= */

function md_generate_test_data() {
    $created = [ 'artistes' => 0, 'releases' => 0, 'pages' => 0, 'terms' => 0 ];

    /* ---------- Taxonomy terms ---------- */
    $genres = [
        'Bass Music', 'Dubstep', 'Drum & Bass', 'Expérimental',
        'Électronique', 'Ambient', 'Hip-Hop',
    ];
    foreach ( $genres as $g ) {
        if ( ! term_exists( $g, 'genre' ) ) { wp_insert_term( $g, 'genre' ); $created['terms']++; }
    }

    $release_types = [ 'EP', 'Album', 'Single', 'Compilation' ];
    foreach ( $release_types as $t ) {
        if ( ! term_exists( $t, 'release_type' ) ) { wp_insert_term( $t, 'release_type' ); $created['terms']++; }
    }

    $artist_types = [ 'Musical', 'Visuel', 'Audiovisuel' ];
    foreach ( $artist_types as $t ) {
        if ( ! term_exists( $t, 'artist_type' ) ) { wp_insert_term( $t, 'artist_type' ); $created['terms']++; }
    }

    /* ---------- Artistes ---------- */
    $artistes = [
        [
            'title'   => 'ZÉPHYR',
            'excerpt' => 'Producteur bass music basé à Genève',
            'bio'     => "ZÉPHYR est un producteur bass music basé à Genève. Actif depuis 2018, il explore les territoires du dubstep, de la bass music et des musiques électroniques expérimentales. Son approche mêle design sonore minutieux et énergie brute, créant un univers sonore unique qui lui a valu des sets dans toute la Suisse romande.\n\nInfluencé par la scène UK bass et les courants plus avant-gardistes, ZÉPHYR développe un son qui oscille entre puissance et subtilité.",
            'genres'  => [ 'Bass Music', 'Dubstep' ],
            'type'    => 'Musical',
            'social'  => [ 'instagram' => 'https://instagram.com/zephyr_bass', 'soundcloud' => 'https://soundcloud.com/zephyr-bass', 'bandcamp' => 'https://zephyr.bandcamp.com' ],
        ],
        [
            'title'   => 'NOVA',
            'excerpt' => 'DJ et productrice drum & bass',
            'bio'     => "NOVA est une DJ et productrice drum & bass originaire de Lausanne. Elle combine des influences allant du liquid au neurofunk, en passant par le jungle. Ses productions se distinguent par des atmosphères cinématographiques et des basslines profondes.\n\nActive sur les scènes suisse et européenne, NOVA est connue pour ses sets énergiques et sa capacité à créer des voyages sonores intenses.",
            'genres'  => [ 'Drum & Bass', 'Électronique' ],
            'type'    => 'Musical',
            'social'  => [ 'instagram' => 'https://instagram.com/nova_dnb', 'soundcloud' => 'https://soundcloud.com/nova-dnb' ],
        ],
        [
            'title'   => 'ÉCHOS',
            'excerpt' => 'Artiste ambient et expérimental',
            'bio'     => "ÉCHOS est un projet ambient et expérimental porté par un duo genevois. À travers des paysages sonores immersifs, field recordings et synthèses granulaires, ÉCHOS crée des espaces contemplatifs qui invitent à l'introspection.\n\nLeur travail se situe à la frontière entre musique et art sonore, explorant les textures et les timbres comme matière première.",
            'genres'  => [ 'Ambient', 'Expérimental' ],
            'type'    => 'Musical',
            'social'  => [ 'bandcamp' => 'https://echos-ambient.bandcamp.com', 'soundcloud' => 'https://soundcloud.com/echos-ambient' ],
        ],
        [
            'title'   => 'PRISME',
            'excerpt' => 'Beatmaker et producteur électronique',
            'bio'     => "PRISME est un beatmaker et producteur basé à Genève. Son univers musical fusionne hip-hop instrumental, électronique et influences world, créant des morceaux colorés et rythmiquement inventifs.\n\nSes productions ont été utilisées dans plusieurs projets audiovisuels et documentaires.",
            'genres'  => [ 'Hip-Hop', 'Électronique' ],
            'type'    => 'Musical',
            'social'  => [ 'instagram' => 'https://instagram.com/prisme_beats', 'spotify' => 'https://open.spotify.com/artist/prisme' ],
        ],
        [
            'title'   => 'FLUX',
            'excerpt' => 'Artiste audiovisuel pluridisciplinaire',
            'bio'     => "FLUX est un collectif audiovisuel basé entre Genève et Lyon. Combinant création sonore, vidéo live et installations interactives, FLUX propose des expériences immersives qui brouillent les frontières entre disciplines.\n\nLeur travail a été présenté dans plusieurs festivals et galeries en Europe.",
            'genres'  => [ 'Électronique', 'Expérimental' ],
            'type'    => 'Audiovisuel',
            'social'  => [ 'instagram' => 'https://instagram.com/flux_av', 'website' => 'https://flux-collective.ch' ],
        ],
    ];

    $artist_ids = [];

    foreach ( $artistes as $data ) {
        // Skip if already exists.
        $existing = get_page_by_title( $data['title'], OBJECT, 'artiste' );
        if ( $existing ) {
            $artist_ids[ $data['title'] ] = $existing->ID;
            continue;
        }

        $id = wp_insert_post( [
            'post_type'    => 'artiste',
            'post_title'   => $data['title'],
            'post_excerpt' => $data['excerpt'],
            'post_status'  => 'publish',
        ] );

        if ( is_wp_error( $id ) ) {
            continue;
        }

        $artist_ids[ $data['title'] ] = $id;

        update_post_meta( $id, '_md_biography', $data['bio'] );

        foreach ( $data['social'] as $platform => $url ) {
            update_post_meta( $id, '_md_social_' . $platform, $url );
        }

        if ( ! empty( $data['genres'] ) ) {
            wp_set_object_terms( $id, $data['genres'], 'genre' );
        }
        if ( ! empty( $data['type'] ) ) {
            wp_set_object_terms( $id, [ $data['type'] ], 'artist_type' );
        }

        $created['artistes']++;
    }

    /* ---------- Releases ---------- */
    $releases = [
        [
            'title'     => 'Vortex EP',
            'artists'   => [ 'ZÉPHYR' ],
            'date'      => '2024-03-15',
            'catalogue' => 'MDI-001',
            'type'      => 'EP',
            'genres'    => [ 'Bass Music', 'Dubstep' ],
            'desc'      => 'Premier EP de ZÉPHYR sur Mango Dragon International. Quatre titres de bass music puissante et atmosphérique.',
            'credits'   => "Produit par ZÉPHYR\nMixé et masterisé au studio Mango Dragon, Genève\nArtwork : FLUX",
            'bandcamp'  => 'https://mangodragon.bandcamp.com/album/vortex',
            'tracklist' => [
                [ 'title' => 'Vortex', 'duration' => '4:32' ],
                [ 'title' => 'Abyssal', 'duration' => '5:10' ],
                [ 'title' => 'Rift', 'duration' => '3:58' ],
                [ 'title' => 'Zenith', 'duration' => '6:15' ],
            ],
        ],
        [
            'title'     => 'Cascade',
            'artists'   => [ 'NOVA' ],
            'date'      => '2024-06-20',
            'catalogue' => 'MDI-002',
            'type'      => 'Album',
            'genres'    => [ 'Drum & Bass', 'Électronique' ],
            'desc'      => 'Premier album de NOVA. Un voyage à travers les sous-genres du drum & bass, du liquid au neurofunk.',
            'credits'   => "Produit par NOVA\nMixé par NOVA & ZÉPHYR\nMasterisé au studio Mango Dragon\nVocals sur \"Cascade\" : Guest MC",
            'bandcamp'  => 'https://mangodragon.bandcamp.com/album/cascade',
            'tracklist' => [
                [ 'title' => 'Intro — Flux',    'duration' => '1:45' ],
                [ 'title' => 'Cascade',          'duration' => '5:22' ],
                [ 'title' => 'Liquid State',     'duration' => '6:08' ],
                [ 'title' => 'Neuron',           'duration' => '4:50' ],
                [ 'title' => 'Parallax',         'duration' => '5:33' ],
                [ 'title' => 'Deep Current',     'duration' => '7:01' ],
                [ 'title' => 'Cascade (Remix)',   'duration' => '5:45' ],
                [ 'title' => 'Outro — Reflux',   'duration' => '2:10' ],
            ],
        ],
        [
            'title'     => 'Liminal',
            'artists'   => [ 'ÉCHOS' ],
            'date'      => '2024-09-01',
            'catalogue' => 'MDI-003',
            'type'      => 'EP',
            'genres'    => [ 'Ambient', 'Expérimental' ],
            'desc'      => 'Cinq pièces ambient explorées à travers le prisme du field recording et de la synthèse granulaire.',
            'credits'   => "Composé et produit par ÉCHOS\nField recordings : Genève, Alpes, lac Léman\nMasterisé au studio Mango Dragon",
            'bandcamp'  => 'https://mangodragon.bandcamp.com/album/liminal',
            'tracklist' => [
                [ 'title' => 'Seuil',     'duration' => '8:20' ],
                [ 'title' => 'Brume',     'duration' => '6:45' ],
                [ 'title' => 'Éclaircie', 'duration' => '7:12' ],
                [ 'title' => 'Reflet',    'duration' => '5:55' ],
                [ 'title' => 'Crépuscule','duration' => '9:30' ],
            ],
        ],
        [
            'title'     => 'Résonance',
            'artists'   => [ 'PRISME' ],
            'date'      => '2024-11-10',
            'catalogue' => 'MDI-004',
            'type'      => 'Single',
            'genres'    => [ 'Hip-Hop', 'Électronique' ],
            'desc'      => 'Single hip-hop instrumental aux couleurs world music.',
            'credits'   => "Produit par PRISME\nMasterisé au studio Mango Dragon",
            'bandcamp'  => 'https://mangodragon.bandcamp.com/track/resonance',
            'tracklist' => [
                [ 'title' => 'Résonance',        'duration' => '3:48' ],
                [ 'title' => 'Résonance (Inst.)', 'duration' => '3:48' ],
            ],
        ],
        [
            'title'     => 'Synthèse',
            'artists'   => [ 'ZÉPHYR', 'NOVA' ],
            'date'      => '2025-01-25',
            'catalogue' => 'MDI-005',
            'type'      => 'Album',
            'genres'    => [ 'Bass Music', 'Drum & Bass' ],
            'desc'      => 'Album collaboratif entre ZÉPHYR et NOVA. La rencontre de deux univers : bass music et drum & bass fusionnées.',
            'credits'   => "Produit par ZÉPHYR & NOVA\nMixé au studio Mango Dragon\nMasterisé par ZÉPHYR\nDesign : FLUX",
            'bandcamp'  => 'https://mangodragon.bandcamp.com/album/synthese',
            'tracklist' => [
                [ 'title' => 'Convergence',  'duration' => '5:15' ],
                [ 'title' => 'Half-Time',    'duration' => '4:40' ],
                [ 'title' => 'Momentum',     'duration' => '6:02' ],
                [ 'title' => 'Fréquences',   'duration' => '5:30' ],
                [ 'title' => 'Dualité',      'duration' => '4:55' ],
                [ 'title' => 'Synthèse',     'duration' => '7:20' ],
            ],
        ],
        [
            'title'     => 'Fragments',
            'artists'   => [ 'FLUX' ],
            'date'      => '2025-04-01',
            'catalogue' => 'MDI-006',
            'type'      => 'EP',
            'genres'    => [ 'Électronique', 'Expérimental' ],
            'desc'      => 'Un EP audiovisuel où chaque morceau est accompagné d\'une pièce vidéo. Expérimental et immersif.',
            'credits'   => "Concept, son et vidéo : FLUX\nMasterisé au studio Mango Dragon",
            'bandcamp'  => 'https://mangodragon.bandcamp.com/album/fragments',
            'tracklist' => [
                [ 'title' => 'Fragment I — Signal',  'duration' => '4:10' ],
                [ 'title' => 'Fragment II — Pixel',   'duration' => '5:25' ],
                [ 'title' => 'Fragment III — Grain',   'duration' => '6:00' ],
                [ 'title' => 'Fragment IV — Bruit',    'duration' => '4:45' ],
            ],
        ],
    ];

    foreach ( $releases as $data ) {
        $existing = get_page_by_title( $data['title'], OBJECT, 'release' );
        if ( $existing ) {
            continue;
        }

        $id = wp_insert_post( [
            'post_type'  => 'release',
            'post_title' => $data['title'],
            'post_status'=> 'publish',
        ] );

        if ( is_wp_error( $id ) ) {
            continue;
        }

        // Meta
        update_post_meta( $id, '_md_release_date',      $data['date'] );
        update_post_meta( $id, '_md_catalogue_number',   $data['catalogue'] );
        update_post_meta( $id, '_md_bandcamp_url',       $data['bandcamp'] );
        update_post_meta( $id, '_md_description',        $data['desc'] );
        update_post_meta( $id, '_md_credits',            $data['credits'] );
        update_post_meta( $id, '_md_tracklist',          wp_json_encode( $data['tracklist'] ) );

        // Artist IDs
        $ids = [];
        foreach ( $data['artists'] as $name ) {
            if ( isset( $artist_ids[ $name ] ) ) {
                $ids[] = $artist_ids[ $name ];
            }
        }
        update_post_meta( $id, '_md_artist_ids', $ids );

        // Taxonomies
        if ( ! empty( $data['genres'] ) ) {
            wp_set_object_terms( $id, $data['genres'], 'genre' );
        }
        if ( ! empty( $data['type'] ) ) {
            wp_set_object_terms( $id, [ $data['type'] ], 'release_type' );
        }

        $created['releases']++;
    }

    /* ---------- Pages ---------- */
    $pages = [
        'a-propos' => 'À propos',
        'mixies'   => 'Mixies',
        'studio'   => 'Studio',
        'contact'  => 'Contact',
    ];

    foreach ( $pages as $slug => $title ) {
        if ( get_page_by_path( $slug ) ) {
            continue;
        }
        wp_insert_post( [
            'post_type'   => 'page',
            'post_title'  => $title,
            'post_name'   => $slug,
            'post_status' => 'publish',
        ] );
        $created['pages']++;
    }

    /* ---------- Static front page ---------- */
    // Create a blank "Accueil" page for the front page setting.
    $home = get_page_by_path( 'accueil' );
    if ( ! $home ) {
        $home_id = wp_insert_post( [
            'post_type'   => 'page',
            'post_title'  => 'Accueil',
            'post_name'   => 'accueil',
            'post_status' => 'publish',
        ] );
        $created['pages']++;
    } else {
        $home_id = $home->ID;
    }

    update_option( 'show_on_front', 'page' );
    update_option( 'page_on_front', $home_id );

    /* ---------- Summary ---------- */
    return sprintf(
        'Créé : %d artistes, %d releases, %d pages, %d termes.',
        $created['artistes'],
        $created['releases'],
        $created['pages'],
        $created['terms']
    );
}
