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

// Attributs lus par filters.js pour le filtrage côté client (mêmes conventions
// que card-release.php : des slugs séparés par des virgules).
$types      = get_the_terms( $visual->ID, 'visual_type' );
$type_slugs = is_array( $types ) ? implode( ',', wp_list_pluck( $types, 'slug' ) ) : '';

$artist_slugs = '';
if ( ! empty( $artist_ids ) ) {
    $slugs = [];
    foreach ( $artist_ids as $aid ) {
        $slugs[] = get_post_field( 'post_name', $aid );
    }
    $artist_slugs = implode( ',', array_filter( $slugs ) );
}
?>
<article class="card-visual"
    data-visual-type="<?php echo esc_attr( $type_slugs ); ?>"
    data-artists="<?php echo esc_attr( $artist_slugs ); ?>">
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
