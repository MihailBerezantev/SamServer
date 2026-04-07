<?php
/**
 * Template: Page — Mixies (long-form mixes section)
 * Slug: mixies
 */
get_header();
?>

<section class="page-header section">
    <div class="container">
        <h1>Mixies</h1>

    </div>
</section>

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
            <p class="no-results">Les mixes arrivent bientôt. Restez connectés.</p>
        </div>
    </div>
</section>

<?php get_footer(); ?>
