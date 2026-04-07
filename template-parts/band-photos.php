<?php
/**
 * Template Part: Band — Photos (scrolling horizontal band — artists + studio photos)
 * Reuses artiste featured images with a different speed class
 */

$artistes = get_posts( [
    'post_type'      => 'artiste',
    'posts_per_page' => -1,
    'orderby'        => 'rand',
] );

if ( empty( $artistes ) ) {
    return;
}

// Repeat 4x per half so one set always exceeds any screen width
$artistes_loop = array_merge( $artistes, $artistes, $artistes, $artistes );
?>
<section class="scroll-band" aria-label="Photos">
    <button class="band-arrow band-arrow--left" aria-label="Défiler à gauche">&#9664;</button>
    <div class="band-track band-track--rtl">
        <?php foreach ( $artistes_loop as $artiste ) :
            $thumb = get_the_post_thumbnail_url( $artiste->ID, 'band-photo' );
            if ( ! $thumb ) {
                $thumb = MD_URI . '/assets/images/logo.png';
            }
        ?>
        <span class="band-item band-item--photo">
            <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $artiste->post_title ); ?>" loading="lazy" width="220" height="165">
            <span class="band-item__label"><?php echo esc_html( $artiste->post_title ); ?></span>
        </span>
        <?php endforeach; ?>
        <!-- Clone for seamless loop -->
        <?php foreach ( $artistes_loop as $artiste ) :
            $thumb = get_the_post_thumbnail_url( $artiste->ID, 'band-photo' );
            if ( ! $thumb ) {
                $thumb = MD_URI . '/assets/images/logo.png';
            }
        ?>
        <span class="band-item band-item--photo" aria-hidden="true">
            <img src="<?php echo esc_url( $thumb ); ?>" alt="" loading="lazy" width="220" height="165">
            <span class="band-item__label"><?php echo esc_html( $artiste->post_title ); ?></span>
        </span>
        <?php endforeach; ?>
    </div>
    <button class="band-arrow band-arrow--right" aria-label="Défiler à droite">&#9654;</button>
</section>
