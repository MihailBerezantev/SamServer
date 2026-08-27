<?php
/**
 * Rendu générique des champs ACF.
 *
 * Objectif : quand l'admin ajoute un champ via l'interface ACF (sur un CPT du
 * thème OU sur un CPT créé via CPT UI), ce champ s'affiche automatiquement sur
 * le site, sans toucher aux gabarits.
 *
 * Les champs « cœur » du thème (mdacf_*) sont déjà affichés à la main dans
 * single-artiste.php / single-release.php : ils sont donc exclus par défaut
 * pour éviter les doublons.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Noms des champs ACF « cœur » déjà rendus explicitement par les templates.
 * Exclus par défaut du rendu générique.
 *
 * @return string[]
 */
function md_core_acf_field_names() {
    return [
        // Artiste
        'mdacf_biography',
        'mdacf_social_instagram',
        'mdacf_social_soundcloud',
        'mdacf_social_bandcamp',
        'mdacf_social_spotify',
        'mdacf_social_website',
        // Release
        'mdacf_release_date',
        'mdacf_catalogue_number',
        'mdacf_bandcamp_url',
        'mdacf_description',
        'mdacf_credits',
        'mdacf_artist_ids',
    ];
}

/**
 * Formate la valeur d'un champ ACF pour l'affichage selon son type.
 *
 * @param array $field  Objet champ ACF (issu de get_field_objects()).
 * @return string  HTML échappé, prêt à afficher.
 */
function md_format_acf_value( $field ) {
    $type  = isset( $field['type'] ) ? $field['type'] : 'text';
    $value = isset( $field['value'] ) ? $field['value'] : '';

    switch ( $type ) {

        case 'url':
            return sprintf(
                '<a href="%s" target="_blank" rel="noopener">%s</a>',
                esc_url( $value ),
                esc_html( $value )
            );

        case 'email':
            return sprintf( '<a href="mailto:%s">%s</a>', esc_attr( $value ), esc_html( $value ) );

        case 'textarea':
            // ACF applique déjà le formatage des retours à la ligne selon le
            // réglage « new_lines » du champ : 'wpautop'/'br' -> HTML déjà prêt
            // (on nettoie via wp_kses_post) ; 'off' -> texte brut (on échappe).
            $nl = isset( $field['new_lines'] ) ? $field['new_lines'] : 'wpautop';
            if ( $nl === 'off' || $nl === '' ) {
                return nl2br( esc_html( $value ) );
            }
            return wp_kses_post( $value );

        case 'wysiwyg':
            return wp_kses_post( $value );

        case 'true_false':
            return $value ? 'Yes' : 'No';

        case 'image':
            $url = '';
            if ( is_array( $value ) && ! empty( $value['url'] ) ) {
                $url = $value['url'];
            } elseif ( is_numeric( $value ) ) {
                $url = wp_get_attachment_image_url( (int) $value, 'large' );
            } elseif ( is_string( $value ) ) {
                $url = $value;
            }
            return $url ? sprintf( '<img src="%s" alt="" style="max-width:100%%;height:auto;">', esc_url( $url ) ) : '';

        case 'file':
            $url = is_array( $value ) && ! empty( $value['url'] ) ? $value['url'] : ( is_string( $value ) ? $value : '' );
            return $url ? sprintf( '<a href="%s" target="_blank" rel="noopener">Download</a>', esc_url( $url ) ) : '';

        case 'relationship':
        case 'post_object':
            $items = is_array( $value ) ? $value : ( $value ? [ $value ] : [] );
            $links = [];
            foreach ( $items as $item ) {
                $pid   = is_object( $item ) ? $item->ID : (int) $item;
                $title = get_the_title( $pid );
                $perma = get_permalink( $pid );
                if ( $title ) {
                    $links[] = $perma
                        ? sprintf( '<a href="%s">%s</a>', esc_url( $perma ), esc_html( $title ) )
                        : esc_html( $title );
                }
            }
            return $links ? implode( ', ', $links ) : '';

        case 'select':
        case 'checkbox':
            $items = is_array( $value ) ? $value : [ $value ];
            return esc_html( implode( ', ', array_map( 'strval', $items ) ) );

        case 'gallery':
            $out = '';
            $items = is_array( $value ) ? $value : [];
            foreach ( $items as $img ) {
                $url = is_array( $img ) && ! empty( $img['url'] ) ? $img['url'] : ( is_numeric( $img ) ? wp_get_attachment_image_url( (int) $img, 'medium' ) : '' );
                if ( $url ) {
                    $out .= sprintf( '<img src="%s" alt="" style="max-width:150px;height:auto;margin:4px;">', esc_url( $url ) );
                }
            }
            return $out;

        default:
            if ( is_array( $value ) ) {
                return esc_html( implode( ', ', array_map( 'strval', array_filter( $value, 'is_scalar' ) ) ) );
            }
            return esc_html( (string) $value );
    }
}

