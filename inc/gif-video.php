<?php
/**
 * GIF animés → vidéo muette en boucle
 *
 * Audit mobile du 9 septembre 2026 : les pages About et Contact chargeaient
 * chacune un GIF de 87 Mo et 113 Mo (1600 px, affiché en 335 px). Le même
 * mouvement tient en quelques Mo en vidéo. Plutôt que de toucher au contenu
 * des pages, on remplace au rendu tout <img src="…gif"> dont une version
 * .mp4 existe à côté dans la médiathèque (même nom, même dossier).
 *
 * Pour activer la conversion d'un GIF : déposer fichier.mp4 (et, en option,
 * fichier.webm et fichier-poster.jpg) à côté de fichier.gif dans uploads/.
 * Sans .mp4, le GIF est servi tel quel.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function md_gif_to_video( $content ) {
    if ( is_admin() || false === stripos( $content, '.gif' ) ) {
        return $content;
    }

    $uploads = wp_get_upload_dir();

    return preg_replace_callback(
        '/<img\b([^>]*?)\bsrc="([^"]+\.gif)"([^>]*)>/i',
        function ( $m ) use ( $uploads ) {
            $src = $m[2];

            // Uniquement les fichiers de la médiathèque de CE site.
            if ( 0 !== strpos( $src, $uploads['baseurl'] ) ) {
                return $m[0];
            }
            $rel  = substr( $src, strlen( $uploads['baseurl'] ) );
            $base = preg_replace( '/\.gif$/i', '', $rel );

            if ( ! file_exists( $uploads['basedir'] . $base . '.mp4' ) ) {
                return $m[0];
            }

            $attrs = $m[1] . ' ' . $m[3];
            $class = preg_match( '/\bclass="([^"]*)"/', $attrs, $c ) ? $c[1] : '';
            $alt   = preg_match( '/\balt="([^"]*)"/', $attrs, $a ) ? $a[1] : '';

            $html  = '<video class="md-gif-video ' . esc_attr( $class ) . '" autoplay muted loop playsinline preload="metadata"';
            if ( file_exists( $uploads['basedir'] . $base . '-poster.jpg' ) ) {
                $html .= ' poster="' . esc_url( $uploads['baseurl'] . $base . '-poster.jpg' ) . '"';
            }
            if ( '' !== $alt ) {
                $html .= ' aria-label="' . esc_attr( $alt ) . '"';
            }
            $html .= '>';
            if ( file_exists( $uploads['basedir'] . $base . '.webm' ) ) {
                $html .= '<source src="' . esc_url( $uploads['baseurl'] . $base . '.webm' ) . '" type="video/webm">';
            }
            $html .= '<source src="' . esc_url( $uploads['baseurl'] . $base . '.mp4' ) . '" type="video/mp4">';
            // Navigateur sans vidéo : le GIF d'origine.
            $html .= '<img src="' . esc_url( $src ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy">';
            $html .= '</video>';

            return $html;
        },
        $content
    );
}
add_filter( 'the_content', 'md_gif_to_video', 20 );
