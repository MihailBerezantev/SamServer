<?php
/**
 * Mango Dragon — Admin Panel: Emails & Demandes
 *
 * Registers a top-level WP admin menu with two sub-pages:
 *   1. Demandes reçues  (slug: md-email-admin)
 *   2. Configuration emails  (slug: md-email-routing)
 *
 * Also registers the admin-only AJAX handlers used by the JS panel.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ==========================================================================
// Menu registration
// ==========================================================================

function md_admin_panel_menu() {
    add_menu_page(
        'Emails & Demandes',
        'Emails & Demandes',
        'manage_options',
        'md-email-admin',
        'md_admin_page_submissions',
        'dashicons-email-alt2',
        30
    );
    add_submenu_page(
        'md-email-admin',
        'Demandes reçues',
        'Demandes reçues',
        'manage_options',
        'md-email-admin',
        'md_admin_page_submissions'
    );
    add_submenu_page(
        'md-email-admin',
        'Configuration emails',
        'Configuration emails',
        'manage_options',
        'md-email-routing',
        'md_admin_page_routing'
    );
}
add_action( 'admin_menu', 'md_admin_panel_menu' );

// ==========================================================================
// Enqueue admin assets
// ==========================================================================

function md_admin_panel_enqueue( $hook ) {
    $allowed = [ 'toplevel_page_md-email-admin', 'emails-demandes_page_md-email-routing' ];
    if ( ! in_array( $hook, $allowed, true ) ) {
        return;
    }
    wp_enqueue_style(
        'md-admin-panel',
        MD_URI . '/assets/css/admin-panel.css',
        [],
        MD_VERSION
    );
    wp_enqueue_script(
        'md-admin-panel',
        MD_URI . '/assets/js/admin-panel.js',
        [],
        MD_VERSION,
        true
    );
    wp_localize_script( 'md-admin-panel', 'mdAdminData', [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'md_admin_nonce' ),
    ] );
}
add_action( 'admin_enqueue_scripts', 'md_admin_panel_enqueue' );

// ==========================================================================
// Helper: status badge HTML
// ==========================================================================

function md_status_badge( $status ) {
    $map = md_submission_statuses();
    $label = $map[ $status ] ?? $status;
    return '<span class="md-badge md-badge--' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
}

// ==========================================================================
// Page: Demandes reçues
// ==========================================================================

function md_admin_page_submissions() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Accès refusé.' );
    }

    // Detail view?
    if ( isset( $_GET['view'] ) && $_GET['view'] === 'detail' && ! empty( $_GET['id'] ) ) {
        md_admin_view_submission_detail( (int) $_GET['id'] );
        return;
    }

    $statuses   = md_submission_statuses();
    $filter     = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';

    // Query submissions
    $args = [
        'post_type'      => 'md_submission',
        'posts_per_page' => 50,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'post_status'    => $filter && array_key_exists( $filter, $statuses ) ? $filter : array_keys( $statuses ),
    ];
    $query = new WP_Query( $args );
    ?>
    <div class="wrap md-admin-wrap">
        <h1 class="wp-heading-inline">Demandes reçues</h1>
        <hr class="wp-header-end">

        <?php md_admin_tabs( 'submissions' ); ?>

        <!-- Status filter -->
        <ul class="subsubsub">
            <li>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=md-email-admin' ) ); ?>"
                   class="<?php echo $filter === '' ? 'current' : ''; ?>">Toutes</a> |
            </li>
            <?php foreach ( $statuses as $slug => $label ) : ?>
            <li>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=md-email-admin&status=' . $slug ) ); ?>"
                   class="<?php echo $filter === $slug ? 'current' : ''; ?>">
                    <?php echo esc_html( $label ); ?>
                </a>
                <?php echo $slug !== array_key_last( $statuses ) ? ' |' : ''; ?>
            </li>
            <?php endforeach; ?>
        </ul>

        <?php if ( $query->have_posts() ) : ?>
        <table class="wp-list-table widefat fixed striped md-submissions-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Projet</th>
                    <th>Contact</th>
                    <th>Type</th>
                    <th>Statut</th>
                    <th>Notifications</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                <?php
                $id      = get_the_ID();
                $email   = get_post_meta( $id, '_md_sub_email', true );
                $type    = get_post_meta( $id, '_md_sub_type', true );
                $log     = get_post_meta( $id, '_md_sub_notify_log', true );
                $log     = is_array( $log ) ? $log : [];
                $sent_ct = count( array_filter( $log, fn( $e ) => ! empty( $e['sent'] ) ) );
                $err_ct  = count( array_filter( $log, fn( $e ) => empty( $e['sent'] ) ) );
                ?>
                <tr>
                    <td><?php echo esc_html( get_the_date( 'd/m/Y H:i' ) ); ?></td>
                    <td><strong><?php the_title(); ?></strong></td>
                    <td><?php echo esc_html( $email ); ?></td>
                    <td><?php echo esc_html( $type ); ?></td>
                    <td><?php echo md_status_badge( get_post_status( $id ) ); ?></td>
                    <td>
                        <?php if ( $sent_ct ) : ?>
                            <span class="md-log-ok">✓ <?php echo $sent_ct; ?> envoyé<?php echo $sent_ct > 1 ? 's' : ''; ?></span>
                        <?php endif; ?>
                        <?php if ( $err_ct ) : ?>
                            <span class="md-log-err">✗ <?php echo $err_ct; ?> erreur<?php echo $err_ct > 1 ? 's' : ''; ?></span>
                        <?php endif; ?>
                        <?php if ( ! $sent_ct && ! $err_ct ) echo '<span class="md-log-none">—</span>'; ?>
                    </td>
                    <td class="md-row-actions">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=md-email-admin&view=detail&id=' . $id ) ); ?>">Voir</a>
                        <button class="button button-small md-resend-btn" data-id="<?php echo $id; ?>">Renvoyer</button>
                        <select class="md-status-select" data-id="<?php echo $id; ?>">
                            <?php foreach ( $statuses as $slug => $label ) : ?>
                                <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( get_post_status( $id ), $slug ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            <?php endwhile; wp_reset_postdata(); ?>
            </tbody>
        </table>
        <?php else : ?>
        <p class="md-empty">Aucune demande reçue pour l'instant.</p>
        <?php endif; ?>
    </div>
    <?php
}

// ==========================================================================
// Detail view
// ==========================================================================

function md_admin_view_submission_detail( $id ) {
    $post = get_post( $id );
    if ( ! $post || $post->post_type !== 'md_submission' ) {
        echo '<div class="wrap"><p>Demande introuvable.</p></div>';
        return;
    }

    $statuses    = md_submission_statuses();
    $email       = get_post_meta( $id, '_md_sub_email', true );
    $description = get_post_meta( $id, '_md_sub_description', true );
    $files_link  = get_post_meta( $id, '_md_sub_files_link', true );
    $type        = get_post_meta( $id, '_md_sub_type', true );
    $log         = get_post_meta( $id, '_md_sub_notify_log', true );
    $log         = is_array( $log ) ? $log : [];
    $status      = get_post_status( $id );
    ?>
    <div class="wrap md-admin-wrap">
        <h1>
            Demande : <?php echo esc_html( $post->post_title ); ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=md-email-admin' ) ); ?>" class="page-title-action">← Retour</a>
        </h1>
        <?php md_admin_tabs( 'submissions' ); ?>

        <div class="md-detail-grid">
            <div class="md-detail-main">
                <table class="form-table md-detail-table">
                    <tr><th>Date</th><td><?php echo esc_html( get_the_date( 'd/m/Y H:i', $post ) ); ?></td></tr>
                    <tr><th>Contact (email)</th><td><?php echo esc_html( $email ); ?></td></tr>
                    <tr><th>Projet</th><td><?php echo esc_html( $post->post_title ); ?></td></tr>
                    <tr><th>Type</th><td><?php echo esc_html( $type ); ?></td></tr>
                    <tr><th>Statut</th><td><?php echo md_status_badge( $status ); ?></td></tr>
                    <?php if ( $description ) : ?>
                    <tr><th>Description</th><td><?php echo nl2br( esc_html( $description ) ); ?></td></tr>
                    <?php endif; ?>
                    <?php if ( $files_link ) : ?>
                    <tr><th>Lien fichiers</th><td><a href="<?php echo esc_url( $files_link ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $files_link ); ?></a></td></tr>
                    <?php endif; ?>
                </table>
            </div>

            <div class="md-detail-sidebar">
                <div class="md-card">
                    <h3>Changer le statut</h3>
                    <select class="md-status-select" data-id="<?php echo $id; ?>">
                        <?php foreach ( $statuses as $slug => $label ) : ?>
                            <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $status, $slug ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="md-card">
                    <h3>Notification</h3>
                    <button class="button button-primary md-resend-btn" data-id="<?php echo $id; ?>">Renvoyer la notification</button>
                    <p class="md-resend-result" id="resend-result-<?php echo $id; ?>"></p>
                </div>
            </div>
        </div>

        <!-- Notification log -->
        <h2>Journal des notifications</h2>
        <?php if ( ! empty( $log ) ) : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Destinataire</th>
                    <th>Résultat</th>
                    <th>Erreur</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( array_reverse( $log ) as $entry ) : ?>
                <tr>
                    <td><?php echo esc_html( $entry['timestamp'] ?? '' ); ?></td>
                    <td><?php echo esc_html( $entry['recipient'] ?? '' ); ?></td>
                    <td>
                        <?php if ( ! empty( $entry['sent'] ) ) : ?>
                            <span class="md-log-ok">✓ Envoyé</span>
                        <?php else : ?>
                            <span class="md-log-err">✗ Erreur</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html( $entry['error'] ?? '' ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else : ?>
        <p class="md-empty">Aucune notification envoyée pour l'instant.</p>
        <?php endif; ?>
    </div>
    <?php
}

// ==========================================================================
// Page: Configuration emails
// ==========================================================================

function md_admin_page_routing() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Accès refusé.' );
    }

    $rules = md_get_routing();

    // Detect rules without active recipients
    $empty_rules = array_filter( $rules, fn( $r ) => ! empty( $r['enabled'] ) && empty( $r['recipients'] ) );
    ?>
    <div class="wrap md-admin-wrap">
        <h1 class="wp-heading-inline">Configuration emails</h1>
        <hr class="wp-header-end">

        <?php md_admin_tabs( 'routing' ); ?>

        <?php if ( ! empty( $empty_rules ) ) : ?>
        <div class="notice notice-warning">
            <p><strong>Attention :</strong> <?php echo count( $empty_rules ); ?> règle(s) active(s) n'ont aucun destinataire configuré. Les demandes correspondantes ne seront pas envoyées par email.</p>
        </div>
        <?php endif; ?>

        <table class="wp-list-table widefat fixed striped md-routing-table">
            <thead>
                <tr>
                    <th>Libellé</th>
                    <th>Type</th>
                    <th>Destinataires</th>
                    <th>CC / BCC</th>
                    <th>Statut</th>
                    <th>Modifié le</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="md-routing-list">
            <?php if ( empty( $rules ) ) : ?>
                <tr id="md-no-rules"><td colspan="7" class="md-empty">Aucune règle configurée.</td></tr>
            <?php endif; ?>
            <?php foreach ( $rules as $rule ) :
                $rid       = esc_attr( $rule['id'] );
                $enabled   = ! empty( $rule['enabled'] );
                $recips    = implode( ', ', $rule['recipients'] ?? [] );
                $cc_str    = implode( ', ', $rule['cc'] ?? [] );
                $bcc_str   = implode( ', ', $rule['bcc'] ?? [] );
                $cc_bcc    = array_filter( [ $cc_str ? 'CC: ' . $cc_str : '', $bcc_str ? 'BCC: ' . $bcc_str : '' ] );
                $updated   = $rule['updated_at'] ? date_i18n( 'd/m/Y H:i', strtotime( $rule['updated_at'] ) ) : '—';
            ?>
            <tr data-rule-id="<?php echo $rid; ?>">
                <td><strong><?php echo esc_html( $rule['label'] ?? '' ); ?></strong></td>
                <td><code><?php echo esc_html( $rule['request_type'] ?? '' ); ?></code></td>
                <td class="md-recipients"><?php echo esc_html( $recips ?: '—' ); ?></td>
                <td><?php echo esc_html( implode( ' · ', $cc_bcc ) ?: '—' ); ?></td>
                <td>
                    <button class="button button-small md-toggle-rule" data-id="<?php echo $rid; ?>" data-enabled="<?php echo $enabled ? '1' : '0'; ?>">
                        <?php echo $enabled ? 'Actif' : 'Inactif'; ?>
                    </button>
                </td>
                <td><?php echo esc_html( $updated ); ?></td>
                <td class="md-row-actions">
                    <button class="button button-small md-edit-rule-btn" data-id="<?php echo $rid; ?>">Modifier</button>
                    <button class="button button-small md-test-rule" data-id="<?php echo $rid; ?>">Tester</button>
                    <button class="button button-small md-delete-rule" data-id="<?php echo $rid; ?>">Supprimer</button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <p>
            <button class="button button-primary" id="md-add-rule-btn">+ Ajouter une règle</button>
        </p>

        <!-- Add / Edit form (hidden by default) -->
        <div id="md-rule-form-wrap" class="md-card" style="display:none;">
            <h2 id="md-rule-form-title">Nouvelle règle</h2>
            <input type="hidden" id="md-rule-id" value="">
            <table class="form-table">
                <tr>
                    <th><label for="md-rule-label">Libellé</label></th>
                    <td><input type="text" id="md-rule-label" class="regular-text" placeholder="Ex: Envoi de démo"></td>
                </tr>
                <tr>
                    <th><label for="md-rule-type">Type de demande</label></th>
                    <td>
                        <select id="md-rule-type">
                            <option value="demo_submission">Envoi de démo</option>
                            <option value="contact_general">Contact général</option>
                            <option value="quote_request">Demande de devis</option>
                            <option value="support_request">Support</option>
                            <option value="other">Autre</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="md-rule-recipients">Destinataires <small>(un par ligne)</small></label></th>
                    <td><textarea id="md-rule-recipients" rows="3" class="regular-text" placeholder="email@exemple.com"></textarea></td>
                </tr>
                <tr>
                    <th><label for="md-rule-cc">CC <small>(un par ligne)</small></label></th>
                    <td><textarea id="md-rule-cc" rows="2" class="regular-text"></textarea></td>
                </tr>
                <tr>
                    <th><label for="md-rule-bcc">BCC <small>(un par ligne)</small></label></th>
                    <td><textarea id="md-rule-bcc" rows="2" class="regular-text"></textarea></td>
                </tr>
                <tr>
                    <th><label for="md-rule-enabled">Activer</label></th>
                    <td><input type="checkbox" id="md-rule-enabled" checked></td>
                </tr>
            </table>
            <p>
                <button class="button button-primary" id="md-rule-save-btn">Enregistrer</button>
                <button class="button" id="md-rule-cancel-btn">Annuler</button>
                <span class="md-form-result" id="md-rule-form-result"></span>
            </p>
        </div>
    </div>
    <?php
}

// ==========================================================================
// Tabs helper
// ==========================================================================

function md_admin_tabs( $active ) {
    ?>
    <nav class="nav-tab-wrapper md-nav-tabs">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=md-email-admin' ) ); ?>"
           class="nav-tab <?php echo $active === 'submissions' ? 'nav-tab-active' : ''; ?>">Demandes reçues</a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=md-email-routing' ) ); ?>"
           class="nav-tab <?php echo $active === 'routing' ? 'nav-tab-active' : ''; ?>">Configuration emails</a>
    </nav>
    <?php
}

// ==========================================================================
// Admin AJAX: update submission status
// ==========================================================================

function md_ajax_update_submission_status() {
    check_ajax_referer( 'md_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Permission refusée.' ] );
    }

    $id     = (int) ( $_POST['id'] ?? 0 );
    $status = sanitize_key( $_POST['status'] ?? '' );

    if ( ! $id || ! $status ) {
        wp_send_json_error( [ 'message' => 'Paramètres manquants.' ] );
    }

    $ok = md_update_submission_status( $id, $status );
    $ok ? wp_send_json_success() : wp_send_json_error( [ 'message' => 'Mise à jour échouée.' ] );
}
add_action( 'wp_ajax_md_update_submission_status', 'md_ajax_update_submission_status' );

// ==========================================================================
// Admin AJAX: resend notification
// ==========================================================================

function md_ajax_resend_notification() {
    check_ajax_referer( 'md_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Permission refusée.' ] );
    }

    $id = (int) ( $_POST['id'] ?? 0 );
    if ( ! $id ) {
        wp_send_json_error( [ 'message' => 'ID manquant.' ] );
    }

    $sent = md_dispatch_email( $id );
    $sent
        ? wp_send_json_success( [ 'message' => 'Notification renvoyée avec succès.' ] )
        : wp_send_json_error( [ 'message' => 'Erreur lors de l\'envoi. Vérifiez la configuration des destinataires.' ] );
}
add_action( 'wp_ajax_md_resend_notification', 'md_ajax_resend_notification' );

// ==========================================================================
// Admin AJAX: send test email
// ==========================================================================

function md_ajax_send_test_email() {
    check_ajax_referer( 'md_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Permission refusée.' ] );
    }

    $rule_id = sanitize_key( $_POST['rule_id'] ?? '' );
    if ( ! $rule_id ) {
        wp_send_json_error( [ 'message' => 'ID de règle manquant.' ] );
    }

    $rules = md_get_routing();
    $rule  = null;
    foreach ( $rules as $r ) {
        if ( ( $r['id'] ?? '' ) === $rule_id ) {
            $rule = $r;
            break;
        }
    }

    if ( ! $rule ) {
        wp_send_json_error( [ 'message' => 'Règle introuvable.' ] );
    }

    $recipients = $rule['recipients'] ?? [];
    if ( empty( $recipients ) ) {
        wp_send_json_error( [ 'message' => 'Aucun destinataire configuré pour cette règle.' ] );
    }

    $subject = '[Mango Dragon] Test — ' . ( $rule['label'] ?? $rule_id );
    $body    = "Ceci est un email de test envoyé depuis le panel admin de Mango Dragon International.\n\n"
             . "Règle : " . ( $rule['label'] ?? $rule_id ) . "\n"
             . "Type : " . ( $rule['request_type'] ?? '' ) . "\n"
             . "Destinataires : " . implode( ', ', $recipients ) . "\n";

    $headers = [];
    foreach ( $rule['cc'] ?? [] as $cc ) {
        $headers[] = 'Cc: ' . $cc;
    }

    $errors = [];
    foreach ( $recipients as $to ) {
        $sent = wp_mail( $to, $subject, $body, $headers );
        if ( ! $sent ) {
            $errors[] = $to;
        }
    }

    if ( empty( $errors ) ) {
        wp_send_json_success( [ 'message' => 'Email de test envoyé à : ' . implode( ', ', $recipients ) ] );
    } else {
        wp_send_json_error( [ 'message' => 'Erreur pour : ' . implode( ', ', $errors ) ] );
    }
}
add_action( 'wp_ajax_md_send_test_email', 'md_ajax_send_test_email' );

// ==========================================================================
// Admin AJAX: save routing rule (add or update)
// ==========================================================================

function md_ajax_save_routing_rule() {
    check_ajax_referer( 'md_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Permission refusée.' ] );
    }

    $raw = [
        'id'           => sanitize_key( $_POST['id'] ?? '' ),
        'request_type' => sanitize_key( $_POST['request_type'] ?? 'demo_submission' ),
        'label'        => sanitize_text_field( $_POST['label'] ?? '' ),
        'recipients'   => explode( "\n", $_POST['recipients'] ?? '' ),
        'cc'           => explode( "\n", $_POST['cc'] ?? '' ),
        'bcc'          => explode( "\n", $_POST['bcc'] ?? '' ),
        'enabled'      => ! empty( $_POST['enabled'] ),
    ];

    $rule = md_sanitize_routing_rule( $raw );

    if ( empty( $rule['label'] ) ) {
        wp_send_json_error( [ 'message' => 'Le libellé est obligatoire.' ] );
    }
    if ( empty( $rule['recipients'] ) ) {
        wp_send_json_error( [ 'message' => 'Au moins un destinataire valide est requis.' ] );
    }

    $rules   = md_get_routing();
    $updated = false;

    foreach ( $rules as &$r ) {
        if ( ( $r['id'] ?? '' ) === $rule['id'] ) {
            $r       = $rule;
            $updated = true;
            break;
        }
    }
    unset( $r );

    if ( ! $updated ) {
        $rules[] = $rule;
    }

    md_save_routing( $rules );
    wp_send_json_success( [ 'rule' => $rule ] );
}
add_action( 'wp_ajax_md_save_routing_rule', 'md_ajax_save_routing_rule' );

// ==========================================================================
// Admin AJAX: delete routing rule
// ==========================================================================

function md_ajax_delete_routing_rule() {
    check_ajax_referer( 'md_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Permission refusée.' ] );
    }

    $rule_id = sanitize_key( $_POST['rule_id'] ?? '' );
    if ( ! $rule_id ) {
        wp_send_json_error( [ 'message' => 'ID manquant.' ] );
    }

    $rules = md_get_routing();
    $rules = array_values( array_filter( $rules, fn( $r ) => ( $r['id'] ?? '' ) !== $rule_id ) );
    md_save_routing( $rules );

    wp_send_json_success();
}
add_action( 'wp_ajax_md_delete_routing_rule', 'md_ajax_delete_routing_rule' );

// ==========================================================================
// Admin AJAX: toggle routing rule enabled/disabled
// ==========================================================================

function md_ajax_toggle_routing_rule() {
    check_ajax_referer( 'md_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Permission refusée.' ] );
    }

    $rule_id = sanitize_key( $_POST['rule_id'] ?? '' );
    if ( ! $rule_id ) {
        wp_send_json_error( [ 'message' => 'ID manquant.' ] );
    }

    $rules   = md_get_routing();
    $new_val = null;

    foreach ( $rules as &$r ) {
        if ( ( $r['id'] ?? '' ) === $rule_id ) {
            $r['enabled'] = ! $r['enabled'];
            $r['updated_at'] = gmdate( 'c' );
            $new_val = $r['enabled'];
            break;
        }
    }
    unset( $r );

    if ( $new_val === null ) {
        wp_send_json_error( [ 'message' => 'Règle introuvable.' ] );
    }

    md_save_routing( $rules );
    wp_send_json_success( [ 'enabled' => $new_val ] );
}
add_action( 'wp_ajax_md_toggle_routing_rule', 'md_ajax_toggle_routing_rule' );
