<?php
/**
 * Template Part: Nav Section — Navigation blocks below bands on homepage
 */

$nav_items = [
    [
        'url'   => home_url( '/a-propos/' ),
        'title' => 'About',
        'desc'  => 'The label, our story',
    ],
    [
        'url'   => get_post_type_archive_link( 'artiste' ),
        'title' => 'Artists',
        'desc'  => 'Our roster',
    ],
    [
        'url'   => get_post_type_archive_link( 'release' ),
        'title' => 'Releases',
        'desc'  => 'Albums, EPs, singles',
    ],
    [
        'url'   => home_url( '/mixies/' ),
        'title' => 'Mixies',
        'desc'  => 'Mixes, live sessions',
    ],
    [
        'url'   => home_url( '/studio/' ),
        'title' => 'Studio',
        'desc'  => 'Recording, mixing, mastering',
    ],
    [
        'url'   => home_url( '/contact/' ),
        'title' => 'Contact',
        'desc'  => 'Demos, collaborations',
    ],
];
?>
<section class="nav-section" aria-label="Site sections">
    <div class="nav-section__grid">
        <?php foreach ( $nav_items as $item ) : ?>
        <a href="<?php echo esc_url( $item['url'] ); ?>" class="nav-section__item" data-ajax-link>
            <span class="nav-section__title"><?php echo esc_html( $item['title'] ); ?></span>
            <span class="nav-section__desc"><?php echo esc_html( $item['desc'] ); ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</section>
