<?php
/**
 * Template: Single — Artiste detail page
 * Layout: Photo (LEFT) + Bio/Info (RIGHT)
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
// Portrait affiché en grand sur la fiche → taille 'large' (1024) pour rester net (retina).
$thumb      = get_the_post_thumbnail_url( $artiste_id, 'large' );
if ( ! $thumb ) {
    $thumb  = get_the_post_thumbnail_url( $artiste_id, 'artist-card' );
}

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

// Concerts / dates : les « gigs » sont un CPT géré via CPT UI + ACF.
// On récupère ceux rattachés à cet artiste (champ relation ACF gig_artists),
// puis on répartit automatiquement passés / à venir selon la date (champ gig_date).
$today         = current_time( 'Y-m-d' );
$upcoming_gigs = [];
$past_gigs     = [];
$gig_posts = get_posts( [
    'post_type'      => 'gig',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'meta_query'     => [
        [
            'key'     => 'gig_artists',
            'value'   => '"' . $artiste_id . '"', // relation ACF stockée en tableau sérialisé d'IDs
            'compare' => 'LIKE',
        ],
    ],
] );
foreach ( $gig_posts as $gp ) {
    $date = function_exists( 'get_field' ) ? get_field( 'gig_date', $gp->ID ) : get_post_meta( $gp->ID, 'gig_date', true );
    if ( empty( $date ) ) {
        continue;
    }
    $g = [
        'date'  => $date,
        'venue' => get_the_title( $gp->ID ),
        'city'  => function_exists( 'get_field' ) ? get_field( 'gig_city', $gp->ID ) : get_post_meta( $gp->ID, 'gig_city', true ),
        'url'   => function_exists( 'get_field' ) ? get_field( 'gig_url', $gp->ID ) : get_post_meta( $gp->ID, 'gig_url', true ),
    ];
    if ( $date >= $today ) {
        $upcoming_gigs[] = $g;
    } else {
        $past_gigs[] = $g;
    }
}
usort( $upcoming_gigs, function ( $a, $b ) { return strcmp( $a['date'], $b['date'] ); } ); // à venir : plus proche d'abord
usort( $past_gigs, function ( $a, $b ) { return strcmp( $b['date'], $a['date'] ); } );     // passés : plus récent d'abord
?>

<!-- HERO BANNER -->
<section class="single-hero">
    <div class="container">
        <h1><?php the_title(); ?></h1>

</section>

<!-- MAIN CONTENT: PHOTO (LEFT) + BIO/INFO (RIGHT) -->
<section class="single-content-layout section">
    <div class="container">
        <div class="content-grid">
            <!-- LEFT: PHOTO -->
            <div class="content-photo">
                <?php if ( $thumb ) : ?>
                    <img src="<?php echo esc_url( $thumb ); ?>"
                         alt="<?php echo esc_attr( get_the_title( $artiste_id ) ); ?>"
                         class="content-image"
                         loading="lazy">
                <?php else : ?>
                    <div class="content-image content-image--placeholder"></div>
                <?php endif; ?>
            </div>

            <!-- RIGHT: BIO + LINKS + INFO -->
            <div class="content-text">
                <!-- Biography -->
                <?php if ( $biography ) : ?>
                <div class="content-section">
                    <h2>Biography</h2>
                    <?php echo wp_kses_post( wpautop( $biography ) ); ?>
                </div>
                <?php endif; ?>

                <!-- Social Links -->
                <?php
                $has_social = false;
                foreach ( $social as $v ) {
                    if ( $v ) { $has_social = true; break; }
                }
                if ( $has_social ) :
                ?>
                <div class="content-section">
                    <h4>Links</h4>
                    <ul class="content-links">
                        <?php foreach ( $social as $platform => $url ) :
                            if ( ! $url ) continue;
                            $labels = [
                                'instagram'  => 'Instagram',
                                'soundcloud' => 'SoundCloud',
                                'bandcamp'   => 'Bandcamp',
                                'spotify'    => 'Spotify',
                                'website'    => 'Website',
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

                <!-- Artist Type -->
                <?php if ( $types && ! is_wp_error( $types ) ) : ?>
                <div class="content-section">
                    <h3>Category</h3>
                    <div class="content-tags">
                        <?php foreach ( $types as $t ) : ?>
                            <span class="tag"><?php echo esc_html( $t->name ); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                    <?php if ( $genres && ! is_wp_error( $genres ) ) : ?>

      
        <div class="single-hero__tags">
            <h4>Genres:</h4>
             

            <?php foreach ( $genres as $g ) : ?>
                <span class="tag"><?php echo esc_html( $g->name ); ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
            </div>
        </div>
    </div>
</section>

<!-- VIDEOS : mix et sets VJ (YouTube) -->
<?php
$video_ids = md_youtube_ids( get_post_meta( $artiste_id, '_md_videos', true ) );
if ( $video_ids ) :
?>
<section class="single-videos section">
    <div class="container">
        <h2>Videos</h2>
        <div class="video-grid">
            <?php foreach ( $video_ids as $md_vid ) : ?>
            <div class="video-embed">
                <iframe
                    src="<?php echo esc_url( md_youtube_embed_url( $md_vid ) ); ?>"
                    title="<?php echo esc_attr( sprintf( 'Vidéo de %s', get_the_title( $artiste_id ) ) ); ?>"
                    loading="lazy"
                    allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    referrerpolicy="strict-origin-when-cross-origin"
                    allowfullscreen></iframe>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- GIGS : Upcoming gigs + Past events (tri automatique par date) -->
<?php if ( $upcoming_gigs || $past_gigs ) : ?>
<section class="single-gigs section">
    <div class="container">
        <?php if ( $upcoming_gigs ) : ?>
        <div class="gigs-block">
            <h2>Upcoming gigs</h2>
            <ul class="gig-list">
                <?php foreach ( $upcoming_gigs as $g ) : ?>
                <li class="gig gig--upcoming">
                    <span class="gig__date"><?php echo esc_html( date_i18n( 'j M Y', strtotime( $g['date'] ) ) ); ?></span>
                    <span class="gig__info">
                        <?php if ( ! empty( $g['venue'] ) ) : ?><span class="gig__venue"><?php echo esc_html( $g['venue'] ); ?></span><?php endif; ?>
                        <?php if ( ! empty( $g['city'] ) ) : ?><span class="gig__city"><?php echo esc_html( $g['city'] ); ?></span><?php endif; ?>
                    </span>
                    <?php if ( ! empty( $g['url'] ) ) : ?>
                    <a class="gig__link" href="<?php echo esc_url( $g['url'] ); ?>" target="_blank" rel="noopener">Tickets</a>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if ( $past_gigs ) : ?>
        <div class="gigs-block gigs-block--past">
            <h2>Past events</h2>
            <ul class="gig-list gig-list--past">
                <?php foreach ( $past_gigs as $g ) : ?>
                <li class="gig gig--past">
                    <span class="gig__date"><?php echo esc_html( date_i18n( 'j M Y', strtotime( $g['date'] ) ) ); ?></span>
                    <span class="gig__info">
                        <?php if ( ! empty( $g['venue'] ) ) : ?><span class="gig__venue"><?php echo esc_html( $g['venue'] ); ?></span><?php endif; ?>
                        <?php if ( ! empty( $g['city'] ) ) : ?><span class="gig__city"><?php echo esc_html( $g['city'] ); ?></span><?php endif; ?>
                    </span>
                    <?php if ( ! empty( $g['url'] ) ) : ?>
                    <a class="gig__link" href="<?php echo esc_url( $g['url'] ); ?>" target="_blank" rel="noopener">Details</a>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- CHAMPS ACF SUPPLÉMENTAIRES (ajoutés via l'interface ACF, affichés automatiquement) -->
<?php if ( function_exists( 'md_render_extra_acf_fields' ) ) : ?>
<section class="single-extra section">
    <div class="container">
        <?php md_render_extra_acf_fields( $artiste_id ); ?>
    </div>
</section>
<?php endif; ?>

<!-- RELEASES -->
<?php if ( ! empty( $artist_releases ) ) : ?>
<section class="single-releases section">
    <div class="container">
        <h2>Releases</h2>
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
