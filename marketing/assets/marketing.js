/**
 * 6ix Developers — marketing motion.
 * Subtle, professional scroll-reveal for content blocks. Respects
 * prefers-reduced-motion and degrades gracefully without IntersectionObserver.
 */
(function () {
  'use strict';
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var sel = [
    '.mk-section .mk-card',
    '.mk-sec-head',
    '.mk-narrative',
    '.mk-team-card',
    '.mk-included-card',
    '.mk-grow-left',
    '.mk-formwrap',
    '.mk-portal-band',
    '.mk-highlight',
    '.mk-dd-card',
    '.mk-audit-item',
    '.mk-offer-card',
    '.mk-logo-item'
  ].join(',');

  function run() {
    var els = Array.prototype.slice.call(document.querySelectorAll(sel));
    if (!els.length) return;

    if (reduce || !('IntersectionObserver' in window)) {
      els.forEach(function (el) { el.classList.add('mk-in'); });
      return;
    }

    els.forEach(function (el) {
      el.classList.add('mk-reveal');
      // Stagger siblings inside the same grid/row for a gentle cascade.
      var parent = el.parentNode;
      if (parent && parent.children && parent.children.length > 1) {
        var idx = Array.prototype.indexOf.call(parent.children, el);
        el.style.transitionDelay = Math.min(idx % 4, 3) * 70 + 'ms';
      }
    });

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add('mk-in'); io.unobserve(e.target); }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -48px 0px' });

    els.forEach(function (el) { io.observe(el); });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
})();

/**
 * Balanced masonry for the gradient feature-card sections.
 * Distributes each card into the currently-shortest column so the two columns
 * end at roughly the same height — no big empty gap on one side. Cards keep
 * their natural height (they are NOT forced equal). Falls back to the CSS
 * column layout if JS is unavailable.
 */
(function () {
  'use strict';
  function balance() {
    var grids = document.querySelectorAll('.mk-feature-grid');
    Array.prototype.forEach.call(grids, function (grid) {
      if (!grid.__cards) {
        grid.__cards = Array.prototype.filter.call(grid.children, function (c) {
          return c.classList && c.classList.contains('mk-feature-card');
        });
      }
      var cards = grid.__cards;
      if (!cards.length) return;

      var twoCol = window.innerWidth > 760;
      // Detach cards, then re-lay them out.
      cards.forEach(function (c) { if (c.parentNode) c.parentNode.removeChild(c); });
      grid.innerHTML = '';

      if (!twoCol) {
        grid.classList.remove('mk-fg-js');
        cards.forEach(function (c) { grid.appendChild(c); });
        return;
      }

      grid.classList.add('mk-fg-js');
      var c1 = document.createElement('div'), c2 = document.createElement('div');
      c1.className = 'mk-feature-col'; c2.className = 'mk-feature-col';
      grid.appendChild(c1); grid.appendChild(c2);

      var h1 = 0, h2 = 0;
      cards.forEach(function (card) {
        var target = (h1 <= h2) ? c1 : c2;
        target.appendChild(card);
        var hh = card.getBoundingClientRect().height;
        if (target === c1) h1 += hh; else h2 += hh;
      });
    });
  }

  var t;
  function onResize() { clearTimeout(t); t = setTimeout(balance, 150); }

  function init() { balance(); window.addEventListener('resize', onResize); window.addEventListener('load', balance); }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();

/**
 * Multi-step lead forms (six_forms_render()'s per-field "step" grouping,
 * portal/class-forms.php) — mirrors the original site's step-by-step
 * Eligibility/Audit forms:
 * only the active step's fields are shown, "Next" validates the active
 * step's fields (native HTML5 checkValidity/reportValidity) before
 * advancing, "Previous" goes back with no validation. The real submit
 * button lives inside the final step (server-rendered), so there's no fake
 * "submit step" to fake here — Next is simply hidden once that step is
 * reached.
 */
(function () {
  'use strict';
  function setup(form) {
    var steps = Array.prototype.slice.call(form.querySelectorAll('.mk-form-step'));
    var nav = form.querySelector('.mk-form-stepnav');
    if (steps.length < 2 || !nav) return;
    var prev = nav.querySelector('.mk-step-prev');
    var next = nav.querySelector('.mk-step-next');
    if (!prev || !next) return;
    var active = 0;

    function show(i) {
      steps.forEach(function (s, idx) { s.classList.toggle('mk-form-step-active', idx === i); });
      prev.disabled = (i === 0);
      next.style.display = (i === steps.length - 1) ? 'none' : '';
      active = i;
    }

    function activeStepInvalid() {
      var els = Array.prototype.slice.call(steps[active].querySelectorAll('input, select, textarea'));
      for (var i = 0; i < els.length; i++) {
        if (!els[i].checkValidity()) { els[i].reportValidity(); return true; }
      }
      return false;
    }

    next.addEventListener('click', function () {
      if (activeStepInvalid()) return;
      if (active < steps.length - 1) show(active + 1);
    });
    prev.addEventListener('click', function () { if (active > 0) show(active - 1); });

    show(0);
  }

  function init() {
    Array.prototype.forEach.call(document.querySelectorAll('form.mk-form'), setup);
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();

/**
 * Native AJAX submission for every form rendered by the forms system
 * (data-mk-ajax, set by six_forms_render() in portal/class-forms.php) —
 * posts to six_forms_handle_submit() (portal/class-forms-submit.php),
 * which logs the submission and emails it via wp_mail(). On success,
 * either redirects to the form's configured redirect URL or replaces the
 * form with a plain confirmation message (the original site's
 * .success-message pattern); surfaces the server's error message and
 * re-enables the button otherwise.
 */
(function () {
  'use strict';
  function setup(form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = form.querySelector('.mk-form-submit');
      var err = form.querySelector('.mk-form-error');
      if (!err) {
        err = document.createElement('p');
        err.className = 'mk-form-error';
        err.setAttribute('role', 'alert');
        var actions = form.querySelector('.mk-form-actions');
        (actions || form).appendChild(err);
      }
      err.hidden = true;
      err.textContent = '';
      if (btn) {
        btn.disabled = true;
        btn.dataset.mkLabel = btn.dataset.mkLabel || btn.textContent;
        btn.textContent = 'Sending…';
      }

      var url = (window.sixMkAjax && window.sixMkAjax.url) || '/wp-admin/admin-ajax.php';
      var fd = new FormData(form);
      fd.append('action', 'six_forms_submit');

      fetch(url, { method: 'POST', credentials: 'same-origin', body: fd })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res && res.success) {
            if (res.data && res.data.redirect) { window.location.href = res.data.redirect; return; }
            var wrap = form.closest('.mk-formwrap');
            var msg = document.createElement('div');
            msg.className = 'mk-form-success';
            msg.textContent = (res.data && res.data.message) || "Thanks — we've received your submission.";
            if (wrap) { wrap.innerHTML = ''; wrap.appendChild(msg); }
          } else {
            err.textContent = (res && res.data && res.data.message) || 'Something went wrong — please try again.';
            err.hidden = false;
            if (btn) { btn.disabled = false; btn.textContent = btn.dataset.mkLabel; }
          }
        })
        .catch(function () {
          err.textContent = 'Something went wrong — please try again, or call us directly.';
          err.hidden = false;
          if (btn) { btn.disabled = false; btn.textContent = btn.dataset.mkLabel; }
        });
    });
  }

  function init() {
    Array.prototype.forEach.call(document.querySelectorAll('form.mk-form[data-mk-ajax]'), setup);
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
