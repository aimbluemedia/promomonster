/* PromoMonster — progressive enhancement only.
 *
 * Nothing here is required for the page to work. The calculator renders correct
 * server-side defaults, and reveal animations are opt-in via a class this file
 * adds, so a failure to load leaves a complete, readable page rather than a
 * blank one.
 */
(function () {
  'use strict';

  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---- Scroll reveal --------------------------------------------------- */
  /* Deliberately NOT driven by IntersectionObserver alone.
   *
   * Hiding content and relying on a callback to bring it back is a bad trade:
   * if the callback is coalesced away — which is exactly what happens under
   * fast programmatic scrolling — whole sections stay at opacity 0 and the
   * visitor sees a blank page. A rAF-throttled rect check is a few more lines
   * and cannot miss. There is also a hard timeout that reveals everything
   * regardless, so the worst case is "the animation did not play", never
   * "the content is gone".
   */
  function reveals() {
    var items = Array.prototype.slice.call(document.querySelectorAll('[data-reveal]'));
    if (!items.length || reduced) return;

    // Only hide once we are certain we can show again.
    document.documentElement.classList.add('js-reveal');

    var pending = items;
    var ticking = false;

    function showAll() {
      pending.forEach(function (el) { el.classList.add('is-in'); });
      pending = [];
      window.removeEventListener('scroll', onScroll);
      window.removeEventListener('resize', onScroll);
    }

    function check() {
      ticking = false;
      var limit = window.innerHeight * 0.92;
      pending = pending.filter(function (el) {
        if (el.getBoundingClientRect().top < limit) {
          el.classList.add('is-in');
          return false;
        }
        return true;
      });
      if (!pending.length) {
        window.removeEventListener('scroll', onScroll);
        window.removeEventListener('resize', onScroll);
      }
    }

    function onScroll() {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(check);
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll);
    check();

    // Failsafe. If anything above goes wrong, nothing stays invisible.
    setTimeout(showAll, 8000);
  }

  /* ---- Count-up ---------------------------------------------------------- */
  function countUp(el, to, duration) {
    if (reduced) { el.textContent = format(to); return; }

    var from = 0;
    var start = null;
    function step(now) {
      if (start === null) start = now;
      var p = Math.min(1, (now - start) / duration);
      // easeOutCubic: fast first, settles on the number rather than snapping.
      var eased = 1 - Math.pow(1 - p, 3);
      el.textContent = format(Math.round(from + (to - from) * eased));
      if (p < 1) requestAnimationFrame(step);
    }
    requestAnimationFrame(step);
  }

  function counters() {
    var els = document.querySelectorAll('[data-count]');
    if (!els.length || !('IntersectionObserver' in window)) return;

    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        var el = entry.target;
        countUp(el, parseFloat(el.getAttribute('data-count')), 1100);
        io.unobserve(el);
      });
    }, { threshold: 0.5 });

    els.forEach(function (el) { io.observe(el); });
  }

  function format(n) { return n.toLocaleString('en-US'); }

  function roundTo(value, places) {
    var f = Math.pow(10, places);
    return Math.round(value * f) / f;
  }

  /* ---- Review calculator -------------------------------------------------- */
  /* To lift an average of `a` over `n` reviews to `t` using five-star reviews:
   *     k = n(t - a) / (5 - t)
   * Exact, not modelled. At t = 5 it is unreachable unless you are already at 5,
   * because a five-star review cannot pull an average above five. */
  var RESPONSE_RATE = 0.25;

  function calculator(root) {
    var el = {
      rating:   root.querySelector('#calc-rating'),
      reviews:  root.querySelector('#calc-reviews'),
      target:   root.querySelector('#calc-target'),
      jobs:     root.querySelector('#calc-jobs'),
      needed:   root.querySelector('[data-calc-needed]'),
      unit:     root.querySelector('[data-calc-unit]'),
      now:      root.querySelector('[data-calc-now]'),
      goal:     root.querySelector('[data-calc-goal]'),
      starsNow: root.querySelector('[data-calc-stars-now]'),
      starsTgt: root.querySelector('[data-calc-stars-target]'),
      requests: root.querySelector('[data-calc-requests]'),
      months:   root.querySelector('[data-calc-months]'),
      total:    root.querySelector('[data-calc-total]'),
      delta:    root.querySelector('[data-calc-delta]'),
      result:   root.querySelector('[data-calc-result]')
    };
    if (!el.rating || !el.needed) return;

    var outs = {
      rating:  root.querySelector('#calc-rating-out'),
      reviews: root.querySelector('#calc-reviews-out'),
      target:  root.querySelector('#calc-target-out'),
      jobs:    root.querySelector('#calc-jobs-out')
    };

    function paintTrack(input) {
      var min = parseFloat(input.min), max = parseFloat(input.max);
      var pct = ((parseFloat(input.value) - min) / (max - min)) * 100;
      input.style.setProperty('--pct', pct + '%');
    }

    function render() {
      var a = parseFloat(el.rating.value);
      var n = parseInt(el.reviews.value, 10);
      var t = parseFloat(el.target.value);
      var jobs = parseInt(el.jobs.value, 10);

      outs.rating.textContent  = a.toFixed(1);
      outs.reviews.textContent = format(n);
      outs.target.textContent  = t.toFixed(1);
      outs.jobs.textContent    = format(jobs);
      [el.rating, el.reviews, el.target, el.jobs].forEach(paintTrack);

      el.now.textContent  = a.toFixed(1);
      el.goal.textContent = t.toFixed(1);
      el.starsNow.style.setProperty('--fill', roundTo(a / 5 * 100, 2) + '%');
      el.starsTgt.style.setProperty('--fill', roundTo(t / 5 * 100, 2) + '%');

      // Already there, or asking for less than you have.
      if (t <= a) {
        el.needed.textContent = '0';
        el.unit.textContent = 'you are already at or above ' + t.toFixed(1) + ' stars';
        el.requests.textContent = '0';
        el.months.textContent = '—';
        el.total.textContent = format(n);
        el.delta.textContent = '';
        return;
      }

      // A five-star review cannot lift an average to a full 5 unless every
      // review is already five. Say so rather than printing Infinity.
      if (t >= 5 && a < 5) {
        el.needed.textContent = '∞';
        el.unit.textContent = 'a perfect 5.0 is unreachable while any review below 5 stands';
        el.requests.textContent = '—';
        el.months.textContent = '—';
        el.total.textContent = format(n);
        el.delta.textContent = '';
        return;
      }

      // Round before ceiling. The sliders step in tenths, and in binary
      // 4.9 - 3.0 is 1.9000000000000004 while 5 - 4.9 is 0.09999999999999964,
      // so the exact answer 19 arrives as 19.00000000000007 and ceil() reports
      // 20. One review wrong is a number a customer can catch us on.
      var needed = Math.ceil(roundTo((n * (t - a)) / (5 - t), 6));
      var requests = Math.ceil(needed / RESPONSE_RATE);
      var months = Math.max(1, Math.ceil(requests / jobs));

      el.needed.textContent = format(needed);
      el.unit.textContent = 'to go from ' + a.toFixed(1) + ' to ' + t.toFixed(1) + ' stars';
      el.requests.textContent = format(requests);
      el.months.textContent = format(months) + (months === 1 ? ' month' : ' months');
      el.total.textContent = format(n + needed);
      el.delta.textContent = '+' + format(needed);
    }

    [el.rating, el.reviews, el.target, el.jobs].forEach(function (input) {
      input.addEventListener('input', render);
      paintTrack(input);
    });

    render();

    // Count the headline number up once, when the panel first comes into view.
    // After that every update is instant — animating a number while someone is
    // dragging the slider that produces it just feels broken.
    if (!reduced && 'IntersectionObserver' in window && el.result) {
      var once = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (!entry.isIntersecting) return;
          once.disconnect();
          var target = parseInt(el.needed.textContent.replace(/[^0-9]/g, ''), 10);
          if (!isNaN(target) && target > 0) countUp(el.needed, target, 950);
        });
      }, { threshold: 0.4 });
      once.observe(el.result);
    }
  }

  function init() {
    reveals();
    counters();
    document.querySelectorAll('[data-calc]').forEach(function (root) { calculator(root); });
  }

  function boot() {
    try {
      init();
    } catch (e) {
      // A broken enhancement must never cost the visitor the page.
      document.documentElement.classList.remove('js-reveal');
      document.querySelectorAll('[data-reveal]').forEach(function (el) {
        el.classList.add('is-in');
      });
      if (window.console) console.error(e);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
