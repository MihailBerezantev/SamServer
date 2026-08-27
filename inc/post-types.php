<?php
/**
 * Custom Post Types — Artiste & Release
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function md_register_post_types() {

    // ======================================================================
    // CPT: Artiste  (fallback — CPT UI est prioritaire s'il le définit)
    // ======================================================================
    if ( ! md_cptui_owns_post_type( 'artiste' ) )
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
    // CPT: Release  (fallback — CPT UI est prioritaire s'il le définit)
    // ======================================================================
    if ( ! md_cptui_owns_post_type( 'release' ) )
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

    // Le CPT « Photos Studio » (studio_photo) a été supprimé : les images sont
    // désormais gérées via les images à la une du CPT « Service ».

    // ======================================================================
    // CPT: Gig / Concert  (fallback — CPT UI est prioritaire s'il le définit)
    // Affiché sur les fiches artistes (sections « Upcoming gigs » / « Past events »).
    // ======================================================================
    if ( ! md_cptui_owns_post_type( 'gig' ) )
    register_post_type( 'gig', [
        'labels' => [
            'name'               => 'Gigs',
            'singular_name'      => 'Gig',
            'add_new'            => 'Ajouter',
            'add_new_item'       => 'Ajouter un concert',
            'edit_item'          => 'Modifier le concert',
            'new_item'           => 'Nouveau concert',
            'search_items'       => 'Rechercher des concerts',
            'not_found'          => 'Aucun concert trouvé',
            'not_found_in_trash' => 'Aucun concert dans la corbeille',
            'all_items'          => 'Tous les concerts',
            'menu_name'          => 'Gigs',
        ],
        'public'             => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'has_archive'        => false,
        'exclude_from_search'=> true,
        'rewrite'            => false,
        'menu_icon'          => 'dashicons-calendar-alt',
        'menu_position'      => 8,
        'supports'           => [ 'title' ],
        'show_in_rest'       => true,
        'publicly_queryable' => false,
    ] );

    // ======================================================================
    // CPT: Service  (fallback — CPT UI est prioritaire s'il le définit)
    // Blocs de la page Studio (Enregistrement, Mixage, Mastering…).
    // ======================================================================
    if ( ! md_cptui_owns_post_type( 'service' ) )
    register_post_type( 'service', [
        'labels' => [
            'name'               => 'Services',
            'singular_name'      => 'Service',
            'add_new'            => 'Ajouter',
            'add_new_item'       => 'Ajouter un service',
            'edit_item'          => 'Modifier le service',
            'new_item'           => 'Nouveau service',
            'search_items'       => 'Rechercher des services',
            'not_found'          => 'Aucun service trouvé',
            'not_found_in_trash' => 'Aucun service dans la corbeille',
            'all_items'          => 'Tous les services',
            'menu_name'          => 'Services',
        ],
        'public'             => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'has_archive'        => false,
        'exclude_from_search'=> true,
        'rewrite'            => false,
        'menu_icon'          => 'dashicons-admin-tools',
        'menu_position'      => 9,
        'supports'           => [ 'title', 'page-attributes' ],
        'show_in_rest'       => true,
        'publicly_queryable' => false,
    ] );

    // ======================================================================
    // CPT: Visual  (fallback — CPT UI est prioritaire s'il le définit)
    // Galerie de la page « Visuals » : une entrée = une image à la une, avec
    // sa propre page (photo en grand + description), comme les Releases.
    // ======================================================================
    if ( ! md_cptui_owns_post_type( 'visual' ) )
    register_post_type( 'visual', [
        'labels' => [
            'name'               => 'Visuals',
            'singular_name'      => 'Visual',
            'add_new'            => 'Ajouter',
            'add_new_item'       => 'Ajouter un visuel',
            'edit_item'          => 'Modifier le visuel',
            'new_item'           => 'Nouveau visuel',
            'search_items'       => 'Rechercher des visuels',
            'not_found'          => 'Aucun visuel trouvé',
            'not_found_in_trash' => 'Aucun visuel dans la corbeille',
            'all_items'          => 'Tous les visuels',
            'menu_name'          => 'Visuals',
        ],
        'public'             => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'has_archive'        => false,
        'exclude_from_search'=> true,
        'rewrite'            => [ 'slug' => 'visuals', 'with_front' => false ],
        'menu_icon'          => 'dashicons-format-image',
        'menu_position'      => 8,
        // 'editor' : permet d'insérer un bloc Galerie natif WordPress dans la
        // fiche (choix multiple de photos depuis la médiathèque).
        'supports'           => [ 'title', 'editor', 'thumbnail', 'page-attributes' ],
        'show_in_rest'       => true,
        'publicly_queryable' => true,
    ] );
}
add_action( 'init', 'md_register_post_types' );
