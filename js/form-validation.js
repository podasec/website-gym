/**
 * POWER GYM - Form Validation
 * form-validation.js
 *
 * Features:
 *  - Real-time validation on input/blur
 *  - Regex validation for email and phone
 *  - Inline error messages
 *  - Visual feedback (border red/green)
 *  - Submit handler with success message
 *  - Supports: contact form, CTA form, login form
 */

'use strict';

/* ============================================================
   VALIDATION RULES
   ============================================================ */
const RULES = {
  required: {
    test: function (v) { return v.trim().length > 0; },
    message: 'Questo campo è obbligatorio.',
  },
  email: {
    test: function (v) {
      return /^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/.test(v.trim());
    },
    message: 'Inserisci un indirizzo email valido.',
  },
  phone: {
    test: function (v) {
      // Accepts: +39 02 1234567, 02-1234567, 3391234567, etc.
      return /^[+]?[\s./0-9\-]{8,20}$/.test(v.trim());
    },
    message: 'Inserisci un numero di telefono valido.',
  },
  minLength: function (min) {
    return {
      test: function (v) { return v.trim().length >= min; },
      message: 'Minimo ' + min + ' caratteri richiesti.',
    };
  },
  maxLength: function (max) {
    return {
      test: function (v) { return v.trim().length <= max; },
      message: 'Massimo ' + max + ' caratteri consentiti.',
    };
  },
  password: {
    test: function (v) { return v.length >= 6; },
    message: 'La password deve essere di almeno 6 caratteri.',
  },
};

/* ============================================================
   CORE VALIDATION ENGINE
   ============================================================ */

/**
 * Validate a single field element.
 * Reads `data-rules` attribute (comma-separated rule names).
 * Returns true if valid, false if invalid.
 *
 * @param {HTMLInputElement|HTMLTextAreaElement|HTMLSelectElement} field
 * @returns {boolean}
 */
function validateField(field) {
  const rulesAttr = field.getAttribute('data-rules') || '';
  const ruleNames = rulesAttr.split(',').map(r => r.trim()).filter(Boolean);
  const value     = field.value;
  const errorEl   = getErrorElement(field);

  for (const ruleName of ruleNames) {
    let rule;

    if (ruleName === 'required') {
      rule = RULES.required;
    } else if (ruleName === 'email') {
      rule = RULES.email;
    } else if (ruleName === 'phone') {
      rule = RULES.phone;
    } else if (ruleName === 'password') {
      rule = RULES.password;
    } else if (ruleName.startsWith('minLength:')) {
      const min = parseInt(ruleName.split(':')[1], 10);
      rule = RULES.minLength(min);
    } else if (ruleName.startsWith('maxLength:')) {
      const max = parseInt(ruleName.split(':')[1], 10);
      rule = RULES.maxLength(max);
    } else {
      continue; // Unknown rule, skip
    }

    if (!rule.test(value)) {
      setInvalid(field, errorEl, rule.message);
      return false;
    }
  }

  // Special case: phone field can be empty if not required
  if (ruleNames.length === 0 || (ruleNames.length === 1 && ruleNames[0] === 'phone' && value.trim() === '')) {
    clearValidation(field, errorEl);
    return true;
  }

  setValid(field, errorEl);
  return true;
}

/**
 * Find or create the error message element for a field.
 */
function getErrorElement(field) {
  const group = field.closest('.form-group');
  if (!group) return null;

  let errorEl = group.querySelector('.form-error');
  if (!errorEl) {
    errorEl = document.createElement('span');
    errorEl.classList.add('form-error');
    errorEl.setAttribute('role', 'alert');
    errorEl.setAttribute('aria-live', 'polite');
    group.appendChild(errorEl);
  }
  return errorEl;
}

function setInvalid(field, errorEl, message) {
  field.classList.add('invalid');
  field.classList.remove('valid');
  field.setAttribute('aria-invalid', 'true');
  if (errorEl) errorEl.textContent = message;
}

function setValid(field, errorEl) {
  field.classList.add('valid');
  field.classList.remove('invalid');
  field.setAttribute('aria-invalid', 'false');
  if (errorEl) errorEl.textContent = '';
}

function clearValidation(field, errorEl) {
  field.classList.remove('valid', 'invalid');
  field.removeAttribute('aria-invalid');
  if (errorEl) errorEl.textContent = '';
}

/* ============================================================
   FORM INITIALIZATION
   ============================================================ */

/**
 * Bind validation events to all validatable fields in a form.
 * @param {HTMLFormElement} form
 */
function bindValidation(form) {
  const fields = form.querySelectorAll('input[data-rules], textarea[data-rules], select[data-rules]');

  fields.forEach(function (field) {
    // Real-time: validate on input (debounced)
    let inputTimer;
    field.addEventListener('input', function () {
      clearTimeout(inputTimer);
      inputTimer = setTimeout(function () {
        validateField(field);
      }, 350);
    });

    // Validate on blur immediately
    field.addEventListener('blur', function () {
      clearTimeout(inputTimer);
      if (field.value.trim() !== '' || field.classList.contains('invalid')) {
        validateField(field);
      }
    });

    // Clear invalid state on focus if empty (fresh start)
    field.addEventListener('focus', function () {
      if (field.value.trim() === '') {
        const errorEl = getErrorElement(field);
        clearValidation(field, errorEl);
      }
    });
  });
}

