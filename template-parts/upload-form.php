<?php
/**
 * Template Part: Upload Form (reusable — EmailJS powered)
 *
 * Expects: $form_config (array) with keys:
 *   'id'          => unique form ID
 *   'submit_text' => button label (e.g., "Envoyer la demande" or "Envoyer la démo")
 */

if ( ! isset( $form_config ) ) {
    $form_config = [
        'id'          => 'upload-form',
        'submit_text' => 'Envoyer',
    ];
}
?>
<form class="upload-form" id="<?php echo esc_attr( $form_config['id'] ); ?>" novalidate>
    <div class="form-group">
        <label class="form-label" for="<?php echo esc_attr( $form_config['id'] ); ?>-email">Email *</label>
        <input
            type="email"
            name="user_email"
            id="<?php echo esc_attr( $form_config['id'] ); ?>-email"
            class="form-input"
            placeholder="Votre email"
            required
            autocomplete="email"
        >
    </div>

    <div class="form-group">
        <label class="form-label" for="<?php echo esc_attr( $form_config['id'] ); ?>-subject">Nom ou titre du projet *</label>
        <input
            type="text"
            name="project_name"
            id="<?php echo esc_attr( $form_config['id'] ); ?>-subject"
            class="form-input"
            placeholder="Nom ou titre du projet"
            required
        >
    </div>

    <div class="form-group">
        <label class="form-label" for="<?php echo esc_attr( $form_config['id'] ); ?>-desc">Description du projet audio</label>
        <textarea
            name="project_description"
            id="<?php echo esc_attr( $form_config['id'] ); ?>-desc"
            class="form-textarea"
            placeholder="Description du projet audio"
            rows="5"
        ></textarea>
    </div>

    <div class="form-group">
        <label class="form-label" for="<?php echo esc_attr( $form_config['id'] ); ?>-link">Lien vers les fichiers audio</label>
        <input
            type="url"
            name="files_link"
            id="<?php echo esc_attr( $form_config['id'] ); ?>-link"
            class="form-input"
            placeholder="Lien WeTransfer, Google Drive, Dropbox..."
        >
        <p style="font-size:0.75rem;color:var(--color-text-muted);margin-top:var(--space-2xs);">
            Formats acceptés : .wav, .mp3, .flac, .aiff — Utilisez un service de partage pour les fichiers volumineux.
        </p>
    </div>

    <button type="submit" class="btn btn--filled" id="<?php echo esc_attr( $form_config['id'] ); ?>-submit">
        <?php echo esc_html( $form_config['submit_text'] ); ?>
    </button>

    <div class="form-message" id="<?php echo esc_attr( $form_config['id'] ); ?>-message" style="display:none;" role="alert"></div>
</form>
