<?php
/**
 * Template Part: Band — Artistes (scrolling horizontal band)
 */

$artistes = get_posts( [
    'post_type'      => 'artiste',
    'posts_per_page' => -1,
    'orderby'        => 'rand',
    'order'          => 'ASC',
] );

if ( empty( $artistes ) ) {
    return;
}

// Repeat 4x per half so one set always exceeds any screen width
$artistes_loop = array_merge( $artistes, $artistes, $artistes, $artistes );
?>
<section class="scroll-band" aria-label="Artists">
    <div class="band-track band-track--rtl">
        <?php foreach ( $artistes_loop as $artiste ) :
            $thumb = get_the_post_thumbnail_url( $artiste->ID, 'band-photo' );
            if ( ! $thumb ) {
                $thumb = MD_URI . '/assets/images/logo.png';
            }
        ?>
        <a href="<?php echo esc_url( get_permalink( $artiste->ID ) ); ?>" class="band-item band-item--artist" data-ajax-link>
            <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $artiste->post_title ); ?>" loading="lazy" width="220" height="165">
            <span class="band-item__label"><?php echo esc_html( $artiste->post_title ); ?></span>
        </a>
        <?php endforeach; ?>
        <!-- Clone for seamless loop -->
        <?php foreach ( $artistes_loop as $artiste ) :
            $thumb = get_the_post_thumbnail_url( $artiste->ID, 'band-photo' );
            if ( ! $thumb ) {
                $thumb = MD_URI . '/assets/images/logo.png';
            }
        ?>
        <a href="<?php echo esc_url( get_permalink( $artiste->ID ) ); ?>" class="band-item band-item--artist" aria-hidden="true" tabindex="-1" data-ajax-link>
            <img src="<?php echo esc_url( $thumb ); ?>" alt="" loading="lazy" width="220" height="165">
            <span class="band-item__label"><?php echo esc_html( $artiste->post_title ); ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</section>
