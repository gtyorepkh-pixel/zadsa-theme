/* HS Tools — shared lead sender (Vanilla JS). Saves the request, then opens WhatsApp. */
(function () {
  'use strict';
  var t0 = Date.now();
  var AR = { '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9' };
  function digits(s) { return String(s || '').replace(/[٠-٩]/g, function (c) { return AR[c]; }); }
  function phoneOk(s) { return /^(05\d{8}|9665\d{8}|\+9665\d{8})$/.test(digits(s).replace(/[\s\-]+/g, '')); }
  function $(sel, r) { return (r || document).querySelector(sel); }

  function waUrl(root, text) {
    var n = (root.getAttribute('data-wa') || '').replace(/\D+/g, '');
    return 'https://wa.me/' + n + '?text=' + encodeURIComponent(text);
  }

  /** Must be called synchronously inside the user's click/submit so the popup is not blocked. */
  function send(o) {
    var root = o.root, form = o.form, err = $('.hs-err', form), fb = $('.hs-fallback', form), btn = $('button[type=submit]', form);
    var fd = new FormData(form);
    var name = (fd.get('name') || '').trim(), phone = digits(fd.get('phone') || '').trim();
    function fail(m) { err.textContent = m; err.hidden = false; }
    err.hidden = true; fb.hidden = true;
    if (name.length < 2) { return fail('يرجى كتابة الاسم.'); }
    if (!phoneOk(phone)) { return fail('رقم الجوال غير صحيح. اكتبه بصيغة 05XXXXXXXX.'); }
    if (!root.getAttribute('data-wa')) { return fail('رقم الواتساب غير مضبوط حالياً، يرجى الاتصال بنا.'); }

    var w = null;
    try { w = window.open('', '_blank'); if (w) { w.document.write('<meta charset="utf-8"><p dir="rtl" style="font-family:sans-serif;padding:24px">جارٍ التحويل إلى واتساب…</p>'); } } catch (e) { w = null; }
    btn.disabled = true;
    var p = o.payload();
    var body = {
      tool: root.getAttribute('data-tool'), name: name, phone: phone, area: (fd.get('area') || '') + (p.area ? ((fd.get('area') ? ' - ' : '') + p.area) : ''),
      notes: fd.get('notes') || '', summary: p.summary, data: p.data || {}, hs_website: fd.get('hs_website') || '',
      elapsed: Math.round((Date.now() - t0) / 1000)
    };
    fetch(root.getAttribute('data-rest'), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body), credentials: 'omit' })
      .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
      .then(function (res) {
        btn.disabled = false;
        if (!res.ok) { if (w) { try { w.close(); } catch (e) {} } return fail((res.j && res.j.message) || 'تعذّر إرسال الطلب، حاول مرة أخرى.'); }
        var msg = p.waText + '\n\nالاسم: ' + name + '\nالجوال: ' + phone + ((fd.get('notes')) ? '\nملاحظات: ' + fd.get('notes') : '');
        var url = waUrl(root, msg);
        var opened = false;
        if (w && !w.closed) { try { w.location.href = url; opened = true; } catch (e) {} }
        form.classList.add('is-sent');
        fb.hidden = false;
        fb.innerHTML = '<p class="hs-ok">تم استلام طلبك ✓</p>';
        var a = document.createElement('a');
        a.className = 'hs-btn hs-btn--wa'; a.href = url; a.target = '_blank'; a.rel = 'noopener';
        a.textContent = opened ? 'لم تُفتح المحادثة؟ أكمل عبر واتساب' : 'أكمل عبر واتساب';
        fb.appendChild(a);
        if (o.done) { o.done(); }
      })
      .catch(function () {
        btn.disabled = false;
        if (w) { try { w.close(); } catch (e) {} }
        fail('تعذّر الاتصال، تحقق من الإنترنت وحاول مرة أخرى.');
      });
  }

  /** Bind a lead form: payload() returns {summary, waText, data, area?}. */
  function bind(root, payload, done) {
    var form = $('.hs-form', root);
    if (!form || form.__hs) { return form; }
    form.__hs = true;
    form.addEventListener('submit', function (e) { e.preventDefault(); send({ root: root, form: form, payload: payload, done: done }); });
    return form;
  }

  function esc(s) { var d = document.createElement('div'); d.textContent = String(s == null ? '' : s); return d.innerHTML; }
  function data(root) { try { return JSON.parse($('.hs-data', root).textContent); } catch (e) { return {}; } }

  window.HSLead = { bind: bind, esc: esc, data: data, $: $ };
})();
