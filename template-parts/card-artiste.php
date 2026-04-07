<?php
/**
 * Template Part: Card — Artiste (reusable card for grids)
 *
 * Expects: $artiste (WP_Post object) to be set via extract() or set_query_var()
 */

if ( ! isset( $artiste ) ) {
    global $post;
    $artiste = $post;
}

$thumb  = get_the_post_thumbnail_url( $artiste->ID, 'artist-card' );
$genres = get_the_terms( $artiste->ID, 'genre' );
$types  = get_the_terms( $artiste->ID, 'artist_type' );
$genre_slugs = $genres ? implode( ',', wp_list_pluck( $genres, 'slug' ) ) : '';
$type_slugs  = $types ? implode( ',', wp_list_pluck( $types, 'slug' ) ) : '';
?>
<article class="card-artiste"
    data-genres="<?php echo esc_attr( $genre_slugs ); ?>"
    data-type="<?php echo esc_attr( $type_slugs ); ?>">
    <a href="<?php echo esc_url( get_permalink( $artiste->ID ) ); ?>" data-ajax-link>
        <?php if ( $thumb ) : ?>
            <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $artiste->post_title ); ?>" class="card-artiste__image" loading="lazy" width="400" height="500">
        <?php else : ?>
            <div class="card-artiste__image" style="background-color:var(--color-border);aspect-ratio:4/5;"></div>
        <?php endif; ?>
        <div class="card-artiste__overlay">
            <span class="card-artiste__name"><?php echo esc_html( $artiste->post_title ); ?></span>
            <span class="card-artiste__cta">Voir le profil</span>
        </div>
    </a>
</article>
