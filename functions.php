<?php
/**
 * Mango Dragon International â€” Theme Functions
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MD_VERSION', '4.9.26' );
define( 'MD_DIR', get_template_directory() );
define( 'MD_URI', get_template_directory_uri() );

// ==========================================================================
// Includes
// ==========================================================================
require_once MD_DIR . '/inc/post-types.php';
require_once MD_DIR . '/inc/taxonomies.php';
require_once MD_DIR . '/inc/cpt-ui-compat.php'; // CPT UI: empÃªche la redÃ©claration des CPT/taxonomies du thÃ¨me
require_once MD_DIR . '/inc/meta-boxes.php';
require_once MD_DIR . '/inc/acf-fields.php';   // ACF: groupes de champs + masquage des meta-boxes natives
require_once MD_DIR . '/inc/acf-compat.php';   // ACF: pont vers les clÃ©s _md_* lues par les templates
require_once MD_DIR . '/inc/render-fields.php'; // ACF: rendu gÃ©nÃ©rique des champs supplÃ©mentaires
require_once MD_DIR . '/inc/visual-gallery.php'; // Visuals: collection de photos (mÃ©diathÃ¨que)
require_once MD_DIR . '/inc/submissions.php';   // stockage des demandes reÃ§ues (CPT md_submission)
require_once MD_DIR . '/inc/ajax-handlers.php';
require_once MD_DIR . '/inc/inbox.php';         // BoÃ®te de rÃ©ception (wp-admin > E-mails)
require_once MD_DIR . '/inc/bots.php';         // Bots : pages admin, réservées aux administrateurs
require_once MD_DIR . '/inc/bots-runner.php';  // Bots : exécution via OmniRoute (local, 127.0.0.1)
require_once MD_DIR . '/inc/video-embeds.php'; // Vidéos YouTube des artistes
require_once MD_DIR . '/inc/legacy-urls.php'; // Redirections 301 des anciennes URLs françaises
require_once MD_DIR . '/inc/blocks.php';
require_once MD_DIR . '/inc/test-data.php';

// ==========================================================================
// Theme Setup
// ==========================================================================
function md_setup() {
    // Title tag
    add_theme_support( 'title-tag' );

    // Post thumbnails
    add_theme_support( 'post-thumbnails' );

    // Custom logo
    add_theme_support( 'custom-logo', [
        'height'      => 200,
        'width'       => 400,
        'flex-height' => true,
        'flex-width'  => true,
    ] );

    // HTML5 markup
    add_theme_support( 'html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ] );

    // Navigation menus (manageable from WP Admin > Apparence > Menus)
    register_nav_menus( [
        'primary' => __( 'Menu principal', 'mango-dragon' ),
        'footer'  => __( 'Menu footer', 'mango-dragon' ),
    ] );

    // Custom image sizes â€” dimensionnÃ©es pour rester nettes sur grand Ã©cran (retina).
    // Les cartes de grille s'affichent ~250-400px : une source 600-750px Ã©vite la pixelisation.
    add_image_size( 'artist-card', 600, 750, [ 'center', 'top' ] );
    add_image_size( 'release-card', 600, 600, true );
    add_image_size( 'band-photo', 300, 300, [ 'center', 'top' ] );
    add_image_size( 'hero-large', 1200, 800, true );
    add_image_size( 'studio-slide', 1200, 500, true ); // carrousel Services (page Studio)
}
add_action( 'after_setup_theme', 'md_setup' );

// QualitÃ© de compression des images gÃ©nÃ©rÃ©es (dÃ©faut WP = 82). 85 = un peu plus net,
// impact minime sur le poids / la vitesse.
add_filter( 'wp_editor_set_quality', function () { return 85; } );

// ==========================================================================
// E-mails â€” expÃ©diteur valide (dÃ©livrabilitÃ©)
// Le serveur rejette l'expÃ©diteur d'enveloppe par dÃ©faut (kr7kme_â€¦@<compte> â†’
// Â« Sender address rejected: Domain not found Â»), ce qui empÃªchait TOUT envoi
// (formulaire de contact inclus). On force le Return-Path et l'adresse From sur
// le domaine â†’ e-mails acceptÃ©s et dÃ©livrÃ©s.
// ==========================================================================
add_action( 'phpmailer_init', function ( $phpmailer ) {
    $phpmailer->Sender = 'contact@mango-dragon.com'; // Return-Path (enveloppe)
} );
add_filter( 'wp_mail_from', function () { return 'contact@mango-dragon.com'; } );
add_filter( 'wp_mail_from_name', function () { return 'Site Mango Dragon'; } );

// ==========================================================================
// Admin: Enqueue media library for Release editor
// ==========================================================================
function md_admin_enqueue_media( $hook ) {
    if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
        return;
    }
    $pt = get_post_type();
    if ( 'release' === $pt ) {
        wp_enqueue_media();
    }
    if ( 'visual' === $pt ) {
        wp_enqueue_media();                        // sÃ©lecteur mÃ©diathÃ¨que
        wp_enqueue_script( 'jquery-ui-sortable' ); // rÃ©ordonnancement des photos
    }
}
add_action( 'admin_enqueue_scripts', 'md_admin_enqueue_media' );

// ==========================================================================
// Enqueue Styles & Scripts
// ==========================================================================
function md_enqueue_assets() {
    // Google Fonts
    wp_enqueue_style(
        'md-google-fonts',
        'https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Space+Mono:wght@400;700&family=DM+Mono:wght@300;400;500&display=swap',
        [],
        null
    );

    // Theme CSS
    // 'mobile' EN DERNIER : standard mobile, doit pouvoir corriger les autres feuilles.
    $css_files = [ 'variables', 'base', 'layout', 'navbar', 'components', 'bands', 'player', 'pages', 'mobile' ];
    foreach ( $css_files as $file ) {
        wp_enqueue_style( "md-{$file}", MD_URI . "/assets/css/{$file}.css", [], MD_VERSION );
    }

    // Main stylesheet (style.css â€” mostly just theme header)
    wp_enqueue_style( 'md-style', get_stylesheet_uri(), [], MD_VERSION );

    // Theme JS
    $js_files = [ 'theme-toggle', 'player', 'bands', 'filters', 'lightbox', 'forms', 'app' ];
    foreach ( $js_files as $file ) {
        wp_enqueue_script( "md-{$file}", MD_URI . "/assets/js/{$file}.js", [], MD_VERSION, true );
    }

    // Pass data to JS
    wp_localize_script( 'md-app', 'mdData', [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'siteUrl' => home_url( '/' ),
        'themeUrl' => MD_URI,
        'nonce'   => wp_create_nonce( 'md_ajax_nonce' ),
    ] );

    // Pass global track list to player.js for random-play-on-empty-queue
    wp_localize_script( 'md-player', 'mdPlayerData', [
        'tracks' => md_get_all_tracks_for_player(),
    ] );

    // Pass band settings to bands.js
    wp_localize_script( 'md-bands', 'mdBands', [
        'speed'  => [
            (int) get_theme_mod( 'md_band1_speed',  80 ),
            (int) get_theme_mod( 'md_band2_speed',  80 ),
            (int) get_theme_mod( 'md_band3_speed',  50 ),
        ],
        'width'  => [
            (int) get_theme_mod( 'md_band1_width',  440 ),
            (int) get_theme_mod( 'md_band2_width',  320 ),
            (int) get_theme_mod( 'md_band3_width',  440 ),
        ],
        'height' => [
            (int) get_theme_mod( 'md_band1_height', 330 ),
            (int) get_theme_mod( 'md_band2_height', 320 ),
            (int) get_theme_mod( 'md_band3_height', 330 ),
        ],
    ] );
}
add_action( 'wp_enqueue_scripts', 'md_enqueue_assets' );

// ==========================================================================
// Global track list for JS random play
// ==========================================================================

function md_get_all_tracks_for_player() {
    $tracks = [];

    $query = new WP_Query( [
        'post_type'      => 'release',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ] );

    foreach ( $query->posts as $release_id ) {
        $tracklist  = get_post_meta( $release_id, '_md_tracklist', true );
        $artist_ids = get_post_meta( $release_id, '_md_artist_ids', true );
        // Vignette du lecteur : affichÃ©e en petit â†’ 'medium' (300px) suffit, plus lÃ©ger.
        $artwork    = get_the_post_thumbnail_url( $release_id, 'medium' );
        $artwork    = $artwork ? $artwork : '';

        if ( ! empty( $artist_ids ) && is_array( $artist_ids ) ) {
            $artist_names = array_filter( array_map( 'get_the_title', $artist_ids ) );
            $artist       = implode( ', ', $artist_names );
        } else {
            $artist = get_the_title( $release_id );
        }
        if ( ! $artist ) {
            $artist = get_the_title( $release_id );
        }

        if ( empty( $tracklist ) || ! is_array( $tracklist ) ) {
            continue;
        }

        foreach ( $tracklist as $t ) {
            if ( empty( $t['audioUrl'] ) ) {
                continue;
            }
            $tracks[] = [
                'title'    => isset( $t['title'] )    ? sanitize_text_field( $t['title'] )    : '',
                'artist'   => sanitize_text_field( $artist ),
                'audioUrl' => esc_url_raw( $t['audioUrl'] ),
                'artwork'  => esc_url_raw( $artwork ),
                'duration' => isset( $t['duration'] ) ? sanitize_text_field( $t['duration'] ) : '',
            ];
        }
    }

    return $tracks;
}

// ==========================================================================
// Fallback Menu (when no menu is assigned in WP Admin)
// ==========================================================================
function md_fallback_menu() {
    $items = [
        home_url( '/' )                              => 'HOME',
        home_url( '/a-propos/' )                     => 'ABOUT US',
        get_post_type_archive_link( 'artiste' )      => 'ARTISTS',
        get_post_type_archive_link( 'release' )      => 'DISCOGRAPHY',
        home_url( '/mixies/' )                       => 'MIXIES',
        home_url( '/studio/' )                       => 'STUDIO',
        home_url( '/contact/' )                      => 'CONTACT',
    ];

    echo '<ul class="nav-links">';
    foreach ( $items as $url => $label ) {
        $active = ( rtrim( $url, '/' ) === rtrim( home_url( $_SERVER['REQUEST_URI'] ), '/' ) ) ? ' current-menu-item' : '';
        printf( '<li class="menu-item%s"><a href="%s">%s</a></li>', esc_attr( $active ), esc_url( $url ), esc_html( $label ) );
    }
    echo '</ul>';
}

// ==========================================================================
// Nav menu: add separator class for | styling via CSS
// ==========================================================================
function md_nav_menu_css_class( $classes, $item, $args ) {
    if ( isset( $args->theme_location ) && $args->theme_location === 'primary' ) {
        $classes[] = 'nav-item';
    }
    return $classes;
}
add_filter( 'nav_menu_css_class', 'md_nav_menu_css_class', 10, 3 );

// ==========================================================================
// Flush rewrite rules on theme activation
// ==========================================================================
function md_activate() {
    md_register_post_types();
    md_register_taxonomies();
    flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'md_activate' );

// ==========================================================================
// Excerpt length
// ==========================================================================
function md_excerpt_length( $length ) {
    return 25;
}
add_filter( 'excerpt_length', 'md_excerpt_length' );

// ==========================================================================
// Customizer â€” Background color controls
// ==========================================================================
function md_customize_register( $wp_customize ) {
    $wp_customize->add_section( 'md_colors', [
        'title'    => __( 'Couleurs de fond', 'mango-dragon' ),
        'priority' => 30,
    ] );

    // ------------------------------------------------------------------
    // Section: Bandes dÃ©filantes
    // ------------------------------------------------------------------
    $wp_customize->add_section( 'md_bands', [
        'title'    => __( 'Bandes dÃ©filantes', 'mango-dragon' ),
        'priority' => 31,
    ] );

    $band_defaults = [
        1 => [ 'label' => 'Bande 1 â€” Artistes', 'speed' => 80,  'width' => 440, 'height' => 330 ],
        2 => [ 'label' => 'Bande 2 â€” Releases', 'speed' => 80,  'width' => 320, 'height' => 320 ],
        3 => [ 'label' => 'Bande 3 â€” Photos',   'speed' => 50,  'width' => 440, 'height' => 330 ],
    ];

    foreach ( $band_defaults as $n => $d ) {
        // Speed
        $wp_customize->add_setting( "md_band{$n}_speed", [
            'default'           => $d['speed'],
            'sanitize_callback' => 'absint',
            'transport'         => 'postMessage',
        ] );
        $wp_customize->add_control( "md_band{$n}_speed", [
            'label'       => $d['label'] . ' â€” Vitesse (px/s)',
            'description' => __( 'Plus la valeur est grande, plus la bande va vite.', 'mango-dragon' ),
            'section'     => 'md_bands',
            'type'        => 'range',
            'input_attrs' => [ 'min' => 10, 'max' => 300, 'step' => 5 ],
        ] );
        // Width
        $wp_customize->add_setting( "md_band{$n}_width", [
            'default'           => $d['width'],
            'sanitize_callback' => 'absint',
            'transport'         => 'postMessage',
        ] );
        $wp_customize->add_control( "md_band{$n}_width", [
            'label'       => $d['label'] . ' â€” Largeur items (px)',
            'section'     => 'md_bands',
            'type'        => 'range',
            'input_attrs' => [ 'min' => 100, 'max' => 800, 'step' => 10 ],
        ] );
        // Height
        $wp_customize->add_setting( "md_band{$n}_height", [
            'default'           => $d['height'],
            'sanitize_callback' => 'absint',
            'transport'         => 'postMessage',
        ] );
        $wp_customize->add_control( "md_band{$n}_height", [
            'label'       => $d['label'] . ' â€” Hauteur items (px)',
            'section'     => 'md_bands',
            'type'        => 'range',
            'input_attrs' => [ 'min' => 100, 'max' => 800, 'step' => 10 ],
        ] );
    }

    // Bright mode background
    $wp_customize->add_setting( 'md_bg_bright', [
        'default'           => '#f7f4eb',
        'sanitize_callback' => 'sanitize_hex_color',
        'transport'         => 'postMessage',
    ] );
    $wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'md_bg_bright', [
        'label'   => __( 'Fond â€” Mode clair', 'mango-dragon' ),
        'section' => 'md_colors',
    ] ) );

    // Dark mode background
    $wp_customize->add_setting( 'md_bg_dark', [
        'default'           => '#000000',
        'sanitize_callback' => 'sanitize_hex_color',
        'transport'         => 'postMessage',
    ] );
    $wp_customize->add_control( new WP_Customize_Color_Control( $wp_customize, 'md_bg_dark', [
        'label'   => __( 'Fond â€” Mode sombre', 'mango-dragon' ),
        'section' => 'md_colors',
    ] ) );
}
add_action( 'customize_register', 'md_customize_register' );

function md_customize_css() {
    $bright = get_theme_mod( 'md_bg_bright', '#f7f4eb' );
    $dark   = get_theme_mod( 'md_bg_dark',   '#000000' );

    $w1 = (int) get_theme_mod( 'md_band1_width',  440 );
    $h1 = (int) get_theme_mod( 'md_band1_height', 330 );
    $w2 = (int) get_theme_mod( 'md_band2_width',  320 );
    $h2 = (int) get_theme_mod( 'md_band2_height', 320 );
    $w3 = (int) get_theme_mod( 'md_band3_width',  440 );
    $h3 = (int) get_theme_mod( 'md_band3_height', 330 );

    echo '<style id="md-customizer-colors">
        :root {
            --color-bg: ' . esc_attr( $bright ) . ';
            --footer-bg: ' . esc_attr( $bright ) . ';
            --band1-item-w: ' . $w1 . 'px;
            --band1-item-h: ' . $h1 . 'px;
            --band2-item-w: ' . $w2 . 'px;
            --band2-item-h: ' . $h2 . 'px;
            --band3-item-w: ' . $w3 . 'px;
            --band3-item-h: ' . $h3 . 'px;
        }
        html.dark-mode {
            --color-bg: ' . esc_attr( $dark ) . ';
            --footer-bg: ' . esc_attr( $dark ) . ';
        }
    </style>' . "\n";
}
add_action( 'wp_head', 'md_customize_css' );

// Live preview via postMessage
function md_customize_preview_js() {
    ?>
    <script>
    ( function( $ ) {
        wp.customize( 'md_bg_bright', function( value ) {
            value.bind( function( color ) {
                document.documentElement.style.setProperty( '--color-bg', color );
                document.documentElement.style.setProperty( '--footer-bg', color );
            } );
        } );
        wp.customize( 'md_bg_dark', function( value ) {
            value.bind( function( color ) {
                if ( document.documentElement.classList.contains( 'dark-mode' ) ) {
                    document.documentElement.style.setProperty( '--color-bg', color );
                    document.documentElement.style.setProperty( '--footer-bg', color );
                }
            } );
        } );

        // Bands live preview
        [ [1,'--band1-item-w','--band1-item-h'], [2,'--band2-item-w','--band2-item-h'], [3,'--band3-item-w','--band3-item-h'] ].forEach(function(b) {
            var n = b[0], wVar = b[1], hVar = b[2];
            wp.customize( 'md_band' + n + '_width', function( value ) {
                value.bind( function( v ) {
                    document.documentElement.style.setProperty( wVar, v + 'px' );
                    // Recalculate band duration after resize
                    if ( window.mdRecalcBand ) window.mdRecalcBand( n - 1 );
                } );
            } );
            wp.customize( 'md_band' + n + '_height', function( value ) {
                value.bind( function( v ) {
                    document.documentElement.style.setProperty( hVar, v + 'px' );
                } );
            } );
            wp.customize( 'md_band' + n + '_speed', function( value ) {
                value.bind( function( v ) {
                    if ( window.mdRecalcBand ) window.mdRecalcBand( n - 1, parseInt( v, 10 ) );
                } );
            } );
        } );
    } )( jQuery );
    </script>
    <?php
}
add_action( 'customize_preview_init', function() {
    add_action( 'wp_footer', 'md_customize_preview_js' );
} );

// ==========================================================================
// Remove admin bar margin when logged in (interferes with fixed nav)
// ==========================================================================
function md_admin_bar_adjust() {
    if ( is_admin_bar_showing() ) {
        echo '<style>
            .main-nav { top: 32px !important; }
            @media screen and (max-width: 782px) {
                .main-nav { top: 46px !important; }
            }
        </style>';
    }
}
add_action( 'wp_head', 'md_admin_bar_adjust' );
