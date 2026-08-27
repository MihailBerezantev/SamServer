<?php
/**
 * Mango Dragon — Submission storage
 *
 * Registers the hidden `md_submission` CPT and provides helpers to
 * save / retrieve form submissions.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ==========================================================================
// CPT registration
// ==========================================================================

function md_register_submission_cpt() {
    register_post_type( 'md_submission', [
        'labels'              => [
            'name'          => 'Demandes',
            'singular_name' => 'Demande',
        ],
        'public'              => false,
        'publicly_queryable'  => false,
        'show_ui'             => false,
        'show_in_menu'        => false,
        'show_in_rest'        => false,
        'supports'            => [ 'title', 'custom-fields' ],
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
    ] );
}
add_action( 'init', 'md_register_submission_cpt' );

// ==========================================================================
// Stockage privé des pièces jointes (hors accès public direct)
// ==========================================================================

/**
 * Dossier privé pour les fichiers reçus. Protégé par .htaccess (Apache) :
 * aucun accès direct par URL — les fichiers ne sont servis que via l'endpoint sécurisé.
 */
function md_private_dir() {
    $u   = wp_get_upload_dir();
    $dir = $u['basedir'] . '/md-private';
    if ( ! is_dir( $dir ) ) {
        wp_mkdir_p( $dir );
    }
    $ht = $dir . '/.htaccess';
    if ( ! file_exists( $ht ) ) {
        file_put_contents(
            $ht,
            "# Accès direct interdit — fichiers servis uniquement via l'endpoint sécurisé.\n"
            . "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n"
        );
    }
    $idx = $dir . '/index.php';
    if ( ! file_exists( $idx ) ) {
        file_put_contents( $idx, "<?php // Silence is golden.\n" );
    }
    return $dir;
}

/** Chemin absolu d'un fichier privé depuis son chemin relatif (relatif au basedir uploads). */
function md_private_path( $rel ) {
    $u = wp_get_upload_dir();
    return $u['basedir'] . '/' . ltrim( (string) $rel, '/\\' );
}

/** Cherche un fichier privé DÉJÀ stocké ayant la même empreinte (anti-doublon). Retourne son chemin relatif ou ''. */
function md_find_stored_by_hash( $hash ) {
    if ( ! $hash ) { return ''; }
    $ids = get_posts( [ 'post_type' => 'md_submission', 'post_status' => array_keys( md_submission_statuses() ), 'posts_per_page' => -1, 'fields' => 'ids' ] );
    foreach ( $ids as $id ) {
        $atts = get_post_meta( $id, '_md_sub_attachments', true );
        foreach ( (array) $atts as $a ) {
            if ( ( $a['kind'] ?? '' ) === 'private' && ( $a['hash'] ?? '' ) === $hash
                && ! empty( $a['path'] ) && file_exists( md_private_path( $a['path'] ) ) ) {
                return $a['path'];
            }
        }
    }
    return '';
}

/** Vrai si un chemin de fichier privé est encore référencé par une AUTRE demande (évite de supprimer un fichier partagé). */
function md_path_referenced( $path, $exclude_id = 0 ) {
    if ( ! $path ) { return false; }
    $ids = get_posts( [ 'post_type' => 'md_submission', 'post_status' => array_keys( md_submission_statuses() ), 'posts_per_page' => -1, 'fields' => 'ids' ] );
    foreach ( $ids as $id ) {
        if ( (int) $id === (int) $exclude_id ) { continue; }
        $atts = get_post_meta( $id, '_md_sub_attachments', true );
        foreach ( (array) $atts as $a ) {
            if ( ( $a['kind'] ?? '' ) === 'private' && ( $a['path'] ?? '' ) === $path ) {
                return true;
            }
        }
    }
    return false;
}

// ==========================================================================
// Save a submission to the database
// ==========================================================================

/**
 * Save a form submission.
 *
 * @param array $data {
 *   @type string $email         Contact email.
 *   @type string $project       Project / subject name.
 *   @type string $description   Optional description.
 *   @type string $files_link    Optional cloud-storage link.
 *   @type string $attachment    Full path to uploaded file (temporary, will be logged).
 *   @type string $type          Request type slug, e.g. 'demo_submission'.
 * }
 * @return int|WP_Error Post ID on success.
 */
