<?php
/**
 * Template Part: Upload Form (reusable)
 *
 * Expects: $form_config (array) with keys:
 *   'id'          => unique form ID
 *   'submit_text' => button label
 *   'page_id'     => (optionnel) ID de la page → lit la config ACF « Champs du formulaire »
 *                    (activer/désactiver + renommer chaque champ, par page).
 */

if ( ! isset( $form_config ) ) {
    $form_config = [
        'id'          => 'upload-form',
        'submit_text' => 'Send',
    ];
}

$fid = esc_attr( $form_config['id'] );
$pid = isset( $form_config['page_id'] ) ? (int) $form_config['page_id'] : 0;

/**
 * Lit une valeur de configuration ACF sur la page ; retourne $default si non défini.
 * (Ainsi, tant que rien n'est configuré, tous les champs s'affichent comme avant.)
 */
$md_cfg = function ( $key, $default ) use ( $pid ) {
    if ( ! $pid || ! function_exists( 'get_field' ) ) {
        return $default;
    }
    $v = get_field( $key, $pid );
    if ( $v === null ) {
        return $default;
    }
    if ( is_string( $v ) && trim( $v ) === '' ) {
        return $default;
    }
    return $v;
};

$label_email   = $md_cfg( 'label_email', 'Email' );
$show_project  = (bool) $md_cfg( 'show_project', true );
$label_project = $md_cfg( 'label_project', 'Project name or title' );
$req_project   = (bool) $md_cfg( 'req_project', true );
$show_desc     = (bool) $md_cfg( 'show_description', true );
$label_desc    = $md_cfg( 'label_description', 'Audio project description' );
$show_link     = (bool) $md_cfg( 'show_link', true );
$label_link    = $md_cfg( 'label_link', 'Link to your audio files' );
$show_file     = (bool) $md_cfg( 'show_file', true );
$label_file    = $md_cfg( 'label_file', 'Or upload a file from your computer' );
?>
<form class="upload-form" id="<?php echo $fid; ?>" novalidate enctype="multipart/form-data">
    <!-- Email (toujours affiché — nécessaire pour la réponse) -->
    <div class="form-group">
        <label class="form-label" for="<?php echo $fid; ?>-email"><?php echo esc_html( $label_email ); ?> *</label>
        <input type="email" name="user_email" id="<?php echo $fid; ?>-email" class="form-input"
               placeholder="<?php echo esc_attr( $label_email ); ?>" required autocomplete="email">
    </div>

    <?php if ( $show_project ) : ?>
    <div class="form-group">
        <label class="form-label" for="<?php echo $fid; ?>-subject"><?php echo esc_html( $label_project ); ?><?php echo $req_project ? ' *' : ''; ?></label>
        <input type="text" name="project_name" id="<?php echo $fid; ?>-subject" class="form-input"
               placeholder="<?php echo esc_attr( $label_project ); ?>"<?php echo $req_project ? ' required' : ''; ?>>
    </div>
    <?php endif; ?>

    <?php if ( $show_desc ) : ?>
    <div class="form-group">
        <label class="form-label" for="<?php echo $fid; ?>-desc"><?php echo esc_html( $label_desc ); ?></label>
        <textarea name="project_description" id="<?php echo $fid; ?>-desc" class="form-textarea"
                  placeholder="<?php echo esc_attr( $label_desc ); ?>" rows="5"></textarea>
    </div>
    <?php endif; ?>

    <?php if ( $show_link ) : ?>
    <div class="form-group">
        <label class="form-label" for="<?php echo $fid; ?>-link"><?php echo esc_html( $label_link ); ?></label>
        <input type="url" name="files_link" id="<?php echo $fid; ?>-link" class="form-input"
               placeholder="WeTransfer, Google Drive, Dropbox link...">
    </div>
    <?php endif; ?>

    <?php if ( $show_file ) : ?>
    <div class="form-group">
        <label class="form-label"><?php echo esc_html( $label_file ); ?></label>
        <div class="upload-zone" id="<?php echo $fid; ?>-dropzone">
            <input type="file" name="demo_file[]" id="<?php echo $fid; ?>-file" accept=".wav,.mp3,.flac,.aiff,.zip,.rar" multiple style="display:none;">
            <p class="upload-zone__text">Drag files here or click to browse (multiple files allowed)</p>
            <p class="upload-zone__formats">.wav, .mp3, .flac, .aiff — 300 MB max</p>
        </div>
        <div class="upload-file-list" id="<?php echo $fid; ?>-filelist"></div>
    </div>
    <?php endif; ?>

    <button type="submit" class="btn btn--filled" id="<?php echo $fid; ?>-submit">
        <?php echo esc_html( $form_config['submit_text'] ); ?>
    </button>

    <div class="form-message" id="<?php echo $fid; ?>-message" style="display:none;" role="alert"></div>
</form>
