<?php
/**
 * AJAX Handlers — Navigation AJAX for persistent audio player
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AJAX endpoint: load page content (the <main> inner HTML) for SPA-like navigation.
 * Accepts: 'url' parameter (the page URL to load)
 * Returns: JSON { html, title, bodyClass }
 */
function md_ajax_load_page() {
    check_ajax_referer( 'md_ajax_nonce', 'nonce' );

    $url = isset( $_GET['url'] ) ? esc_url_raw( $_GET['url'] ) : '';

    if ( empty( $url ) ) {
        wp_send_json_error( [ 'message' => 'URL manquante' ] );
    }

    // Convert URL to a relative path for WP to process
    $site_url = home_url( '/' );
    $path = str_replace( $site_url, '/', $url );
    $path = '/' . ltrim( $path, '/' );

    // Use WP internal URL-to-query resolution
    $post_id = url_to_postid( $url );

    // Start output buffering — simulate a page load
    ob_start();

    // Override the main query to match the requested URL
    global $wp, $wp_query;

    // Save current query
    $original_query = clone $wp_query;

    // Parse the URL and run the query
    $wp->parse_request( ltrim( $path, '/' ) );
    $wp->query_posts();
    $wp->register_globals();

    // Determine which template to load
    $template = '';

    if ( is_front_page() ) {
        $template = MD_DIR . '/front-page.php';
    } elseif ( is_post_type_archive( 'artiste' ) ) {
        $template = MD_DIR . '/archive-artiste.php';
    } elseif ( is_post_type_archive( 'release' ) ) {
        $template = MD_DIR . '/archive-release.php';
    } elseif ( is_singular( 'artiste' ) ) {
        $template = MD_DIR . '/single-artiste.php';
    } elseif ( is_singular( 'release' ) ) {
        $template = MD_DIR . '/single-release.php';
    } elseif ( is_page() ) {
        $slug = get_post_field( 'post_name', get_queried_object_id() );
        $page_template = MD_DIR . "/page-{$slug}.php";
        if ( file_exists( $page_template ) ) {
            $template = $page_template;
        } else {
            $template = MD_DIR . '/page.php';
            if ( ! file_exists( $template ) ) {
                $template = MD_DIR . '/index.php';
            }
        }
    } elseif ( is_404() ) {
        $template = MD_DIR . '/404.php';
    } else {
        $template = MD_DIR . '/index.php';
    }

    // We only want the content inside <main>, not header/footer
    // Templates call get_header() and get_footer() which we need to suppress
    // Instead, define a flag that templates can check
    define( 'MD_AJAX_REQUEST', true );

    if ( file_exists( $template ) ) {
        include $template;
    }

    $html = ob_get_clean();

    // Extract content between <main> and </main> if present
    // (for AJAX, our templates output content directly when MD_AJAX_REQUEST is defined)
    $title = wp_get_document_title();
    $body_class = implode( ' ', get_body_class() );

    // Restore original query
    $wp_query = $original_query;
    wp_reset_postdata();

    wp_send_json_success( [
        'html'      => $html,
        'title'     => $title,
        'bodyClass' => $body_class,
    ] );
}
add_action( 'wp_ajax_md_load_page', 'md_ajax_load_page' );
add_action( 'wp_ajax_nopriv_md_load_page', 'md_ajax_load_page' );
