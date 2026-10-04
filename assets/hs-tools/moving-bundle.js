/* أداة: باقة الانتقال — الحساب للعرض فقط؛ السيرفر يعيد الحساب عند الحفظ (hs_bundle_calc). */
(function () {
  'use strict';
  var L = window.HSLead;
  function norm(s) { return String(s || '').replace(/\s+/g, ' ').trim().toLowerCase(); }

  /** Mirror of hs_bundle_calc() in leads.php. */
  function calc(D, sel) {
    var chosen = Object.keys(D.services).filter(function (k) { return sel.svcs.indexOf(k) > -1; });
    var osz = D.sizes[sel.old.size] ? sel.old.size : '', nsz = D.sizes[sel['new'].size] ? sel['new'].size : '';
    var inter = norm(sel.old.city) && norm(sel['new'].city) && norm(sel.old.city) !== norm(sel['new'].city);
    function P(s, z) { return z ? (D.prices[s] && D.prices[s][z]) || null : null; }
    var lines = [], sub = 0, unknown = false;
    chosen.forEach(function (k) {
      var price = null, notes = [];
      if (k === 'clean_old') { price = P('clean_old', osz); }
      if (k === 'clean_new') { price = P('clean_new', nsz); }
      if (k === 'spray') { price = P('spray', nsz); }
      if (k === 'move') {
        if (inter) { notes.push('النقل بين المدن يُسعّر بعد التواصل'); }
        else {
          price = P('move', osz);
          if (price !== null) {
            var fl = (sel.old.lift ? 0 : Math.max(0, Math.min(10, sel.old.floor))) + (sel['new'].lift ? 0 : Math.max(0, Math.min(10, sel['new'].floor)));
            if (fl > 0) { if (D.floor_fee) { price += fl * D.floor_fee; } else { notes.push('رسوم الأدوار تحدد بعد المعاينة'); } }
            if (sel.pack) { if (D.pack_fee) { price += D.pack_fee; } else { notes.push('رسم التغليف يحدد بعد المعاينة'); } }
            if (sel.assemble) { if (D.assemble_fee) { price += D.assemble_fee; } else { notes.push('رسم الفك والتركيب يحدد بعد المعاينة'); } }
          }
        }
      }
      if (price === null) { unknown = true; } else { sub += price; }
      if (notes.length && price !== null) { unknown = true; }
      lines.push({ key: k, label: D.services[k], price: price, notes: notes });
    });
    var n = chosen.length, rate = n >= 2 ? (D.disc[Math.min(4, n)] || 0) : 0, disc = Math.round(sub * rate / 100);
    return { lines: lines, n: n, subtotal: sub, rate: rate, discount: disc, total: sub - disc, unknown: unknown };
  }
  window.HSBundleCalc = calc;

  function init(root) {
    var D = L.data(root), steps = [].slice.call(root.querySelectorAll('.hs-step')), prog = [].slice.call(root.querySelectorAll('.hs-progress li')), cur = 1, sumEl = L.$('.hs-sum', root), lastCalc = null;
    var date = L.$('input[name=date]', root);
    var t = new Date(); date.min = t.getFullYear() + '-' + ('0' + (t.getMonth() + 1)).slice(-2) + '-' + ('0' + t.getDate()).slice(-2);

    function val(n) { var e = L.$('[name=' + n + ']', root); return e ? e.value : ''; }
    function chk(n) { var e = L.$('[name=' + n + ']', root); return !!(e && e.checked); }
    function sel() {
      return {
        old: { city: val('old_city').trim(), hood: val('old_hood').trim(), size: val('old_size'), floor: +val('old_floor') || 0, lift: chk('old_lift') },
        'new': { city: val('new_city').trim(), hood: val('new_hood').trim(), size: val('new_size'), floor: +val('new_floor') || 0, lift: chk('new_lift') },
        svcs: [].filter.call(root.querySelectorAll('[name=svc]'), function (c) { return c.checked; }).map(function (c) { return c.value; }),
        pack: chk('pack'), assemble: chk('assemble'), date: date.value
      };
    }
    function go(n) {
      cur = n;
      steps.forEach(function (s, i) { s.hidden = (i + 1) !== n; });
      prog.forEach(function (p, i) { p.classList.toggle('is-on', i < n); p.classList.toggle('is-cur', i + 1 === n); });
      root.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion:reduce)').matches ? 'auto' : 'smooth', block: 'start' });
    }
    function err(stepNo, m) { var e = L.$('.hs-err', steps[stepNo - 1]); e.textContent = m; e.hidden = !m; }
    function interCheck() {
      var s = sel(); var inter = norm(s.old.city) && norm(s['new'].city) && norm(s.old.city) !== norm(s['new'].city);
      L.$('[data-inter]', root).hidden = !inter;
    }
    root.addEventListener('input', function (e) { if (/_city$/.test(e.target.name || '')) { interCheck(); } });

    function money(n) { return n.toLocaleString('en-US') + ' ر.س'; }
    function render() {
      var s = sel(), c = calc(D, s); lastCalc = c;
      var h = '<h3 class="hs-sub">ملخص باقتك</h3><ul class="hs-lines">';
      c.lines.forEach(function (l) {
        h += '<li><span>' + L.esc(l.label) + (l.notes.length ? '<small>' + L.esc(l.notes.join(' — ')) + '</small>' : '') + '</span><b>' + (l.price === null ? 'يحدد بعد المعاينة' : money(l.price)) + '</b></li>';
      });
      h += '</ul>';
      var known = c.lines.some(function (l) { return l.price !== null; });
      if (c.discount > 0) { h += '<p class="hs-save">وفّرت ' + money(c.discount) + ' (خصم الباقة ' + c.rate + '%)</p>'; }
      if (c.n >= 1 && c.n < 4) {
        var nxt = D.disc[c.n + 1] || 0; if (nxt > c.rate) { h += '<p class="hs-nudge">أضف خدمة واحدة لتحصل على خصم ' + nxt + '%</p>'; }
      }
      h += '<p class="hs-total"><span>الإجمالي التقديري</span><b>' + (known ? money(c.total) : 'يحدد بعد المعاينة') + '</b></p>';
      if (known && c.unknown) { h += '<p class="hs-note">الإجمالي يشمل البنود المسعّرة فقط؛ البنود المكتوب عليها «يحدد بعد المعاينة» لم تُحتسب.</p>'; }
      sumEl.innerHTML = h;
    }

    root.addEventListener('click', function (e) {
      if (e.target.closest('[data-prev]')) { go(cur - 1); return; }
      if (e.target.closest('[data-order]')) { var f = L.$('.hs-form', root); f.hidden = false; f.classList.remove('is-sent'); L.$('input[name=name]', f).focus(); return; }
      if (!e.target.closest('[data-next]')) { return; }
      var s = sel();
      if (cur === 1) {
        if (!s.old.city || !s['new'].city) { return err(1, 'اكتب مدينة الشقتين.'); }
        if (!s.old.size || !s['new'].size) { return err(1, 'اختر حجم الشقتين.'); }
        if (s.date && s.date < date.min) { return err(1, 'اختر تاريخاً غير ماضٍ.'); }
        err(1, ''); go(2);
      } else if (cur === 2) {
        if (!s.svcs.length) { return err(2, 'اختر خدمة واحدة على الأقل.'); }
        err(2, ''); render(); go(3);
      }
    });
    root.addEventListener('change', function (e) { if (e.target.name === 'svc' || e.target.name === 'pack' || e.target.name === 'assemble') { if (cur === 3) { render(); } } });

    L.bind(root, function () {
      var s = sel(), c = calc(D, s), known = c.lines.some(function (l) { return l.price !== null; });
      var txt = 'الشقة القديمة: ' + s.old.city + (s.old.hood ? ' - ' + s.old.hood : '') + '، ' + D.sizes[s.old.size] + '، الدور ' + s.old.floor + (s.old.lift ? ' (مصعد)' : ' (بدون مصعد)') +
        '\nالشقة الجديدة: ' + s['new'].city + (s['new'].hood ? ' - ' + s['new'].hood : '') + '، ' + D.sizes[s['new'].size] + '، الدور ' + s['new'].floor + (s['new'].lift ? ' (مصعد)' : ' (بدون مصعد)') +
        (s.date ? '\nتاريخ الانتقال: ' + s.date : '');
      var lines = c.lines.map(function (l) { return '- ' + l.label + ': ' + (l.price === null ? 'يحدد بعد المعاينة' : l.price + ' ر.س'); }).join('\n');
      var tot = known ? c.total + ' ر.س (تقديري' + (c.unknown ? '، دون البنود التي تحدد بعد المعاينة' : '') + ')' : 'يحدد بعد المعاينة';
      return {
        area: s.old.city + (s.old.city !== s['new'].city ? ' ← ' + s['new'].city : ''),
        summary: txt + '\nالخدمات:\n' + lines + (s.pack ? '\nتغليف' : '') + (s.assemble ? '\nفك وتركيب' : '') + '\nالإجمالي التقديري: ' + tot,
        waText: 'مرحباً، أرغب بطلب باقة الانتقال:\n' + txt + '\nالخدمات:\n' + lines + (s.pack ? '\nمع تغليف' : '') + (s.assemble ? '\nمع فك وتركيب' : '') + '\nالإجمالي التقديري: ' + tot + '\n(سعر تقديري - السعر النهائي بعد المعاينة)',
        data: s
      };
    });
  }
  function boot() { [].forEach.call(document.querySelectorAll('.hs-moving-bundle'), init); }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
