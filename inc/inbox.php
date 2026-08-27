<?php
/**
 * Mango Dragon — Boîte de réception (wp-admin > E-mails).
 *
 * Consultation des demandes reçues via les formulaires (contact / studio) et de
 * leurs pièces jointes. Interface d'ADMINISTRATION → libellés en FRANÇAIS.
 *
 * - Liste + fiche détaillée (contenu nettoyé, images distantes non chargées).
 * - Téléchargement / aperçu via un ENDPOINT SÉCURISÉ (droits + nonce).
 * - Statuts de traitement + historique.
 * - Recherche et filtres simples.
 * - Panneau « Stockage » (disque, seuils 80/90 %).
 *
 * Périmètre adapté au site (label) : pas de clients/sociétés/employés/paie ni MongoDB.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const MD_INBOX_CAP = 'manage_options'; // droit de base (vue)

/**
 * Permissions spécifiques (spec §9). Adaptées : un seul label, pas de multi-tenant.
 * Les administrateurs les reçoivent automatiquement ; on peut les attribuer finement
 * à d'autres rôles (voir/ télécharger / statut / supprimer).
 */
function md_email_caps() {
    return [ 'md_email_view', 'md_email_download', 'md_email_status', 'md_email_delete' ];
}
add_action( 'init', function () {
    $role = get_role( 'administrator' );
    if ( $role ) {
        foreach ( md_email_caps() as $c ) {
            if ( ! $role->has_cap( $c ) ) { $role->add_cap( $c ); } // écrit une seule fois
        }
    }
}, 1 );

/** Vrai si l'utilisateur a la capacité demandée (ou est administrateur). */
function md_can( $cap ) {
    return current_user_can( $cap ) || current_user_can( 'manage_options' );
}

/* ==========================================================================
   Menu
   ========================================================================== */
add_action( 'admin_menu', function () {
    add_menu_page( 'E-mails', 'E-mails', MD_INBOX_CAP, 'md-inbox', 'md_inbox_page', 'dashicons-email-alt', 26 );
    add_submenu_page( 'md-inbox', 'Boîte de réception', 'Boîte de réception', MD_INBOX_CAP, 'md-inbox', 'md_inbox_page' );
    add_submenu_page( 'md-inbox', 'Stockage', 'Stockage', MD_INBOX_CAP, 'md-inbox-storage', 'md_inbox_storage_page' );
} );

/* ==========================================================================
   Helpers
   ========================================================================== */
function md_inbox_all_statuses() { return array_keys( md_submission_statuses() ); }

function md_inbox_get_attachments( $id ) {
    $a = get_post_meta( $id, '_md_sub_attachments', true );
    return is_array( $a ) ? $a : [];
}

function md_inbox_total_size( $atts ) {
    $t = 0;
    foreach ( $atts as $a ) { $t += (int) ( $a['size'] ?? 0 ); }
    return $t;
}

/** URL sécurisée vers l'endpoint fichier (téléchargement ou aperçu). */
function md_inbox_file_url( $sub_id, $idx, $mode = 'download' ) {
    return wp_nonce_url(
        admin_url( 'admin-post.php?action=md_inbox_file&id=' . (int) $sub_id . '&idx=' . (int) $idx . '&mode=' . $mode ),
        'md_inbox_file_' . (int) $sub_id . '_' . (int) $idx
    );
}

/* ==========================================================================
   Endpoint sécurisé : téléchargement / aperçu d'une pièce jointe
   ========================================================================== */
