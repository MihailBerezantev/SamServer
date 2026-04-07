<?php
/**
 * Template: Single — Release detail page
 */
get_header();

$release_id      = get_the_ID();
$release_date    = get_post_meta( $release_id, '_md_release_date', true );
$catalogue       = get_post_meta( $release_id, '_md_catalogue_number', true );
$bandcamp_url    = get_post_meta( $release_id, '_md_bandcamp_url', true );
$description     = get_post_meta( $release_id, '_md_description', true );
$credits         = get_post_meta( $release_id, '_md_credits', true );
$tracklist_json  = get_post_meta( $release_id, '_md_tracklist', true );
$tracklist       = $tracklist_json ? json_decode( $tracklist_json, true ) : [];
$artist_ids      = get_post_meta( $release_id, '_md_artist_ids', true );
$artist_ids      = is_array( $artist_ids ) ? $artist_ids : [];
$genres          = get_the_terms( $release_id, 'genre' );
$types           = get_the_terms( $release_id, 'release_type' );
$thumb           = get_the_post_thumbnail_url( $release_id, 'release-card' );

// Artist names and links
$artists_html = [];
foreach ( $artist_ids as $aid ) {
    $name = get_the_title( $aid );
    $url  = get_permalink( $aid );
    $artists_html[] = '<a href="' . esc_url( $url ) . '" data-ajax-link>' . esc_html( $name ) . '</a>';
}
?>

<section class="single-release-header section">
    <div class="container">
        <div class="release-header-grid">
            <div class="release-artwork">
                <?php if ( $thumb ) : ?>
                    <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php the_title_attribute(); ?>" width="500" height="500">
                <?php else : ?>
                    <div class="release-artwork__placeholder"></div>
                <?php endif; ?>
            </div>

            <div class="release-info">
                <h1><?php the_title(); ?></h1>

                <?php if ( ! empty( $artists_html ) ) : ?>
                <p class="release-artists subtitle">
                    <?php echo implode( ', ', $artists_html ); ?>
                </p>
                <?php endif; ?>

                <div class="release-meta">
                    <?php if ( $release_date ) : ?>
                    <div class="release-meta__item">
                        <span class="label">Date</span>
                        <span><?php echo esc_html( date_i18n( 'j F Y', strtotime( $release_date ) ) ); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ( $types && ! is_wp_error( $types ) ) : ?>
                    <div class="release-meta__item">
                        <span class="label">Type</span>
                        <span>
                            <?php echo esc_html( implode( ', ', wp_list_pluck( $types, 'name' ) ) ); ?>
                        </span>
                    </div>
                    <?php endif; ?>

                    <?php if ( $catalogue ) : ?>
                    <div class="release-meta__item">
                        <span class="label">Catalogue</span>
                        <span><?php echo esc_html( $catalogue ); ?></span>
                    </div>
                    <?php endif; ?>

                    <?php if ( $genres && ! is_wp_error( $genres ) ) : ?>
                    <div class="release-meta__item">
                        <span class="label">Genre</span>
                        <span>
                            <?php foreach ( $genres as $g ) : ?>
                                <span class="tag"><?php echo esc_html( $g->name ); ?></span>
                            <?php endforeach; ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ( $bandcamp_url ) : ?>
                <a href="<?php echo esc_url( $bandcamp_url ); ?>" class="btn btn--filled" target="_blank" rel="noopener">
                    Écouter sur Bandcamp
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php if ( ! empty( $tracklist ) ) : ?>
<section class="single-release-tracklist section">
    <div class="container">
        <h2>Tracklist</h2>
        <ol class="tracklist" id="tracklist"
            data-release-title="<?php echo esc_attr( get_the_title() ); ?>"
            data-release-artwork="<?php echo esc_attr( $thumb ); ?>"
            data-release-artists="<?php echo esc_attr( implode( ', ', wp_list_pluck( $artist_ids, 'ID' ) ?: array_map( 'get_the_title', $artist_ids ) ) ); ?>">
            <?php foreach ( $tracklist as $i => $track ) :
                $has_audio = ! empty( $track['audioUrl'] );
            ?>
            <li class="tracklist__item<?php echo $has_audio ? ' tracklist__item--playable' : ''; ?>"
                <?php if ( $has_audio ) : ?>
                data-audio-url="<?php echo esc_url( $track['audioUrl'] ); ?>"
                data-track-title="<?php echo esc_attr( $track['title'] ); ?>"
                data-track-duration="<?php echo esc_attr( $track['duration'] ?? '' ); ?>"
                <?php endif; ?>>
                <span class="tracklist__number"><?php echo esc_html( $i + 1 ); ?></span>
                <span class="tracklist__title"><?php echo esc_html( $track['title'] ); ?></span>
                <span class="tracklist__duration"><?php echo esc_html( $track['duration'] ?? '' ); ?></span>
                <?php if ( $has_audio ) : ?>
                <button class="tracklist__play-btn" aria-label="Écouter <?php echo esc_attr( $track['title'] ); ?>">
                    <svg viewBox="0 0 24 24" width="16" height="16"><path d="M8 5v14l11-7z"/></svg>
                </button>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ol>

        <?php if ( array_filter( $tracklist, fn( $t ) => ! empty( $t['audioUrl'] ) ) ) : ?>
        <button class="btn btn--filled" id="play-all-tracks">
            Écouter tout
        </button>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<?php if ( $description ) : ?>
<section class="single-release-description section">
    <div class="container">
        <h2>À propos</h2>
        <?php echo wp_kses_post( wpautop( $description ) ); ?>
    </div>
</section>
<?php endif; ?>

<?php if ( $credits ) : ?>
<section class="single-release-credits section">
    <div class="container">
        <h2>Crédits</h2>
        <?php echo wp_kses_post( wpautop( $credits ) ); ?>
    </div>
</section>
<?php endif; ?>

<?php get_footer(); ?>
