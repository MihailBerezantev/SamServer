<?php
/**
 * Template: Page — Contact (multiple departments)
 * Slug: contact
 */
get_header();
?>

<section class="page-header section">
    <div class="container">
        <h1>Contact</h1>
        <?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
        <?php if ( get_the_content() ) : ?>
        <div class="page-intro"><?php the_content(); ?></div>
        <?php endif; endwhile; endif; wp_reset_postdata(); ?>
    </div>
</section>

<section class="contact-departments section">
    <div class="container">
        <div class="departments-grid">
            <div class="department" id="dept-demo">
                <h3>Envoyer une démo</h3>
                <p>Vous souhaitez rejoindre le label ? Envoyez-nous votre musique.</p>
                <?php
                $form_config = [
                    'id'          => 'demo-form',
                    'submit_text' => 'Envoyer la démo',
                ];
                set_query_var( 'form_config', $form_config );
                get_template_part( 'template-parts/upload', 'form' );
                ?>
            </div>

            <div class="department" id="dept-collab">
                <h3>Collaborations</h3>
                <p>Propositions de collaborations, événements, partenariats.</p>
                <?php
                $form_config = [
                    'id'          => 'collab-form',
                    'submit_text' => 'Envoyer la proposition',
                ];
                set_query_var( 'form_config', $form_config );
                get_template_part( 'template-parts/upload', 'form' );
                ?>
            </div>

            <div class="department" id="dept-studio">
                <h3>Studio</h3>
                <p>Réservation studio, mastering, mixage.</p>
                <a href="<?php echo esc_url( home_url( '/studio/' ) ); ?>" class="btn btn--filled" data-ajax-link>
                    Voir les services studio
                </a>
            </div>

            <div class="department" id="dept-general">
                <h3>Contact général</h3>
                <p>Pour toute autre question.</p>
                <p class="contact-email">
                    <a href="mailto:contact@mangodragon.ch">contact@mangodragon.ch</a>
                </p>
            </div>
        </div>
    </div>
</section>

<?php get_footer(); ?>
