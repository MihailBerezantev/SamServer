<?php
/**
 * Template: Page — Studio (mastering, recording, mixing services)
 * Slug: studio
 */
get_header();
// Titre de page (page-header « STUDIO ») retiré sur cette page uniquement.
?>

<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
<?php if ( get_the_content() ) : ?>
<section class="page-intro section">
    <div class="container">
        <div class="page-intro-content"><?php the_content(); ?></div>
    </div>
</section>
<?php endif; endwhile; endif; wp_reset_postdata(); ?>

<?php
// Services : CPT géré via CPT UI + ACF (menu « Services »). Ordre via le champ « Ordre » (menu_order).
$md_services = get_posts( [
    'post_type'      => 'service',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => [ 'menu_order' => 'ASC', 'date' => 'ASC' ],
] );
?>
<?php if ( ! empty( $md_services ) ) :
    $md_slide_count = count( $md_services );
?>
<section class="studio-services studio-slider<?php echo $md_slide_count > 1 ? '' : ' studio-slider--single'; ?>" id="studio-slider" style="padding-top:20px;">
    <div class="container">

        <!-- Photo du service sélectionné (défilement latéral) — pleine largeur, sans flèches par-dessus -->
        <div class="slider-photos">
            <div class="slider-photos__viewport">
                <div class="slider-photos__track">
                    <?php foreach ( $md_services as $i => $sp ) :
                        $thumb_id = get_post_thumbnail_id( $sp->ID );
                    ?>
                    <div class="slide-photo">
                        <?php
                        if ( $thumb_id ) {
                            // Image responsive (srcset) — nette sur grand écran / retina. Recadrée en 1200×500 via le CSS (object-fit).
                            $img_attr = [
                                'alt'     => esc_attr( get_the_title( $sp->ID ) ),
                                'sizes'   => '(max-width: 700px) 100vw, (max-width: 1200px) 92vw, 1300px',
                                'loading' => 0 === $i ? 'eager' : 'lazy',
                            ];
                            if ( 0 === $i ) { $img_attr['fetchpriority'] = 'high'; }
                            echo wp_get_attachment_image( $thumb_id, 'large', false, $img_attr );
                        } else {
                            echo '<div class="slide-photo__placeholder"></div>';
                        }
                        ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Navigation : flèches + points, SOUS l'image (jamais par-dessus, ne réduit pas la largeur de l'image) -->
        <?php if ( $md_slide_count > 1 ) : ?>
        <div class="slider-nav">
            <button type="button" class="slider-arrow slider-arrow--prev" aria-label="Previous service">&lsaquo;</button>
            <div class="slider-dots" role="tablist" aria-label="Choose a service">
            <?php foreach ( $md_services as $i => $sp ) : ?>
            <button type="button" class="dot<?php echo $i === 0 ? ' active' : ''; ?>" data-index="<?php echo intval( $i ); ?>" role="tab" aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>" aria-label="<?php echo esc_attr( get_the_title( $sp->ID ) ); ?>"></button>
            <?php endforeach; ?>
            </div>
            <button type="button" class="slider-arrow slider-arrow--next" aria-label="Next service">&rsaquo;</button>
        </div>
        <?php endif; ?>

        <!-- Détails du service sélectionné -->
        <div class="slider-details">
            <?php foreach ( $md_services as $i => $sp ) :
                $desc  = function_exists( 'get_field' ) ? get_field( 'service_description', $sp->ID ) : '';
                $feat  = function_exists( 'get_field' ) ? get_field( 'service_features', $sp->ID ) : '';
                $lines = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $feat ) ) );
            ?>
            <div class="slide-detail<?php echo $i === 0 ? ' active' : ''; ?>" data-index="<?php echo intval( $i ); ?>">
                <h3><?php echo esc_html( get_the_title( $sp->ID ) ); ?></h3>
                <?php if ( $desc ) { echo wp_kses_post( $desc ); } ?>
                <?php if ( ! empty( $lines ) ) : ?>
                <ul class="service-features">
                    <?php foreach ( $lines as $l ) : ?><li><?php echo esc_html( $l ); ?></li><?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

    </div>
</section>
<?php endif; ?>

<section class="studio-contact section">
    <div class="container">
        <h2>Request a quote</h2>
        <p>
            Tell us about your project and we'll get back to you with a tailored proposal.
        </p>
        <?php
        $form_config = [
            'id'          => 'studio-form',
            'submit_text' => 'Send request',
            'page_id'     => get_queried_object_id(),
        ];
        set_query_var( 'form_config', $form_config );
        get_template_part( 'template-parts/upload', 'form' );
        ?>
    </div>
</section>

<?php get_footer(); ?>
