<?php
/**
 * Template: Archive — Artistes catalog
 */
get_header();
set_query_var( 'page_title', 'Artists' );
get_template_part( 'template-parts/page-header' );

$artistes = get_posts( [
    'post_type'      => 'artiste',
    'posts_per_page' => -1,
    'orderby'        => 'rand',
    'order'          => 'ASC',
] );
?>

<section class="section">
    <div class="container">
        <?php
        // Ordre d'affichage des lignes de filtres : Genre, Artist, Type.
        // « Type » = type de release (EP, Mix…), pas artist_type.
        $filter_config = [
            'rows' => [
                [ 'type' => 'taxonomy', 'taxonomy' => 'genre',        'label' => 'Genre' ],
                [ 'type' => 'artists',                                'label' => 'Artist' ],
                [ 'type' => 'taxonomy', 'taxonomy' => 'release_type', 'label' => 'Type' ],
            ],
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
        <p class="no-results">No artists yet.</p>
        <?php endif; ?>
    </div>
</section>

<?php get_footer(); ?>
