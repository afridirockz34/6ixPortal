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
