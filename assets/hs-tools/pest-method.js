/* أداة: أي طريقة مكافحة تناسبك؟ */
(function () {
  'use strict';
  var L = window.HSLead;
  function init(root) {
    var D = L.data(root), A = {}, resEl = L.$('.hs-result', root), more = L.$('.hs-more', root), go = L.$('[data-go]', root), picked = null, ranked = null;
    var Q = ['kids', 'pets', 'leave', 'sens', 'sev'];

    root.addEventListener('click', function (e) {
      var b = e.target.closest('.hs-opt'); if (!b || !root.contains(b)) { return; }
      var q = b.closest('.hs-q').getAttribute('data-q');
      A[q] = b.getAttribute('data-v');
      [].forEach.call(b.parentNode.children, function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); x.classList.toggle('is-on', x === b); });
      if (q === 'pest') { more.hidden = false; }
      go.disabled = !(A.pest && Q.every(function (k) { return A[k] != null; }));
    });
    go.addEventListener('click', function () { show(); });

    function rank() {
      var rows = D.matrix[A.pest] || [], sev = +A.sev, allowed = +A.leave, out = [];
      rows.forEach(function (r, i) {
        var m = D.methods[r[0]], key = r[0], res = { key: key, m: m, note: r[2] || '', score: 100 - i * 10, out: '', warn: '' };
        if (r[1] === 'severe_only' && sev < 3) { return; }
        if (m.licensed_only) { res.warn = 'يحتاج معاينة فني مرخّص، ويخضع لاشتراطات رسمية. لا يُنفَّذ في مسكن مأهول أو شقة بعمارة سكنية.'; }
        if (m.leave_hours[0] > allowed) { res.out = 'تحتاج مغادرة البيت ' + m.leave_hours[0] + ' ساعة أو أكثر'; }
        if (A.sens === 'yes' && m.smell >= 2) { res.score -= 30; res.sensHit = true; }
        var kp = A.kids === 'yes' || A.pets !== 'none';
        if (kp && m.kids_pets === 'low') { res.score -= 30; } else if (kp && m.kids_pets === 'medium') { res.score -= 10; }
        if (A.pets === 'birdfish' && m.smell >= 1) { res.score -= 10; }
        if (sev === 3) { res.score += (4 - m.speed_rank) * 8; } else if (sev === 1) { res.score += m.speed_rank * 3; }
        out.push(res);
      });
      out.sort(function (a, b) { return (a.out ? 1 : 0) - (b.out ? 1 : 0) || (b.score - a.score); });
      return out;
    }
    function smellTxt(n) { return ['بدون', 'خفيفة', 'متوسطة', 'قوية'][n]; }
    function leaveTxt(h) { return h[1] === 0 ? 'لا تحتاج' : (h[0] === h[1] ? h[0] + ' ساعة' : h[0] + '–' + h[1] + ' ساعة'); }
    function kpTxt(k) { return { high: 'مناسبة', medium: 'بحذر', low: 'غير مناسبة' }[k]; }
    function why(res) {
      var m = res.m, s = [];
      s.push('تناسب ' + D.pests[A.pest] + ' لأنها ' + m.summary.replace(/^./, function (c) { return c; }).replace(/\.$/, '') + '.');
      var f = [];
      if (m.leave_hours[1] === 0) { f.push('لا تحتاج مغادرة البيت'); }
      if (m.smell === 0) { f.push('بدون رائحة' + (A.sens === 'yes' ? ' تناسب الحساسية' : '')); }
      if ((A.kids === 'yes' || A.pets !== 'none') && m.kids_pets === 'high') { f.push('أنسب خيار مع وجود أطفال أو حيوانات'); }
      if (+A.sev === 3 && m.speed_rank === 1) { f.push('سريعة وتناسب الإصابة الكبيرة'); }
      if (+A.sev === 1 && m.speed_rank >= 2) { f.push('كافية لإصابة خفيفة'); }
      s.push(f.length ? 'واخترناها لك لأنها ' + f.join('، ') + '.' : 'وهي الأعلى ملاءمة لظروف بيتك حسب إجاباتك.');
      return s;
    }

    function show() {
      ranked = rank();
      var best = ranked.filter(function (r) { return !r.out && !r.m.licensed_only; })[0] || null;
      var h = '';
      if (best) {
        picked = best;
        var w = why(best);
        h += '<div class="hs-best"><span class="hs-badge">الأنسب لك</span><h3>' + L.esc(best.m.name) + '</h3><p>' + L.esc(w[0]) + ' ' + L.esc(w[1]) + '</p>' +
          (best.note ? '<p class="hs-fine">' + L.esc(best.note) + '</p>' : '') +
          '<p class="hs-price"><span>السعر التقريبي:</span> <b>' + L.esc(D.prices[best.key]) + '</b></p><small class="hs-fine">سعر تقديري - السعر النهائي بعد المعاينة</small>' +
          '<button type="button" class="hs-btn hs-btn--main" data-order>اطلب هذه الطريقة</button></div>';
      } else {
        picked = null;
        h += '<div class="hs-best hs-best--none"><h3>تحتاج معاينة</h3><p>ظروفك لا تتيح اقتراح طريقة واحدة بثقة، فالأفضل أن يعاينها فني ويحدد المناسب. يمكنك المقارنة بين الطرق أدناه.</p><button type="button" class="hs-btn hs-btn--main" data-order>اطلب معاينة</button></div>';
      }
      if (D.tips[A.pest]) { h += '<p class="hs-tip">' + L.esc(D.tips[A.pest]) + '</p>'; }
      h += '<h3 class="hs-sub">مقارنة الطرق المناسبة لـ' + L.esc(D.pests[A.pest]) + '</h3><div class="hs-tablewrap"><table class="hs-cmp"><thead><tr><th>الطريقة</th><th>الرائحة</th><th>مغادرة البيت</th><th>الأطفال والحيوانات</th><th>السرعة</th><th>مدة المفعول</th><th>السعر</th></tr></thead><tbody>';
      ranked.forEach(function (r) {
        var m = r.m;
        h += '<tr class="' + (r.out ? 'is-out' : '') + (best && r === best ? ' is-best' : '') + '"><th scope="row" data-l="الطريقة">' + L.esc(m.name) + (r.out ? '<small class="hs-why">' + L.esc(r.out) + '</small>' : '') + (r.warn ? '<small class="hs-warn">' + L.esc(r.warn) + '</small>' : '') + '</th>' +
          '<td data-l="الرائحة">' + smellTxt(m.smell) + '</td><td data-l="مغادرة البيت">' + leaveTxt(m.leave_hours) + '</td><td data-l="الأطفال والحيوانات">' + kpTxt(m.kids_pets) + '<small>' + L.esc(m.kids_note) + '</small></td>' +
          '<td data-l="السرعة">' + L.esc(m.speed) + '</td><td data-l="مدة المفعول">' + L.esc(m.duration) + '</td><td data-l="السعر">' + L.esc(D.prices[r.key]) + '</td></tr>';
      });
      h += '</tbody></table></div>';
      resEl.innerHTML = h;
      resEl.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion:reduce)').matches ? 'auto' : 'smooth', block: 'start' });
      var form = L.$('.hs-form', root); form.hidden = true;
    }

    root.addEventListener('click', function (e) {
      if (!e.target.closest('[data-order]')) { return; }
      var form = L.$('.hs-form', root);
      form.hidden = false; form.classList.remove('is-sent');
      L.$('input[name=name]', form).focus();
    });

    var yn = { yes: 'نعم', no: 'لا' }, pt = { none: 'لا', catdog: 'قطط أو كلاب', birdfish: 'طيور أو أسماك' }, lv = { 0: 'لا أستطيع المغادرة', 4: 'حتى 4 ساعات', 24: 'يوم كامل', 72: 'أكثر من يوم' }, sv = { 1: 'أحياناً', 2: 'يومياً', 3: 'نهاراً وبأعداد كبيرة' };
    L.bind(root, function () {
      var mname = picked ? picked.m.name : 'معاينة (لم تُحدَّد طريقة)';
      var cond = 'أطفال: ' + yn[A.kids] + ' | حيوانات: ' + pt[A.pets] + ' | مغادرة: ' + lv[A.leave] + ' | حساسية: ' + yn[A.sens] + ' | حجم المشكلة: ' + sv[A.sev];
      return {
        summary: 'الحشرة: ' + D.pests[A.pest] + '\nالطريقة: ' + mname + '\n' + cond,
        waText: 'مرحباً، استخدمت أداة اختيار طريقة المكافحة وأرغب بطلب: ' + mname + '\nالحشرة: ' + D.pests[A.pest] + '\nظروف البيت: ' + cond,
        data: { method: picked ? picked.key : '', pest: A.pest, answers: A }
      };
    });
  }
  function boot() { [].forEach.call(document.querySelectorAll('.hs-pest-method'), init); }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
