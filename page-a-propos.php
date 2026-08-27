<?php
/**
 * Template: Page — À propos
 * Slug: a-propos
 */

get_header();
get_template_part( 'template-parts/page-header' );
?>

<section class="about-content section">
    <div class="container container--narrow" style="text-align:center">
        <div class="about-text">
        <?php
        if ( have_posts() ) :
            while ( have_posts() ) : the_post();
                the_content();
            endwhile;
        endif;
        ?>
        </div>

        <div class="about-stats">
            <div class="stat-item">
                <span class="stat-number">
                    <?php echo wp_count_posts( 'artiste' )->publish; ?>
                </span>
                <span class="stat-label label">Artists</span>
            </div>
            <div class="stat-item">
                <span class="stat-number">
                    <?php echo wp_count_posts( 'release' )->publish; ?>
                </span>
                <span class="stat-label label">Releases</span>
            </div>
            <div class="stat-item">
                <span class="stat-number">Geneva</span>
                <span class="stat-label label">Based in</span>
            </div>
        </div>

        <p><a href="mailto:contact@mango-dragon.com">contact@mango-dragon.com</a></p>
        <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="btn btn--filled" data-ajax-link>Contact</a>
    </div>
</section>

<?php get_footer(); ?>
