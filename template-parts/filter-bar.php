<?php
/**
 * Template Part: Filter Bar (réutilisable — releases, artistes, visuels)
 *
 * Attend $filter_config['rows'] : une liste ORDONNÉE de lignes. Chaque ligne
 * produit sa propre ligne à l'écran, dans l'ordre déclaré ici. C'est le gabarit
 * qui fixe l'ordre, pas le CSS.
 *
 *   'rows' => [
 *       [ 'type' => 'taxonomy', 'taxonomy' => 'genre', 'label' => 'Genre' ],
 *       [ 'type' => 'artists',  'label' => 'Artist', 'ids' => [12, 34] ],
 *       [ 'type' => 'sort',     'label' => 'Sort' ],
 *   ]
 *
 * Types de ligne :
 *   'taxonomy' — pastilles issues des termes de la taxonomie ; 'taxonomy' requis
 *   'artists'  — pastilles issues du CPT artiste ; 'ids' facultatif pour
 *                restreindre la liste (sinon tous les artistes publiés)
 *   'sort'     — pastilles de tri, gérées par filters.js
 *
 * L'ancienne forme ('taxonomies' / 'show_artists' / 'show_sort') reste acceptée
 * et se convertit en lignes ci-dessous.
 */

if ( ! isset( $filter_config ) ) {
    return;
}

$tax_labels = [
    'genre'        => 'Genre',
    'release_type' => 'Type',
    'artist_type'  => 'Category',
    'visual_type'  => 'Type',
];

// --- Compatibilité ascendante avec l'ancienne configuration ------------------
if ( empty( $filter_config['rows'] ) ) {
    $rows = [];
    foreach ( ( $filter_config['taxonomies'] ?? [] ) as $tax ) {
        $rows[] = [ 'type' => 'taxonomy', 'taxonomy' => $tax ];
    }
    if ( ! empty( $filter_config['show_artists'] ) ) {
        $rows[] = [ 'type' => 'artists' ];
    }
    if ( ! empty( $filter_config['show_sort'] ) ) {
        $rows[] = [ 'type' => 'sort' ];
    }
    $filter_config['rows'] = $rows;
}

// --- Pré-calcul : on n'affiche que les lignes qui ont réellement du contenu ---
$md_rendered = [];

foreach ( $filter_config['rows'] as $row ) {
    $type = $row['type'] ?? 'taxonomy';

    if ( 'taxonomy' === $type ) {
        $tax   = $row['taxonomy'] ?? '';
        $terms = $tax ? get_terms( [ 'taxonomy' => $tax, 'hide_empty' => true ] ) : [];
        if ( empty( $terms ) || is_wp_error( $terms ) ) {
            continue;
        }
        $md_rendered[] = [
            'key'   => $tax,
            'label' => $row['label'] ?? ( $tax_labels[ $tax ] ?? $tax ),
            'pills' => array_map(
                function ( $t ) { return [ 'value' => $t->slug, 'name' => $t->name ]; },
                $terms
            ),
        ];
        continue;
    }

    if ( 'artists' === $type ) {
        $args = [
            'post_type'      => 'artiste',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];
        // 'ids' vide = aucun artiste concerné : on masque la ligne plutôt que
        // d'afficher des pastilles qui ne filtreraient rien.
        if ( isset( $row['ids'] ) ) {
            if ( empty( $row['ids'] ) ) {
                continue;
            }
            $args['include'] = array_map( 'intval', $row['ids'] );
            $args['orderby'] = 'title';
        }
        $artistes = get_posts( $args );
        if ( empty( $artistes ) ) {
            continue;
        }
        $md_rendered[] = [
            'key'   => 'artists',
            'label' => $row['label'] ?? 'Artist',
            'pills' => array_map(
                function ( $a ) { return [ 'value' => $a->post_name, 'name' => $a->post_title ]; },
                $artistes
            ),
        ];
        continue;
    }

    if ( 'sort' === $type ) {
        $md_rendered[] = [
            'key'   => 'sort',
            'label' => $row['label'] ?? 'Sort',
            'sort'  => true,
        ];
    }
}

if ( empty( $md_rendered ) ) {
    return;
}
?>
<div class="filter-bar" id="filter-bar" role="group" aria-label="Filters">
    <?php foreach ( $md_rendered as $group ) : ?>
    <div class="filter-group" data-filter-taxonomy="<?php echo esc_attr( $group['key'] ); ?>">
        <span class="filter-group__label"><?php echo esc_html( $group['label'] ); ?></span>
        <div class="filter-group__pills">
            <?php if ( ! empty( $group['sort'] ) ) : ?>
                <button class="filter-pill sort-pill active" type="button" data-sort="latest" aria-pressed="true">Latest</button>
                <button class="filter-pill sort-pill" type="button" data-sort="oldest" aria-pressed="false">Oldest</button>
                <button class="filter-pill sort-pill" type="button" data-sort="random" aria-pressed="false">Random</button>
            <?php else : ?>
                <?php foreach ( $group['pills'] as $pill ) : ?>
                <button class="filter-pill"
                    type="button"
                    data-filter="<?php echo esc_attr( $group['key'] ); ?>"
                    data-value="<?php echo esc_attr( $pill['value'] ); ?>"
                    aria-pressed="false">
                    <?php echo esc_html( $pill['name'] ); ?>
                </button>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <button class="filter-reset" id="filter-reset" type="button" style="display:none;">Reset</button>
</div>
