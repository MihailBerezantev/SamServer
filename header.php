<?php
/**
 * Header — Mango Dragon International
 */
?><!DOCTYPE html>
<html <?php language_attributes(); ?> class="">
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<!-- Banner Logo — always visible at top -->
<header class="site-header" id="site-header">
    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="header-logo-link" aria-label="<?php esc_attr_e( 'Home', 'mango-dragon' ); ?>">
        <img
            src="<?php echo esc_url( MD_URI . '/assets/images/logo-banner.png' ); ?>"
            alt="Mango Dragon International"
            class="header-logo"
        >
    </a>
    <p class="header-subtitle">Audiovisual Label — Geneva</p>
</header>

<!-- Navigation — centered below banner -->
<nav class="main-nav" id="main-nav" role="navigation" aria-label="<?php esc_attr_e( 'Main navigation', 'mango-dragon' ); ?>">
    <div class="nav-container">
        <?php
        wp_nav_menu( [
            'theme_location' => 'primary',
            'container'      => false,
            'menu_class'     => 'nav-links',
            'fallback_cb'    => 'md_fallback_menu',
            'depth'          => 1,
        ] );
        ?>

        <button class="theme-toggle" id="theme-toggle" aria-label="<?php esc_attr_e( 'Toggle light/dark theme', 'mango-dragon' ); ?>">
            <span class="theme-toggle-icon" aria-hidden="true"></span>
        </button>

        <button class="mobile-menu-toggle" id="mobile-menu-toggle" aria-label="Menu" aria-expanded="false" aria-controls="mobile-menu-overlay">
            <span class="hamburger" aria-hidden="true"></span>
        </button>
    </div>
</nav>

<!-- Mobile Menu Overlay -->
<div class="mobile-menu-overlay" id="mobile-menu-overlay" aria-hidden="true" role="dialog" aria-label="Mobile menu">
    <?php
    wp_nav_menu( [
        'theme_location' => 'primary',
        'container'      => false,
        'menu_class'     => 'mobile-nav-links',
        'fallback_cb'    => 'md_fallback_menu',
        'depth'          => 1,
    ] );
    ?>
    <button class="theme-toggle mobile-theme-toggle" aria-label="<?php esc_attr_e( 'Toggle light/dark theme', 'mango-dragon' ); ?>">
        <span class="theme-toggle-icon" aria-hidden="true"></span>
    </button>
</div>

<main id="content" class="site-content" role="main">