add_action( 'admin_post_md_inbox_file', 'md_inbox_serve_file' );
function md_inbox_serve_file() {
    $id  = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
    $idx = isset( $_GET['idx'] ) ? (int) $_GET['idx'] : 0;
    $mode = ( isset( $_GET['mode'] ) && $_GET['mode'] === 'preview' ) ? 'preview' : 'download';

    // Droits + nonce
    if ( ! md_can( 'md_email_download' ) ) { wp_die( 'Accès refusé.', 403 ); }
    check_admin_referer( 'md_inbox_file_' . $id . '_' . $idx );

    $sub = get_post( $id );
    if ( ! $sub || $sub->post_type !== 'md_submission' ) { wp_die( 'Introuvable.', 404 ); }

    $atts = md_inbox_get_attachments( $id );
    if ( ! isset( $atts[ $idx ] ) ) { wp_die( 'Pièce jointe introuvable.', 404 ); }
    $rec = $atts[ $idx ];

    // Les fichiers kDrive ne sont pas sur ce serveur → renvoyer vers kDrive.
    if ( ( $rec['kind'] ?? '' ) === 'kdrive' ) {
        wp_safe_redirect( 'https://kdrive.infomaniak.com/app/drive/2836181/' );
        exit;
    }

    // Chemin résolu depuis une source fiable (stockée), pas depuis une entrée utilisateur.
    if ( ( $rec['kind'] ?? '' ) === 'private' ) {
        $path = md_private_path( $rec['path'] ?? '' );
    } else {
        $path = ( ! empty( $rec['media_id'] ) ) ? get_attached_file( (int) $rec['media_id'] ) : ''; // legacy Médiathèque
    }
    // Anti-traversal : le fichier DOIT être sous le dossier uploads.
    $uploads = wp_get_upload_dir();
    $real    = $path ? realpath( $path ) : false;
    $base    = realpath( $uploads['basedir'] );
    if ( ! $real || ! $base || strpos( $real, $base ) !== 0 || ! is_file( $real ) ) {
        wp_die( 'Fichier indisponible sur le serveur.', 404 );
    }

    // Journalisation du téléchargement.
    md_add_submission_history( $id, get_post_status( $id ), get_post_status( $id ),
        ( $mode === 'preview' ? 'Aperçu' : 'Téléchargement' ) . ' : ' . ( $rec['orig'] ?? basename( $real ) ) );

    $mime = $rec['mime'] ?? ( function_exists( 'mime_content_type' ) ? mime_content_type( $real ) : 'application/octet-stream' );
    $name = $rec['orig'] ?: basename( $real );

    // Aperçu inline seulement pour des types sûrs ; sinon téléchargement forcé.
    $inline_ok = in_array( $mime, [ 'application/pdf', 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'text/plain' ], true );
    $disp      = ( $mode === 'preview' && $inline_ok ) ? 'inline' : 'attachment';

    nocache_headers();
    header( 'Content-Type: ' . $mime );
    header( 'Content-Disposition: ' . $disp . '; filename="' . rawurlencode( $name ) . '"' );
    header( 'Content-Length: ' . filesize( $real ) );
    header( 'X-Content-Type-Options: nosniff' );
    if ( ob_get_level() ) { ob_end_clean(); }
    readfile( $real );
    exit;
}

/* ==========================================================================
   Page : Boîte de réception (liste + fiche)
   ========================================================================== */
function md_inbox_page() {
    if ( ! current_user_can( MD_INBOX_CAP ) ) { wp_die( 'Accès refusé.' ); }

    // Suppression d'une demande (POST) — droit md_email_delete.
    if ( isset( $_POST['md_inbox_delete'], $_POST['sub_id'] ) && check_admin_referer( 'md_inbox_delete' ) && md_can( 'md_email_delete' ) ) {
        $sid = (int) $_POST['sub_id'];
        foreach ( md_inbox_get_attachments( $sid ) as $a ) {
            if ( ( $a['kind'] ?? '' ) === 'private' && ! empty( $a['path'] ) ) {
                // Ne supprime le fichier que s'il n'est PAS partagé (dédupliqué) avec une autre demande.
                if ( ! md_path_referenced( $a['path'], $sid ) ) {
                    $p = md_private_path( $a['path'] );
                    if ( file_exists( $p ) ) { wp_delete_file( $p ); }
                }
            }
        }
        wp_delete_post( $sid, true );
        echo '<div class="wrap"><div class="notice notice-success"><p>Demande supprimée.</p></div>'
            . '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=md-inbox' ) ) . '">← Retour à la liste</a></p></div>';
        return;
    }

    // Changement de statut (POST) — droit md_email_status.
    if ( isset( $_POST['md_inbox_set_status'], $_POST['sub_id'] ) && check_admin_referer( 'md_inbox_status' ) && md_can( 'md_email_status' ) ) {
        $sid  = (int) $_POST['sub_id'];
        $new  = sanitize_key( $_POST['new_status'] ?? '' );
        $note = sanitize_text_field( $_POST['status_comment'] ?? '' );
        $old  = get_post_status( $sid );
        if ( array_key_exists( $new, md_submission_statuses() ) && md_update_submission_status( $sid, $new ) ) {
            md_add_submission_history( $sid, $old, $new, $note );
            echo '<div class="notice notice-success is-dismissible"><p>Statut mis à jour.</p></div>';
        }
    }

    $view = ( isset( $_GET['view'] ) && $_GET['view'] === 'detail' ) ? 'detail' : 'list';
    if ( $view === 'detail' ) {
        md_inbox_detail( (int) ( $_GET['id'] ?? 0 ) );
    } else {
        md_inbox_list();
    }
}

