<?php
/**
 * Footer — Mango Dragon International
 */
?>
</main><!-- #content -->

<?php get_template_part( 'template-parts/player-bar' ); ?>

<footer class="site-footer" role="contentinfo">
    <div class="footer-container">
        <div class="footer-bottom">
            <?php $md_socials = md_footer_social_links(); ?>
            <?php if ( $md_socials ) : ?>
            <div class="social-links">
                <?php foreach ( $md_socials as $md_s ) : ?>
                <a href="<?php echo esc_url( $md_s['url'] ); ?>" class="social-link" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $md_s['label'] ); ?>">
                    <?php echo $md_s['icon']; // SVG statique défini dans functions.php ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <a class="footer-email" href="mailto:contact@mango-dragon.com">contact@mango-dragon.com</a>
            <p class="footer-copyright">© Mango Dragon International <?php echo esc_html( date( 'Y' ) ); ?></p>
        </div>
    </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
