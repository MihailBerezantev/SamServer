<?php
/**
 * Template Part: Band — Releases (scrolling horizontal band)
 */

$releases = get_posts( [
    'post_type'      => 'release',
    'posts_per_page' => -1,
    'orderby'        => 'rand',
    'order'          => 'DESC',
] );

if ( empty( $releases ) ) {
    return;
}

// Repeat 4x per half so one set always exceeds any screen width
$releases_loop = array_merge( $releases, $releases, $releases, $releases );
?>
<section class="scroll-band" aria-label="Recent releases">
    <div class="band-track band-track--ltr">
        <?php foreach ( $releases_loop as $release ) :
            $thumb = get_the_post_thumbnail_url( $release->ID, 'release-card' );
            if ( ! $thumb ) {
                $thumb = MD_URI . '/assets/images/logo.png';
            }
        ?>
        <a href="<?php echo esc_url( get_permalink( $release->ID ) ); ?>" class="band-item band-item--release" data-ajax-link>
            <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $release->post_title ); ?>" loading="lazy" width="160" height="160">
            <span class="band-item__label"><?php echo esc_html( $release->post_title ); ?></span>
        </a>
        <?php endforeach; ?>
        <!-- Clone for seamless loop -->
        <?php foreach ( $releases_loop as $release ) :
            $thumb = get_the_post_thumbnail_url( $release->ID, 'release-card' );
            if ( ! $thumb ) {
                $thumb = MD_URI . '/assets/images/logo.png';
            }
        ?>
        <a href="<?php echo esc_url( get_permalink( $release->ID ) ); ?>" class="band-item band-item--release" aria-hidden="true" tabindex="-1" data-ajax-link>
            <img src="<?php echo esc_url( $thumb ); ?>" alt="" loading="lazy" width="160" height="160">
            <span class="band-item__label"><?php echo esc_html( $release->post_title ); ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</section>
