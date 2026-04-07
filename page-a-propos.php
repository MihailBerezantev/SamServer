<?php
/**
 * Template: Page — À propos
 * Slug: a-propos
 */
get_header();
?>

<section class="about-hero section">
    <div class="container" style="text-align:center">
        <h1>About Us</h1>
    </div>
</section>

<section class="about-content section">
    <div class="container container--narrow" style="text-align:center">
        <h2>Mango Dragon International</h2>

        <?php
        if ( have_posts() ) :
            while ( have_posts() ) : the_post();
                the_content();
            endwhile;
        endif;
        ?>

        <div class="about-stats">
            <div class="stat-item">
                <span class="stat-number">
                    <?php echo wp_count_posts( 'artiste' )->publish; ?>
                </span>
                <span class="stat-label label">Artistes</span>
            </div>
            <div class="stat-item">
                <span class="stat-number">
                    <?php echo wp_count_posts( 'release' )->publish; ?>
                </span>
                <span class="stat-label label">Releases</span>
            </div>
            <div class="stat-item">
                <span class="stat-number">Genève</span>
                <span class="stat-label label">Basé à</span>
            </div>
        </div>

        <p><a href="mailto:contact@mangodragon.ch">contact@mangodragon.ch</a></p>
        <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="btn btn--filled" data-ajax-link>Contact</a>
    </div>
</section>

<?php get_footer(); ?>
