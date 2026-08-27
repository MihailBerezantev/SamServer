<?php
/**
 * Template: Single — Release detail page
 * Layout: Photo (LEFT) + Info (RIGHT)
 */
get_header();

$release_id      = get_the_ID();
$release_date    = get_post_meta( $release_id, '_md_release_date', true );
$catalogue       = get_post_meta( $release_id, '_md_catalogue_number', true );
$bandcamp_url    = get_post_meta( $release_id, '_md_bandcamp_url', true );
$description     = get_post_meta( $release_id, '_md_description', true );
$credits         = get_post_meta( $release_id, '_md_credits', true );
$tracklist       = get_post_meta( $release_id, '_md_tracklist', true );
$tracklist       = is_array( $tracklist ) ? $tracklist : [];
$artist_ids      = get_post_meta( $release_id, '_md_artist_ids', true );
$artist_ids      = is_array( $artist_ids ) ? $artist_ids : [];
$genres          = get_the_terms( $release_id, 'genre' );
$types           = get_the_terms( $release_id, 'release_type' );
// Pochette affichée en grand sur la fiche album → taille 'large' (1024) pour rester nette (retina).
$thumb           = get_the_post_thumbnail_url( $release_id, 'large' );
if ( ! $thumb ) {
    $thumb       = get_the_post_thumbnail_url( $release_id, 'release-card' );
}

// Artist names and links
$artists_html = [];
foreach ( $artist_ids as $aid ) {
    $name = get_the_title( $aid );
    $url  = get_permalink( $aid );
    $artists_html[] = '<a href="' . esc_url( $url ) . '" data-ajax-link>' . esc_html( $name ) . '</a>';
}
?>

<!-- HERO BANNER -->
<section class="single-hero">
    <div class="container">
        <h1><?php the_title(); ?></h1>

</section>


<!-- MAIN CONTENT: PHOTO (LEFT) + INFO (RIGHT) -->
<section class="single-content-layout section">
    <div class="container">
        <div class="content-grid">
            <!-- LEFT: PHOTO -->
            <div class="content-photo">
                <?php if ( $thumb ) : ?>
                    <img src="<?php echo esc_url( $thumb ); ?>"
                         alt="<?php echo esc_attr( get_the_title( $release_id ) ); ?>"
                         class="content-image content-image--natural"
                         loading="lazy">
                <?php else : ?>
                    <div class="content-image content-image--placeholder"></div>
                <?php endif; ?>
            </div>

            <!-- RIGHT: METADATA + BUTTONS -->
            <div class="content-text">
                <!-- Release Metadata -->
                <?php if ( $release_date || $types || $catalogue ) : ?>
                <div class="content-section">
                    <div class="content-meta">
                        <?php if ( $release_date ) : ?>
                        <div class="meta-item">
                            <span class="meta-label">Date</span>
                            <span><?php echo esc_html( date_i18n( 'j F Y', strtotime( $release_date ) ) ); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if ( $types && ! is_wp_error( $types ) ) : ?>
                        <div class="meta-item">
                            <span class="meta-label">Type</span>
                            <span><?php echo esc_html( implode( ', ', wp_list_pluck( $types, 'name' ) ) ); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if ( $catalogue ) : ?>
                        <div class="meta-item">
                            <span class="meta-label">Catalogue</span>
                            <span><?php echo esc_html( $catalogue ); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Bandcamp Button -->
                <?php if ( $bandcamp_url ) : ?>
                <div class="content-section">
                    <a href="<?php echo esc_url( $bandcamp_url ); ?>" class="btn btn--filled" target="_blank" rel="noopener">
                        Buy on Bandcamp
                    </a>
                </div>
                <?php endif; ?>
                <?php if ( $description ) : ?>

        <h4>About</h4>
        <?php echo wp_kses_post( wpautop( $description ) ); ?>

<?php endif; ?>

<!-- CREDITS -->
<?php if ( $credits ) : ?>

        <h4>Credits</h4>
        <?php echo wp_kses_post( wpautop( $credits ) ); ?>

<?php endif; ?>

                <?php if ( $genres && ! is_wp_error( $genres ) ) : ?>
                   
                <div class="single-hero__tags">
           
                
                        <h4>Genres:</h4>
                   

                        <div class="single-hero__tags-label_12">
                                <?php foreach ( $genres as $g ) : ?>
                        <span class="tag"><?php echo esc_html( $g->name ); ?></span>
                    <?php endforeach; ?>
                        </div>
                    
                
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
    
