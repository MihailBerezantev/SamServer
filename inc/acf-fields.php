<?php
/**
 * ACF Fields — Groupes de champs pour les CPT Artiste & Release.
 *
 * Les groupes sont créés UNE SEULE FOIS en base de données, puis gérés
 * normalement depuis l'interface ACF (visibles, modifiables, suppression de
 * champs possible). La création est protégée par une garde anti-doublon.
 *
 * IMPORTANT — Collision de clés évitée :
 * ACF stocke la valeur d'un champ sur la meta `<name>` et sa référence interne
 * sur la meta `_<name>`. Les templates lisent, eux, les clés `_md_*`. Pour ne
 * PAS les écraser, les champs ACF sont nommés `mdacf_*` (référence ACF =
 * `_mdacf_*`). Le pont acf-compat.php recopie ensuite `mdacf_*` -> `_md_*`.
 * => Ne renomme PAS un champ `mdacf_*` depuis l'interface, sinon le contenu
 *    ne s'affiche plus côté site. Tu peux en revanche les voir, les réordonner
 *    ou en ajouter.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ==========================================================================
// JSON local ACF = SAUVEGARDE AUTOMATIQUE À SENS UNIQUE.
//
// La BASE DE DONNÉES reste la source unique de vérité au runtime. Le dossier
// /acf-json sert uniquement de filet de sécurité local (il n'y a pas de git
// ici) : ACF y ÉCRIT une copie fraîche de chaque groupe de champs à chaque
// enregistrement.
//
//   - ÉCRITURE (save) : ACTIVE  -> sauvegarde à jour en continu.
//   - LECTURE  (load) : DÉSACTIVÉE pour le dossier du thème -> la base fait
//     toujours foi, pas de badge « Synchronisation disponible », pas de
//     double-copie.
//
// Restauration en cas de suppression accidentelle : ACF > Outils > Importer
// (on téléverse le .json voulu depuis wp-content/themes/mango-dragon/acf-json).
//
// Filtre ACF 6.2+ : acf/json/load_paths, scopé au seul dossier du thème
// (n'affecte pas d'éventuels autres chemins déclarés par des extensions).
// ==========================================================================
add_filter( 'acf/json/load_paths', 'md_acf_strip_theme_json_path' );
function md_acf_strip_theme_json_path( $paths ) {
    $json_dir = rtrim( get_template_directory() . '/acf-json', '/' );
    return array_values( array_filter( (array) $paths, function( $p ) use ( $json_dir ) {
        return rtrim( (string) $p, '/' ) !== $json_dir;
    } ) );
}

// ==========================================================================
// Définitions des groupes (servent UNIQUEMENT à la création initiale)
// ==========================================================================
function md_acf_group_definitions() {
    return [
        // -----------------------------------------------------------------
        // Groupe 1 — Artiste
        // -----------------------------------------------------------------
        [
            'key'                   => 'group_md_artiste',
            'title'                 => 'Détails de l\'artiste',
            'fields'                => [
                [
                    'key'       => 'field_md_biography',
                    'label'     => 'Biographie',
                    'name'      => 'mdacf_biography',
                    'type'      => 'textarea',
                    'rows'      => 8,
                    'new_lines' => 'wpautop',
                    'required'  => 0,
                ],
                [
                    'key'      => 'field_md_social_instagram',
                    'label'    => 'Instagram URL',
                    'name'     => 'mdacf_social_instagram',
                    'type'     => 'url',
                    'required' => 0,
                ],
                [
                    'key'      => 'field_md_social_soundcloud',
                    'label'    => 'SoundCloud URL',
                    'name'     => 'mdacf_social_soundcloud',
                    'type'     => 'url',
                    'required' => 0,
                ],
                [
                    'key'      => 'field_md_social_bandcamp',
                    'label'    => 'Bandcamp URL',
                    'name'     => 'mdacf_social_bandcamp',
                    'type'     => 'url',
                    'required' => 0,
                ],
                [
                    'key'      => 'field_md_social_spotify',
                    'label'    => 'Spotify URL',
                    'name'     => 'mdacf_social_spotify',
                    'type'     => 'url',
                    'required' => 0,
                ],
                [
                    'key'      => 'field_md_social_website',
                    'label'    => 'Site web',
                    'name'     => 'mdacf_social_website',
                    'type'     => 'url',
                    'required' => 0,
                ],
                [
                    'key'          => 'field_md_videos',
                    'label'        => 'Vidéos YouTube',
                    'name'         => 'mdacf_videos',
                    'type'         => 'textarea',
                    'rows'         => 4,
                    // Pas de wpautop : le contenu est une liste d'URL, pas de la prose.
                    'new_lines'    => '',
                    'instructions' => 'Une URL par ligne (mix, set VJ…). Formats acceptés : youtube.com/watch?v=…, youtu.be/…, /shorts/…',
                    'required'     => 0,
                ],
            ],
            'location'              => [
                [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'artiste' ] ],
            ],
            'menu_order'            => 0,
            'position'              => 'normal',
            'style'                 => 'default',
            'label_placement'       => 'top',
            'instruction_placement' => 'label',
            'active'                => true,
        ],

        // -----------------------------------------------------------------
        // Groupe 2 — Release
        // -----------------------------------------------------------------
        [
            'key'                   => 'group_md_release',
            'title'                 => 'Détails de la release',
            'fields'                => [
                [
                    'key'            => 'field_md_release_date',
                    'label'          => 'Date de sortie',
                    'name'           => 'mdacf_release_date',
                    'type'           => 'date_picker',
                    'display_format' => 'd/m/Y',
                    'return_format'  => 'Y-m-d',
                    'first_day'      => 1,
                    'required'       => 0,
                ],
                [
                    'key'      => 'field_md_catalogue_number',
                    'label'    => 'Numéro de catalogue',
                    'name'     => 'mdacf_catalogue_number',
                    'type'     => 'text',
                    'required' => 0,
                ],
                [
                    'key'      => 'field_md_bandcamp_url',
                    'label'    => 'URL Bandcamp',
                    'name'     => 'mdacf_bandcamp_url',
                    'type'     => 'url',
                    'required' => 0,
                ],
                [
                    'key'       => 'field_md_description',
                    'label'     => 'Description',
                    'name'      => 'mdacf_description',
                    'type'      => 'textarea',
                    'rows'      => 5,
                    'new_lines' => 'wpautop',
                    'required'  => 0,
                ],
                [
                    'key'       => 'field_md_credits',
                    'label'     => 'Crédits',
                    'name'      => 'mdacf_credits',
                    'type'      => 'textarea',
                    'rows'      => 3,
                    'new_lines' => 'wpautop',
                    'required'  => 0,
                ],
                [
                    'key'           => 'field_md_artist_ids',
                    'label'         => 'Artistes associés',
                    'name'          => 'mdacf_artist_ids',
                    'type'          => 'relationship',
                    'post_type'     => [ 'artiste' ],
                    'taxonomy'      => [],
                    'filters'       => [ 'search' ],
                    'return_format' => 'id',
                    'required'      => 0,
                    'min'           => 0,
                    'max'           => 0,
                ],
            ],
            'location'              => [
                [ [ 'param' => 'post_type', 'operator' => '==', 'value' => 'release' ] ],
            ],
            'menu_order'            => 0,
            'position'              => 'normal',
            'style'                 => 'default',
            'label_placement'       => 'top',
            'instruction_placement' => 'label',
            'active'                => true,
        ],
    ];
}

// ==========================================================================
// Création initiale en base — UNE SEULE FOIS (garde anti-doublon)
// Après ça, ACF gère les groupes normalement depuis l'interface.
// ==========================================================================
add_action( 'acf/init', 'md_acf_seed_groups', 20 );
function md_acf_seed_groups() {
    if ( ! function_exists( 'acf_import_field_group' ) || ! function_exists( 'acf_get_field_group' ) ) {
        return;
    }
    if ( get_option( 'md_acf_seeded' ) === '1' ) {
        return; // déjà créés : on ne touche plus à rien (édition libre dans l'UI)
    }
    foreach ( md_acf_group_definitions() as $group ) {
        if ( ! acf_get_field_group( $group['key'] ) ) {
            acf_import_field_group( $group ); // crée le groupe en base
        }
    }
    update_option( 'md_acf_seeded', '1' );
}

// ==========================================================================
// Masque les meta-boxes PHP natives quand ACF est actif (sauf la tracklist)
// ==========================================================================
add_action( 'add_meta_boxes', 'md_hide_metaboxes_when_acf_active', 100 );
function md_hide_metaboxes_when_acf_active() {
    if ( ! class_exists( 'ACF' ) ) {
        return;
    }

    // Artiste : retire les boîtes détails (biographie) et liens sociaux
    remove_meta_box( 'md_artiste_details', 'artiste', 'normal' );
    remove_meta_box( 'md_artiste_social',  'artiste', 'normal' );

    // Release : retire détails et artistes (la tracklist reste en natif)
    remove_meta_box( 'md_release_details',  'release', 'normal' );
    remove_meta_box( 'md_release_artists',  'release', 'side' );
}
