/**
 * Mango Dragon — Forms (EmailJS integration)
 */
(function () {
  'use strict';

  function isValidEmail(e) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e); }

  function showMsg(el, text, type) {
    if (!el) return;
    el.textContent = text;
    el.className = 'form-message form-message--' + type;
    el.style.display = '';
  }

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

  function handleSubmit(form) {
    var email   = form.querySelector('[name="user_email"]');
    var project = form.querySelector('[name="project_name"]');
    var msgEl   = form.querySelector('.form-message');
    var btn     = form.querySelector('[type="submit"]');

    if (!email || !email.value || !isValidEmail(email.value)) {
      showMsg(msgEl, 'Veuillez entrer un email valide.', 'error');
      if (email) email.focus();
      return;
    }
    if (!project || !project.value.trim()) {
      showMsg(msgEl, 'Veuillez entrer le nom du projet.', 'error');
      project.focus();
      return;
    }

    btn.disabled = true;
    btn.textContent = 'Envoi en cours\u2026';

    var done = function (ok) {
      if (ok) {
        showMsg(msgEl, 'Message envoy\u00e9 avec succ\u00e8s\u00a0! Nous vous r\u00e9pondrons rapidement.', 'success');
        form.reset();
      } else {
        showMsg(msgEl, 'Erreur lors de l\u2019envoi. Veuillez r\u00e9essayer ou nous \u00e9crire \u00e0 contact@mangodragon.ch', 'error');
      }
      btn.disabled = false;
      btn.textContent = btn.dataset.originalText || 'Envoyer';
    };

    if (typeof emailjs !== 'undefined') {
      emailjs.sendForm('YOUR_SERVICE_ID', 'YOUR_TEMPLATE_ID', form)
        .then(function () { done(true); })
        .catch(function () { done(false); });
    } else {
      /* Dev fallback — log form data */
      console.info('[MDForms] EmailJS not loaded. Data:', Object.fromEntries(new FormData(form)));
      setTimeout(function () { done(true); }, 600);
    }
  }

  initForms();
  window.mdInitForms = initForms;
})();
