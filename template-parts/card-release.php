<?php
/**
 * Template Part: Card — Release (reusable card for grids)
 *
 * Expects: $release (WP_Post object)
 */

if ( ! isset( $release ) ) {
    global $post;
    $release = $post;
}

$thumb       = get_the_post_thumbnail_url( $release->ID, 'release-card' );
$genres      = get_the_terms( $release->ID, 'genre' );
$types       = get_the_terms( $release->ID, 'release_type' );
$artist_ids  = get_post_meta( $release->ID, '_md_artist_ids', true );
$artist_ids  = is_array( $artist_ids ) ? $artist_ids : [];
$genre_slugs = $genres ? implode( ',', wp_list_pluck( $genres, 'slug' ) ) : '';
$type_slugs  = $types ? implode( ',', wp_list_pluck( $types, 'slug' ) ) : '';
$artist_slugs = '';
if ( ! empty( $artist_ids ) ) {
    $slugs = [];
    foreach ( $artist_ids as $aid ) {
        $slugs[] = get_post_field( 'post_name', $aid );
    }
    $artist_slugs = implode( ',', $slugs );
}

// Get artist names for display
$artist_names = [];
foreach ( $artist_ids as $aid ) {
    $artist_names[] = get_the_title( $aid );
}
$artist_display = ! empty( $artist_names ) ? implode( ', ', $artist_names ) : '';

// Date de sortie (ISO Y-m-d) pour le tri client-side. Repli sur la date de
// publication si la release n'a pas de date de sortie renseignée.
$release_date = get_post_meta( $release->ID, '_md_release_date', true );
if ( empty( $release_date ) ) {
    $release_date = get_the_date( 'Y-m-d', $release );
}
?>
<article class="card-release"
    data-genres="<?php echo esc_attr( $genre_slugs ); ?>"
    data-release-type="<?php echo esc_attr( $type_slugs ); ?>"
    data-artists="<?php echo esc_attr( $artist_slugs ); ?>"
    data-date="<?php echo esc_attr( $release_date ); ?>">
    <a href="<?php echo esc_url( get_permalink( $release->ID ) ); ?>" data-ajax-link>
        <?php if ( $thumb ) : ?>
            <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $release->post_title ); ?>" class="card-release__image" loading="lazy" width="400" height="400">
        <?php else : ?>
            <div class="card-release__image" style="background-color:var(--color-border);aspect-ratio:1/1;"></div>
        <?php endif; ?>
        <div class="card-release__overlay">
            <span class="card-release__title"><?php echo esc_html( $release->post_title ); ?></span>
            <?php if ( $artist_display ) : ?>
                <span class="card-release__artist"><?php echo esc_html( $artist_display ); ?></span>
            <?php endif; ?>
        </div>
    </a>
</article>
