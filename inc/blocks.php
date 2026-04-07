<?php
/**
 * Mango Dragon — Custom Gutenberg Blocks
 * Registers the 3 scroll band blocks for use in the block editor.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ==========================================================================
// Block category: "Mango Dragon"
// ==========================================================================
add_filter( 'block_categories_all', function ( $categories ) {
    array_unshift( $categories, [
        'slug'  => 'mango-dragon',
        'title' => 'Mango Dragon',
        'icon'  => null,
    ] );
    return $categories;
} );

// ==========================================================================
// Register the 3 band blocks (server-side rendered)
// ==========================================================================
add_action( 'init', function () {
    $make_render = function ( $tpl ) {
        return function () use ( $tpl ) {
            ob_start();
            get_template_part( 'template-parts/band', $tpl );
            return ob_get_clean();
        };
    };

    register_block_type(
        MD_DIR . '/blocks/band-artistes',
        [ 'render_callback' => $make_render( 'artistes' ) ]
    );
    register_block_type(
        MD_DIR . '/blocks/band-releases',
        [ 'render_callback' => $make_render( 'releases' ) ]
    );
    register_block_type(
        MD_DIR . '/blocks/band-photos',
        [ 'render_callback' => $make_render( 'photos' ) ]
    );
} );

// ==========================================================================
// Enqueue editor JS (provides edit-mode previews)
// ==========================================================================
add_action( 'enqueue_block_editor_assets', function () {
    wp_enqueue_script(
        'md-blocks-editor',
        MD_URI . '/assets/js/blocks-editor.js',
        [ 'wp-blocks', 'wp-element' ],
        MD_VERSION,
        true
    );
} );
