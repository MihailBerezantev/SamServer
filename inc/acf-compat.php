<?php
/**
 * ACF Compatibility — Bridge between ACF fields and existing _md_* meta keys.
 *
 * After each ACF save, mirrors values to _md_* keys so front-end templates
 * need no changes. Provides helper functions for templates and future code.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ==========================================================================
// Template helpers
// ==========================================================================

/**
 * Read a field, trying ACF get_field() first, then get_post_meta( _md_* ).
 *
 * @param string $field_name  ACF field name (no leading underscore), e.g. 'md_biography'
 * @param int    $post_id
 * @param mixed  $default
 * @return mixed
 */
function md_get_field( $field_name, $post_id = null, $default = '' ) {
    $post_id = $post_id ? $post_id : get_the_ID();

    if ( function_exists( 'get_field' ) ) {
        $value = get_field( $field_name, $post_id );
        if ( $value !== null && $value !== false && $value !== '' ) {
            return $value;
        }
    }

    $meta_key = '_' . ltrim( $field_name, '_' );
    $value    = get_post_meta( $post_id, $meta_key, true );
    return ( $value !== '' && $value !== false ) ? $value : $default;
}

/**
 * Get a social platform URL for an artiste.
 * Resolution order: ACF field → individual _md_social_* key → legacy _md_social_links array.
 *
 * @param string $platform  instagram|soundcloud|bandcamp|spotify|website
 * @param int    $post_id
 * @return string  URL or empty string
 */
function md_get_social( $platform, $post_id = null ) {
    $platform = sanitize_key( $platform );
    $post_id  = $post_id ? $post_id : get_the_ID();

    // 1. ACF field (registered as mdacf_social_<platform>)
    if ( function_exists( 'get_field' ) ) {
        $value = get_field( 'mdacf_social_' . $platform, $post_id );
        if ( ! empty( $value ) ) {
            return $value;
        }
    }

    // 2. Individual meta key (stored by bridge or fixed meta-boxes.php)
    $value = get_post_meta( $post_id, '_md_social_' . $platform, true );
    if ( ! empty( $value ) ) {
        return $value;
    }

    // 3. Legacy _md_social_links array
    $social = get_post_meta( $post_id, '_md_social_links', true );
    if ( is_array( $social ) && ! empty( $social[ $platform ] ) ) {
        return $social[ $platform ];
    }

    return '';
}

/**
 * Get release artist IDs, checking ACF relationship first then _md_artist_ids meta.
 *
 * @param int $post_id
 * @return int[]
 */
function md_get_release_artist_ids( $post_id = null ) {
    $post_id = $post_id ? $post_id : get_the_ID();

    if ( function_exists( 'get_field' ) ) {
        $value = get_field( 'mdacf_artist_ids', $post_id );
        if ( ! empty( $value ) && is_array( $value ) ) {
            // ACF relationship with return_format=id returns array of IDs
            return array_map( 'intval', $value );
        }
    }

    $ids = get_post_meta( $post_id, '_md_artist_ids', true );
    return is_array( $ids ) ? array_map( 'intval', $ids ) : [];
}

// ==========================================================================
// ACF → _md_* mirror bridge
// ==========================================================================

/**
 * After ACF saves a post, copy the ACF field values to the corresponding
 * _md_* post-meta keys that all front-end templates read from.
 */
add_action( 'acf/save_post', 'md_acf_mirror_to_meta', 20 );

function md_acf_mirror_to_meta( $post_id ) {
    if ( ! function_exists( 'get_field' ) ) {
        return;
    }

    // Bail on non-post saves (options pages, etc.)
    if ( ! is_numeric( $post_id ) ) {
        return;
    }

    $post_type = get_post_type( $post_id );

    // -----------------------------------------------------------------------
    // Artiste fields
    // -----------------------------------------------------------------------
    if ( 'artiste' === $post_type ) {

        // Biography
        $bio = get_field( 'mdacf_biography', $post_id );
        if ( $bio !== null && $bio !== false ) {
            update_post_meta( $post_id, '_md_biography', $bio );
        }

        // Social links — save each platform individually
        $platforms = [ 'instagram', 'soundcloud', 'bandcamp', 'spotify', 'website' ];
        $social    = [];
        foreach ( $platforms as $platform ) {
            $url = get_field( 'mdacf_social_' . $platform, $post_id );
            if ( $url === null || $url === false ) {
                $url = '';
            }
            update_post_meta( $post_id, '_md_social_' . $platform, $url );
            $social[ $platform ] = $url;
        }
        // Also keep the legacy array for backward compat
        update_post_meta( $post_id, '_md_social_links', $social );

        // Vidéos YouTube : textarea, une URL par ligne.
        $videos = get_field( 'mdacf_videos', $post_id );
        if ( $videos === null || $videos === false ) {
            $videos = '';
        }
        update_post_meta( $post_id, '_md_videos', $videos );
    }

    // -----------------------------------------------------------------------
    // Release fields
    // -----------------------------------------------------------------------
    if ( 'release' === $post_type ) {

        // Simple text / date / url fields
        $map = [
            'mdacf_release_date'     => '_md_release_date',
            'mdacf_catalogue_number' => '_md_catalogue_number',
            'mdacf_bandcamp_url'     => '_md_bandcamp_url',
            'mdacf_description'      => '_md_description',
            'mdacf_credits'          => '_md_credits',
        ];
        foreach ( $map as $acf_name => $meta_key ) {
            $value = get_field( $acf_name, $post_id );
            if ( $value !== null && $value !== false ) {
                update_post_meta( $post_id, $meta_key, $value );
            }
        }

        // Artist IDs — ACF relationship returns array of post IDs (return_format = id)
        $artist_ids = get_field( 'mdacf_artist_ids', $post_id );
        if ( is_array( $artist_ids ) ) {
            $artist_ids = array_map( 'intval', $artist_ids );
        } elseif ( empty( $artist_ids ) ) {
            $artist_ids = [];
        }
        update_post_meta( $post_id, '_md_artist_ids', $artist_ids );
    }
}

