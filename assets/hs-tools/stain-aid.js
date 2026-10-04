/* أداة: إسعافات البقع — تحسين تفاعلي فوق دليل HTML كامل (الدليل يعمل بدون JS). */
(function () {
  'use strict';
  var L = window.HSLead;
  function init(root) {
    var D = L.data(root), stain = '', fab = '';
    var sections = [].slice.call(root.querySelectorAll('.hs-stain')), fabQ = L.$('[data-q=fabric]', root), after = L.$('.hs-after', root), timer = L.$('.hs-timer', root), clock = L.$('.hs-clock', root), tStart = L.$('[data-tstart]', root);
    root.classList.add('hs-js');
    var rules = L.$('.hs-rules', root), fabSec = L.$('.hs-fabrics', root);

    // checkboxes + per-step timer buttons
    sections.forEach(function (s) {
      [].forEach.call(s.querySelectorAll('.hs-stepslist li'), function (li, i) {
        if (li.__hs) { return; } li.__hs = true;
        var id = 'hs-' + s.id + '-' + i, txt = li.innerHTML;
        li.innerHTML = '<label><input type="checkbox" id="' + id + '"><span>' + txt + '</span></label>';
        var min = +li.getAttribute('data-min');
        if (min) { var b = document.createElement('button'); b.type = 'button'; b.className = 'hs-mini'; b.textContent = 'مؤقت ' + min + ' د'; b.setAttribute('data-tmin', min); li.appendChild(b); }
      });
    });

    function select(id, push) {
      if (!D.stains[id]) { return; }
      stain = id;
      [].forEach.call(root.querySelectorAll('[data-q=stain] .hs-opt'), function (a) { var on = a.getAttribute('data-v') === id; a.classList.toggle('is-on', on); a.setAttribute('aria-pressed', on); });
      sections.forEach(function (s) { s.hidden = s.id !== id; });
      fabQ.hidden = false; after.hidden = false; timer.hidden = false; applyFab();
      if (push && history.replaceState) { history.replaceState(null, '', '#' + id); }
      var sec = document.getElementById(id); if (sec) { sec.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion:reduce)').matches ? 'auto' : 'smooth', block: 'start' }); }
    }
    function applyFab() {
      var cur = L.$('.hs-stain:not([hidden])', root);
      if (cur) { [].forEach.call(cur.querySelectorAll('.hs-fabnote p'), function (p) { p.hidden = !!fab && p.getAttribute('data-fab') !== fab; }); var fn = L.$('.hs-fabnote', cur); if (fn && fab) { fn.open = true; } }
      [].forEach.call(fabSec.querySelectorAll('details'), function (d) { var on = d.getAttribute('data-fab') === fab; d.hidden = !!fab ? !on : true; d.open = on; });
      fabSec.hidden = !fab;
    }
    function hideAll() { sections.forEach(function (s) { s.hidden = true; }); fabSec.hidden = true; }
    hideAll();

    root.addEventListener('click', function (e) {
      var a = e.target.closest('[data-q=stain] .hs-opt');
      if (a) { e.preventDefault(); select(a.getAttribute('data-v'), true); return; }
      var f = e.target.closest('[data-q=fabric] .hs-opt');
      if (f) {
        fab = f.getAttribute('data-v');
        [].forEach.call(f.parentNode.children, function (x) { var on = x === f; x.classList.toggle('is-on', on); x.setAttribute('aria-pressed', on); });
        applyFab(); return;
      }
      var tm = e.target.closest('[data-tmin]'); if (tm) { startTimer(+tm.getAttribute('data-tmin') * 60); return; }
      if (e.target.closest('[data-tstart]')) { startTimer(600); return; }
      if (e.target.closest('[data-treset]')) { stopTimer(); clock.textContent = '10:00'; return; }
      if (e.target.closest('[data-order]')) { var form = L.$('.hs-form', root); form.hidden = false; form.classList.remove('is-sent'); L.$('input[name=name]', form).focus(); }
    });

    var iv = null, left = 0;
    function fmt(n) { return ('0' + Math.floor(n / 60)).slice(-2) + ':' + ('0' + (n % 60)).slice(-2); }
    function stopTimer() { if (iv) { clearInterval(iv); iv = null; } tStart.disabled = false; }
    function startTimer(sec) {
      stopTimer(); left = sec; clock.textContent = fmt(left); tStart.disabled = true;
      iv = setInterval(function () {
        left -= 1; clock.textContent = fmt(Math.max(left, 0));
        if (left <= 0) { stopTimer(); clock.textContent = 'انتهى الوقت'; try { if (navigator.vibrate) { navigator.vibrate([200, 100, 200]); } } catch (e) {} }
      }, 1000);
    }

    function fromHash() { var h = decodeURIComponent((location.hash || '').slice(1)); if (h && D.stains[h]) { select(h, false); } }
    window.addEventListener('hashchange', fromHash); fromHash();

    L.bind(root, function () {
      var sn = D.stains[stain] || 'غير محددة', fn = fab ? D.fabrics[fab] : 'غير محدد';
      return { summary: 'البقعة: ' + sn + '\nالقماش/السطح: ' + fn, waText: 'مرحباً، جرّبت أداة إسعافات البقع ولم تختفِ البقعة وأرغب بتنظيف متخصص.\nنوع البقعة: ' + sn + '\nالقماش/السطح: ' + fn, data: { stain: stain, fabric: fab } };
    });
  }
  function boot() { [].forEach.call(document.querySelectorAll('.hs-stain-aid'), init); }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
