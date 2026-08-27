<?php
/**
 * Template: Single — Visual detail page (« projet »)
 * Layout: Photo de couverture (LEFT) + Artistes/Description (RIGHT), puis
 * une collection de photos choisies dans la médiathèque (bloc Galerie natif),
 * ouvrables en grand avec navigation par flèches (même style que le Studio).
 */
get_header();

$visual_id   = get_the_ID();
$description = function_exists( 'get_field' ) ? get_field( 'mdvis_item_description', $visual_id ) : '';
$artist_ids  = function_exists( 'get_field' ) ? get_field( 'mdvis_artist_ids', $visual_id ) : [];
$artist_ids  = is_array( $artist_ids ) ? $artist_ids : [];
$release_ids = function_exists( 'get_field' ) ? get_field( 'mdvis_release_ids', $visual_id ) : [];
$release_ids = is_array( $release_ids ) ? $release_ids : [];
$visual_types = get_the_terms( $visual_id, 'visual_type' );

// Artist names and links (même pattern que single-release.php)
$artists_html = [];
foreach ( $artist_ids as $aid ) {
    $artists_html[] = '<a href="' . esc_url( get_permalink( $aid ) ) . '" data-ajax-link>' . esc_html( get_the_title( $aid ) ) . '</a>';
}

// Release names and links associées à ce projet visuel
$releases_html = [];
foreach ( $release_ids as $rid ) {
    $releases_html[] = '<a href="' . esc_url( get_permalink( $rid ) ) . '" data-ajax-link>' . esc_html( get_the_title( $rid ) ) . '</a>';
}

// Collection de photos du projet : meta-box « Photos du projet » (médiathèque),
// avec repli automatique sur un bloc Galerie placé dans le contenu.
$gallery_ids = function_exists( 'md_visual_gallery_ids' )
    ? md_visual_gallery_ids( $visual_id )
    : ( function_exists( 'md_get_post_gallery_image_ids' ) ? md_get_post_gallery_image_ids( $visual_id ) : [] );
$gallery_ids = is_array( $gallery_ids ) ? array_values( $gallery_ids ) : [];

// L'image de mise en avant (couverture) est INCLUSE dans la galerie, en 1re position
// (dé-doublonnée si elle figure déjà dans la collection).
$thumb_id = get_post_thumbnail_id( $visual_id );
if ( $thumb_id ) {
    $gallery_ids = array_values( array_filter( $gallery_ids, function ( $gid ) use ( $thumb_id ) {
        return (int) $gid !== (int) $thumb_id;
    } ) );
    array_unshift( $gallery_ids, (int) $thumb_id );
}
?>

<!-- HERO BANNER -->
<section class="single-hero single-hero--centered">
    <div class="container">
        <h1><?php the_title(); ?></h1>
    </div>
</section>

<!-- TEXTE DESCRIPTIF : entre le titre et la collection -->
<?php if ( $description ) : ?>
<section class="visual-intro section">
    <div class="container container--narrow">
        <div class="visual-intro__text"><?php echo wp_kses_post( wpautop( $description ) ); ?></div>
    </div>
    <?php if ( ! empty( $gallery_ids ) ) : ?>
    <?php // Trait dans un conteneur PLEINE LARGEUR (mêmes bords que la grille d'images). ?>
    <div class="container">
        <hr class="visual-intro__rule">
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>