function md_inbox_list() {
    $statuses = md_submission_statuses();

    // Filtres
    $f_status = isset( $_GET['fstatus'] ) ? sanitize_key( $_GET['fstatus'] ) : '';
    $f_read   = isset( $_GET['fread'] ) ? sanitize_key( $_GET['fread'] ) : '';   // '', 'read', 'unread'
    $f_att    = isset( $_GET['fatt'] ) ? sanitize_key( $_GET['fatt'] ) : '';     // '', 'yes', 'no'
    $search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
    $paged    = max( 1, (int) ( $_GET['paged'] ?? 1 ) );

    $args = [
        'post_type'      => 'md_submission',
        'post_status'    => $f_status && isset( $statuses[ $f_status ] ) ? [ $f_status ] : md_inbox_all_statuses(),
        'posts_per_page' => 25,
        'paged'          => $paged,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];
    // Recherche sur le haystack (expéditeur + objet + message + noms de fichiers).
    if ( $search !== '' ) {
        $args['meta_query'] = [ [ 'key' => '_md_sub_search', 'value' => strtolower( $search ), 'compare' => 'LIKE' ] ];
    }
    $q = new WP_Query( $args );
    ?>
    <div class="wrap">
        <h1>Boîte de réception</h1>
        <p>Demandes reçues via les formulaires du site (contact / studio) et leurs pièces jointes.</p>

        <form method="get" style="margin:12px 0;">
            <input type="hidden" name="page" value="md-inbox">
            <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Rechercher (objet, expéditeur, message, fichier…)" class="regular-text">
            <select name="fstatus">
                <option value="">Tous les statuts</option>
                <?php foreach ( $statuses as $k => $lbl ) : ?>
                    <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $f_status, $k ); ?>><?php echo esc_html( $lbl ); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="fread">
                <option value="">Lu / non lu</option>
                <option value="unread" <?php selected( $f_read, 'unread' ); ?>>Non lu</option>
                <option value="read" <?php selected( $f_read, 'read' ); ?>>Lu</option>
            </select>
            <select name="fatt">
                <option value="">Pièces jointes</option>
                <option value="yes" <?php selected( $f_att, 'yes' ); ?>>Avec pièces jointes</option>
                <option value="no" <?php selected( $f_att, 'no' ); ?>>Sans pièce jointe</option>
            </select>
            <button class="button">Filtrer</button>
        </form>

        <table class="widefat striped">
            <thead><tr>
                <th>Expéditeur</th><th>Objet</th><th>Reçu le</th><th>Lecture</th><th>Statut</th><th>P.J.</th><th>Taille</th><th></th>
            </tr></thead>
            <tbody>
            <?php
            if ( ! $q->have_posts() ) {
                echo '<tr><td colspan="8"><em>Aucune demande.</em></td></tr>';
            }
            while ( $q->have_posts() ) : $q->the_post();
                $id     = get_the_ID();
                $email  = get_post_meta( $id, '_md_sub_email', true );
                $read   = (int) get_post_meta( $id, '_md_sub_read', true );
                $atts   = md_inbox_get_attachments( $id );
                $nb     = count( $atts );
                $status = get_post_status( $id );

                // Filtres client-side (lecture / P.J.) — appliqués après la requête.
                if ( $f_read === 'read' && ! $read ) { continue; }
                if ( $f_read === 'unread' && $read ) { continue; }
                if ( $f_att === 'yes' && $nb === 0 ) { continue; }
                if ( $f_att === 'no' && $nb > 0 ) { continue; }

                $url = admin_url( 'admin.php?page=md-inbox&view=detail&id=' . $id );
                ?>
                <tr style="<?php echo $read ? '' : 'font-weight:600;'; ?>">
                    <td><?php echo esc_html( $email ?: '—' ); ?></td>
                    <td><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( get_the_title() ?: '(sans objet)' ); ?></a></td>
                    <td><?php echo esc_html( get_the_date( 'Y-m-d H:i' ) ); ?></td>
                    <td><?php echo $read ? 'Lu' : '<span style="color:#c00">Non lu</span>'; ?></td>
                    <td><?php echo esc_html( $statuses[ $status ] ?? $status ); ?></td>
                    <td><?php echo $nb ? '📎 ' . intval( $nb ) : '—'; ?></td>
                    <td><?php echo $nb ? esc_html( size_format( md_inbox_total_size( $atts ) ) ) : '—'; ?></td>
                    <td><a class="button button-small" href="<?php echo esc_url( $url ); ?>">Ouvrir</a></td>
                </tr>
            <?php endwhile; wp_reset_postdata(); ?>
            </tbody>
        </table>

        <?php
        $total_pages = (int) $q->max_num_pages;
        if ( $total_pages > 1 ) {
            echo '<p>' . paginate_links( [
                'base'    => add_query_arg( 'paged', '%#%' ),
                'format'  => '',
                'current' => $paged,
                'total'   => $total_pages,
            ] ) . '</p>';
        }
        ?>
    </div>
    <?php
}

