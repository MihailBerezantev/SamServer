<?php
/**
 * Template Part: Filter Bar (reusable for artistes + discographie pages)
 *
 * Expects: $filter_config (array) with keys:
 *   'taxonomies' => [ 'genre', 'release_type', ... ]
 *   'show_artists' => true|false (for discographie page)
 */

if ( ! isset( $filter_config ) ) {
    return;
}

$tax_labels = [
    'genre'        => 'Genre',
    'release_type' => 'Type',
    'artist_type'  => 'Catégorie',
];
?>
<div class="filter-bar" id="filter-bar" role="group" aria-label="Filtres">
    <?php foreach ( $filter_config['taxonomies'] as $tax_name ) :
        $terms = get_terms( [
            'taxonomy'   => $tax_name,
            'hide_empty' => true,
        ] );
        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            continue;
        }
        $label = $tax_labels[ $tax_name ] ?? $tax_name;
    ?>
    <div class="filter-group" data-filter-taxonomy="<?php echo esc_attr( $tax_name ); ?>">
        <span class="filter-group__label"><?php echo esc_html( $label ); ?></span>
        <?php foreach ( $terms as $term ) : ?>
        <button class="filter-pill"
            data-filter="<?php echo esc_attr( $tax_name ); ?>"
            data-value="<?php echo esc_attr( $term->slug ); ?>"
            aria-pressed="false">
            <?php echo esc_html( $term->name ); ?>
        </button>
        <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <?php if ( ! empty( $filter_config['show_artists'] ) ) :
        $artistes = get_posts( [
            'post_type'      => 'artiste',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );
        if ( ! empty( $artistes ) ) :
    ?>
    <div class="filter-group" data-filter-taxonomy="artists">
        <span class="filter-group__label">Artiste</span>
        <?php foreach ( $artistes as $artiste ) : ?>
        <button class="filter-pill"
            data-filter="artists"
            data-value="<?php echo esc_attr( $artiste->post_name ); ?>"
            aria-pressed="false">
            <?php echo esc_html( $artiste->post_title ); ?>
        </button>
        <?php endforeach; ?>
    </div>
    <?php endif; endif; ?>

    <button class="filter-reset" id="filter-reset" style="display:none;">Réinitialiser</button>
</div>
