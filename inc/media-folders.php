<?php
/**
 * Médiathèque — vue « Non classés ».
 *
 * Les dossiers de la médiathèque (plugin Folders) sont des étiquettes stockées
 * dans la taxonomie hiérarchique media_folder, pas des tiroirs : ranger un
 * fichier dans un dossier ne le retire pas de la vue principale, qui continue
 * d'afficher l'intégralité des médias. Impossible, dans ces conditions, de
 * savoir ce qu'il reste à trier.
 *
 * Ce module ajoute une vue « Non classés » à côté de Tous / Images / Audio :
 * elle ne liste que les médias n'appartenant à aucun dossier, et affiche leur
 * nombre. La liste se vide à mesure du rangement.
 *
 * Ne s'active que si la taxonomie existe — donc rien ne s'affiche si le plugin
 * est désactivé.
 */

if ( ! defined( 'MD_MEDIA_FOLDER_TAX' ) ) {
    define( 'MD_MEDIA_FOLDER_TAX', 'media_folder' );
}
if ( ! defined( 'MD_UNFILED_VAR' ) ) {
    define( 'MD_UNFILED_VAR', 'md_unfiled' );
}

/**
 * Clause réutilisée par le comptage et par le filtrage de la liste.
 *
 * @return array
 */
function md_unfiled_tax_query() {
    return [
        [
            'taxonomy' => MD_MEDIA_FOLDER_TAX,
            'operator' => 'NOT EXISTS',
        ],
    ];
}

/**
 * Nombre de médias n'appartenant à aucun dossier.
 *
 * Mémoïsé : la vue et la requête principale le demandent tous deux dans la
 * même requête HTTP.
 *
 * @return int
 */
function md_unfiled_media_count() {
    static $count = null;

    if ( null !== $count ) {
        return $count;
    }

    if ( ! taxonomy_exists( MD_MEDIA_FOLDER_TAX ) ) {
        $count = 0;
        return $count;
    }

    $query = new WP_Query( [
        'post_type'      => 'attachment',
        'post_status'    => 'inherit',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'tax_query'      => md_unfiled_tax_query(),
    ] );

    $count = (int) $query->found_posts;

    return $count;
}

/**
 * Ajoute le lien « Non classés » à la barre de vues de la médiathèque.
 */
function md_media_unfiled_view( $views ) {
    if ( ! taxonomy_exists( MD_MEDIA_FOLDER_TAX ) ) {
        return $views;
    }

    $active = ! empty( $_GET[ MD_UNFILED_VAR ] );

    $views['md_unfiled'] = sprintf(
        '<a href="%s"%s>%s <span class="count">(%s)</span></a>',
        esc_url( add_query_arg( MD_UNFILED_VAR, 1, admin_url( 'upload.php' ) ) ),
        $active ? ' class="current" aria-current="page"' : '',
        esc_html__( 'Non classés', 'mango-dragon' ),
        number_format_i18n( md_unfiled_media_count() )
    );

    return $views;
}
add_filter( 'views_upload', 'md_media_unfiled_view' );

/**
 * Restreint la liste aux médias sans dossier quand la vue est active.
 */
function md_media_filter_unfiled( $query ) {
    global $pagenow;

    if ( ! is_admin() || 'upload.php' !== $pagenow || ! $query->is_main_query() ) {
        return;
    }
    if ( empty( $_GET[ MD_UNFILED_VAR ] ) || ! taxonomy_exists( MD_MEDIA_FOLDER_TAX ) ) {
        return;
    }

    $query->set( 'tax_query', md_unfiled_tax_query() );
}
add_action( 'pre_get_posts', 'md_media_filter_unfiled' );
