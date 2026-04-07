<?php
/**
 * Custom Post Types — Artiste & Release
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function md_register_post_types() {

    // ======================================================================
    // CPT: Artiste
    // ======================================================================
    register_post_type( 'artiste', [
        'labels' => [
            'name'               => 'Artistes',
            'singular_name'      => 'Artiste',
            'add_new'            => 'Ajouter',
            'add_new_item'       => 'Ajouter un artiste',
            'edit_item'          => 'Modifier l\'artiste',
            'new_item'           => 'Nouvel artiste',
            'view_item'          => 'Voir l\'artiste',
            'search_items'       => 'Rechercher des artistes',
            'not_found'          => 'Aucun artiste trouvé',
            'not_found_in_trash' => 'Aucun artiste dans la corbeille',
            'all_items'          => 'Tous les artistes',
            'menu_name'          => 'Artistes',
        ],
        'public'             => true,
        'has_archive'        => true,
        'rewrite'            => [ 'slug' => 'artistes', 'with_front' => false ],
        'menu_icon'          => 'dashicons-groups',
        'menu_position'      => 5,
        'supports'           => [ 'title', 'thumbnail', 'excerpt' ],
        'show_in_rest'       => true,
        'publicly_queryable' => true,
    ] );

    // ======================================================================
    // CPT: Release
    // ======================================================================
    register_post_type( 'release', [
        'labels' => [
            'name'               => 'Releases',
            'singular_name'      => 'Release',
            'add_new'            => 'Ajouter',
            'add_new_item'       => 'Ajouter une release',
            'edit_item'          => 'Modifier la release',
            'new_item'           => 'Nouvelle release',
            'view_item'          => 'Voir la release',
            'search_items'       => 'Rechercher des releases',
            'not_found'          => 'Aucune release trouvée',
            'not_found_in_trash' => 'Aucune release dans la corbeille',
            'all_items'          => 'Toutes les releases',
            'menu_name'          => 'Releases',
        ],
        'public'             => true,
        'has_archive'        => true,
        'rewrite'            => [ 'slug' => 'discographie', 'with_front' => false ],
        'menu_icon'          => 'dashicons-album',
        'menu_position'      => 6,
        'supports'           => [ 'title', 'thumbnail', 'excerpt' ],
        'show_in_rest'       => true,
        'publicly_queryable' => true,
    ] );
}
add_action( 'init', 'md_register_post_types' );