<!-- COLLECTION (EN HAUT) : image de couverture incluse en 1re position, photos cliquables en grand -->
<?php if ( ! empty( $gallery_ids ) ) : ?>
<section class="visual-collection section">
    <div class="container">
        <div class="visual-collection__grid" data-lightbox-gallery>
            <?php foreach ( $gallery_ids as $md_i => $md_img_id ) :
                // Dimensionnement piloté par la HAUTEUR (axe vertical) : on calcule le ratio
                // largeur/hauteur de chaque image → la vignette prend sa largeur naturelle à
                // hauteur de rangée constante (galerie « justifiée », voir CSS --v-row-h / --ar).
                $md_src = wp_get_attachment_image_src( $md_img_id, 'large' );
                if ( ! $md_src ) {
                    continue;
                }
                $md_thumb_url = $md_src[0];
                $md_w  = ! empty( $md_src[1] ) ? (int) $md_src[1] : 4;
                $md_h  = ! empty( $md_src[2] ) ? (int) $md_src[2] : 3;
                $md_ar = $md_h > 0 ? round( $md_w / $md_h, 4 ) : 1.3333;
                // Justification jusqu'aux bords : les vignettes CARRÉES ont un poids 0 → elles
                // ne s'étirent JAMAIS (restent carrées) ; les NON-CARRÉES ont un gros poids
                // (>= 1) → elles absorbent tout l'espace restant de la rangée pour venir
                // accrocher les bords gauche/droit (flexbox ne répartit tout l'espace que si
                // la somme des flex-grow d'une ligne est >= 1).
                $md_is_square = abs( $md_ar - 1.0 ) < 0.06;
                $md_grow = $md_is_square ? 0 : round( $md_ar * 100, 2 );
                $md_full_url = wp_get_attachment_image_url( $md_img_id, 'full' );
            ?>
            <button type="button" class="visual-collection__item" style="--ar:<?php echo esc_attr( $md_ar ); ?>;--grow:<?php echo esc_attr( $md_grow ); ?>" data-full="<?php echo esc_url( $md_full_url ); ?>" data-index="<?php echo (int) $md_i; ?>">
                <img src="<?php echo esc_url( $md_thumb_url ); ?>" alt="<?php echo esc_attr( get_the_title( $visual_id ) ); ?>" loading="lazy">
            </button>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- LIGHTBOX : photo en grand + navigation flèches (même style que le carrousel Studio) -->
<div class="md-lightbox" id="visual-lightbox" aria-hidden="true">
    <button type="button" class="md-lightbox__close" aria-label="Fermer">&times;</button>
    <button type="button" class="slider-arrow md-lightbox__arrow md-lightbox__arrow--prev" aria-label="Photo précédente">&lsaquo;</button>
    <div class="md-lightbox__stage">
        <img src="" alt="" class="md-lightbox__image">
    </div>
    <button type="button" class="slider-arrow md-lightbox__arrow md-lightbox__arrow--next" aria-label="Photo suivante">&rsaquo;</button>
</div>
<?php endif; ?>

<!-- DÉTAILS (EN BAS) : Artistes / Releases / Type
     (la couverture est dans la galerie, la description est passée sous le titre) -->
<section class="single-content-layout section">
    <div class="container">
        <div class="content-text">
            <?php if ( ! empty( $artists_html ) ) : ?>
            <div class="content-section">
                <h2>Artistes</h2>
                <p><?php echo implode( ', ', $artists_html ); ?></p>
            </div>
            <?php endif; ?>

            <?php if ( ! empty( $releases_html ) ) : ?>
            <div class="content-section">
                <h2>Releases</h2>
                <p><?php echo implode( ', ', $releases_html ); ?></p>
            </div>
            <?php endif; ?>

            <?php if ( $visual_types && ! is_wp_error( $visual_types ) ) : ?>
            <div class="content-section">
                <h2>Type</h2>
                <div class="content-tags">
                    <?php foreach ( $visual_types as $vt ) : ?>
                        <span class="tag"><?php echo esc_html( $vt->name ); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php
            // Tout autre champ ACF ajouté sur ce visuel s'affiche automatiquement.
            if ( function_exists( 'md_render_extra_acf_fields' ) ) {
                md_render_extra_acf_fields( $visual_id, [ 'mdvis_item_description', 'mdvis_artist_ids', 'mdvis_release_ids' ] );
            }
            ?>
        </div>
    </div>
</section>

<?php get_footer(); ?>
