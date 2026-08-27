/**
 * Mango Dragon — Forms (AJAX upload + EmailJS fallback)
 */
(function () {
  'use strict';

  var MAX_FILE_SIZE = 300 * 1024 * 1024; // 300 Mo (plafond serveur ; les gros fichiers vont sur kDrive)
  var ALLOWED_EXT = ['.wav', '.mp3', '.flac', '.aiff', '.zip', '.rar'];

  function isValidEmail(e) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e); }

  function showMsg(el, text, type) {
    if (!el) return;
    el.textContent = text;
    el.className = 'form-message form-message--' + type;
    el.style.display = '';
  }

  /* --- Upload zone (drag & drop + click) --- */
  function initUploadZones() {
    document.querySelectorAll('.upload-zone').forEach(function (zone) {
      if (zone._mdBound) return;
      zone._mdBound = true;

      var input = zone.querySelector('input[type="file"]');
      var listEl = zone.parentElement.querySelector('.upload-file-list');
      if (!input || !listEl) return;

      zone.addEventListener('click', function () { input.click(); });

      zone.addEventListener('dragover', function (e) {
        e.preventDefault();
        zone.classList.add('dragover');
      });
      zone.addEventListener('dragleave', function () {
        zone.classList.remove('dragover');
      });
      zone.addEventListener('drop', function (e) {
        e.preventDefault();
        zone.classList.remove('dragover');
        if (e.dataTransfer.files.length) {
          input.files = e.dataTransfer.files;
          input.dispatchEvent(new Event('change'));
        }
      });

      input.addEventListener('change', function () {
        listEl.innerHTML = '';
        if (!input.files.length) return;

        var err = '';
        Array.prototype.forEach.call(input.files, function (file) {
          var ext = '.' + file.name.split('.').pop().toLowerCase();
          if (ALLOWED_EXT.indexOf(ext) === -1) { err = 'Unsupported format. Please use: ' + ALLOWED_EXT.join(', '); return; }
          if (file.size > MAX_FILE_SIZE) { err = 'File too large (300 MB max). Please use a share link.'; return; }
          var item = document.createElement('div');
          item.className = 'upload-file-item';
          item.innerHTML = '<span>' + file.name + ' (' + (file.size / 1024 / 1024).toFixed(1) + ' MB)</span>';
          listEl.appendChild(item);
        });

        if (err) {
          listEl.innerHTML = '<p class="form-message form-message--error" style="display:block;">' + err + '</p>';
          input.value = '';
          return;
        }

        var clear = document.createElement('span');
        clear.className = 'upload-file-remove';
        clear.textContent = 'Remove all';
        clear.addEventListener('click', function () { input.value = ''; listEl.innerHTML = ''; });
        listEl.appendChild(clear);
      });
    });
  }

  /* --- Form init --- */
  function initForms() {
    document.querySelectorAll('.upload-form').forEach(function (form) {
      if (form._mdBound) return;
      form._mdBound = true;

      var submitBtn = form.querySelector('[type="submit"]');
      if (submitBtn) submitBtn.dataset.originalText = submitBtn.textContent;

      form.addEventListener('submit', function (e) {
        e.preventDefault();
        handleSubmit(form);
      });
    });
  }

  /* --- Form submit --- */
  function handleSubmit(form) {
    var email   = form.querySelector('[name="user_email"]');
    var project = form.querySelector('[name="project_name"]');
    var fileInput = form.querySelector('[name="demo_file"]');
    var msgEl   = form.querySelector('.form-message');
    var btn     = form.querySelector('[type="submit"]');

    if (!email || !email.value || !isValidEmail(email.value)) {
      showMsg(msgEl, 'Please enter a valid email address.', 'error');
      if (email) email.focus();
      return;
    }
    // Le champ « projet » peut être masqué ou non-obligatoire (config par page) :
    // on ne valide que s'il est présent ET marqué obligatoire.
    if (project && project.required && !project.value.trim()) {
      showMsg(msgEl, 'Please enter the project name.', 'error');
      project.focus();
      return;
    }

    btn.disabled = true;
    btn.textContent = 'Sending\u2026';

    var done = function (ok) {
      if (ok) {
        showMsg(msgEl, 'Message sent successfully! We\u2019ll get back to you soon.', 'success');
        form.reset();
        var listEl = form.querySelector('.upload-file-list');
        if (listEl) listEl.innerHTML = '';
      } else {
        showMsg(msgEl, 'Something went wrong. Please try again or email us at contact@mango-dragon.com', 'error');
      }
      btn.disabled = false;
      btn.textContent = btn.dataset.originalText || 'Send';
    };

    /* Send via WordPress AJAX (supports file upload) */
    if (typeof mdData !== 'undefined' && mdData.ajaxUrl) {
      var fd = new FormData(form);
      fd.append('action', 'md_contact_form');
      fd.append('nonce', mdData.nonce);

      var xhr = new XMLHttpRequest();
      xhr.open('POST', mdData.ajaxUrl);
      xhr.onload = function () {
        var res;
        try { res = JSON.parse(xhr.responseText); } catch (e) { res = null; }
        done(res && res.success);
      };
      xhr.onerror = function () { done(false); };
      xhr.send(fd);
    } else if (typeof emailjs !== 'undefined') {
      emailjs.sendForm('YOUR_SERVICE_ID', 'YOUR_TEMPLATE_ID', form)
        .then(function () { done(true); })
        .catch(function () { done(false); });
    } else {
      console.info('[MDForms] Data:', Object.fromEntries(new FormData(form)));
      setTimeout(function () { done(true); }, 600);
    }
  }

  initForms();
  initUploadZones();
  window.mdInitForms = function () { initForms(); initUploadZones(); };
})();
