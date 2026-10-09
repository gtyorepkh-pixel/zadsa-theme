/*! zad-tools core — vanilla JS, no dependencies. The pure functions (digits, num, fmt, qsString, qsParse, waMessage, cleanParams) have
 *  a PHP twin in includes/core.php and are tested for identical results (tests/parity.test.mjs). */
(function (root) {
	'use strict';
	var AR = { '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9', '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4', '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9', '٬': ',', '٫': '.', '،': ',' };

	/** Arabic / Persian digits → ASCII (also the Arabic thousands / decimal marks). */
	function digits(s) { return String(s == null ? '' : s).replace(/[٠-٩۰-۹٬٫،]/g, function (c) { return AR[c]; }); }

	/** A number typed by a person ("٣٫٥", "1,200", "12 م²") → Number, or null. */
	function num(v) {
		var t = digits(v).replace(/,/g, ''), m = /-?\d+(?:\.\d+)?/.exec(t);
		return m ? parseFloat(m[0]) : null;
	}

	/** Display: Latin digits (as the rest of the site), thousands separator ",", at most 2 decimals, no trailing zeros. n >= 0. */
	function fmt(n) {
		if (n == null || isNaN(n)) { return ''; }
		var cents = Math.floor(n * 100 + 0.5 + 1e-9), whole = Math.floor(cents / 100), frac = cents % 100;
		var w = String(whole).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
		if (frac === 0) { return w; }
		var f = (frac < 10 ? '0' : '') + frac;
		return w + '.' + f.replace(/0$/, '');
	}

	/** {a:1,b:'x',c:''} → "a=1&b=x" (empty values are dropped; keys sorted so the same state always gives the same URL). */
	function qsString(o) {
		return Object.keys(o).sort().filter(function (k) { return o[k] !== '' && o[k] != null && o[k] !== false; })
			.map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(o[k] === true ? '1' : o[k]); }).join('&');
	}
	function qsParse(s) {
		var o = {};
		String(s || '').replace(/^\?/, '').split('&').forEach(function (p) {
			if (!p) { return; }
			var i = p.indexOf('='), k = decodeURIComponent((i < 0 ? p : p.slice(0, i)).replace(/\+/g, ' ')), v = i < 0 ? '' : decodeURIComponent(p.slice(i + 1).replace(/\+/g, ' '));
			o[k] = v;
		});
		return o;
	}

	/** The ready WhatsApp text: the result sentence, then the hood sentence when the hood is known. */
	function waMessage(result, hood, service) {
		var out = ['السلام عليكم،', String(result || '').replace(/\s+/g, ' ').trim()];
		if (service) { out.push(String(service).trim()); }
		if (hood) { out.push('في حي ' + String(hood).replace(/^\s*حي\s+/, '').trim() + '.'); }
		return out.filter(Boolean).join(' ');
	}
	/** wa.me URL: encodeURIComponent + the five characters it leaves alone, so the bytes equal PHP rawurlencode (zt_wa_url). */
	function rawenc(t) { return encodeURIComponent(t).replace(/[!'()*]/g, function (c) { return '%' + c.charCodeAt(0).toString(16).toUpperCase(); }); }
	function waLink(number, text) { var n = String(number || '').replace(/\D/g, ''); return n ? 'https://wa.me/' + n + '?text=' + rawenc(String(text)) : ''; }

	/* ---------- shared view renderer (PHP twin: zt_result_html in includes/ui.php; tests/tools-parity.test.mjs checks identical HTML) ---------- */
	function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;'); }

	function optinHtml(o, env) {
		var items = o.items && o.items.length ? ' data-items="' + esc(JSON.stringify(o.items)) + '"' : '';
		var h = '<form class="zt-optin zt-form zt-jsonly" data-zt-optin data-date="' + esc(o.date || '') + '" data-service="' + esc(o.service || '') + '"' + items + ' novalidate>';
		h += '<h3 class="zt-h3">' + esc(o.title || 'ذكّرني بالموعد') + '</h3>';
		h += '<div class="zt-row"><label class="fld"><span>اسمك الأول</span><input name="first_name" autocomplete="given-name" maxlength="60"></label>';
		h += '<label class="fld"><span>رقم الجوال</span><input name="phone" type="tel" inputmode="tel" dir="ltr" autocomplete="tel" placeholder="05xxxxxxxx"></label></div>';
		h += '<label class="fld"><span>الإيميل (اختياري)</span><input name="email" type="email" dir="ltr" autocomplete="email"></label>';
		h += '<div class="zt-hp" aria-hidden="true"><label>لا تملأ هذا الحقل<input name="website" tabindex="-1" autocomplete="off"></label></div>';
		var priv = env && env.privacy ? ' <a href="' + esc(env.privacy) + '" target="_blank" rel="noopener">سياسة الخصوصية</a>' : '';
		h += '<label class="zt-consent"><input type="checkbox" name="consent" value="1"><span>أوافق على أن تتواصل معي زاد برسالة تذكير بهذا الموعد فقط، ويمكنني إيقاف التذكير في أي وقت.' + priv + '</span></label>';
		return h + '<button class="btn btn--accent" type="submit">فعّل التذكير</button><p class="zt-optin__msg" role="status" aria-live="polite"></p></form>';
	}

	function resultHtml(v, env) {
		env = env || {};
		if (v.error) { return '<p class="zt-error" role="alert">' + esc(v.error) + '</p>'; }
		var h = '<div class="zt-card" data-zt-ok="1">';
		if (v.badge) { h += '<span class="zt-est">' + esc(v.badge) + '</span>'; }
		h += '<p class="zt-big">' + esc(v.big || '') + '</p>';
		if (v.lines && v.lines.length) { h += '<ul class="zt-lines">' + v.lines.map(function (l) { return '<li>' + esc(l) + '</li>'; }).join('') + '</ul>'; }
		if (v.table && v.table.rows && v.table.rows.length) {
			var t = v.table;
			h += '<div class="zt-tblwrap">' + (t.title ? '<h3 class="zt-h3">' + esc(t.title) + '</h3>' : '') + '<table class="zt-table"><thead><tr>' +
				t.head.map(function (c) { return '<th scope="col">' + esc(c) + '</th>'; }).join('') + '</tr></thead><tbody>' +
				t.rows.map(function (r) { return '<tr>' + r.map(function (c, i) { return i === 0 ? '<th scope="row">' + esc(c) + '</th>' : '<td>' + esc(c) + '</td>'; }).join('') + '</tr>'; }).join('') + '</tbody></table></div>';
		}
		if (v.cards && v.cards.length) {
			h += '<ul class="zt-cards">' + v.cards.map(function (c) {
				return '<li class="zt-cards__i"><a class="zt-cards__a" href="' + esc(c.u) + '">' + (c.img ? '<img src="' + esc(c.img) + '" alt="' + esc(c.alt || '') + '" width="72" height="72" loading="lazy">' : '') +
					'<span><strong>' + esc(c.t) + '</strong><em>نسبة التطابق: ' + esc(fmt(c.p)) + '%</em></span></a>' + (c.svc ? '<a class="btn btn--accent" href="' + esc(c.svc[1]) + '" data-zt-event="tool_cta">' + esc(c.svc[0]) + '</a>' : '') + '</li>';
			}).join('') + '</ul>';
		}
		if (v.cal && v.cal.length) {
			h += '<ol class="zt-cal">' + v.cal.map(function (c) {
				return '<li class="zt-cal__m"><strong>' + esc(c.m) + '</strong>' + (c.items.length ? '<ul>' + c.items.map(function (it) { return '<li>' + (it[1] ? '<a href="' + esc(it[1]) + '">' + esc(it[0]) + '</a>' : esc(it[0])) + '</li>'; }).join('') + '</ul>' : '<span class="zt-cal__none">—</span>') + '</li>';
			}).join('') + '</ol>';
		}
		(v.notes || []).forEach(function (n) { h += '<p class="zt-noteline">' + esc(n) + '</p>'; });
		var acts = '', wa = v.wa ? waLink(env.wa, v.wa) : '';
		if (wa) { acts += '<a class="btn btn--wa" target="_blank" rel="noopener" data-zt-event="tool_whatsapp_click" href="' + esc(wa) + '">أرسل النتيجة على واتساب</a>'; }
		(v.links || []).forEach(function (l) { acts += '<a class="btn btn--ghost" href="' + esc(l.href) + '"' + (l.event ? ' data-zt-event="' + esc(l.event) + '"' : '') + '>' + esc(l.label) + '</a>'; });
		acts += '<button type="button" class="btn btn--ghost zt-jsonly" data-zt-share>شارك النتيجة</button><button type="button" class="btn btn--ghost zt-jsonly" data-zt-print>اطبع</button>';
		h += '<div class="zt-actions">' + acts + '</div>';
		if (v.optin) { h += optinHtml(v.optin, env); }
		return h + '</div>';
	}

	/** Arabic counted noun (PHP twin: zt_ar_count). */
	function arCount(n, one, two, few, many, oneAlone) {
		n = Number(n);
		if (n === 1) { return oneAlone == null ? one : oneAlone; }
		if (n === 2) { return two; }
		var s = fmt(n);
		if (n >= 3 && n <= 10 && Math.floor(n) === n) { return s + ' ' + few; }
		return s + ' ' + many;
	}
	function pctLabel(p) { p = Number(p); return p > 0 ? '+' + fmt(p) + '%' : (p < 0 ? '\u2212' + fmt(-p) + '%' : '0%'); }

	/* dates as integers only (no Date object) so JS and PHP cannot disagree */
	var MN = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
	function dim(y, m) { return m === 2 && ((y % 4 === 0 && y % 100 !== 0) || y % 400 === 0) ? 29 : [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31][m - 1]; }
	function pad(n, w) { var s = String(n); while (s.length < w) { s = '0' + s; } return s; }
	function parseYmd(s) {
		var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(s || ''));
		if (!m) { return null; }
		var y = +m[1], mo = +m[2], d = +m[3];
		return mo < 1 || mo > 12 || d < 1 || d > dim(y, mo) ? null : [y, mo, d];
	}
	function addMonths(s, n) {
		var p = parseYmd(s); if (!p) { return ''; }
		var t = p[0] * 12 + (p[1] - 1) + n, y2 = Math.floor(t / 12), m2 = t % 12 + 1;
		return pad(y2, 4) + '-' + pad(m2, 2) + '-' + pad(Math.min(p[2], dim(y2, m2)), 2);
	}
	function addDays(s, n) {
		var p = parseYmd(s); if (!p) { return ''; }
		var y = p[0], mo = p[1], d = p[2] + n;
		while (d > dim(y, mo)) { d -= dim(y, mo); mo++; if (mo > 12) { mo = 1; y++; } }
		while (d < 1) { mo--; if (mo < 1) { mo = 12; y--; } d += dim(y, mo); }
		return pad(y, 4) + '-' + pad(mo, 2) + '-' + pad(d, 2);
	}
	function arMonth(y, m) { return MN[m - 1] + ' ' + pad(y, 4); }
	function arDate(s) { var p = parseYmd(s); return p ? p[2] + ' ' + MN[p[1] - 1] + ' ' + pad(p[0], 4) : ''; }
	function icsUrl(base, date, title) { return base ? base + '?' + qsString({ d: date, t: title, zad_ics: '1' }) : ''; }
	/** Many events in one .ics: ev = "YYYY-MM-DD|title~…" (PHP twin: zt_ics_url_multi). */
	function icsUrlMulti(base, events) { return base ? base + '?' + qsString({ ev: events.map(function (e) { return e[0] + '|' + String(e[1]).replace(/[~|]/g, ' '); }).join('~'), zad_ics: '1' }) : ''; }
	/** «Send to myself»: wa.me without a number opens the chooser (PHP twin: zt_wa_self_url). */
	function waSelf(text) { return 'https://wa.me/?text=' + rawenc(String(text)); }

	/** GA4 parameters without anything personal (no name / phone / email / free text). */
	function cleanParams(p) {
		var out = {}, bad = /phone|mobile|tel|name|email|mail|address|note|msg|message|token/i;
		Object.keys(p || {}).forEach(function (k) { if (!bad.test(k) && (typeof p[k] === 'number' || typeof p[k] === 'boolean' || (typeof p[k] === 'string' && p[k].length <= 60))) { out[k] = p[k]; } });
		return out;
	}

	var ZT = { digits: digits, num: num, fmt: fmt, qsString: qsString, qsParse: qsParse, waMessage: waMessage, waLink: waLink, cleanParams: cleanParams, esc: esc, resultHtml: resultHtml, arCount: arCount, pctLabel: pctLabel,
		addMonths: addMonths, addDays: addDays, arDate: arDate, arMonth: arMonth, parseYmd: parseYmd, icsUrl: icsUrl, icsUrlMulti: icsUrlMulti, waSelf: waSelf, rawenc: rawenc, cfg: {} };

	if (typeof module === 'object' && module.exports) { module.exports = ZT; return; }

	/* ----------------------------- browser part ----------------------------- */
	ZT.cfg = root.ZT_CFG || {};

	/** tool_start / tool_complete / tool_whatsapp_click / tool_share / reminder_optin — never any personal data. */
	ZT.ev = function (name, params) {
		if (!ZT.cfg.ga) { return; }
		var p = cleanParams(params || {}); p.tool = ZT.cfg.tool || '';
		try { if (root.dataLayer) { root.dataLayer.push({ event: name, zad: p }); } if (root.gtag) { root.gtag('event', name, p); } } catch (e) {}
	};

	/** The result sentence is also what we put in the URL: the page opens with the same answer when the link is shared. */
	ZT.state = {
		read: function () { return qsParse(root.location.search); },
		write: function (o) { try { var q = qsString(o); root.history.replaceState(null, '', root.location.pathname + (q ? '?' + q : '') + root.location.hash); } catch (e) {} },
	};

	ZT.live = function (html) { var el = document.getElementById('zt-result'); if (el) { el.innerHTML = html; } };

	ZT.waButton = function (text, label) {
		var href = waLink(ZT.cfg.wa, text);
		return href ? '<a class="btn btn--wa" target="_blank" rel="noopener" data-zt-event="tool_whatsapp_click" href="' + href.replace(/"/g, '&quot;') + '">' + (label || 'أرسل النتيجة على واتساب') + '</a>' : '';
	};

	ZT.share = function () {
		var url = root.location.href;
		ZT.ev('tool_share', {});
		if (navigator.share) { return navigator.share({ title: document.title, url: url }).catch(function () {}); }
		if (navigator.clipboard) { return navigator.clipboard.writeText(url); }
	};

	/** Token + POST (cache-safe: the token is fetched fresh, never printed in the cached page). Resolves { ok, body }. */
	ZT.post = function (path, data) {
		var base = ZT.cfg.rest || '/wp-json/zad/v1/';
		return fetch(base + 'token', { cache: 'no-store', credentials: 'omit' }).then(function (r) { return r.json(); }).then(function (j) {
			data.t = j.t;
			return fetch(base + path, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data), credentials: 'omit' });
		}).then(function (r) { return r.json().then(function (b) { return { ok: r.ok, body: b }; }); });
	};
	ZT.postReminder = function (data) {
		data.tool = ZT.cfg.tool;
		return ZT.post('reminders', data).then(function (res) { if (res.ok) { ZT.ev('reminder_optin', {}); } return res; });
	};

	/** Today in Saudi Arabia (fixed UTC+3, no daylight saving) as YYYY-MM-DD — the server uses the site timezone, set to Asia/Riyadh. */
	ZT.today = function () {
		try { return new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Riyadh' }).format(new Date()); } catch (e) { var d = new Date(Date.now() + 3 * 3600000); return d.toISOString().slice(0, 10); }
	};
	ZT.env = function () { return { wa: ZT.cfg.wa, privacy: ZT.cfg.privacy }; };

	ZT.enhance = function () {}; // kept for tools that call it: share / print / opt-in are in the markup now and shown by CSS (.zt-js .zt-jsonly)

	/**
	 * Wire a tool form. o = { calc(cfg, input, today) → view, read(form) → input, params(input) → {k:v}, fill(form, query), ga(view) → {…} }.
	 * The server already printed the answer for a shared link (?…), so on load we only fill the form and enhance the printed result.
	 */
	ZT.mount = function (o) {
		var form = document.querySelector('form[data-zt-form]'), box = document.getElementById('zt-result');
		if (!form || !box) { return; }
		var cfg = {}; try { cfg = JSON.parse(form.getAttribute('data-cfg') || '{}'); } catch (e) {}
		var q = ZT.state.read();
		if (Object.keys(q).length && o.fill) { o.fill(form, q); }
		ZT.enhance(box);
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var input = o.read(form), view = o.calc(cfg, input, ZT.today());
			box.innerHTML = ZT.resultHtml(view, ZT.env());
			ZT.enhance(box);
			if (!view.error) { ZT.state.write(o.params(input)); ZT.ev('tool_complete', o.ga ? o.ga(view, input) : {}); }
			var card = box.querySelector('.zt-card, .zt-error'); if (card && card.scrollIntoView && !root.matchMedia('(prefers-reduced-motion: reduce)').matches) { card.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }
		});
	};
	/** Form values by name → plain object (numeric fields are normalised from Arabic digits by the caller). */
	ZT.formVals = function (form) {
		var o = {}; [].forEach.call(form.elements, function (el) { if (!el.name) { return; } if (el.type === 'checkbox') { o[el.name] = el.checked; } else if (el.type === 'radio') { if (el.checked) { o[el.name] = el.value; } } else { o[el.name] = el.value; } });
		return o;
	};

	document.addEventListener('click', function (e) {
		var t = e.target.closest && e.target.closest('[data-zt-share],[data-zt-print]');
		if (!t) { return; }
		if (t.hasAttribute('data-zt-share')) { ZT.share(); } else { ZT.ev('tool_print', {}); root.print(); }
	});
	document.addEventListener('submit', function (e) {
		var f = e.target; if (!f || !f.matches || !f.matches('form[data-zt-optin]')) { return; }
		e.preventDefault();
		var msg = f.querySelector('.zt-optin__msg'), v = ZT.formVals(f), btn = f.querySelector('button[type=submit]');
		msg.textContent = ''; msg.className = 'zt-optin__msg';
		if (!v.consent) { msg.textContent = 'يلزم الموافقة على استلام التذكير.'; msg.classList.add('is-err'); return; }
		btn.disabled = true;
		var base = { first_name: v.first_name, phone: ZT.digits(v.phone), email: v.email, hood: (document.querySelector('#zt-tool [name="hood"]') || {}).value || '', consent: true, website: v.website }, items = null;
		try { items = JSON.parse(f.getAttribute('data-items') || 'null'); } catch (x) {}
		var req = items ? ZT.postRemindersBatch(Object.assign(base, { items: items })) : ZT.postReminder(Object.assign(base, { service: f.getAttribute('data-service'), due_date: f.getAttribute('data-date') }));
		req
			.then(function (r) { msg.textContent = r.ok ? (r.body && r.body.added > 1 ? 'تم تفعيل ' + r.body.added + ' تذكيرات' + (r.body.skipped ? ' (وتعذّر ' + r.body.skipped + ' بسبب الحد الأقصى)' : '') + '. في كل رسالة رابط لإيقافها.' : 'تم تفعيل التذكير. هنراسلك في الموعد، وفي كل رسالة رابط لإيقافه.') : ((r.body && r.body.message) || 'تعذر التفعيل، حاول مرة أخرى.'); msg.classList.add(r.ok ? 'is-ok' : 'is-err'); if (r.ok) { f.querySelectorAll('input').forEach(function (i) { i.disabled = true; }); } else { btn.disabled = false; } })
			.catch(function () { msg.textContent = 'تعذر الاتصال، حاول مرة أخرى.'; msg.classList.add('is-err'); btn.disabled = false; });
	});

	/** One consent, one token, many due dates (the maintenance plan). */
	ZT.postRemindersBatch = function (data) {
		data.tool = ZT.cfg.tool;
		return ZT.post('reminders/batch', data).then(function (res) { if (res.ok) { ZT.ev('reminder_optin', { count: (data.items || []).length }); } return res; });
	};

	function boot() {
		var box = document.getElementById('zt-tool'), started = false;
		document.addEventListener('click', function (e) { var a = e.target.closest && e.target.closest('[data-zt-event]'); if (a) { ZT.ev(a.getAttribute('data-zt-event'), {}); } });
		if (box) {
			var start = function () { if (!started) { started = true; ZT.ev('tool_start', {}); } };
			box.addEventListener('input', start); box.addEventListener('change', start);
			var q = ZT.state.read();
			if (q.hood) { [].forEach.call(box.querySelectorAll('[name="hood"]'), function (el) { if (!el.value) { el.value = q.hood; } }); }
			// numeric inputs accept Arabic digits: normalised as the user leaves the field
			box.addEventListener('blur', function (e) { var t = e.target; if (t && t.matches && t.matches('input[data-zt-num]')) { t.value = digits(t.value); } }, true);
		}
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
	root.ZT = ZT;
})(typeof window !== 'undefined' ? window : globalThis);
