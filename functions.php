<?php
/**
 * Mango Dragon International â€” Theme Functions
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MD_VERSION', '4.9.29' );
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
require_once MD_DIR . '/inc/gif-video.php';    // GIF animés servis en vidéo muette quand un .mp4 existe à côté
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

    // ------------------------------------------------------------------
    // Section: Pied de page — liens sociaux (vides = icône masquée)
    // ------------------------------------------------------------------
    $wp_customize->add_section( 'md_footer_links', [
        'title'    => __( 'Pied de page — liens', 'mango-dragon' ),
        'priority' => 32,
    ] );
    foreach ( md_footer_social_platforms() as $key => $platform ) {
        $wp_customize->add_setting( "md_social_{$key}", [
            'default'           => '',
            'sanitize_callback' => 'esc_url_raw',
        ] );
        $wp_customize->add_control( "md_social_{$key}", [
            'label'       => $platform['label'],
            'section'     => 'md_footer_links',
            'type'        => 'url',
            'input_attrs' => [ 'placeholder' => $platform['placeholder'] ],
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

/**
 * Plateformes du pied de page, dans l'ordre d'affichage.
 * Spotify retiré le 2026-09-09 à la demande de Mihail, remplacé par Linktree.
 */
function md_footer_social_platforms() {
    return [
        'instagram'  => [
            'label'       => 'Instagram',
            'placeholder' => 'https://www.instagram.com/…',
            'icon'        => '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>',
        ],
        'soundcloud' => [
            'label'       => 'SoundCloud',
            'placeholder' => 'https://soundcloud.com/…',
            'icon'        => '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M11.56 8.87V17h8.76c1.85-.04 2.68-1.18 2.68-2.57 0-1.41-1.04-2.56-2.46-2.56-.34 0-.67.07-.96.2-.31-2.33-2.27-4.1-4.68-4.1-1.19 0-2.27.44-3.1 1.15-.12.1-.24.34-.24.52v.23zM10.22 9.3V17h.67V8.97c-.21.09-.46.19-.67.33zM9.06 10.13V17h.67V9.6c-.23.15-.45.33-.67.53zM7.9 17h.67V11.2c-.13.2-.32.37-.47.54-.07.06-.14.12-.2.18V17zM6.73 17h.67v-4.49c-.23.3-.44.63-.67.97V17zM5.57 17h.67v-2.6c-.17.39-.37.77-.55 1.15-.04.08-.08.16-.12.24V17zM4.4 17h.67v-.58c-.22.24-.43.39-.67.58zM1.23 15.53c0 .81.66 1.47 1.47 1.47.82 0 1.48-.66 1.48-1.47v-1.7c-.4-.2-.84-.31-1.31-.31-.65 0-1.22.23-1.64.72v1.29z"/></svg>',
        ],
        'bandcamp'   => [
            'label'       => 'Bandcamp',
            'placeholder' => 'https://….bandcamp.com/',
            'icon'        => '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M0 18.75l7.437-13.5H24l-7.438 13.5H0z"/></svg>',
        ],
        'linktree'   => [
            'label'       => 'Linktree',
            'placeholder' => 'https://linktr.ee/…',
            // Arbre à six branches, dessin du logo Linktree simplifié.
            'icon'        => '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M10.9 0h2.2v6.6l4.7-4.7 1.55 1.55-4.7 4.7H21.3v2.2h-6.65l4.7 4.7-1.55 1.55L13.1 11.9V24h-2.2V11.9l-4.7 4.7-1.55-1.55 4.7-4.7H2.7v-2.2h6.65L4.65 3.45 6.2 1.9l4.7 4.7z"/></svg>',
        ],
    ];
}

/**
 * Liens du pied de page réellement renseignés (Apparence > Personnaliser >
 * Pied de page — liens). Une URL vide = icône absente : plus de lien mort « # ».
 */
function md_footer_social_links() {
    $links = [];
    foreach ( md_footer_social_platforms() as $key => $platform ) {
        $url = trim( (string) get_theme_mod( "md_social_{$key}", '' ) );
        if ( '' === $url ) {
            continue;
        }
        $links[] = [ 'url' => $url, 'label' => $platform['label'], 'icon' => $platform['icon'] ];
    }
    return $links;
}

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
