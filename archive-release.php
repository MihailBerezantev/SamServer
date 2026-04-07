<?php
/**
 * Template: Archive — Releases / Discographie
 */
get_header();

$releases = get_posts( [
    'post_type'      => 'release',
    'posts_per_page' => -1,
    'orderby'        => 'rand',
    'order'          => 'DESC',
] );
?>

<section class="page-header section">
    <div class="container">
        <h1>Discographie</h1>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php
        $filter_config = [
            'taxonomies'   => [ 'genre', 'release_type' ],
            'show_artists' => true,
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
        <p class="no-results">Aucune release pour le moment.</p>
        <?php endif; ?>
    </div>
</section>

<?php get_footer(); ?>
