<?php
/**
 * Template générique — Single.
 *
 * Sert de gabarit par défaut pour tout contenu qui n'a pas de template dédié,
 * notamment les types de contenu créés via CPT UI. Affiche titre, image mise en
 * avant, contenu, puis TOUS les champs ACF attachés (rendu automatique).
 */
get_header();

while ( have_posts() ) : the_post();
    $post_id   = get_the_ID();
    $thumb     = get_the_post_thumbnail_url( $post_id, 'large' );
    $pt_obj    = get_post_type_object( get_post_type() );
    $pt_label  = $pt_obj ? $pt_obj->labels->singular_name : '';
?>

<!-- HERO -->
<section class="single-hero">
    <div class="container">
        <?php if ( $pt_label ) : ?>
            <p class="single-hero__kicker"><?php echo esc_html( $pt_label ); ?></p>
        <?php endif; ?>
        <h1><?php the_title(); ?></h1>
    </div>
</section>

<!-- CONTENU -->
<section class="single-content-layout section">
    <div class="container">
        <div class="content-grid">
            <?php if ( $thumb ) : ?>
            <div class="content-photo">
                <img src="<?php echo esc_url( $thumb ); ?>"
                     alt="<?php echo esc_attr( get_the_title() ); ?>"
                     class="content-image" loading="lazy">
            </div>
            <?php endif; ?>

            <div class="content-text">
                <?php if ( get_the_content() ) : ?>
                <div class="content-section">
                    <?php the_content(); ?>
                </div>
                <?php endif; ?>

                <?php
                // Rendu automatique de tous les champs ACF de ce contenu.
                if ( function_exists( 'md_render_extra_acf_fields' ) ) {
                    md_render_extra_acf_fields( $post_id, [], '' );
                }
                ?>
            </div>
        </div>
    </div>
</section>

<?php
endwhile;

get_footer();
