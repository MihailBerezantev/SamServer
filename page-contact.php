<?php
/**
 * Template: Page — Contact (multiple departments)
 * Slug: contact
 */
get_header();
get_template_part( 'template-parts/page-header' );
?>

<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
<?php if ( get_the_content() ) : ?>
<section class="page-intro section">
    <div class="container">
        <div class="page-intro-content"><?php the_content(); ?></div>
    </div>
</section>
<?php endif; endwhile; endif; wp_reset_postdata(); ?>

<section class="contact-departments section">
    <div class="container">
        <div class="departments-grid">
            <div class="department" id="dept-demo">
                <h3>Send a demo</h3>
                <p>Want to join the label? Send us your music.</p>
                <?php
                $form_config = [
                    'id'          => 'demo-form',
                    'submit_text' => 'Send demo',
                    'page_id'     => get_queried_object_id(),
                ];
                set_query_var( 'form_config', $form_config );
                get_template_part( 'template-parts/upload', 'form' );
                ?>
            </div>


            <div class="department" id="dept-general">
                <h3>General enquiries</h3>
                <p>For any other enquiries.</p>
                <p class="contact-email">
                    <a href="mailto:contact@mango-dragon.com">contact@mango-dragon.com</a>
                </p>
            </div>
        </div>
    </div>
</section>

<?php get_footer(); ?>
