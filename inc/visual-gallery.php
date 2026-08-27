<?php
/**
 * Visuals — collection de photos du projet.
 *
 * ACF gratuit n'a pas de champ « Galerie » (réservé à ACF PRO). On fournit donc
 * une meta-box dédiée qui ouvre la MÉDIATHÈQUE WordPress (sélection multiple,
 * réordonnable), bien plus découvrable que le bloc « Galerie » de l'éditeur.
 *
 * Stockage : meta `_md_visual_gallery` = tableau d'IDs de pièces jointes.
 * Repli    : si la meta est vide, on lit une galerie placée dans le contenu
 *            (bloc Galerie natif) → aucune régression pour l'existant.
 *
 * Interface d'ADMINISTRATION → libellés en FRANÇAIS.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const MD_VISUAL_GALLERY_META = '_md_visual_gallery';

/**
 * IDs des photos de la collection d'un visuel.
 * Priorité : meta dédiée, puis repli sur une galerie du contenu.
 *
 * @param int $post_id
 * @return int[]
 */
function md_visual_gallery_ids( $post_id ) {
    $ids = get_post_meta( $post_id, MD_VISUAL_GALLERY_META, true );
    $ids = is_array( $ids ) ? array_values( array_filter( array_map( 'intval', $ids ) ) ) : [];

    if ( empty( $ids ) && function_exists( 'md_get_post_gallery_image_ids' ) ) {
        $ids = md_get_post_gallery_image_ids( $post_id ); // repli : bloc Galerie du contenu
    }

    // On ne garde que les pièces jointes réellement existantes.
    return array_values( array_filter( $ids, function ( $id ) {
        return wp_attachment_is_image( $id );
    } ) );
}

/* ==========================================================================
   Meta-box
   ========================================================================== */
add_action( 'add_meta_boxes', function () {
    add_meta_box(
        'md_visual_gallery',
        'Photos du projet (collection)',
        'md_visual_gallery_cb',
        'visual',
        'normal',
        'high'
    );
} );

function md_visual_gallery_cb( $post ) {
    wp_nonce_field( 'md_visual_gallery_save', 'md_visual_gallery_nonce' );
    $ids = get_post_meta( $post->ID, MD_VISUAL_GALLERY_META, true );
    $ids = is_array( $ids ) ? array_filter( array_map( 'intval', $ids ) ) : [];
    ?>
    <p class="description">
        Choisissez les photos de ce projet dans la médiathèque. Elles s'affichent sur la page du projet
        et s'ouvrent en grand (navigation par flèches). Glissez-déposez pour changer l'ordre.
    </p>

    <ul id="md-vis-gallery-list" class="md-vis-gallery__list">
        <?php foreach ( $ids as $id ) :
            $src = wp_get_attachment_image_url( $id, 'thumbnail' );
            if ( ! $src ) { continue; }
        ?>
        <li data-id="<?php echo (int) $id; ?>">
            <img src="<?php echo esc_url( $src ); ?>" alt="">
            <button type="button" class="md-vis-gallery__remove" aria-label="Retirer cette photo">&times;</button>
        </li>
        <?php endforeach; ?>
    </ul>

    <input type="hidden" name="md_visual_gallery" id="md-visual-gallery-input"
           value="<?php echo esc_attr( implode( ',', $ids ) ); ?>">

    <p>
        <button type="button" class="button button-primary" id="md-vis-gallery-add">Ajouter des photos</button>
        <button type="button" class="button" id="md-vis-gallery-clear">Tout retirer</button>
        <span id="md-vis-gallery-count" class="description" style="margin-left:8px;"><?php echo count( $ids ); ?> photo(s)</span>
    </p>

    <style>
        .md-vis-gallery__list { display:flex; flex-wrap:wrap; gap:10px; margin:12px 0; padding:0; list-style:none; min-height:20px; }
        .md-vis-gallery__list li { position:relative; width:100px; height:100px; cursor:move; background:#f0f0f1; border:1px solid #dcdcde; }
        .md-vis-gallery__list img { width:100%; height:100%; object-fit:cover; display:block; }
        .md-vis-gallery__remove { position:absolute; top:-8px; right:-8px; width:22px; height:22px; border-radius:50%;
            border:1px solid #dcdcde; background:#fff; cursor:pointer; line-height:1; font-size:15px; padding:0; }
        .md-vis-gallery__remove:hover { background:#d63638; color:#fff; border-color:#d63638; }
        .md-vis-gallery__placeholder { width:100px; height:100px; border:2px dashed #c3c4c7; }
    </style>

    <script>
    jQuery(function ($) {
        var $list  = $('#md-vis-gallery-list');
        var $input = $('#md-visual-gallery-input');
        var $count = $('#md-vis-gallery-count');
        var frame;

        function sync() {
            var ids = $list.children('li').map(function () { return $(this).data('id'); }).get();
            $input.val(ids.join(','));
            $count.text(ids.length + ' photo(s)');
        }

        // Réordonnancement par glisser-déposer
        if ($.fn.sortable) {
            $list.sortable({ placeholder: 'md-vis-gallery__placeholder', update: sync });
        }

        // Retirer une photo
        $list.on('click', '.md-vis-gallery__remove', function () {
            $(this).closest('li').remove();
            sync();
        });

        // Tout retirer
        $('#md-vis-gallery-clear').on('click', function () {
            $list.empty();
            sync();
        });

        // Médiathèque (sélection multiple)
        $('#md-vis-gallery-add').on('click', function (e) {
            e.preventDefault();
            if (frame) { frame.open(); return; }
            frame = wp.media({
                title: 'Choisir les photos du projet',
                button: { text: 'Ajouter à la collection' },
                library: { type: 'image' },
                multiple: 'add'
            });
            frame.on('select', function () {
                var existing = $list.children('li').map(function () { return String($(this).data('id')); }).get();
                frame.state().get('selection').each(function (att) {
                    var a = att.toJSON();
                    if (existing.indexOf(String(a.id)) !== -1) { return; }   // pas de doublon
                    var src = (a.sizes && a.sizes.thumbnail) ? a.sizes.thumbnail.url : a.url;
                    $list.append(
                        '<li data-id="' + a.id + '"><img src="' + src + '" alt="">' +
                        '<button type="button" class="md-vis-gallery__remove" aria-label="Retirer cette photo">&times;</button></li>'
                    );
                });
                sync();
            });
            frame.open();
        });
    });
    </script>
    <?php
}

/* ==========================================================================
   Enregistrement
   ========================================================================== */
add_action( 'save_post_visual', function ( $post_id ) {
    if ( ! isset( $_POST['md_visual_gallery_nonce'] )
        || ! wp_verify_nonce( $_POST['md_visual_gallery_nonce'], 'md_visual_gallery_save' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
    if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

    $raw = isset( $_POST['md_visual_gallery'] ) ? sanitize_text_field( wp_unslash( $_POST['md_visual_gallery'] ) ) : '';
    $ids = array_values( array_filter( array_map( 'intval', explode( ',', $raw ) ) ) );

    if ( empty( $ids ) ) {
        delete_post_meta( $post_id, MD_VISUAL_GALLERY_META );
    } else {
        update_post_meta( $post_id, MD_VISUAL_GALLERY_META, $ids );
    }
} );
