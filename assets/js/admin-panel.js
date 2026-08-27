/**
 * Mango Dragon — Admin Panel JS
 * Handles: status changes, resend notifications, routing rule CRUD, test emails.
 */
(function () {
  'use strict';

  var ajax   = window.mdAdminData ? window.mdAdminData.ajaxUrl : '';
  var nonce  = window.mdAdminData ? window.mdAdminData.nonce   : '';

  // -------------------------------------------------------------------------
  // Utility: AJAX POST wrapper
  // -------------------------------------------------------------------------
  function post(action, data, cb) {
    data.action = action;
    data.nonce  = nonce;

    var body = new URLSearchParams(data);

    fetch(ajax, {
      method:  'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body:    body.toString(),
    })
      .then(function (r) { return r.json(); })
      .then(function (res) { cb(null, res); })
      .catch(function (err) { cb(err, null); });
  }

  // -------------------------------------------------------------------------
  // Status select — change submission status
  // -------------------------------------------------------------------------
  document.addEventListener('change', function (e) {
    if (!e.target.classList.contains('md-status-select')) return;

    var select = e.target;
    var id     = select.dataset.id;
    var status = select.value;

    post('md_update_submission_status', { id: id, status: status }, function (err, res) {
      if (err || !res.success) {
        alert('Erreur lors de la mise à jour du statut.');
        return;
      }
      // Update badge in same row if present
      var row   = select.closest('tr');
      var badge = row ? row.querySelector('.md-badge') : null;
      if (badge) {
        badge.className = 'md-badge md-badge--' + status;
        badge.textContent = select.options[select.selectedIndex].text;
      }
    });
  });

  // -------------------------------------------------------------------------
  // Resend notification button
  // -------------------------------------------------------------------------
  document.addEventListener('click', function (e) {
    if (!e.target.classList.contains('md-resend-btn')) return;

    var btn = e.target;
    var id  = btn.dataset.id;

    btn.disabled = true;
    btn.textContent = 'Envoi…';

    post('md_resend_notification', { id: id }, function (err, res) {
      btn.disabled    = false;
      btn.textContent = 'Renvoyer';

      var resultEl = document.getElementById('resend-result-' + id);
      if (resultEl) {
        resultEl.textContent = res && res.data && res.data.message ? res.data.message : (err ? 'Erreur réseau.' : '');
        resultEl.style.color = (res && res.success) ? '#166534' : '#991b1b';
      } else {
        alert( res && res.data && res.data.message ? res.data.message : 'Erreur.' );
      }
    });
  });

  // -------------------------------------------------------------------------
  // Toggle rule enabled / disabled
  // -------------------------------------------------------------------------
  document.addEventListener('click', function (e) {
    if (!e.target.classList.contains('md-toggle-rule')) return;

    var btn    = e.target;
    var ruleId = btn.dataset.id;

    btn.disabled = true;

    post('md_toggle_routing_rule', { rule_id: ruleId }, function (err, res) {
      btn.disabled = false;
      if (err || !res.success) {
        alert('Erreur lors de la mise à jour.');
        return;
      }
      var enabled = res.data.enabled;
      btn.dataset.enabled = enabled ? '1' : '0';
      btn.textContent     = enabled ? 'Actif' : 'Inactif';
    });
  });

  // -------------------------------------------------------------------------
  // Test email
  // -------------------------------------------------------------------------
  document.addEventListener('click', function (e) {
    if (!e.target.classList.contains('md-test-rule')) return;

    var btn    = e.target;
    var ruleId = btn.dataset.id;

    btn.disabled    = true;
    btn.textContent = 'Envoi…';

    post('md_send_test_email', { rule_id: ruleId }, function (err, res) {
      btn.disabled    = false;
      btn.textContent = 'Tester';
      var msg = res && res.data && res.data.message ? res.data.message : (err ? 'Erreur réseau.' : '');
      alert(msg);
    });
  });

  // -------------------------------------------------------------------------
  // Delete rule
  // -------------------------------------------------------------------------
  document.addEventListener('click', function (e) {
    if (!e.target.classList.contains('md-delete-rule')) return;

    var btn    = e.target;
    var ruleId = btn.dataset.id;

    if (!confirm('Supprimer cette règle de routing ? Cette action est irréversible.')) return;

    btn.disabled = true;

    post('md_delete_routing_rule', { rule_id: ruleId }, function (err, res) {
      if (err || !res.success) {
        btn.disabled = false;
        alert('Erreur lors de la suppression.');
        return;
      }
      // Remove row from table
      var row = btn.closest('tr');
      if (row) {
        row.remove();
      }
      // Show empty message if no rows left
      var tbody = document.getElementById('md-routing-list');
      if (tbody && tbody.querySelectorAll('tr').length === 0) {
        tbody.innerHTML = '<tr id="md-no-rules"><td colspan="7" class="md-empty">Aucune règle configurée.</td></tr>';
      }
    });
  });

  // -------------------------------------------------------------------------
  // Add rule button — show form
  // -------------------------------------------------------------------------
  var addBtn = document.getElementById('md-add-rule-btn');
  if (addBtn) {
    addBtn.addEventListener('click', function () {
      resetRuleForm();
      document.getElementById('md-rule-form-title').textContent = 'Nouvelle règle';
      showRuleForm();
    });
  }

  // -------------------------------------------------------------------------
  // Edit rule button — populate form
  // -------------------------------------------------------------------------
  document.addEventListener('click', function (e) {
    if (!e.target.classList.contains('md-edit-rule-btn')) return;

    var btn    = e.target;
    var ruleId = btn.dataset.id;
    var row    = btn.closest('tr');
    if (!row) return;

    // Read displayed data from the row to pre-fill the form
    var recipientsText = row.querySelector('.md-recipients') ? row.querySelector('.md-recipients').textContent.trim() : '';
    // recipientsText may be '—' if empty
    if (recipientsText === '—') recipientsText = '';

    resetRuleForm();
    document.getElementById('md-rule-id').value = ruleId;
    document.getElementById('md-rule-form-title').textContent = 'Modifier la règle';

    // Populate recipients as one-per-line
    var recipsFormatted = recipientsText.split(',').map(function (r) { return r.trim(); }).filter(Boolean).join('\n');
    document.getElementById('md-rule-recipients').value = recipsFormatted;

    // Type: read from <code> cell
    var codeEl = row.querySelector('code');
    if (codeEl) {
      var typeSelect = document.getElementById('md-rule-type');
      typeSelect.value = codeEl.textContent.trim();
    }

    // Label: read from <strong> cell
    var strongEl = row.querySelector('strong');
    if (strongEl) {
      document.getElementById('md-rule-label').value = strongEl.textContent.trim();
    }

    // Enabled
    var toggleBtn = row.querySelector('.md-toggle-rule');
    if (toggleBtn) {
      document.getElementById('md-rule-enabled').checked = toggleBtn.dataset.enabled === '1';
    }

    showRuleForm();
  });

  // -------------------------------------------------------------------------
  // Save rule
  // -------------------------------------------------------------------------
  var saveBtn = document.getElementById('md-rule-save-btn');
  if (saveBtn) {
    saveBtn.addEventListener('click', function () {
      var id         = document.getElementById('md-rule-id').value;
      var label      = document.getElementById('md-rule-label').value.trim();
      var type       = document.getElementById('md-rule-type').value;
      var recipients = document.getElementById('md-rule-recipients').value.trim();
      var cc         = document.getElementById('md-rule-cc').value.trim();
      var bcc        = document.getElementById('md-rule-bcc').value.trim();
      var enabled    = document.getElementById('md-rule-enabled').checked ? '1' : '';

      var resultEl = document.getElementById('md-rule-form-result');

      if (!label) {
        showFormResult(resultEl, 'Le libellé est obligatoire.', false);
        return;
      }
      if (!recipients) {
        showFormResult(resultEl, 'Au moins un destinataire est requis.', false);
        return;
      }

      // Validate email lines
      var recipLines = recipients.split('\n').map(function (r) { return r.trim(); }).filter(Boolean);
      var invalid = recipLines.filter(function (r) { return !isValidEmail(r); });
      if (invalid.length) {
        showFormResult(resultEl, 'Email invalide : ' + invalid.join(', '), false);
        return;
      }

      saveBtn.disabled    = true;
      saveBtn.textContent = 'Enregistrement…';

      post('md_save_routing_rule', {
        id:           id,
        label:        label,
        request_type: type,
        recipients:   recipLines.join('\n'),
        cc:           cc,
        bcc:          bcc,
        enabled:      enabled,
      }, function (err, res) {
        saveBtn.disabled    = false;
        saveBtn.textContent = 'Enregistrer';

        if (err || !res.success) {
          var msg = res && res.data && res.data.message ? res.data.message : 'Erreur lors de l\'enregistrement.';
          showFormResult(resultEl, msg, false);
          return;
        }

        showFormResult(resultEl, 'Enregistré.', true);
        // Reload page to reflect changes
        setTimeout(function () { window.location.reload(); }, 800);
      });
    });
  }

  // -------------------------------------------------------------------------
  // Cancel form
  // -------------------------------------------------------------------------
  var cancelBtn = document.getElementById('md-rule-cancel-btn');
  if (cancelBtn) {
    cancelBtn.addEventListener('click', function () {
      hideRuleForm();
    });
  }

  // -------------------------------------------------------------------------
  // Helpers
  // -------------------------------------------------------------------------
  function showRuleForm() {
    var wrap = document.getElementById('md-rule-form-wrap');
    if (wrap) {
      wrap.style.display = '';
      wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  function hideRuleForm() {
    var wrap = document.getElementById('md-rule-form-wrap');
    if (wrap) wrap.style.display = 'none';
  }

  function resetRuleForm() {
    document.getElementById('md-rule-id').value            = '';
    document.getElementById('md-rule-label').value         = '';
    document.getElementById('md-rule-type').value          = 'demo_submission';
    document.getElementById('md-rule-recipients').value    = '';
    document.getElementById('md-rule-cc').value            = '';
    document.getElementById('md-rule-bcc').value           = '';
    document.getElementById('md-rule-enabled').checked     = true;
    var resultEl = document.getElementById('md-rule-form-result');
    if (resultEl) { resultEl.textContent = ''; resultEl.className = 'md-form-result'; }
  }

  function showFormResult(el, msg, ok) {
    if (!el) return;
    el.textContent = msg;
    el.className   = 'md-form-result ' + (ok ? 'success' : 'error');
  }

  function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }
}());
