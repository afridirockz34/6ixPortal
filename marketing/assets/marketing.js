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
