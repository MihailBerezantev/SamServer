<?php
/**
 * Template Name: Visuals
 * Template: Page — Visuals
 *
 * Description éditable (champ ACF « Description » sur la page) + galerie des
 * photos gérées via le CPT « visual » (une entrée = une image à la une).
 * Tout est administrable depuis le back-office (CPT UI + ACF), comme les
 * autres sections.
 */
get_header();
set_query_var( 'page_title', 'Visuals' );
get_template_part( 'template-parts/page-header' );

// Description (champ ACF wysiwyg attaché à la page Visuals)
$md_vis_desc = function_exists( 'get_field' ) ? get_field( 'mdvis_description' ) : '';

// Photos : CPT « visual » (image à la une). Ordre via le champ « Ordre » (menu_order).
$md_visuals = get_posts( [
	'post_type'      => 'visual',
	'post_status'    => 'publish',
	'posts_per_page' => -1,
	'orderby'        => [ 'menu_order' => 'ASC', 'date' => 'DESC' ],
] );
?>

<?php if ( $md_vis_desc ) : ?>
<section class="page-intro section">
	<div class="container">
		<div class="page-intro-content"><?php echo wp_kses_post( $md_vis_desc ); ?></div>
	</div>
</section>
<?php endif; ?>

<section class="section visuals-section">
	<div class="container">
		<?php
		// Ligne « Artist » restreinte aux artistes qui ont réellement des
		// visuels : lister tout le catalogue produirait des pastilles ne
		// filtrant rien. Ordre demandé : Artist puis Type.
		$md_vis_artist_ids = [];
		foreach ( $md_visuals as $md_v ) {
			$md_ids = function_exists( 'get_field' ) ? get_field( 'mdvis_artist_ids', $md_v->ID ) : [];
			if ( is_array( $md_ids ) ) {
				$md_vis_artist_ids = array_merge( $md_vis_artist_ids, $md_ids );
			}
		}
		$md_vis_artist_ids = array_values( array_unique( array_map( 'intval', $md_vis_artist_ids ) ) );

		$filter_config = [
			'rows' => [
				[ 'type' => 'artists',                               'label' => 'Artist', 'ids' => $md_vis_artist_ids ],
				[ 'type' => 'taxonomy', 'taxonomy' => 'visual_type', 'label' => 'Type' ],
			],
		];
		set_query_var( 'filter_config', $filter_config );
		get_template_part( 'template-parts/filter', 'bar' );
		?>

		<?php if ( ! empty( $md_visuals ) ) : ?>
		<div class="cards-grid" id="cards-grid">
			<?php foreach ( $md_visuals as $md_v ) :
				set_query_var( 'visual', $md_v );
				get_template_part( 'template-parts/card', 'visual' );
			endforeach; ?>
		</div>
		<?php else : ?>
		<p class="no-results">Aucun visuel pour le moment.</p>
		<?php endif; ?>
	</div>
</section>

<?php get_footer(); ?>