</section>


<!-- INLINE PLAYER + TRACKLIST -->
<?php if ( ! empty( $tracklist ) ) : ?>
<section class="release-player section">
    <div class="container">
        <div class="release-player__layout<?php echo $thumb ? '' : ' release-player__layout--no-cover'; ?>" id="tracklist"
            data-release-title="<?php echo esc_attr( get_the_title() ); ?>"
            data-release-artwork="<?php echo esc_attr( $thumb ); ?>"
            data-release-artists="<?php echo esc_attr( implode( ', ', array_map( 'get_the_title', $artist_ids ) ) ); ?>">

            <!-- LEFT: Artwork + main play 
             
            
            
            
                 <?php if ( $thumb ) : ?>
            <div class="release-player__cover">
                <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>" class="release-player__artwork">
                <button class="release-player__play-all" id="play-all-tracks" aria-label="Play all">
                    <svg viewBox="0 0 24 24" width="32" height="32"><path d="M8 5v14l11-7z" fill="currentColor"/></svg>
                    <span>Play all</span>
                </button>
            </div>
            <?php endif; ?>
            
            
            -->
       

            <!-- RIGHT: Tracklist -->
            <div class="release-player__tracks">
                <div class="release-player__header">
                    <span class="release-player__album-title"><?php the_title(); ?></span>
                    <?php if ( ! empty( $artists_html ) ) : ?>
                        <span class="release-player__album-artist"><?php echo implode( ', ', $artists_html ); ?></span>
                    <?php endif; ?>
                </div>
                <ol class="release-tracklist">
                    <?php foreach ( $tracklist as $i => $track ) :
                        $has_audio = ! empty( $track['audioUrl'] );
                    ?>
                    <li class="release-tracklist__item<?php echo $has_audio ? ' release-tracklist__item--playable' : ''; ?>"
                        <?php if ( $has_audio ) : ?>
                        data-audio-url="<?php echo esc_url( $track['audioUrl'] ); ?>"
                        data-track-title="<?php echo esc_attr( $track['title'] ); ?>"
                        data-track-duration="<?php echo esc_attr( $track['duration'] ?? '' ); ?>"
                        <?php endif; ?>>
                        <span class="release-tracklist__play-icon">
                            <?php if ( $has_audio ) : ?>
                                <svg class="icon-play" viewBox="0 0 24 24"><path d="M8 5v14l11-7z" fill="currentColor"/></svg>
                                <svg class="icon-pause" viewBox="0 0 24 24" style="display:none;"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z" fill="currentColor"/></svg>
                                <span class="icon-eq" aria-hidden="true"><span></span><span></span><span></span></span>
                            <?php else : ?>
                                <span class="release-tracklist__num"><?php echo esc_html( $i + 1 ); ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="release-tracklist__title"><?php echo esc_html( $track['title'] ); ?></span>
                        <?php if ( $has_audio ) : ?>
                        <span class="release-tracklist__current-time"></span>
                        <?php endif; ?>
                        <span class="release-tracklist__duration"><?php echo esc_html( $track['duration'] ?? '' ); ?></span>
                        <?php if ( $has_audio ) : ?>
                        <button class="release-tracklist__queue-btn" aria-label="<?php esc_attr_e( 'Add to queue', 'mango-dragon' ); ?>" tabindex="-1">
                            <svg viewBox="0 0 24 24" width="14" height="14"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z" fill="currentColor"/></svg>
                        </button>
                        <div class="release-tracklist__progress">
                            <div class="release-tracklist__progress-fill"></div>
                        </div>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ol>
                <?php if ( ! $thumb ) : ?>
                <button class="release-player__play-all release-player__play-all--bottom" id="play-all-tracks" aria-label="Play all">
                    <svg viewBox="0 0 24 24" width="20" height="20"><path d="M8 5v14l11-7z" fill="currentColor"/></svg>
                    <span>Play all</span>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- CHAMPS ACF SUPPLÉMENTAIRES (ajoutés via l'interface ACF, affichés automatiquement) -->
<?php if ( function_exists( 'md_render_extra_acf_fields' ) ) : ?>
<section class="single-extra section">
    <div class="container">
        <?php md_render_extra_acf_fields( $release_id ); ?>
    </div>
</section>
<?php endif; ?>

<?php get_footer(); ?>
