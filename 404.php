<?php
/**
 * 404 Page — Mango Dragon International
 */

get_header();
?>

<div class="page-404">
    <h1>404</h1>
    <p>Page not found</p>
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn">Back home</a>
</div>

<?php
get_footer();
