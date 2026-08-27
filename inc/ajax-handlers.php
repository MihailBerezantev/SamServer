<?php
/**
 * AJAX Handlers — Navigation AJAX for persistent audio player
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Limiteur de débit simple par IP (anti-abus / anti-DoS pour les endpoints publics).
 *
 * @param string $action  Identifiant de l'action (namespace du compteur).
 * @param int    $max     Nombre max d'appels autorisés dans la fenêtre.
 * @param int    $window  Fenêtre en secondes.
 * @return bool  true si autorisé, false si la limite est atteinte.
 */
function md_rate_limit( $action, $max, $window ) {
    $ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
    $key = 'md_rl_' . $action . '_' . md5( $ip );
    $count = (int) get_transient( $key );
    if ( $count >= $max ) {
        return false;
    }
    set_transient( $key, $count + 1, $window );
    return true;
}

/**
 * AJAX endpoint: load page content (the <main> inner HTML) for SPA-like navigation.
 * Accepts: 'url' parameter (the page URL to load)
 * Returns: JSON { html, title, bodyClass }
 */
function md_ajax_load_page() {
    check_ajax_referer( 'md_ajax_nonce', 'nonce' );

    if ( ! md_rate_limit( 'load_page', 90, 60 ) ) {
        wp_send_json_error( [ 'message' => 'Too many requests. Please wait a moment.' ], 429 );
    }

    $url = isset( $_GET['url'] ) ? esc_url_raw( $_GET['url'] ) : '';

    if ( empty( $url ) ) {
        wp_send_json_error( [ 'message' => 'Missing URL' ] );
    }

    // Convert URL to a relative path for WP to process
    $site_url = home_url( '/' );
    $path = str_replace( $site_url, '/', $url );
    $path = '/' . ltrim( $path, '/' );

    // Use WP internal URL-to-query resolution
    $post_id = url_to_postid( $url );

    // Start output buffering — simulate a page load
    ob_start();

    // Override the main query to match the requested URL
    global $wp, $wp_query;

    // Save current query
    $original_query = clone $wp_query;

    // Parse the URL and run the query
    $wp->parse_request( ltrim( $path, '/' ) );
    $wp->query_posts();
    $wp->register_globals();

    // Determine which template to load
    $template = '';

    if ( is_front_page() ) {
        $template = MD_DIR . '/front-page.php';
    } elseif ( is_post_type_archive( 'artiste' ) ) {
        $template = MD_DIR . '/archive-artiste.php';
    } elseif ( is_post_type_archive( 'release' ) ) {
        $template = MD_DIR . '/archive-release.php';
    } elseif ( is_singular( 'artiste' ) ) {
        $template = MD_DIR . '/single-artiste.php';
    } elseif ( is_singular( 'release' ) ) {
        $template = MD_DIR . '/single-release.php';
    } elseif ( is_page() ) {
        $slug = get_post_field( 'post_name', get_queried_object_id() );
        $page_template = MD_DIR . "/page-{$slug}.php";
        if ( file_exists( $page_template ) ) {
            $template = $page_template;
        } else {
            $template = MD_DIR . '/page.php';
            if ( ! file_exists( $template ) ) {
                $template = MD_DIR . '/index.php';
            }
        }
    } elseif ( is_404() ) {
        $template = MD_DIR . '/404.php';
    } else {
        $template = MD_DIR . '/index.php';
    }

    // We only want the content inside <main>, not header/footer
    // Templates call get_header() and get_footer() which we need to suppress
    // Instead, define a flag that templates can check
    define( 'MD_AJAX_REQUEST', true );

    if ( file_exists( $template ) ) {
        include $template;
    }

    $html = ob_get_clean();

    // Extract content between <main> and </main> if present
    // (for AJAX, our templates output content directly when MD_AJAX_REQUEST is defined)
    $title = wp_get_document_title();
    $body_class = implode( ' ', get_body_class() );

    // Restore original query
    $wp_query = $original_query;
    wp_reset_postdata();

    wp_send_json_success( [
        'html'      => $html,
        'title'     => $title,
        'bodyClass' => $body_class,
    ] );
}
add_action( 'wp_ajax_md_load_page', 'md_ajax_load_page' );
add_action( 'wp_ajax_nopriv_md_load_page', 'md_ajax_load_page' );

