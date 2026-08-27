<?php
/**
 * Vidéos YouTube des artistes (mix, sets VJ…).
 *
 * ACF est en version gratuite : le champ répétable n'existe pas. Les vidéos
 * sont donc saisies dans un textarea, une URL par ligne, et ce module en
 * extrait les identifiants.
 *
 * Les lecteurs sont servis depuis youtube-nocookie.com : YouTube n'y dépose
 * aucun cookie de suivi tant que le visiteur ne lance pas la lecture. Sur un
 * site européen, c'est le comportement à privilégier par défaut.
 */

/**
 * Extrait les identifiants de vidéo YouTube d'un texte libre.
 *
 * Accepte les formes rencontrées en pratique : watch?v=, youtu.be/, /embed/,
 * /shorts/, /live/ et /v/. Les lignes non reconnues sont ignorées en silence
 * plutôt que d'afficher un lecteur cassé.
 *
 * @param string $text Une URL par ligne.
 * @return string[] Identifiants, sans doublon, dans l'ordre de saisie.
 */
function md_youtube_ids( $text ) {
    if ( ! is_string( $text ) || '' === trim( $text ) ) {
        return [];
    }

    $ids     = [];
    $pattern = '~(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:[^ ]*&)?v=|embed/|shorts/|live/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})~i';

    foreach ( preg_split( '/\R/', $text ) as $line ) {
        $line = trim( $line );
        if ( '' === $line ) {
            continue;
        }
        if ( preg_match( $pattern, $line, $matches ) ) {
            $ids[] = $matches[1];
        }
    }

    return array_values( array_unique( $ids ) );
}

/**
 * URL d'intégration, sans cookie de suivi avant lecture.
 *
 * @param string $id Identifiant de 11 caractères.
 * @return string
 */
function md_youtube_embed_url( $id ) {
    return 'https://www.youtube-nocookie.com/embed/' . rawurlencode( $id );
}

/**
 * Ajoute le champ « Vidéos YouTube » aux groupes ACF existants.
 *
 * md_acf_seed_groups() n'importe md_acf_group_definitions() qu'UNE SEULE FOIS,
 * protégé par l'option md_acf_seeded. Passée cette amorce, ACF gère les groupes
 * depuis sa propre interface : ajouter un champ à la définition PHP n'a plus
 * aucun effet et le champ reste invisible dans le back-office.
 *
 * On passe donc par le filtre acf/load_fields, qui AJOUTE le champ à la liste
 * déjà chargée. À ne surtout pas remplacer par acf_add_local_field() avec un
 * 'parent' : ACF considère alors le groupe comme local et sert uniquement les
 * champs déclarés en PHP, faisant disparaître la biographie, les liens sociaux
 * et la description de l'interface.
 *
 * @param array $fields Champs déjà chargés pour le groupe.
 * @param array $parent Le groupe en cours de chargement.
 * @return array
 */
add_filter( 'acf/load_fields', 'md_append_video_field', 10, 2 );
function md_append_video_field( $fields, $parent ) {
    $cibles = [
        'group_md_artiste'     => [ 'key' => 'field_md_videos',    'name' => 'mdacf_videos' ],
        'group_md_visual_item' => [ 'key' => 'field_mdvis_videos', 'name' => 'mdvis_videos' ],
    ];

    $cle = isset( $parent['key'] ) ? $parent['key'] : '';
    if ( ! isset( $cibles[ $cle ] ) ) {
        return $fields;
    }

    // Déjà présent (groupe modifié depuis l'interface) : on ne double pas.
    foreach ( (array) $fields as $f ) {
        if ( isset( $f['name'] ) && $f['name'] === $cibles[ $cle ]['name'] ) {
            return $fields;
        }
    }

    $fields[] = [
        'ID'           => 0,
        'key'          => $cibles[ $cle ]['key'],
        'name'         => $cibles[ $cle ]['name'],
        'label'        => 'Vidéos YouTube',
        'type'         => 'textarea',
        'rows'         => 4,
        // Pas de wpautop : le contenu est une liste d'URL, pas de la prose.
        'new_lines'    => '',
        'instructions' => 'Une URL par ligne. Formats acceptés : youtube.com/watch?v=…, youtu.be/…, /shorts/…',
        'required'     => 0,
        'parent'       => $cle,
        'menu_order'   => 99,
    ];

    return $fields;
}
