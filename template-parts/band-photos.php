<?php
/**
 * Template Part: Band — Photos studio (scrolling horizontal band)
 * Utilise les images à la une du CPT « service » (Services du studio).
 * Repli sur les photos des artistes si aucun service n'a d'image.
 */

$service_posts = get_posts( [
    'post_type'      => 'service',
    'posts_per_page' => -1,
    'orderby'        => 'rand',
] );

$photos = [];
foreach ( $service_posts as $sp ) {
    $url = get_the_post_thumbnail_url( $sp->ID, 'band-photo' );
    if ( $url ) {
        $photos[] = [ 'url' => $url, 'alt' => $sp->post_title ];
    }
}

// Repli : images à la une des artistes si aucun service n'a d'image.
if ( empty( $photos ) ) {
    $artistes = get_posts( [
        'post_type'      => 'artiste',
        'posts_per_page' => -1,
        'orderby'        => 'rand',
    ] );
    foreach ( $artistes as $a ) {
        $url = get_the_post_thumbnail_url( $a->ID, 'band-photo' );
        if ( $url ) {
            $photos[] = [ 'url' => $url, 'alt' => $a->post_title ];
        }
    }
}

if ( empty( $photos ) ) {
    return;
}

$studio_url  = home_url( '/studio/' );
$photos_loop = array_merge( $photos, $photos, $photos, $photos );
?>
<section class="scroll-band" aria-label="Studio photos">
    <button class="band-arrow band-arrow--left" aria-label="Scroll left">&#9664;</button>
    <div class="band-track band-track--rtl">
        <?php foreach ( $photos_loop as $photo ) : ?>
        <a href="<?php echo esc_url( $studio_url ); ?>" class="band-item band-item--photo" data-ajax-link>
            <img src="<?php echo esc_url( $photo['url'] ); ?>" alt="<?php echo esc_attr( $photo['alt'] ); ?>" loading="lazy" width="220" height="165">
        </a>
        <?php endforeach; ?>
        <!-- Clone for seamless loop -->
        <?php foreach ( $photos_loop as $photo ) : ?>
        <a href="<?php echo esc_url( $studio_url ); ?>" class="band-item band-item--photo" aria-hidden="true" tabindex="-1" data-ajax-link>
            <img src="<?php echo esc_url( $photo['url'] ); ?>" alt="" loading="lazy" width="220" height="165">
        </a>
        <?php endforeach; ?>
    </div>
    <button class="band-arrow band-arrow--right" aria-label="Scroll right">&#9654;</button>
</section>
