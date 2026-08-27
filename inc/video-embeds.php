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
 * Meta-box « Vidéos » — une ligne par URL, ajoutable et supprimable.
 *
 * Volontairement native plutôt qu'ACF. Le champ avait d'abord été déclaré dans
 * md_acf_group_definitions(), qui n'est importé qu'une seule fois (option
 * md_acf_seeded) : il n'atteignait jamais le back-office. Puis via le filtre
 * acf/load_fields : il s'affichait, mais ACF ne pouvait pas le retrouver par sa
 * clé au moment d'enregistrer — acf_get_field() renvoyait null — et jetait la
 * valeur saisie.
 *
 * Une meta-box native n'a aucun de ces problèmes et donne l'interface
 * répétable demandée, sur le modèle de la tracklist des releases.
 *
 * Stockage : _md_videos, une URL par ligne — le format que md_youtube_ids()
 * sait déjà lire.
 */
add_action( 'add_meta_boxes', 'md_add_videos_meta_box' );
function md_add_videos_meta_box() {
    foreach ( [ 'artiste', 'visual' ] as $type ) {
        add_meta_box(
            'md_videos_box',
            'Vidéos YouTube',
            'md_videos_meta_box_cb',
            $type,
            'normal',
            'default'
        );
    }
}

function md_videos_meta_box_cb( $post ) {
    // Nonce dédié : cette meta-box reste native même quand ACF est actif.
    wp_nonce_field( 'md_videos_save', 'md_videos_nonce' );

    $urls = preg_split( '/\R/', (string) get_post_meta( $post->ID, '_md_videos', true ) );
    $urls = array_values( array_filter( array_map( 'trim', (array) $urls ) ) );
    ?>
    <div id="md-videos-wrapper">
        <table class="widefat" id="md-videos-table">
            <thead>
                <tr>
                    <th style="width:30px;">#</th>
                    <th>URL de la vidéo</th>
                    <th style="width:40px;"></th>
                </tr>
            </thead>
            <tbody id="md-videos-body">
                <?php foreach ( $urls as $i => $url ) : ?>
                <tr class="md-video-row">
                    <td class="md-video-num"><?php echo intval( $i + 1 ); ?></td>
                    <td><input type="url" name="md_videos[]" value="<?php echo esc_url( $url ); ?>" class="widefat" placeholder="https://www.youtube.com/watch?v=..."></td>
                    <td><button type="button" class="button md-remove-video" title="Supprimer">&#10005;</button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p style="margin-top:10px;">
            <button type="button" class="button button-primary" id="md-add-video">+ Ajouter une vidéo</button>
        </p>
        <p class="description">
            Une URL par ligne. Formats acceptés : youtube.com/watch?v=…, youtu.be/…, /shorts/…, /live/…
            Les lignes non reconnues sont ignorées plutôt que d'afficher un lecteur cassé.
        </p>
    </div>

    <script>
    (function () {
        var tbody  = document.getElementById('md-videos-body');
        var addBtn = document.getElementById('md-add-video');
        if (!tbody || !addBtn) return;

        function renumber() {
            tbody.querySelectorAll('.md-video-row').forEach(function (row, i) {
                row.querySelector('.md-video-num').textContent = i + 1;
            });
        }

        function addRow(value) {
            var tr = document.createElement('tr');
            tr.className = 'md-video-row';
            tr.innerHTML = '<td class="md-video-num"></td>'
                + '<td><input type="url" name="md_videos[]" class="widefat" placeholder="https://www.youtube.com/watch?v=..."></td>'
                + '<td><button type="button" class="button md-remove-video" title="Supprimer">&#10005;</button></td>';
            tbody.appendChild(tr);
            if (value) tr.querySelector('input').value = value;
            renumber();
        }

        addBtn.addEventListener('click', function () { addRow(''); });

        tbody.addEventListener('click', function (e) {
            if (!e.target.classList.contains('md-remove-video')) return;
            e.target.closest('.md-video-row').remove();
            renumber();
        });

        // Une ligne vide d'amorce quand il n'y a encore aucune vidéo.
        if (!tbody.querySelector('.md-video-row')) addRow('');
    })();
    </script>
    <?php
}

/**
 * Enregistre les URLs saisies dans _md_videos, une par ligne.
 *
 * Les lignes vides sont écartées : la meta-box affiche toujours une ligne
 * d'amorce, qui ne doit pas se transformer en saut de ligne parasite.
 */
add_action( 'save_post_artiste', 'md_save_videos_meta' );
add_action( 'save_post_visual', 'md_save_videos_meta' );
function md_save_videos_meta( $post_id ) {
    if ( ! isset( $_POST['md_videos_nonce'] ) || ! wp_verify_nonce( $_POST['md_videos_nonce'], 'md_videos_save' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $urls = isset( $_POST['md_videos'] ) ? (array) wp_unslash( $_POST['md_videos'] ) : [];
    $urls = array_values( array_filter( array_map( function ( $u ) {
        return esc_url_raw( trim( $u ) );
    }, $urls ) ) );

    update_post_meta( $post_id, '_md_videos', implode( "
", $urls ) );
}
