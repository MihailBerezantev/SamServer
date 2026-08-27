<?php
/**
 * Template générique — Archive.
 *
 * Gabarit par défaut pour les listes de contenu sans template dédié,
 * notamment les types créés via CPT UI. Reprend le fil d'Ariane visuel du
 * thème (page-header) et une grille de cartes générique.
 */
get_header();

$pt_obj   = get_queried_object();
$pt_label = ( $pt_obj && isset( $pt_obj->labels ) ) ? $pt_obj->labels->name : post_type_archive_title( '', false );
set_query_var( 'page_title', $pt_label );
get_template_part( 'template-parts/page-header' );
?>

<section class="section">
    <div class="container">
        <?php if ( have_posts() ) : ?>
        <div class="cards-grid">
            <?php while ( have_posts() ) : the_post();
                $thumb = get_the_post_thumbnail_url( get_the_ID(), 'release-card' );
            ?>
            <article class="card-generic">
                <a href="<?php the_permalink(); ?>" class="card-generic__link">
                    <?php if ( $thumb ) : ?>
                        <div class="card-generic__media">
                            <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" loading="lazy">
                        </div>
                    <?php else : ?>
                        <div class="card-generic__media card-generic__media--placeholder"></div>
                    <?php endif; ?>
                    <h3 class="card-generic__title"><?php the_title(); ?></h3>
                    <?php if ( has_excerpt() ) : ?>
                        <p class="card-generic__excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>
                    <?php endif; ?>
                </a>
            </article>
            <?php endwhile; ?>
        </div>

        <div class="archive-pagination">
            <?php the_posts_pagination( [ 'mid_size' => 2 ] ); ?>
        </div>

        <?php else : ?>
        <p class="no-results">No content yet.</p>
        <?php endif; ?>
    </div>
</section>

<?php get_footer(); ?>
