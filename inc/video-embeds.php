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