/**
 * Validate all fields in a form and return overall validity.
 * @param {HTMLFormElement} form
 * @returns {boolean}
 */
function validateForm(form) {
  const fields = form.querySelectorAll('input[data-rules], textarea[data-rules], select[data-rules]');
  let isValid = true;

  fields.forEach(function (field) {
    if (!validateField(field)) {
      isValid = false;
    }
  });

  return isValid;
}

/**
 * Show success message for a form.
 * @param {HTMLFormElement} form
 * @param {string} message
 */
function showSuccess(form, message) {
  let successEl = form.querySelector('.success-message');

  if (!successEl) {
    successEl = document.createElement('div');
    successEl.classList.add('success-message');
    form.appendChild(successEl);
  }

  successEl.textContent = message || 'Messaggio inviato con successo! Ti contatteremo presto.';
  successEl.classList.add('show');

  // Scroll to success message
  successEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

  // Auto-hide after 6s
  setTimeout(function () {
    successEl.classList.remove('show');
  }, 6000);
}

/**
 * Reset a form: clear all values, validation states, success messages.
 * @param {HTMLFormElement} form
 */
function resetForm(form) {
  form.reset();
  const fields = form.querySelectorAll('input, textarea, select');
  fields.forEach(function (field) {
    const errorEl = getErrorElement(field);
    clearValidation(field, errorEl);
  });
  const successEl = form.querySelector('.success-message');
  if (successEl) successEl.classList.remove('show');
}

/* ============================================================
   FORM-SPECIFIC HANDLERS
   ============================================================ */

/* --- Contact Form --- */
(function initContactForm() {
  const form = document.querySelector('#contact-form');
  if (!form) return;

  bindValidation(form);

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    if (!validateForm(form)) {
      // Focus first invalid field
      const firstInvalid = form.querySelector('.invalid');
      if (firstInvalid) firstInvalid.focus();
      return;
    }

    // Simulate async submission
    const submitBtn = form.querySelector('[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Invio in corso...';

    setTimeout(function () {
      submitBtn.disabled = false;
      submitBtn.textContent = originalText;
      showSuccess(form, '✓ Messaggio inviato! Ti risponderemo entro 24 ore.');
      resetForm(form);
    }, 1200);
  });
})();

/* --- CTA Inline Form (index.html) --- */
(function initCtaForm() {
  const form = document.querySelector('#cta-form');
  if (!form) return;

  bindValidation(form);

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    if (!validateForm(form)) {
      const firstInvalid = form.querySelector('.invalid');
      if (firstInvalid) firstInvalid.focus();
      return;
    }

    const submitBtn = form.querySelector('[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Registrazione...';

    setTimeout(function () {
      submitBtn.disabled = false;
      submitBtn.textContent = originalText;
      showSuccess(form, '✓ Ottimo! Sarai ricontattato entro breve per la tua prova gratuita.');
      resetForm(form);
    }, 1000);
  });
})();

/* --- Login Form --- */
(function initLoginForm() {
  const form = document.querySelector('#login-form');
  if (!form) return;

  bindValidation(form);

  form.addEventListener('submit', function (e) {
    e.preventDefault();

    if (!validateForm(form)) {
      const firstInvalid = form.querySelector('.invalid');
      if (firstInvalid) firstInvalid.focus();
      return;
    }

    const submitBtn = form.querySelector('[type="submit"]');
    const originalText = submitBtn.textContent;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Accesso in corso...';

    // Simulate async authentication
    setTimeout(function () {
      submitBtn.disabled = false;
      submitBtn.textContent = originalText;

      // In a real app, redirect on success or show error
      showSuccess(form, '✓ Accesso effettuato! Reindirizzamento in corso...');
    }, 1200);
  });
})();

/* --- Generic forms with class .validatable-form --- */
(function initGenericForms() {
  const forms = document.querySelectorAll('form.validatable-form');

  forms.forEach(function (form) {
    // Skip already-initialized forms
    if (form.id && ['contact-form', 'cta-form', 'login-form'].includes(form.id)) return;

    bindValidation(form);

    form.addEventListener('submit', function (e) {
      e.preventDefault();

      if (!validateForm(form)) {
        const firstInvalid = form.querySelector('.invalid');
        if (firstInvalid) firstInvalid.focus();
        return;
      }

      const submitBtn = form.querySelector('[type="submit"]');
      if (submitBtn) {
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Invio...';

        setTimeout(function () {
          submitBtn.disabled = false;
          submitBtn.textContent = originalText;
          showSuccess(form, '✓ Richiesta inviata con successo!');
          resetForm(form);
        }, 1000);
      }
    });
  });
})();
