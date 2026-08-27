<?php
/**
 * Template: Page — Mixies (long-form mixes section)
 * Slug: mixies
 */
get_header();
set_query_var( 'page_title', 'Mixies' );
get_template_part( 'template-parts/page-header' );
?>

<section class="section">
    <div class="container">
        <?php
        if ( have_posts() ) :
            while ( have_posts() ) : the_post();
                the_content();
            endwhile;
        endif;
        ?>

        <div class="mixes-section" id="mixes-list">
            <p class="no-results">Mixies are coming.</p>
        </div>
    </div>
</section>

<?php get_footer(); ?>