function md_inbox_detail( $id ) {
    $sub = get_post( $id );
    if ( ! $sub || $sub->post_type !== 'md_submission' ) {
        echo '<div class="wrap"><h1>Boîte de réception</h1><p>Demande introuvable.</p></div>';
        return;
    }

    // Marquer comme lu à l'ouverture.
    if ( ! (int) get_post_meta( $id, '_md_sub_read', true ) ) {
        md_set_submission_read( $id, true );
    }

    $statuses = md_submission_statuses();
    $email    = get_post_meta( $id, '_md_sub_email', true );
    $desc     = get_post_meta( $id, '_md_sub_description', true );
    $link     = get_post_meta( $id, '_md_sub_files_link', true );
    $atts     = md_inbox_get_attachments( $id );
    $hist     = get_post_meta( $id, '_md_sub_history', true );
    $hist     = is_array( $hist ) ? $hist : [];
    $status   = get_post_status( $id );
    $back     = admin_url( 'admin.php?page=md-inbox' );
    ?>
    <div class="wrap">
        <a href="<?php echo esc_url( $back ); ?>" class="button">← Retour à la liste</a>
        <h1><?php echo esc_html( get_the_title( $id ) ?: '(sans objet)' ); ?></h1>

        <table class="widefat" style="max-width:900px;margin-bottom:16px;">
            <tbody>
                <tr><th style="width:180px;">Expéditeur</th><td><?php echo esc_html( $email ?: '—' ); ?> <?php if ( $email ) : ?>(<a href="mailto:<?php echo esc_attr( $email ); ?>">répondre</a>)<?php endif; ?></td></tr>
                <tr><th>Destinataire</th><td>contact@mango-dragon.com</td></tr>
                <tr><th>Reçu le</th><td><?php echo esc_html( get_the_date( 'Y-m-d H:i', $id ) ); ?></td></tr>
                <tr><th>Statut</th><td><?php echo esc_html( $statuses[ $status ] ?? $status ); ?></td></tr>
                <?php if ( $link ) : ?><tr><th>Lien fichiers</th><td><a href="<?php echo esc_url( $link ); ?>" target="_blank" rel="noopener noreferrer nofollow"><?php echo esc_html( $link ); ?></a></td></tr><?php endif; ?>
            </tbody>
        </table>

        <h2>Message</h2>
        <div style="max-width:900px;padding:12px;background:#fff;border:1px solid #ccd0d4;white-space:pre-wrap;">
            <?php
            // Contenu texte du formulaire → échappé (aucun HTML ni image distante n'est exécuté).
            echo $desc ? esc_html( $desc ) : '<em>(aucun message)</em>';
            ?>
        </div>

        <h2>Pièces jointes (<?php echo count( $atts ); ?>)</h2>
        <?php if ( empty( $atts ) ) : ?>
            <p><em>Aucune pièce jointe.</em></p>
        <?php else : ?>
            <table class="widefat striped" style="max-width:1000px;">
                <thead><tr><th>Nom</th><th>Type</th><th>Taille</th><th>Emplacement</th><th>Empreinte (SHA-256)</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ( $atts as $i => $a ) :
                    $kind = $a['kind'] ?? 'private';
                    if ( $kind === 'private' ) {
                        $avail = ! empty( $a['path'] ) && file_exists( md_private_path( $a['path'] ) );
                    } elseif ( $kind === 'media' ) {
                        $mp    = ! empty( $a['media_id'] ) ? get_attached_file( (int) $a['media_id'] ) : '';
                        $avail = $mp && file_exists( $mp );
                    } else {
                        $avail = false; // kdrive : fichier sur kDrive, pas sur ce serveur
                    }
                    ?>
                    <tr>
                        <td><?php echo esc_html( $a['orig'] ?: ( $a['stored'] ?? '—' ) ); ?></td>
                        <td><?php echo esc_html( $a['mime'] ?: '—' ); ?></td>
                        <td><?php echo esc_html( size_format( (int) ( $a['size'] ?? 0 ) ) ); ?></td>
                        <td><?php
                            if ( $kind === 'kdrive' ) { echo 'kDrive (' . esc_html( $a['kdrive_folder'] ?? '' ) . ')'; }
                            elseif ( $avail ) { echo $kind === 'private' ? 'Serveur (privé)' : 'Serveur'; }
                            else { echo '<span style="color:#c00">Manquant</span>'; }
                        ?></td>
                        <td><code style="font-size:11px;"><?php echo esc_html( substr( (string) ( $a['hash'] ?? '' ), 0, 16 ) ); ?>…</code></td>
                        <td>
                            <?php if ( $kind === 'kdrive' ) : ?>
                                <a class="button button-small" href="<?php echo esc_url( md_inbox_file_url( $id, $i, 'download' ) ); ?>">Ouvrir kDrive</a>
                            <?php elseif ( $avail ) : ?>
                                <a class="button button-small" href="<?php echo esc_url( md_inbox_file_url( $id, $i, 'preview' ) ); ?>" target="_blank" rel="noopener">Aperçu</a>
                                <a class="button button-small" href="<?php echo esc_url( md_inbox_file_url( $id, $i, 'download' ) ); ?>">Télécharger</a>
                            <?php else : ?>
                                <em>indisponible</em>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h2>Traitement</h2>
        <form method="post" style="margin-bottom:16px;">
            <?php wp_nonce_field( 'md_inbox_status' ); ?>
            <input type="hidden" name="sub_id" value="<?php echo (int) $id; ?>">
            <select name="new_status">
                <?php foreach ( $statuses as $k => $lbl ) : ?>
                    <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $status, $k ); ?>><?php echo esc_html( $lbl ); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="status_comment" placeholder="Commentaire (facultatif)" class="regular-text">
            <button class="button button-primary" name="md_inbox_set_status" value="1">Changer le statut</button>
        </form>

        <?php if ( md_can( 'md_email_delete' ) ) : ?>
        <form method="post" onsubmit="return confirm('Supprimer définitivement cette demande et ses fichiers ?');" style="margin-bottom:16px;">
            <?php wp_nonce_field( 'md_inbox_delete' ); ?>
            <input type="hidden" name="sub_id" value="<?php echo (int) $id; ?>">
            <button class="button button-link-delete" name="md_inbox_delete" value="1">Supprimer la demande</button>
        </form>
        <?php endif; ?>

        <h3>Historique</h3>
        <table class="widefat striped" style="max-width:1000px;">
            <thead><tr><th>Date</th><th>Utilisateur</th><th>Ancien</th><th>Nouveau</th><th>Commentaire</th></tr></thead>
            <tbody>
            <?php foreach ( array_reverse( $hist ) as $h ) :
                $u = ! empty( $h['user'] ) ? get_userdata( $h['user'] ) : null; ?>
                <tr>
                    <td><?php echo esc_html( $h['date'] ?? '' ); ?></td>
                    <td><?php echo esc_html( $u ? $u->display_name : '—' ); ?></td>
                    <td><?php echo esc_html( $statuses[ $h['from'] ?? '' ] ?? ( $h['from'] ?? '' ) ); ?></td>
                    <td><?php echo esc_html( $statuses[ $h['to'] ?? '' ] ?? ( $h['to'] ?? '' ) ); ?></td>
                    <td><?php echo esc_html( $h['comment'] ?? '' ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/* ==========================================================================
   Page : Stockage
   ========================================================================== */
function md_inbox_storage_page() {
    if ( ! current_user_can( MD_INBOX_CAP ) ) { wp_die( 'Accès refusé.' ); }

    // Quota compte (configurable) — disk_total_space renvoie le mount physique, pas le quota Infomaniak (~250 Go).
    if ( isset( $_POST['md_save_quota'] ) && check_admin_referer( 'md_storage_quota' ) ) {
        update_option( 'md_storage_quota_gb', max( 1, (int) $_POST['quota_gb'] ) );
        echo '<div class="notice notice-success is-dismissible"><p>Quota mis à jour.</p></div>';
    }
    $quota_gb    = (int) get_option( 'md_storage_quota_gb', 250 );
    $quota_bytes = $quota_gb * 1024 * 1024 * 1024;

    $uploads = wp_get_upload_dir();
    $dir     = $uploads['basedir'];

    $total = @disk_total_space( $dir );
    $free  = @disk_free_space( $dir );
    $used  = ( $total && $free ) ? ( $total - $free ) : 0;
    $pct   = $total ? round( $used / $total * 100, 1 ) : 0;

    // Taille du dossier uploads (mise en cache 1 h — calcul récursif potentiellement long).
    $stats = get_transient( 'md_inbox_uploads_stats' );
    if ( false === $stats ) {
        $size = 0; $count = 0; $biggest = [];
        $it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
        foreach ( $it as $f ) {
            if ( $f->isFile() ) {
                $s = $f->getSize();
                $size += $s; $count++;
                $biggest[] = [ 'p' => str_replace( $dir, '', $f->getPathname() ), 's' => $s ];
            }
        }
        usort( $biggest, function ( $a, $b ) { return $b['s'] <=> $a['s']; } );
        $stats = [ 'size' => $size, 'count' => $count, 'biggest' => array_slice( $biggest, 0, 8 ) ];
        set_transient( 'md_inbox_uploads_stats', $stats, HOUR_IN_SECONDS );
    }

    $warn = $pct >= 90 ? 'notice-error' : ( $pct >= 80 ? 'notice-warning' : 'notice-success' );

    // Usage vs quota compte (métrique la plus pertinente sur hébergement mutualisé).
    $q_pct  = $quota_bytes ? round( $stats['size'] / $quota_bytes * 100, 1 ) : 0;
    $q_warn = $q_pct >= 90 ? 'notice-error' : ( $q_pct >= 80 ? 'notice-warning' : 'notice-success' );

    // Doublons (même empreinte SHA-256) et orphelins (fichiers privés non référencés).
    $all_subs   = get_posts( [ 'post_type' => 'md_submission', 'post_status' => md_inbox_all_statuses(), 'posts_per_page' => -1, 'fields' => 'ids' ] );
    $by_hash    = [];
    $referenced = [];
    foreach ( $all_subs as $sid ) {
        foreach ( md_inbox_get_attachments( $sid ) as $a ) {
            if ( ! empty( $a['hash'] ) ) { $by_hash[ $a['hash'] ][] = [ 'sid' => $sid, 'name' => $a['orig'] ?? '' ]; }
            if ( ( $a['kind'] ?? '' ) === 'private' && ! empty( $a['path'] ) ) { $referenced[ basename( $a['path'] ) ] = true; }
        }
    }
    $dups    = array_filter( $by_hash, function ( $g ) { return count( $g ) > 1; } );
    $orphans = [];
    $privdir = $dir . '/md-private';
    if ( is_dir( $privdir ) ) {
        foreach ( (array) glob( $privdir . '/*' ) as $f ) {
            $b = basename( $f );
            if ( in_array( $b, [ '.htaccess', 'index.php' ], true ) ) { continue; }
            if ( empty( $referenced[ $b ] ) ) { $orphans[] = [ 'name' => $b, 'size' => filesize( $f ) ]; }
        }
    }
    ?>
    <div class="wrap">
        <h1>Stockage</h1>
        <div class="notice <?php echo esc_attr( $warn ); ?>" style="padding:10px 14px;">
            <p><strong>Disque : <?php echo esc_html( $pct ); ?> % utilisé</strong>
               (<?php echo esc_html( size_format( $used ) ); ?> / <?php echo esc_html( size_format( $total ) ); ?>,
               libre : <?php echo esc_html( size_format( $free ) ); ?>).
               <?php if ( $pct >= 90 ) echo '⚠ Critique (≥ 90 %).'; elseif ( $pct >= 80 ) echo '⚠ Attention (≥ 80 %).'; ?>
            </p>
        </div>
        <div class="notice <?php echo esc_attr( $q_warn ); ?>" style="padding:10px 14px;">
            <p><strong>Quota compte : <?php echo esc_html( $q_pct ); ?> % utilisé</strong>
               (<?php echo esc_html( size_format( $stats['size'] ) ); ?> d'uploads / <?php echo esc_html( $quota_gb ); ?> Go).
               <?php if ( $q_pct >= 90 ) echo '⚠ Critique (≥ 90 %).'; elseif ( $q_pct >= 80 ) echo '⚠ Attention (≥ 80 %).'; ?>
            </p>
            <form method="post" style="margin-top:6px;">
                <?php wp_nonce_field( 'md_storage_quota' ); ?>
                Quota du compte : <input type="number" name="quota_gb" value="<?php echo esc_attr( $quota_gb ); ?>" min="1" class="small-text"> Go
                <button class="button" name="md_save_quota" value="1">Enregistrer</button>
            </form>
        </div>
        <table class="widefat" style="max-width:600px;">
            <tbody>
                <tr><th>Espace total</th><td><?php echo esc_html( size_format( $total ) ); ?></td></tr>
                <tr><th>Utilisé</th><td><?php echo esc_html( size_format( $used ) ); ?> (<?php echo esc_html( $pct ); ?> %)</td></tr>
                <tr><th>Libre</th><td><?php echo esc_html( size_format( $free ) ); ?></td></tr>
                <tr><th>Dossier « uploads »</th><td><?php echo esc_html( size_format( $stats['size'] ) ); ?></td></tr>
                <tr><th>Nombre de fichiers (uploads)</th><td><?php echo esc_html( number_format_i18n( $stats['count'] ) ); ?></td></tr>
                <tr><th>Taille moyenne</th><td><?php echo esc_html( $stats['count'] ? size_format( $stats['size'] / $stats['count'] ) : '—' ); ?></td></tr>
            </tbody>
        </table>

        <h2>Fichiers les plus volumineux (uploads)</h2>
        <table class="widefat striped" style="max-width:800px;">
            <thead><tr><th>Fichier</th><th>Taille</th></tr></thead>
            <tbody>
            <?php foreach ( $stats['biggest'] as $b ) : ?>
                <tr><td><?php echo esc_html( ltrim( $b['p'], '/\\' ) ); ?></td><td><?php echo esc_html( size_format( $b['s'] ) ); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <h2>Doublons (même empreinte SHA-256)</h2>
        <?php if ( empty( $dups ) ) : ?>
            <p><em>Aucun doublon détecté.</em></p>
        <?php else : ?>
            <table class="widefat striped" style="max-width:800px;">
                <thead><tr><th>Empreinte</th><th>Occurrences</th><th>Fichiers</th></tr></thead>
                <tbody>
                <?php foreach ( $dups as $h => $g ) : ?>
                    <tr>
                        <td><code style="font-size:11px;"><?php echo esc_html( substr( $h, 0, 16 ) ); ?>…</code></td>
                        <td><?php echo count( $g ); ?></td>
                        <td><?php echo esc_html( implode( ', ', array_map( function ( $x ) { return $x['name']; }, $g ) ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h2>Fichiers orphelins (privés, non référencés)</h2>
        <?php if ( empty( $orphans ) ) : ?>
            <p><em>Aucun fichier orphelin.</em></p>
        <?php else : ?>
            <table class="widefat striped" style="max-width:800px;">
                <thead><tr><th>Fichier</th><th>Taille</th></tr></thead>
                <tbody>
                <?php foreach ( $orphans as $o ) : ?>
                    <tr><td><?php echo esc_html( $o['name'] ); ?></td><td><?php echo esc_html( size_format( $o['size'] ) ); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <p class="description">Aucune suppression automatique (règle de conservation à valider).</p>
        <p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=md-inbox-storage&refresh=1' ), 'md_inbox_refresh' ) ); ?>">Recalculer</a></p>
    </div>
    <?php
    if ( isset( $_GET['refresh'] ) && check_admin_referer( 'md_inbox_refresh' ) ) {
        delete_transient( 'md_inbox_uploads_stats' );
        echo '<script>location.replace("' . esc_url_raw( admin_url( 'admin.php?page=md-inbox-storage' ) ) . '");</script>';
    }
}
