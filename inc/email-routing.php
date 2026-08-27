<?php
/**
 * Mango Dragon — Email routing
 *
 * Manages the routing configuration (stored in wp_options as `md_email_routing`)
 * and dispatches notification emails for submissions.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MD_ROUTING_OPTION', 'md_email_routing' );

// ==========================================================================
// Default routing rules
// ==========================================================================

function md_default_routing() {
    return [
        [
            'id'           => 'routing_demo_submission',
            'request_type' => 'demo_submission',
            'label'        => 'Envoi de démo',
            'recipients'   => [ 'contact@mango-dragon.com' ],
            'cc'           => [],
            'bcc'          => [],
            'enabled'      => true,
            'updated_at'   => gmdate( 'c' ),
        ],
    ];
}

// ==========================================================================
// CRUD helpers
// ==========================================================================

/**
 * Get all routing rules.
 *
 * @return array
 */
function md_get_routing() {
    $rules = get_option( MD_ROUTING_OPTION );
    if ( ! is_array( $rules ) || empty( $rules ) ) {
        $rules = md_default_routing();
        update_option( MD_ROUTING_OPTION, $rules );
    }
    return $rules;
}

/**
 * Persist routing rules.
 *
 * @param array $rules
 * @return bool
 */
function md_save_routing( array $rules ) {
    return update_option( MD_ROUTING_OPTION, $rules );
}

/**
 * Get active routing rules for a specific request type.
 *
 * @param string $type
 * @return array  Array of matching enabled rules.
 */
function md_get_routing_for_type( $type ) {
    return array_values( array_filter( md_get_routing(), function ( $rule ) use ( $type ) {
        return ! empty( $rule['enabled'] )
            && isset( $rule['request_type'] )
            && $rule['request_type'] === $type;
    } ) );
}

/**
 * Sanitize and validate a single routing rule before save.
 *
 * @param array $raw
 * @return array|WP_Error
 */
function md_sanitize_routing_rule( array $raw ) {
    $id = isset( $raw['id'] ) && $raw['id'] ? sanitize_key( $raw['id'] ) : 'routing_' . uniqid();

    $recipients = [];
    foreach ( (array) ( $raw['recipients'] ?? [] ) as $r ) {
        $r = sanitize_email( trim( $r ) );
        if ( is_email( $r ) && ! in_array( $r, $recipients, true ) ) {
            $recipients[] = $r;
        }
    }

    $cc = [];
    foreach ( (array) ( $raw['cc'] ?? [] ) as $r ) {
        $r = sanitize_email( trim( $r ) );
        if ( is_email( $r ) && ! in_array( $r, $cc, true ) ) {
            $cc[] = $r;
        }
    }

    $bcc = [];
    foreach ( (array) ( $raw['bcc'] ?? [] ) as $r ) {
        $r = sanitize_email( trim( $r ) );
        if ( is_email( $r ) && ! in_array( $r, $bcc, true ) ) {
            $bcc[] = $r;
        }
    }

    return [
        'id'           => $id,
        'request_type' => sanitize_key( $raw['request_type'] ?? 'demo_submission' ),
        'label'        => sanitize_text_field( $raw['label'] ?? '' ),
        'recipients'   => $recipients,
        'cc'           => $cc,
        'bcc'          => $bcc,
        'enabled'      => ! empty( $raw['enabled'] ),
        'updated_at'   => gmdate( 'c' ),
    ];
}

// ==========================================================================
// Dispatch
// ==========================================================================

/**
 * Build and send notification emails for a submission, log results.
 *
 * @param int $submission_id  Post ID of the md_submission CPT.
 * @return bool  True if at least one email was sent successfully.
 */
function md_dispatch_email( $submission_id ) {
    $submission_id = (int) $submission_id;
    $post          = get_post( $submission_id );

    if ( ! $post || $post->post_type !== 'md_submission' ) {
        return false;
    }

    $type        = get_post_meta( $submission_id, '_md_sub_type', true ) ?: 'demo_submission';
    $rules       = md_get_routing_for_type( $type );

    if ( empty( $rules ) ) {
        md_log_notification( $submission_id, '', false, 'Aucun destinataire configuré pour ce type de demande.' );
        md_update_submission_status( $submission_id, 'error' );
        return false;
    }

    $email       = get_post_meta( $submission_id, '_md_sub_email', true );
    $description = get_post_meta( $submission_id, '_md_sub_description', true );
    $files_link  = get_post_meta( $submission_id, '_md_sub_files_link', true );
    $attachment  = get_post_meta( $submission_id, '_md_sub_attachment', true );
    $project     = $post->post_title;

    $subject = '[Mango Dragon] ' . $project;

    $body  = "Nouveau message depuis le site :\n\n";
    $body .= "Email : {$email}\n";
    $body .= "Projet : {$project}\n\n";
    if ( $description ) {
        $body .= "Description :\n{$description}\n\n";
    }
    if ( $files_link ) {
        $body .= "Lien fichiers : {$files_link}\n\n";
    }
    if ( $attachment ) {
        $body .= "Fichier joint : " . basename( $attachment ) . "\n";
    }

    $attachments = ( $attachment && file_exists( $attachment ) ) ? [ $attachment ] : [];

    $any_sent = false;

    foreach ( $rules as $rule ) {
        $recipients = $rule['recipients'] ?? [];
        $cc_list    = $rule['cc']         ?? [];
        $bcc_list   = $rule['bcc']        ?? [];

        if ( empty( $recipients ) ) {
            continue;
        }

        $headers = [ 'Reply-To: ' . $email ];
        foreach ( $cc_list as $cc ) {
            $headers[] = 'Cc: ' . $cc;
        }
        foreach ( $bcc_list as $bcc ) {
            $headers[] = 'Bcc: ' . $bcc;
        }

        foreach ( $recipients as $to ) {
            $sent = wp_mail( $to, $subject, $body, $headers, $attachments );
            md_log_notification( $submission_id, $to, $sent, $sent ? '' : 'wp_mail() returned false.' );
            if ( $sent ) {
                $any_sent = true;
            }
        }
    }

    // Clean up uploaded file after all sends
    if ( $attachment && file_exists( $attachment ) ) {
        wp_delete_file( $attachment );
        update_post_meta( $submission_id, '_md_sub_attachment', '' );
    }

    if ( ! $any_sent ) {
        md_update_submission_status( $submission_id, 'error' );
    }

    return $any_sent;
}
