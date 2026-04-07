<?php
/**
 * Template: Page — Studio (mastering, recording, mixing services)
 * Slug: studio
 */
get_header();
?>

<section class="page-header section">
    <div class="container">
        <h1>Studio</h1>
        <?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
        <?php if ( get_the_content() ) : ?>
        <div class="page-intro"><?php the_content(); ?></div>
        <?php endif; endwhile; endif; wp_reset_postdata(); ?>
    </div>
</section>

<section class="studio-services section">
    <div class="container">
        <div class="services-grid">
            <div class="service-card">
                <h3>Enregistrement</h3>
                <p>
                    Sessions d'enregistrement dans notre studio genevois. Équipement professionnel,
                    acoustique traitée et accompagnement artistique pour capturer le meilleur
                    de votre performance.
                </p>
                <ul class="service-features">
                    <li>Prise de son acoustique et électronique</li>
                    <li>Direction artistique</li>
                    <li>Accompagnement à la production</li>
                </ul>
            </div>

            <div class="service-card">
                <h3>Mixage</h3>
                <p>
                    Mixage en environnement calibré pour donner à vos morceaux la profondeur,
                    la clarté et l'impact qu'ils méritent. Collaboratif et itératif.
                </p>
                <ul class="service-features">
                    <li>Mix stéréo et immersif</li>
                    <li>Allers-retours illimités</li>
                    <li>Traitement analogique et numérique</li>
                </ul>
            </div>

            <div class="service-card">
                <h3>Mastering</h3>
                <p>
                    Finalisation professionnelle de vos morceaux pour la distribution digitale
                    et physique. Écoute critique, cohérence sonore et conformité aux standards.
                </p>
                <ul class="service-features">
                    <li>Mastering stéréo</li>
                    <li>Stem mastering</li>
                    <li>Formats streaming, vinyle, CD</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<section class="studio-contact section">
    <div class="container">
        <h2>Demander un devis</h2>
        <p>
            Décrivez votre projet et nous vous répondrons avec une proposition adaptée.
        </p>
        <?php
        $form_config = [
            'id'          => 'studio-form',
            'submit_text' => 'Envoyer la demande',
        ];
        set_query_var( 'form_config', $form_config );
        get_template_part( 'template-parts/upload', 'form' );
        ?>
    </div>
</section>

<?php get_footer(); ?>
