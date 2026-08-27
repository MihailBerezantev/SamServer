<?php
/**
 * Template: Generic Page — fallback for pages without a dedicated template
 */
get_header();
get_template_part( 'template-parts/page-header' );
?>

<section class="section">
    <div class="container container--narrow">
        <?php
        if ( have_posts() ) :
            while ( have_posts() ) : the_post();
                ?>
                <div class="page-content">
                    <?php the_content(); ?>
                </div>
                <?php
            endwhile;
        endif;
        ?>
    </div>
</section>

<?php get_footer(); ?>

