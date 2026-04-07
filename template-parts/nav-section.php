<?php
/**
 * Template Part: Nav Section — Navigation blocks below bands on homepage
 */

$nav_items = [
    [
        'url'   => home_url( '/a-propos/' ),
        'title' => 'À propos',
        'desc'  => 'Le label, notre histoire',
    ],
    [
        'url'   => get_post_type_archive_link( 'artiste' ),
        'title' => 'Artistes',
        'desc'  => 'Notre roster',
    ],
    [
        'url'   => get_post_type_archive_link( 'release' ),
        'title' => 'Discographie',
        'desc'  => 'Albums, EPs, singles',
    ],
    [
        'url'   => home_url( '/mixies/' ),
        'title' => 'Mixies',
        'desc'  => 'Mixes, sessions live',
    ],
    [
        'url'   => home_url( '/studio/' ),
        'title' => 'Studio',
        'desc'  => 'Enregistrement, mixage, mastering',
    ],
    [
        'url'   => home_url( '/contact/' ),
        'title' => 'Contact',
        'desc'  => 'Démos, collaborations',
    ],
];
?>
<section class="nav-section" aria-label="Sections du site">
    <div class="nav-section__grid">
        <?php foreach ( $nav_items as $item ) : ?>
        <a href="<?php echo esc_url( $item['url'] ); ?>" class="nav-section__item" data-ajax-link>
            <span class="nav-section__title"><?php echo esc_html( $item['title'] ); ?></span>
            <span class="nav-section__desc"><?php echo esc_html( $item['desc'] ); ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</section>
