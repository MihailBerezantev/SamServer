<?php
/**
 * Template: Single — Artiste detail page
 */
get_header();

$artiste_id = get_the_ID();
$biography  = get_post_meta( $artiste_id, '_md_biography', true );
$social     = [
    'instagram'  => get_post_meta( $artiste_id, '_md_social_instagram', true ),
    'soundcloud' => get_post_meta( $artiste_id, '_md_social_soundcloud', true ),
    'bandcamp'   => get_post_meta( $artiste_id, '_md_social_bandcamp', true ),
    'spotify'    => get_post_meta( $artiste_id, '_md_social_spotify', true ),
    'website'    => get_post_meta( $artiste_id, '_md_social_website', true ),
];
$genres     = get_the_terms( $artiste_id, 'genre' );
$types      = get_the_terms( $artiste_id, 'artist_type' );
$thumb      = get_the_post_thumbnail_url( $artiste_id, 'hero-large' );

// Get releases for this artist
$all_releases = get_posts( [
    'post_type'      => 'release',
    'posts_per_page' => -1,
    'orderby'        => 'date',
    'order'          => 'DESC',
] );
$artist_releases = [];
foreach ( $all_releases as $rel ) {
    $ids = get_post_meta( $rel->ID, '_md_artist_ids', true );
    $ids = is_array( $ids ) ? $ids : [];
    if ( in_array( $artiste_id, $ids ) ) {
        $artist_releases[] = $rel;
    }
}
?>

<section class="single-artiste-hero" <?php if ( $thumb ) : ?>style="background-image:url('<?php echo esc_url( $thumb ); ?>')"<?php endif; ?>>
    <div class="single-artiste-hero__overlay">
        <div class="container">
            <h1><?php the_title(); ?></h1>
            <?php if ( $genres && ! is_wp_error( $genres ) ) : ?>
            <div class="single-artiste-hero__genres">
                <?php foreach ( $genres as $g ) : ?>
                    <span class="tag"><?php echo esc_html( $g->name ); ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="single-artiste-content section">
    <div class="container">
        <div class="two-col">
            <div class="single-artiste-bio">
                <?php if ( $biography ) : ?>
                    <h2>Biographie</h2>
                    <?php echo wp_kses_post( wpautop( $biography ) ); ?>
                <?php endif; ?>

                <?php if ( has_excerpt() ) : ?>
                    <p class="subtitle"><?php echo esc_html( get_the_excerpt() ); ?></p>
                <?php endif; ?>
            </div>

            <aside class="single-artiste-sidebar">
                <?php
                // Social links
                $has_social = false;
                foreach ( $social as $v ) {
                    if ( $v ) { $has_social = true; break; }
                }
                if ( $has_social ) :
                ?>
                <div class="artiste-social">
                    <h3>Liens</h3>
                    <ul class="social-links-list">
                        <?php foreach ( $social as $platform => $url ) :
                            if ( ! $url ) continue;
                            $labels = [
                                'instagram'  => 'Instagram',
                                'soundcloud' => 'SoundCloud',
                                'bandcamp'   => 'Bandcamp',
                                'spotify'    => 'Spotify',
                                'website'    => 'Site web',
                            ];
                        ?>
                        <li>
                            <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">
                                <?php echo esc_html( $labels[ $platform ] ); ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if ( $types && ! is_wp_error( $types ) ) : ?>
                <div class="artiste-types">
                    <h3>Catégorie</h3>
                    <?php foreach ( $types as $t ) : ?>
                        <span class="tag"><?php echo esc_html( $t->name ); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </aside>
        </div>
    </div>
</section>

<?php if ( ! empty( $artist_releases ) ) : ?>
<section class="single-artiste-releases section">
    <div class="container">
        <h2>Discographie</h2>
        <div class="cards-grid">
            <?php foreach ( $artist_releases as $release ) :
                set_query_var( 'release', $release );
                get_template_part( 'template-parts/card', 'release' );
            endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php get_footer(); ?>
