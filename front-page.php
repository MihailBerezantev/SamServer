<?php
/**
 * Template: Front Page — Homepage with hero, bands, and nav section
 */
get_header();
?>

<?php
if ( have_posts() ) {
    while ( have_posts() ) {
        the_post();
        the_content();
    }
}
?>

<?php get_footer(); ?>