/**
 * Affiche tous les champs ACF « supplémentaires » d'un post (hors champs cœur).
 *
 * @param int|null $post_id
 * @param string[] $extra_exclude  Noms de champs additionnels à ne pas afficher.
 * @param string   $title          Titre de la section (vide = pas de titre).
 * @return void  Affiche directement le HTML.
 */
function md_render_extra_acf_fields( $post_id = null, $extra_exclude = [], $title = 'Additional information' ) {
    if ( ! function_exists( 'get_field_objects' ) ) {
        return;
    }

    $post_id = $post_id ? $post_id : get_the_ID();
    $fields  = get_field_objects( $post_id );
    if ( empty( $fields ) || ! is_array( $fields ) ) {
        return;
    }

    $exclude = array_merge( md_core_acf_field_names(), (array) $extra_exclude );

    // Prépare les lignes à afficher (champs non exclus, non vides, avec un nom).
    $rows = [];
    foreach ( $fields as $name => $field ) {
        if ( in_array( $name, $exclude, true ) ) {
            continue;
        }
        if ( empty( $name ) ) {
            continue; // champ sans nom (brouillon UI)
        }
        $value = isset( $field['value'] ) ? $field['value'] : '';
        if ( $value === '' || $value === null || $value === false || ( is_array( $value ) && count( $value ) === 0 ) ) {
            continue;
        }
        $rows[] = $field;
    }

    if ( empty( $rows ) ) {
        return;
    }

    echo '<div class="md-extra-fields">';
    if ( $title !== '' ) {
        printf( '<h2 class="md-extra-fields__title">%s</h2>', esc_html( $title ) );
    }
    echo '<dl class="md-extra-fields__list">';
    foreach ( $rows as $field ) {
        $label = ! empty( $field['label'] ) ? $field['label'] : $field['name'];
        printf(
            '<dt class="md-extra-fields__label">%s</dt><dd class="md-extra-fields__value">%s</dd>',
            esc_html( $label ),
            md_format_acf_value( $field )
        );
    }
    echo '</dl>';
    echo '</div>';
}

// ==========================================================================
// Galerie de photos choisies dans la médiathèque (bloc Galerie natif)
// ==========================================================================

/**
 * Récupère les IDs d'images d'un bloc Galerie WordPress (ou du shortcode
 * [gallery] classique) présent dans le contenu d'un post.
 *
 * Utilisé par la page Visual : l'admin choisit ses photos depuis la
 * médiathèque via le bloc « Galerie » natif, sans plugin supplémentaire.
 *
 * @param int $post_id
 * @return int[]
 */
function md_get_post_gallery_image_ids( $post_id ) {
    $post = get_post( $post_id );
    if ( ! $post || empty( $post->post_content ) ) {
        return [];
    }

    $ids = md_extract_gallery_ids_from_blocks( parse_blocks( $post->post_content ) );

    if ( empty( $ids ) ) {
        // Repli : galerie via le shortcode classique [gallery] (Éditeur classique).
        $galleries = get_post_galleries( $post, false );
        if ( ! empty( $galleries[0]['ids'] ) ) {
            $ids = array_map( 'intval', explode( ',', $galleries[0]['ids'] ) );
        }
    }

    return array_values( array_unique( array_filter( $ids ) ) );
}

/**
 * Parcourt récursivement un arbre de blocs Gutenberg à la recherche d'un
 * bloc core/gallery, et en extrait les IDs des images (core/image internes).
 *
 * @param array[] $blocks  Résultat de parse_blocks().
 * @return int[]
 */
function md_extract_gallery_ids_from_blocks( $blocks ) {
    $ids = [];
    foreach ( $blocks as $block ) {
        if ( isset( $block['blockName'] ) && 'core/gallery' === $block['blockName'] ) {
            foreach ( (array) ( $block['innerBlocks'] ?? [] ) as $inner ) {
                if ( isset( $inner['blockName'], $inner['attrs']['id'] ) && 'core/image' === $inner['blockName'] ) {
                    $ids[] = (int) $inner['attrs']['id'];
                }
            }
            continue; // déjà traité : ne pas redescendre dans ses innerBlocks
        }
        if ( ! empty( $block['innerBlocks'] ) ) {
            $ids = array_merge( $ids, md_extract_gallery_ids_from_blocks( $block['innerBlocks'] ) );
        }
    }
    return $ids;
}
