<?php
/**
 * Template: Archive — Releases / Discographie
 */
get_header();
set_query_var( 'page_title', 'Releases' );
get_template_part( 'template-parts/page-header' );

// Ordre serveur par défaut = plus récentes d'abord (repli sans JS).
// Le tri interactif Latest / Oldest / Random est appliqué côté client (filters.js).
$releases = get_posts( [
    'post_type'      => 'release',
    'posts_per_page' => -1,
    'orderby'        => 'date',
    'order'          => 'DESC',
] );
?>

<section class="section">
    <div class="container">
        <?php
        $filter_config = [
            'taxonomies'   => [ 'genre', 'release_type' ],
            'show_artists' => true,
            'show_sort'    => true,
        ];
        set_query_var( 'filter_config', $filter_config );
        get_template_part( 'template-parts/filter', 'bar' );
        ?>

        <?php if ( ! empty( $releases ) ) : ?>
        <div class="cards-grid" id="cards-grid">
            <?php foreach ( $releases as $release ) :
                set_query_var( 'release', $release );
                get_template_part( 'template-parts/card', 'release' );
            endforeach; ?>
        </div>
        <?php else : ?>
        <p class="no-results">No releases yet.</p>
        <?php endif; ?>
    </div>
</section>

<?php get_footer(); ?>
