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
	function waLink(number, text) { return number ? 'https://wa.me/' + String(number).replace(/\D/g, '') + '?text=' + encodeURIComponent(text) : ''; }

	/** GA4 parameters without anything personal (no name / phone / email / free text). */
	function cleanParams(p) {
		var out = {}, bad = /phone|mobile|tel|name|email|mail|address|note|msg|message|token/i;
		Object.keys(p || {}).forEach(function (k) { if (!bad.test(k) && (typeof p[k] === 'number' || typeof p[k] === 'boolean' || (typeof p[k] === 'string' && p[k].length <= 60))) { out[k] = p[k]; } });
		return out;
	}

	var ZT = { digits: digits, num: num, fmt: fmt, qsString: qsString, qsParse: qsParse, waMessage: waMessage, waLink: waLink, cleanParams: cleanParams, cfg: {} };

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
