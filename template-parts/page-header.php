<?php
/**
 * Page Header — Unified widget for all page titles
 *
 * Usage:
 * - get_template_part( 'template-parts/page-header' ); (uses the_title())
 * - set_query_var( 'page_title', 'Custom Title' ); get_template_part( 'template-parts/page-header' );
 */

$custom_title = get_query_var( 'page_title', false );
?>

<section class="page-header section">
    <div class="container">
        <h1>
            <?php
            if ( $custom_title ) {
                echo esc_html( $custom_title );
            } else {
                the_title();
            }
            ?>
        </h1>
    </div>
</section>

