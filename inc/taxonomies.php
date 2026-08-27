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
}
add_action( 'init', 'md_register_taxonomies' );