function md_save_submission( array $data ) {
    $post_id = wp_insert_post( [
        'post_type'   => 'md_submission',
        'post_title'  => sanitize_text_field( $data['project'] ?? 'Sans titre' ),
        'post_status' => 'new',
        'post_date'   => current_time( 'mysql' ),
    ], true );

    if ( is_wp_error( $post_id ) ) {
        return $post_id;
    }

    // Pièces jointes : métadonnées structurées pour la Boîte de réception.
    $atts = [];
    if ( ! empty( $data['attachments'] ) && is_array( $data['attachments'] ) ) {
        foreach ( $data['attachments'] as $a ) {
            $atts[] = [
                'kind'          => in_array( ( $a['kind'] ?? '' ), [ 'media', 'kdrive', 'private' ], true ) ? $a['kind'] : 'private',
                'media_id'      => (int) ( $a['media_id'] ?? 0 ),
                'orig'          => sanitize_file_name( $a['orig'] ?? '' ),
                'stored'        => sanitize_file_name( $a['stored'] ?? '' ),
                'mime'          => sanitize_text_field( $a['mime'] ?? '' ),
                'size'          => (int) ( $a['size'] ?? 0 ),
                'hash'          => preg_replace( '/[^a-f0-9]/i', '', (string) ( $a['hash'] ?? '' ) ),
                'state'         => sanitize_key( $a['state'] ?? 'available' ),
                'kdrive_folder' => sanitize_text_field( $a['kdrive_folder'] ?? '' ),
                'path'          => sanitize_text_field( $a['path'] ?? '' ),
                'deduped'       => ! empty( $a['deduped'] ),
            ];
        }
    }

    // Champ « haystack » pour la recherche (expéditeur + objet + message + noms de fichiers).
    $haystack = strtolower( trim(
        ( $data['email'] ?? '' ) . ' '
        . ( $data['subject'] ?? ( $data['project'] ?? '' ) ) . ' '
        . ( $data['description'] ?? '' ) . ' '
        . implode( ' ', array_map( function ( $a ) { return $a['orig'] ?? ''; }, $atts ) )
    ) );

    $meta = [
        '_md_sub_email'       => sanitize_email( $data['email'] ?? '' ),
        '_md_sub_subject'     => sanitize_text_field( $data['subject'] ?? ( $data['project'] ?? '' ) ),
        '_md_sub_description' => sanitize_textarea_field( $data['description'] ?? '' ),
        '_md_sub_files_link'  => esc_url_raw( $data['files_link'] ?? '' ),
        '_md_sub_attachment'  => sanitize_text_field( $data['attachment'] ?? '' ),
        '_md_sub_attachments' => $atts,
        '_md_sub_type'        => sanitize_key( $data['type'] ?? 'demo_submission' ),
        '_md_sub_search'      => $haystack,
        '_md_sub_read'        => 0,
        '_md_sub_notify_log'  => [],
        '_md_sub_history'     => [ [
            'date'    => current_time( 'mysql' ),
            'user'    => 0,
            'from'    => '',
            'to'      => 'new',
            'comment' => 'Reçu via le formulaire',
        ] ],
    ];

    foreach ( $meta as $key => $value ) {
        update_post_meta( $post_id, $key, $value );
    }

    return $post_id;
}

/**
 * Ajoute une entrée à l'historique de traitement d'une demande.
 */
function md_add_submission_history( $post_id, $from, $to, $comment = '' ) {
    $hist = get_post_meta( $post_id, '_md_sub_history', true );
    $hist = is_array( $hist ) ? $hist : [];
    $hist[] = [
        'date'    => current_time( 'mysql' ),
        'user'    => get_current_user_id(),
        'from'    => sanitize_key( $from ),
        'to'      => sanitize_key( $to ),
        'comment' => sanitize_text_field( $comment ),
    ];
    update_post_meta( $post_id, '_md_sub_history', $hist );
}

/** Marque une demande comme lue / non lue. */
function md_set_submission_read( $post_id, $read = true ) {
    update_post_meta( (int) $post_id, '_md_sub_read', $read ? 1 : 0 );
}

// ==========================================================================
// Update submission status
// ==========================================================================

/**
 * Valid statuses for a submission.
 */
function md_submission_statuses() {
    return [
        'new'         => 'Nouveau',
        'read'        => 'Lu',
        'to_process'  => 'À traiter',
        'in_progress' => 'En cours',
        'done'        => 'Traité',
        'archived'    => 'Archivé',
        'error'       => 'En erreur',
        'spam'        => 'Indésirable',
    ];
}

/**
 * Update the status of a submission.
 *
 * @param int    $post_id
 * @param string $status  One of the keys from md_submission_statuses().
 * @return bool
 */
function md_update_submission_status( $post_id, $status ) {
    $valid = array_keys( md_submission_statuses() );
    if ( ! in_array( $status, $valid, true ) ) {
        return false;
    }
    return (bool) wp_update_post( [
        'ID'          => (int) $post_id,
        'post_status' => $status,
    ] );
}

// ==========================================================================
// Append a notification log entry
// ==========================================================================

/**
 * Add a log entry to _md_sub_notify_log.
 *
 * @param int    $post_id
 * @param string $recipient
 * @param bool   $sent
 * @param string $error  Error message or empty string.
 */
function md_log_notification( $post_id, $recipient, $sent, $error = '' ) {
    $log   = get_post_meta( $post_id, '_md_sub_notify_log', true );
    $log   = is_array( $log ) ? $log : [];
    $log[] = [
        'recipient' => $recipient,
        'sent'      => (bool) $sent,
        'error'     => $error,
        'timestamp' => current_time( 'mysql' ),
    ];
    update_post_meta( $post_id, '_md_sub_notify_log', $log );
}

// ==========================================================================
// Allow custom post_status values for md_submission
// ==========================================================================

function md_register_submission_statuses() {
    foreach ( md_submission_statuses() as $slug => $label ) {
        register_post_status( $slug, [
            'label'                     => $label,
            'public'                    => false,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => false,
            'show_in_admin_status_list' => false,
        ] );
    }
}
add_action( 'init', 'md_register_submission_statuses' );
