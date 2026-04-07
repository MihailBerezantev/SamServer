<?php
/**
 * 404 Page — Mango Dragon International
 */

get_header();
?>

<div class="page-404">
    <h1>404</h1>
    <p>Page introuvable</p>
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn">Retour à l'accueil</a>
</div>

<?php
get_footer();