// ==========================================================================
// ACF load_value fallbacks — pre-populate ACF fields from existing _md_* meta
// ==========================================================================

/**
 * When ACF opens a post for editing, if its own field value is empty (post was
 * never saved via ACF), fall back to the existing _md_* meta keys so existing
 * content appears pre-filled without any manual migration.
 */
add_action( 'acf/init', 'md_acf_register_load_value_fallbacks' );

/**
 * Should the legacy _md_* fallback run for this field?
 *
 * Only when ACF has never written its own meta row for it. As soon as that row
 * exists — even holding an empty string — the value the user typed is
 * authoritative, and an empty field means "deliberately cleared".
 *
 * Without this check the fallback treats "empty" as "not migrated yet" and
 * restores the old _md_* value. That made clearing a field impossible: the
 * mirror in md_acf_mirror_to_meta() calls get_field(), get_field() applies
 * these very filters, and the fallback handed the old value straight back to
 * the mirror, which wrote it again. Replacing a value worked (non-empty values
 * return before reaching the fallback); only clearing was broken.
 */
function md_acf_should_fallback( $post_id, $acf_name ) {
    // Non-post contexts (options pages, users, terms) are never migrated.
    if ( ! is_numeric( $post_id ) ) {
        return false;
    }
    return ! metadata_exists( 'post', (int) $post_id, $acf_name );
}

function md_acf_register_load_value_fallbacks() {

    // -----------------------------------------------------------------------
    // Artiste fields
    // -----------------------------------------------------------------------
    $artiste_map = [
        'mdacf_biography'         => '_md_biography',
        'mdacf_social_instagram'  => '_md_social_instagram',
        'mdacf_social_soundcloud' => '_md_social_soundcloud',
        'mdacf_social_bandcamp'   => '_md_social_bandcamp',
        'mdacf_social_spotify'    => '_md_social_spotify',
        'mdacf_social_website'    => '_md_social_website',
        'mdacf_videos'            => '_md_videos',
    ];

    foreach ( $artiste_map as $acf_name => $meta_key ) {
        add_filter(
            'acf/load_value/name=' . $acf_name,
            function( $value, $post_id, $field ) use ( $acf_name, $meta_key ) {
                if ( $value !== null && $value !== false && $value !== '' ) {
                    return $value;
                }
                if ( ! md_acf_should_fallback( $post_id, $acf_name ) ) {
                    return $value;
                }
                $fallback = get_post_meta( $post_id, $meta_key, true );
                if ( ! empty( $fallback ) ) {
                    return $fallback;
                }
                // Also check legacy _md_social_links array for social fields
                if ( strpos( $meta_key, '_md_social_' ) === 0 ) {
                    $platform = str_replace( '_md_social_', '', $meta_key );
                    $social   = get_post_meta( $post_id, '_md_social_links', true );
                    if ( is_array( $social ) && ! empty( $social[ $platform ] ) ) {
                        return $social[ $platform ];
                    }
                }
                return $value;
            },
            10,
            3
        );
    }

    // -----------------------------------------------------------------------
    // Release fields (text / date / url / textarea)
    // -----------------------------------------------------------------------
    $release_map = [
        'mdacf_release_date'     => '_md_release_date',
        'mdacf_catalogue_number' => '_md_catalogue_number',
        'mdacf_bandcamp_url'     => '_md_bandcamp_url',
        'mdacf_description'      => '_md_description',
        'mdacf_credits'          => '_md_credits',
    ];

    foreach ( $release_map as $acf_name => $meta_key ) {
        add_filter(
            'acf/load_value/name=' . $acf_name,
            function( $value, $post_id, $field ) use ( $acf_name, $meta_key ) {
                if ( $value !== null && $value !== false && $value !== '' ) {
                    return $value;
                }
                if ( ! md_acf_should_fallback( $post_id, $acf_name ) ) {
                    return $value;
                }
                return get_post_meta( $post_id, $meta_key, true );
            },
            10,
            3
        );
    }

    // -----------------------------------------------------------------------
    // Release: artistes associés (relationship → array of IDs)
    // -----------------------------------------------------------------------
    add_filter(
        'acf/load_value/name=mdacf_artist_ids',
        function( $value, $post_id, $field ) {
            if ( ! empty( $value ) ) {
                return $value;
            }
            if ( ! md_acf_should_fallback( $post_id, 'mdacf_artist_ids' ) ) {
                return $value;
            }
            $ids = get_post_meta( $post_id, '_md_artist_ids', true );
            return is_array( $ids ) ? $ids : [];
        },
        10,
        3
    );
}
