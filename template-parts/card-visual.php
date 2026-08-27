<?php
/**
 * Template Part: Card — Visual (reusable card for the Visuals gallery)
 *
 * Même gabarit visuel que card-release.php : image carrée (format
 * « release-card »), overlay au survol titre + artistes.
 *
 * Expects: $visual (WP_Post object) to be set via set_query_var()
 */

if ( ! isset( $visual ) ) {
    global $post;
    $visual = $post;
}

$thumb      = get_the_post_thumbnail_url( $visual->ID, 'release-card' );
$artist_ids = function_exists( 'get_field' ) ? get_field( 'mdvis_artist_ids', $visual->ID ) : [];
$artist_ids = is_array( $artist_ids ) ? $artist_ids : [];
$artist_names = array_filter( array_map( 'get_the_title', $artist_ids ) );
$artist_display = ! empty( $artist_names ) ? implode( ', ', $artist_names ) : '';
?>
<article class="card-visual">
    <a href="<?php echo esc_url( get_permalink( $visual->ID ) ); ?>" data-ajax-link>
        <?php if ( $thumb ) : ?>
            <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $visual->post_title ); ?>" class="card-visual__image" loading="lazy" width="400" height="400">
        <?php else : ?>
            <div class="card-visual__image" style="background-color:var(--color-border);aspect-ratio:1/1;"></div>
        <?php endif; ?>
        <div class="card-visual__overlay">
            <span class="card-visual__title"><?php echo esc_html( $visual->post_title ); ?></span>
            <?php if ( $artist_display ) : ?>
                <span class="card-visual__artist"><?php echo esc_html( $artist_display ); ?></span>
            <?php endif; ?>
        </div>
    </a>
</article>
