<?php
/**
 * Redirections des anciennes URLs françaises vers les nouvelles.
 *
 * Les slugs des types de contenu sont passés de « artistes » à « artists » et
 * de « discographie » à « releases ». Sans redirection, toutes les adresses
 * déjà partagées — liens externes, favoris, résultats de moteurs de recherche —
 * renverraient une 404.
 *
 * La redirection est permanente (301) : c'est ce qui indique aux moteurs de
 * recherche de transférer le référencement acquis vers la nouvelle adresse.
 *
 * Couvre l'archive comme les fiches individuelles, puisque seul le premier
 * segment du chemin change :
 *   /discographie/          → /releases/
 *   /discographie/less-ep/  → /releases/less-ep/
 *   /artistes/              → /artists/
 *   /artistes/al-yen/       → /artists/al-yen/
 */

/**
 * Correspondance ancien segment → nouveau segment.
 *
 * @return array<string,string>
 */
function md_legacy_slug_map() {
    return [
        'artistes'     => 'artists',
        'discographie' => 'releases',
    ];
}

/**
 * Redirige en 301 si le premier segment de l'URL est un ancien slug.
 *
 * Priorité 1 sur template_redirect : l'ancienne URL ne correspond plus à
 * aucune règle de réécriture, WordPress s'apprête donc à servir une 404. On
 * intervient avant que le gabarit ne soit choisi.
 */
function md_redirect_legacy_slugs() {
    if ( is_admin() || wp_doing_ajax() ) {
        return;
    }

    $request = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
    $path    = trim( (string) wp_parse_url( $request, PHP_URL_PATH ), '/' );

    if ( '' === $path ) {
        return;
    }

    $segments = explode( '/', $path );
    $map      = md_legacy_slug_map();

    if ( ! isset( $map[ $segments[0] ] ) ) {
        return;
    }

    $segments[0] = $map[ $segments[0] ];
    $target      = home_url( '/' . implode( '/', $segments ) . '/' );

    $query = wp_parse_url( $request, PHP_URL_QUERY );
    if ( $query ) {
        $target .= '?' . $query;
    }

    wp_safe_redirect( $target, 301 );
    exit;
}
add_action( 'template_redirect', 'md_redirect_legacy_slugs', 1 );