/**
 * AJAX endpoint: handle contact / demo form submissions with optional file upload.
 * Sends an email via wp_mail with the file attached if present.
 */
/**
 * Normalise $_FILES[$field] en liste de fichiers uniques (gère mono ET multi `name[]`).
 *
 * @return array<int,array{name:string,type:string,tmp_name:string,error:int,size:int}>
 */
function md_normalize_files( $field ) {
    if ( empty( $_FILES[ $field ] ) || empty( $_FILES[ $field ]['name'] ) ) {
        return [];
    }
    $f = $_FILES[ $field ];
    if ( ! is_array( $f['name'] ) ) {
        return [ $f ]; // un seul fichier
    }
    $out = [];
    foreach ( $f['name'] as $i => $name ) {
        if ( $name === '' || ( isset( $f['error'][ $i ] ) && (int) $f['error'][ $i ] === UPLOAD_ERR_NO_FILE ) ) {
            continue;
        }
        $out[] = [
            'name'     => $f['name'][ $i ],
            'type'     => $f['type'][ $i ] ?? '',
            'tmp_name' => $f['tmp_name'][ $i ] ?? '',
            'error'    => $f['error'][ $i ] ?? 0,
            'size'     => $f['size'][ $i ] ?? 0,
        ];
    }
    return $out;
}

/**
 * Analyse antivirus (optionnelle) d'un fichier téléversé.
 * Ne bloque QUE si un scanner (ClamAV) est présent et détecte une menace.
 * Extensible via le filtre `md_file_is_safe`.
 *
 * @return bool  true = sûr / pas de scanner ; false = menace détectée.
 */
function md_file_is_safe( $tmp_path, $name ) {
    $safe = true;
    if ( $tmp_path && function_exists( 'shell_exec' ) && @is_executable( '/usr/bin/clamscan' ) ) {
        $out = @shell_exec( 'clamscan --no-summary ' . escapeshellarg( $tmp_path ) . ' 2>/dev/null' );
        if ( is_string( $out ) && strpos( $out, 'FOUND' ) !== false ) {
            $safe = false;
        }
    }
    return (bool) apply_filters( 'md_file_is_safe', $safe, $tmp_path, $name );
}

