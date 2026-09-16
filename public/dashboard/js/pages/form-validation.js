// Client-side validation for admin forms marked `needs-validation`.
//
// These forms carry `novalidate`, which suppresses the browser's own prompt,
// and this script used to cancel an invalid submit and then say nothing at all.
// The button simply looked dead. That is how an approved admission could not be
// converted: the Batch select was empty — it is empty on every application —
// the submit was cancelled before any request was made, and nothing appeared on
// screen or in the log to say why.
//
// So a cancelled submit now points at the field that stopped it: brought into
// view, focused, and explained by the browser itself.
(function () {
  'use strict';

  // The first field holding the form up. A visible one is preferred: a control
  // in a collapsed section or a closed modal cannot be shown to anybody.
  function firstInvalid(form) {
    var elements = form.elements;
    var fallback = null;

    for (var i = 0; i < elements.length; i++) {
      var element = elements[i];

      if (!element.willValidate || element.checkValidity()) {
        continue;
      }

      if (!fallback) {
        fallback = element;
      }

      if (element.offsetParent !== null) {
        return element;
      }
    }

    return fallback;
  }

  function reportFirstInvalid(form) {
    var invalid = firstInvalid(form);

    if (!invalid) {
      return;
    }

    // Inside a scrolled modal the field is often out of sight.
    if (typeof invalid.scrollIntoView === 'function') {
      invalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    try {
      invalid.focus({ preventScroll: true });
    } catch (error) {
      invalid.focus();
    }

    // novalidate silenced this, so ask for it explicitly.
    if (typeof invalid.reportValidity === 'function') {
      invalid.reportValidity();
    }
  }

  window.addEventListener('load', function () {
    // Fetch all the forms we want to apply custom Bootstrap validation styles to
    var forms = document.getElementsByClassName('needs-validation');

    // Loop over them and prevent submission
    Array.prototype.filter.call(forms, function (form) {
      form.addEventListener('submit', function (event) {
        if (form.checkValidity() === false) {
          event.preventDefault();
          event.stopPropagation();
          reportFirstInvalid(form);
        }

        form.classList.add('was-validated');
      }, false);
    });
  }, false);
})();
