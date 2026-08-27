<?php
/**
 * Custom Taxonomies — Genre, Release Type, Artist Type
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function md_register_taxonomies() {

    // ======================================================================
    // Genre (shared between artiste & release)
    // ======================================================================
    if ( ! md_cptui_owns_taxonomy( 'genre' ) )
    register_taxonomy( 'genre', [ 'artiste', 'release' ], [
        'labels' => [
            'name'          => 'Genres',
            'singular_name' => 'Genre',
            'search_items'  => 'Rechercher des genres',
            'all_items'     => 'Tous les genres',
            'edit_item'     => 'Modifier le genre',
            'update_item'   => 'Mettre à jour le genre',
            'add_new_item'  => 'Ajouter un genre',
            'new_item_name' => 'Nom du nouveau genre',
            'menu_name'     => 'Genres',
        ],
        'hierarchical' => false,
        'public'       => true,
        'show_in_rest' => true,
        'rewrite'      => [ 'slug' => 'genre' ],
    ] );

    // ======================================================================
    // Type de release (Album, EP, Single, Compilation)
    // ======================================================================
    if ( ! md_cptui_owns_taxonomy( 'release_type' ) )
    register_taxonomy( 'release_type', [ 'release' ], [
        'labels' => [
            'name'          => 'Types de release',
            'singular_name' => 'Type de release',
            'search_items'  => 'Rechercher',
            'all_items'     => 'Tous les types',
            'edit_item'     => 'Modifier le type',
            'update_item'   => 'Mettre à jour',
            'add_new_item'  => 'Ajouter un type',
            'new_item_name' => 'Nom du nouveau type',
            'menu_name'     => 'Types de release',
        ],
        'hierarchical' => true,
        'public'       => true,
        'show_in_rest' => true,
        'rewrite'      => [ 'slug' => 'type-release' ],
    ] );

    // ======================================================================
    // Type d'artiste (Musical, Visual)
    // ======================================================================
    if ( ! md_cptui_owns_taxonomy( 'artist_type' ) )
    register_taxonomy( 'artist_type', [ 'artiste' ], [
        'labels' => [
            'name'          => 'Types d\'artiste',
            'singular_name' => 'Type d\'artiste',
            'search_items'  => 'Rechercher',
            'all_items'     => 'Tous les types',
            'edit_item'     => 'Modifier le type',
            'update_item'   => 'Mettre à jour',
            'add_new_item'  => 'Ajouter un type',
            'new_item_name' => 'Nom du nouveau type',
            'menu_name'     => 'Types d\'artiste',
        ],
        'hierarchical' => true,
        'public'       => true,
        'show_in_rest' => true,
        'rewrite'      => [ 'slug' => 'type-artiste' ],
    ] );

    // ======================================================================
    // Type de visuel (graphic design, texture, vjing, 3D, photo, collage…)
    // ======================================================================
    if ( ! md_cptui_owns_taxonomy( 'visual_type' ) )
    register_taxonomy( 'visual_type', [ 'visual' ], [
        'labels' => [
            'name'          => 'Types de visuel',
            'singular_name' => 'Type de visuel',
            'search_items'  => 'Rechercher',
            'all_items'     => 'Tous les types',
            'edit_item'     => 'Modifier le type',
            'update_item'   => 'Mettre à jour',
            'add_new_item'  => 'Ajouter un type',
            'new_item_name' => 'Nom du nouveau type',
            'menu_name'     => 'Types de visuel',
        ],
        // Aligné sur la taxonomie réellement active, déclarée via CPT UI :
        // une liste plate de types, sans hiérarchie.
        'hierarchical' => false,
        'public'       => true,
        'show_in_rest' => true,
        'rewrite'      => [ 'slug' => 'type-visuel' ],
    ] );
}
add_action( 'init', 'md_register_taxonomies' );

/**
 * Amorce les types de visuel par défaut, une seule fois.
 *
 * L'option sert de garde : sans elle, un type supprimé volontairement dans le
 * back-office réapparaîtrait à chaque chargement. La liste n'est qu'un point de
 * départ — les types se gèrent ensuite depuis l'administration.
 */
function md_seed_visual_types() {
    if ( get_option( 'md_visual_types_seeded' ) ) {
        return;
    }
    if ( ! taxonomy_exists( 'visual_type' ) ) {
        return;
    }
    foreach ( [ 'Graphic Design', 'Texture', 'VJing', '3D', 'Photo', 'Collage' ] as $name ) {
        if ( ! term_exists( $name, 'visual_type' ) ) {
            wp_insert_term( $name, 'visual_type' );
        }
    }
    update_option( 'md_visual_types_seeded', 1 );
}
add_action( 'init', 'md_seed_visual_types', 20 );
