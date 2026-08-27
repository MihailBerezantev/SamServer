<?php
/**
 * CPT UI — Intégration (source de vérité = CPT UI).
 *
 * Les types de contenu (artiste, release, studio_photo) et les taxonomies
 * (genre, release_type, artist_type) sont désormais définis dans CPT UI :
 * toute la configuration est centralisée et éditable depuis le plugin.
 *
 * Le thème ne fournit qu'un FALLBACK de sécurité : post-types.php et
 * taxonomies.php ne ré-enregistrent un élément QUE si CPT UI n'en possède pas
 * la définition (par ex. si le plugin est désactivé ou sa config perdue).
 * Ainsi le site continue de fonctionner en toutes circonstances.
 *
 * Ce fichier expose uniquement les helpers de détection utilisés par ce
 * fallback. (Il ne bloque plus CPT UI : c'est désormais lui le propriétaire.)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CPT UI possède-t-il déjà la définition de ce type de contenu ?
 *
 * @param string $name  Slug du type (ex. 'artiste').
 * @return bool
 */
function md_cptui_owns_post_type( $name ) {
    if ( ! function_exists( 'cptui_get_post_type_data' ) ) {
        return false;
    }
    $data = cptui_get_post_type_data();
    return is_array( $data ) && isset( $data[ $name ] );
}

/**
 * CPT UI possède-t-il déjà la définition de cette taxonomie ?
 *
 * @param string $name  Slug de la taxonomie (ex. 'genre').
 * @return bool
 */
function md_cptui_owns_taxonomy( $name ) {
    if ( ! function_exists( 'cptui_get_taxonomy_data' ) ) {
        return false;
    }
    $data = cptui_get_taxonomy_data();
    return is_array( $data ) && isset( $data[ $name ] );
}
