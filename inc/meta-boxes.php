<?php
/**
 * Meta Boxes — Custom fields for Artiste & Release CPTs
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ==========================================================================
// Register Meta Boxes
// ==========================================================================
function md_add_meta_boxes() {
    // Artiste meta boxes
    add_meta_box( 'md_artiste_details', 'Détails de l\'artiste', 'md_artiste_details_cb', 'artiste', 'normal', 'high' );
    add_meta_box( 'md_artiste_social', 'Liens sociaux', 'md_artiste_social_cb', 'artiste', 'normal', 'default' );
    // Les « Gigs » sont désormais un CPT dédié (CPT UI + ACF), plus une meta-box native.

    // Release meta boxes
    add_meta_box( 'md_release_details', 'Détails de la release', 'md_release_details_cb', 'release', 'normal', 'high' );
    add_meta_box( 'md_release_artists', 'Artistes associés', 'md_release_artists_cb', 'release', 'side', 'default' );
    add_meta_box( 'md_release_tracklist', 'Tracklist', 'md_release_tracklist_cb', 'release', 'normal', 'default' );
}
add_action( 'add_meta_boxes', 'md_add_meta_boxes' );

// ==========================================================================
// Artiste: Details (Biography)
// ==========================================================================
function md_artiste_details_cb( $post ) {
    wp_nonce_field( 'md_artiste_details_nonce', 'md_artiste_nonce' );
    $bio = get_post_meta( $post->ID, '_md_biography', true );
    ?>
    <p>
        <label for="md_biography"><strong>Biographie</strong></label><br>
        <textarea name="md_biography" id="md_biography" rows="8" style="width:100%;"><?php echo esc_textarea( $bio ); ?></textarea>
    </p>
    <?php
}

// ==========================================================================
// Artiste: Social Links
// ==========================================================================
function md_artiste_social_cb( $post ) {
    $social = get_post_meta( $post->ID, '_md_social_links', true );
    $social = is_array( $social ) ? $social : [];
    $fields = [
        'instagram'  => 'Instagram URL',
        'soundcloud' => 'SoundCloud URL',
        'bandcamp'   => 'Bandcamp URL',
        'spotify'    => 'Spotify URL',
        'website'    => 'Site web',
    ];
    ?>
    <table class="form-table" style="margin-top:0;">
        <?php foreach ( $fields as $key => $label ) : ?>
        <tr>
            <th style="padding:8px 10px 8px 0;width:130px;"><label for="md_social_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
            <td style="padding:4px 0;"><input type="url" name="md_social[<?php echo esc_attr( $key ); ?>]" id="md_social_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_url( isset( $social[ $key ] ) ? $social[ $key ] : '' ); ?>" class="widefat"></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <?php
}

// ==========================================================================
// Release: Details
// ==========================================================================
function md_release_details_cb( $post ) {
    wp_nonce_field( 'md_release_details_nonce', 'md_release_nonce' );
    $fields = [
        '_md_release_date'      => [ 'label' => 'Date de sortie', 'type' => 'date' ],
        '_md_catalogue_number'  => [ 'label' => 'Numéro de catalogue', 'type' => 'text' ],
        '_md_bandcamp_url'      => [ 'label' => 'URL Bandcamp', 'type' => 'url' ],
    ];
    $description = get_post_meta( $post->ID, '_md_description', true );
    $credits     = get_post_meta( $post->ID, '_md_credits', true );
    ?>
    <table class="form-table" style="margin-top:0;">
        <?php foreach ( $fields as $key => $info ) :
            $value = get_post_meta( $post->ID, $key, true );
        ?>
        <tr>
            <th style="padding:8px 10px 8px 0;width:160px;"><label for="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $info['label'] ); ?></label></th>
            <td style="padding:4px 0;"><input type="<?php echo esc_attr( $info['type'] ); ?>" name="<?php echo esc_attr( $key ); ?>" id="<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" class="widefat"></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <p>
        <label for="md_description"><strong>Description du projet</strong></label><br>
        <textarea name="md_description" id="md_description" rows="5" style="width:100%;"><?php echo esc_textarea( $description ); ?></textarea>
    </p>
    <p>
        <label for="md_credits"><strong>Crédits</strong></label><br>
        <textarea name="md_credits" id="md_credits" rows="3" style="width:100%;"><?php echo esc_textarea( $credits ); ?></textarea>
    </p>
    <?php
}

// ==========================================================================
// Release: Associated Artists (checkboxes)
// ==========================================================================
function md_release_artists_cb( $post ) {
    $selected = get_post_meta( $post->ID, '_md_artist_ids', true );
    $selected = is_array( $selected ) ? $selected : [];

    $artistes = get_posts( [
        'post_type'      => 'artiste',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ] );

    if ( empty( $artistes ) ) {
        echo '<p>Aucun artiste créé. Ajoutez des artistes d\'abord.</p>';
        return;
    }

    foreach ( $artistes as $artiste ) {
        $checked = in_array( $artiste->ID, $selected ) ? 'checked' : '';
        printf(
            '<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="md_artist_ids[]" value="%d" %s> %s</label>',
            intval( $artiste->ID ),
            $checked,
            esc_html( $artiste->post_title )
        );
    }
}

// ==========================================================================
// Release: Tracklist (dynamic repeater)
// ==========================================================================
function md_release_tracklist_cb( $post ) {
    // Nonce propre à la tracklist : cette meta-box reste native même avec ACF actif,
    // alors que la meta-box « Détails » (et son nonce) est retirée par ACF.
    wp_nonce_field( 'md_release_tracklist_save', 'md_release_tracklist_nonce' );
    $tracklist = get_post_meta( $post->ID, '_md_tracklist', true );
    $tracklist = is_array( $tracklist ) ? $tracklist : [];
    ?>
    <div id="md-tracklist-wrapper">
        <table class="widefat" id="md-tracklist-table">
            <thead>
                <tr>
                    <th style="width:30px;">#</th>
                    <th>Titre</th>
                    <th style="width:80px;">Durée</th>
                    <th>URL Audio</th>
                    <th style="width:40px;"></th>
                </tr>
            </thead>
            <tbody id="md-tracklist-body">
                <?php if ( ! empty( $tracklist ) ) : ?>
                    <?php foreach ( $tracklist as $i => $track ) : ?>
                    <tr class="md-track-row">
                        <td class="md-track-num"><?php echo intval( $i + 1 ); ?></td>
                        <td><input type="text" name="md_tracklist[<?php echo intval( $i ); ?>][title]" value="<?php echo esc_attr( $track['title'] ?? '' ); ?>" class="widefat" placeholder="Titre du morceau"></td>
                        <td><input type="text" name="md_tracklist[<?php echo intval( $i ); ?>][duration]" value="<?php echo esc_attr( $track['duration'] ?? '' ); ?>" class="widefat" placeholder="3:42"></td>
                        <td>
                            <div style="display:flex;gap:4px;">
                                <input type="url" name="md_tracklist[<?php echo intval( $i ); ?>][audioUrl]" value="<?php echo esc_url( $track['audioUrl'] ?? '' ); ?>" class="widefat md-audio-url" placeholder="https://...">
                                <button type="button" class="button md-browse-audio" title="Parcourir la médiathèque">📁</button>
                            </div>
                        </td>
                        <td><button type="button" class="button md-remove-track" title="Supprimer">✕</button></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <p style="margin-top:10px;">
            <button type="button" class="button button-primary" id="md-add-track">+ Ajouter un morceau</button>
        </p>
    </div>

    <script>
    (function() {
        var tbody = document.getElementById('md-tracklist-body');
        var addBtn = document.getElementById('md-add-track');

        function renumber() {
            var rows = tbody.querySelectorAll('.md-track-row');
            rows.forEach(function(row, i) {
                row.querySelector('.md-track-num').textContent = i + 1;
                row.querySelectorAll('input').forEach(function(input) {
                    input.name = input.name.replace(/md_tracklist\[\d+\]/, 'md_tracklist[' + i + ']');
                });
            });
        }

        addBtn.addEventListener('click', function() {
            var index = tbody.querySelectorAll('.md-track-row').length;
            var row = document.createElement('tr');
            row.className = 'md-track-row';
            row.innerHTML =
                '<td class="md-track-num">' + (index + 1) + '</td>' +
                '<td><input type="text" name="md_tracklist[' + index + '][title]" class="widefat" placeholder="Titre du morceau"></td>' +
                '<td><input type="text" name="md_tracklist[' + index + '][duration]" class="widefat" placeholder="3:42"></td>' +
                '<td><div style="display:flex;gap:4px;"><input type="url" name="md_tracklist[' + index + '][audioUrl]" class="widefat md-audio-url" placeholder="https://..."><button type="button" class="button md-browse-audio" title="Parcourir la médiathèque">📁</button></div></td>' +
                '<td><button type="button" class="button md-remove-track" title="Supprimer">✕</button></td>';
            tbody.appendChild(row);
        });

        tbody.addEventListener('click', function(e) {
            if (e.target.classList.contains('md-remove-track')) {
                e.target.closest('.md-track-row').remove();
                renumber();
            }
        });

        /* Media Library browser for audio files */
        var mediaFrame;
        tbody.addEventListener('click', function(e) {
            if (!e.target.classList.contains('md-browse-audio')) return;

            var btn = e.target;
            var urlInput = btn.parentElement.querySelector('.md-audio-url');
            var titleInput = btn.closest('.md-track-row').querySelector('input[name*="[title]"]');

            if (mediaFrame) {
                mediaFrame.off('select');
            } else {
                mediaFrame = wp.media({
                    title: 'Sélectionner un fichier audio',
                    library: { type: 'audio' },
                    button: { text: 'Utiliser ce fichier' },
                    multiple: false
                });
            }

            mediaFrame.on('select', function() {
                var attachment = mediaFrame.state().get('selection').first().toJSON();
                urlInput.value = attachment.url;
                if (titleInput && !titleInput.value) {
                    titleInput.value = attachment.title || attachment.filename.replace(/\.[^.]+$/, '');
                }
            });

            mediaFrame.open();
        });
    })();
    </script>
    <?php
}