function md_contact_form() {
    check_ajax_referer( 'md_ajax_nonce', 'nonce' );

    if ( ! md_rate_limit( 'contact', 5, 600 ) ) {
        wp_send_json_error( [ 'message' => 'Too many submissions. Please try again in a few minutes.' ] );
    }

    $email       = isset( $_POST['user_email'] ) ? sanitize_email( $_POST['user_email'] ) : '';
    $project     = isset( $_POST['project_name'] ) ? sanitize_text_field( $_POST['project_name'] ) : '';
    $description = isset( $_POST['project_description'] ) ? sanitize_textarea_field( $_POST['project_description'] ) : '';
    $link        = isset( $_POST['files_link'] ) ? esc_url_raw( $_POST['files_link'] ) : '';

    // Seul l'email est requis (les autres champs sont configurables par page).
    if ( ! is_email( $email ) ) {
        wp_send_json_error( [ 'message' => 'Please enter a valid email address.' ] );
    }

    $to      = 'contact@mango-dragon.com';
    $subject = '[Mango Dragon] ' . ( $project !== '' ? $project : 'Nouveau message' );

    $body  = "Nouveau message depuis le site :\n\n";
    $body .= "Email : {$email}\n";
    if ( $project !== '' ) {
        $body .= "Projet : {$project}\n\n";
    }
    if ( $description ) {
        $body .= "Description :\n{$description}\n\n";
    }
    if ( $link ) {
        $body .= "Lien fichiers : {$link}\n\n";
    }

    $headers     = [
        'From: Site Mango Dragon <contact@mango-dragon.com>', // expéditeur du domaine → meilleure délivrabilité
        'Reply-To: ' . $email,                                // répondre = écrire à l'expéditeur du formulaire
    ];
    $attachments = [];
    $att_records = []; // métadonnées des pièces jointes pour la Boîte de réception (wp-admin)

    // Fichiers (MULTI) — normalisés, scannés, dédupliqués par empreinte, stockés localement (privé) ou kDrive.
    $allowed_ext = [ 'wav', 'mp3', 'flac', 'aiff', 'zip', 'rar' ];
    $files       = md_normalize_files( 'demo_file' );
    $seen_hashes = []; // anti-doublon au sein d'une même soumission

    foreach ( $files as $f ) {
        $orig_name = sanitize_file_name( $f['name'] );
        $file_ext  = strtolower( pathinfo( $orig_name, PATHINFO_EXTENSION ) );

        if ( ! in_array( $file_ext, $allowed_ext, true ) ) {
            wp_send_json_error( [ 'message' => 'File type not allowed.' ] );
        }
        if ( (int) $f['size'] > 300 * 1024 * 1024 ) {
            wp_send_json_error( [ 'message' => 'File too large (300 MB max).' ] );
        }
        // Antivirus : ne bloque que si un scanner est présent ET détecte une menace.
        if ( ! md_file_is_safe( $f['tmp_name'], $orig_name ) ) {
            wp_send_json_error( [ 'message' => 'A file was flagged as suspicious and was rejected.' ] );
        }

        $upload = wp_handle_upload( $f, [ 'test_form' => false ] );
        if ( isset( $upload['error'] ) ) {
            wp_send_json_error( [ 'message' => $upload['error'] ] );
        }
        $file_path = $upload['file'];
        $file_size = filesize( $file_path );
        $file_hash = @hash_file( 'sha256', $file_path ); // empreinte anti-doublon

        // Stockage LOCAL privé + ANTI-DOUBLON (réutilise un fichier existant de même empreinte).
        $existing = $seen_hashes[ $file_hash ] ?? ( $file_hash ? md_find_stored_by_hash( $file_hash ) : '' );
        $deduped  = false;
        if ( $existing && file_exists( md_private_path( $existing ) ) ) {
            wp_delete_file( $file_path ); // doublon → pas de 2e copie stockée
            $rel     = $existing;
            $deduped = true;
        } else {
            $priv = md_private_dir();
            $safe = wp_unique_filename( $priv, basename( $file_path ) );
            $dest = $priv . '/' . $safe;
            if ( ! @rename( $file_path, $dest ) ) { @copy( $file_path, $dest ); wp_delete_file( $file_path ); }
            $rel = 'md-private/' . $safe;
        }
        if ( $file_hash ) { $seen_hashes[ $file_hash ] = $rel; }

        $attachments[] = md_private_path( $rel ); // pièce jointe e-mail
        $body         .= "Fichier joint : " . $orig_name . ( $deduped ? " (doublon - reutilise)" : " (stockage prive)" ) . "\n";
        $att_records[] = [
            'kind' => 'private', 'media_id' => 0, 'orig' => $orig_name, 'stored' => basename( $rel ),
            'mime' => $upload['type'], 'size' => $file_size, 'hash' => $file_hash,
            'state' => 'available', 'kdrive_folder' => '', 'path' => $rel, 'deduped' => $deduped,
        ];
    }

    // Enregistre la demande dans la Boîte de réception (wp-admin > E-mails).
    if ( function_exists( 'md_save_submission' ) ) {
        md_save_submission( [
            'email'       => $email,
            'project'     => ( $project !== '' ? $project : 'Nouveau message' ),
            'subject'     => $subject,
            'description' => $description,
            'files_link'  => $link,
            'attachments' => $att_records,
            'type'        => 'demo_submission',
        ] );
    }

    $sent = wp_mail( $to, $subject, $body, $headers, $attachments );

    if ( $sent ) {
        wp_send_json_success( [ 'message' => 'Message sent.' ] );
    } else {
        wp_send_json_error( [ 'message' => 'Sending failed.' ] );
    }
}
add_action( 'wp_ajax_md_contact_form', 'md_contact_form' );
add_action( 'wp_ajax_nopriv_md_contact_form', 'md_contact_form' );
