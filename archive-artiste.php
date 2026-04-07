<?php
/**
 * Template: Archive — Artistes catalog
 */
get_header();

$artistes = get_posts( [
    'post_type'      => 'artiste',
    'posts_per_page' => -1,
    'orderby'        => 'rand',
    'order'          => 'ASC',
] );
?>

<section class="page-header section">
    <div class="container">
        <h1>Artistes</h1>
    </div>
</section>

<section class="section">
    <div class="container">
        <?php
        $filter_config = [
            'taxonomies'   => [ 'genre', 'artist_type' ],
            'show_artists' => false,
        ];
        set_query_var( 'filter_config', $filter_config );
        get_template_part( 'template-parts/filter', 'bar' );
        ?>

        <?php if ( ! empty( $artistes ) ) : ?>
        <div class="cards-grid" id="cards-grid">
            <?php foreach ( $artistes as $artiste ) :
                set_query_var( 'artiste', $artiste );
                get_template_part( 'template-parts/card', 'artiste' );
            endforeach; ?>
        </div>
        <?php else : ?>
        <p class="no-results">Aucun artiste pour le moment.</p>
        <?php endif; ?>
    </div>
</section>

<?php get_footer(); ?>