// ==========================================================================
// Save Meta Data
// ==========================================================================
function md_save_artiste_meta( $post_id ) {
    if ( ! isset( $_POST['md_artiste_nonce'] ) || ! wp_verify_nonce( $_POST['md_artiste_nonce'], 'md_artiste_details_nonce' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    // Biography
    if ( isset( $_POST['md_biography'] ) ) {
        update_post_meta( $post_id, '_md_biography', sanitize_textarea_field( $_POST['md_biography'] ) );
    }

    // Social links
    if ( isset( $_POST['md_social'] ) && is_array( $_POST['md_social'] ) ) {
        $social = [];
        foreach ( $_POST['md_social'] as $key => $url ) {
            $social[ sanitize_key( $key ) ] = esc_url_raw( $url );
        }
        update_post_meta( $post_id, '_md_social_links', $social );
    }
}
add_action( 'save_post_artiste', 'md_save_artiste_meta' );

function md_save_release_meta( $post_id ) {
    if ( ! isset( $_POST['md_release_nonce'] ) || ! wp_verify_nonce( $_POST['md_release_nonce'], 'md_release_details_nonce' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    // Simple fields
    $text_fields = [ '_md_release_date', '_md_catalogue_number' ];
    foreach ( $text_fields as $field ) {
        if ( isset( $_POST[ $field ] ) ) {
            update_post_meta( $post_id, $field, sanitize_text_field( $_POST[ $field ] ) );
        }
    }

    // URL fields
    if ( isset( $_POST['_md_bandcamp_url'] ) ) {
        update_post_meta( $post_id, '_md_bandcamp_url', esc_url_raw( $_POST['_md_bandcamp_url'] ) );
    }

    // Textareas
    if ( isset( $_POST['md_description'] ) ) {
        update_post_meta( $post_id, '_md_description', sanitize_textarea_field( $_POST['md_description'] ) );
    }
    if ( isset( $_POST['md_credits'] ) ) {
        update_post_meta( $post_id, '_md_credits', sanitize_textarea_field( $_POST['md_credits'] ) );
    }

    // Artist IDs
    if ( isset( $_POST['md_artist_ids'] ) && is_array( $_POST['md_artist_ids'] ) ) {
        $ids = array_map( 'intval', $_POST['md_artist_ids'] );
        update_post_meta( $post_id, '_md_artist_ids', $ids );
    } else {
        update_post_meta( $post_id, '_md_artist_ids', [] );
    }

    // NB : la tracklist est désormais gérée par son propre handler
    // (md_save_release_tracklist) avec un nonce dédié — voir plus bas.
}
add_action( 'save_post_release', 'md_save_release_meta' );

/**
 * Sauvegarde de la TRACKLIST — handler dédié, indépendant du nonce des « Détails ».
 *
 * Pourquoi&nbsp;: la meta-box Tracklist reste native même quand ACF est actif
 * (ACF retire les autres meta-box natives, dont « Détails » et SON nonce). La
 * sauvegarde de la tracklist était donc bloquée par le nonce absent → les pistes
 * disparaissaient à la mise à jour. Ce handler utilise le nonce propre à la
 * tracklist et fonctionne que ACF soit actif ou non.
 */
function md_save_release_tracklist( $post_id ) {
    if ( ! isset( $_POST['md_release_tracklist_nonce'] )
        || ! wp_verify_nonce( $_POST['md_release_tracklist_nonce'], 'md_release_tracklist_save' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $tracklist = [];
    if ( isset( $_POST['md_tracklist'] ) && is_array( $_POST['md_tracklist'] ) ) {
        foreach ( $_POST['md_tracklist'] as $track ) {
            if ( empty( $track['title'] ) ) {
                continue;
            }
            $tracklist[] = [
                'title'    => sanitize_text_field( $track['title'] ),
                'duration' => sanitize_text_field( $track['duration'] ?? '' ),
                'audioUrl' => esc_url_raw( $track['audioUrl'] ?? '' ),
            ];
        }
    }
    update_post_meta( $post_id, '_md_tracklist', $tracklist );
}
add_action( 'save_post_release', 'md_save_release_tracklist' );
