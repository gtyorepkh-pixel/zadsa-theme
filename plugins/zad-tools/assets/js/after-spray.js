/*! zad-tools · «متى أرجع بعد الرش» — pure module (PHP twin: zt_spray_calc / zt_spray_view in includes/tools/after-spray.php). */
(function (root) {
	'use strict';
	var ZT = typeof module === 'object' && module.exports ? require('./zt-core.js') : root.ZT;

	function find(list, k) { for (var i = 0; i < list.length; i++) { if (list[i].k === k) { return list[i]; } } return null; }
	function pad2(n) { return (n < 10 ? '0' : '') + n; }
	function round(x) { return Math.floor(x + 0.5); }
	function hoursLabel(h) {
		if (h >= 24 && h % 24 === 0) { return ZT.arCount(h / 24, 'يوم', 'يومين', 'أيام', 'يوم'); }
		return ZT.arCount(h, 'ساعة', 'ساعتين', 'ساعات', 'ساعة');
	}
	function clock(min) { var h = Math.floor(min / 60), m = min % 60, h12 = h % 12 === 0 ? 12 : h % 12; return h12 + ':' + pad2(m) + ' ' + (h >= 12 ? 'م' : 'ص'); }
	function dayLabel(n) { return n === 1 ? 'اليوم التالي' : (n === 2 ? 'بعد يومين' : 'بعد ' + ZT.arCount(n, 'يوم', 'يومين', 'أيام', 'يوم')); }
	function parseTime(t) { var m = /^(\d{1,2}):(\d{2})$/.exec(ZT.digits(String(t || '')).trim()); if (!m) { return null; } var h = +m[1], mi = +m[2]; return h > 23 || mi > 59 ? null : h * 60 + mi; }

	/** i = { ps, md, f: [factor keys], t: 'HH:MM' } */
	function calc(cfg, i) {
		var pest = find(cfg.pests, i.ps), method = find(cfg.methods, i.md);
		if (!pest || !method) { return { error: 'اختر نوع الآفة وطريقة المعالجة.' }; }
		var base = cfg.matrix[pest.k + '|' + method.k], hasBase = base !== undefined && base !== null;
		var fs = [], best = null;
		(i.f || []).forEach(function (k) { var f = find(cfg.factors, k); if (f) { fs.push(f); if (f.min != null && (best === null || f.min > best.min)) { best = f; } } });
		var hours = hasBase ? base : null, raisedBy = null;
		if (hasBase && best && best.min > hours) { hours = best.min; raisedBy = best; }
		var start = parseTime(i.t), ret = null;
		if (hours !== null && start !== null) { var tot = start + round(hours * 60); ret = { at: tot % 1440, day: Math.floor(tot / 1440) }; }
		return { pest: pest, method: method, hours: hours, raisedBy: raisedBy, factors: fs, start: start, ret: ret, note: (cfg.notes || {})[pest.k + '|' + method.k] || '' };
	}

	function view(cfg, i) {
		var r = calc(cfg, i);
		if (r.error) { return r; }
		var big = r.hours !== null ? 'ارجع بعد ' + hoursLabel(r.hours) : 'الفني هيحدد المدة بعد المعاينة';
		var lines = ['الآفة: ' + r.pest.l, 'طريقة المعالجة: ' + r.method.l];
		if (r.start !== null) { lines.push('وقت الرش: ' + clock(r.start)); }
		if (r.ret) { lines.push('تقدر ترجع الساعة ' + clock(r.ret.at) + (r.ret.day > 0 ? ' (' + dayLabel(r.ret.day) + ')' : '')); }
		var rows = [];
		[['قبل الرش', cfg.before], ['أثناء الرش', cfg.during], ['بعد الرش', cfg.after]].forEach(function (s) { if (s[1] && s[1].length) { rows.push([s[0], s[1].join(' ')]); } });
		r.factors.forEach(function (f) { if (f.txt) { rows.push(['ملاحظة: ' + f.l, f.txt]); } });
		var notes = [];
		if (r.raisedBy) { notes.push('رفعنا المدة إلى ' + hoursLabel(r.hours) + ' بسبب: ' + r.raisedBy.l + '.'); }
		if (r.note) { notes.push('ملاحظة: ' + r.note); }
		notes.push('هذه إرشادات عامة؛ التزم بتوجيه الفني في زيارتك وبما هو مكتوب على عبوة المبيد.');
		var sum = 'استفسار بعد الرش: ' + r.pest.l + ' بطريقة ' + r.method.l + (r.hours !== null ? '، والأداة تقول أرجع بعد ' + hoursLabel(r.hours) + '. أريد تأكيد الفني.' : '. أريد معرفة المدة المناسبة لرجوعنا.');
		return { badge: 'إرشادي', big: big, lines: lines, table: rows.length ? { title: 'الخطوات', head: ['المرحلة', 'ما تفعله'], rows: rows } : null, notes: notes, wa: ZT.waMessage(sum, i.hood || ''), ga: { pest: r.pest.k, method: r.method.k, hours: r.hours === null ? -1 : r.hours } };
	}

	var API = { calc: calc, view: view, hoursLabel: hoursLabel, clock: clock };
	if (typeof module === 'object' && module.exports) { module.exports = API; return; }

	/* ----------------------------- browser ----------------------------- */
	function readForm(form) {
		var v = ZT.formVals(form), f = [];
		[].forEach.call(form.querySelectorAll('input[data-zt-factor]:checked'), function (el) { f.push(el.getAttribute('data-zt-factor')); });
		return { ps: v.ps, md: v.md, f: f, t: v.t || '', hood: String(v.hood || '').trim().slice(0, 60) };
	}
	function params(i) { var o = { ps: i.ps, md: i.md, t: i.t, hood: i.hood }; i.f.forEach(function (k) { o['f_' + k] = '1'; }); return o; }
	function fill(form, q) {
		[].forEach.call(form.elements, function (el) {
			if (!el.name || el.hasAttribute('data-zt-factor')) { return; }
			if (q[el.name] != null) { el.value = q[el.name]; }
		});
		[].forEach.call(form.querySelectorAll('input[data-zt-factor]'), function (el) { el.checked = q['f_' + el.getAttribute('data-zt-factor')] === '1'; });
	}
	if (ZT) { ZT.mount({ calc: view, read: readForm, params: params, fill: fill, ga: function (v) { return v.ga || {}; } }); }
})(typeof window !== 'undefined' ? window : globalThis);
