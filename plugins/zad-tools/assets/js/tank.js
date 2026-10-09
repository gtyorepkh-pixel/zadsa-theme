/*! zad-tools · water-tank calculator — pure module (PHP twin: zt_tank_calc / zt_tank_view in includes/tools/tank.php). */
(function (root) {
	'use strict';
	var ZT = typeof module === 'object' && module.exports ? require('./zt-core.js') : root.ZT;
	var SERVICE = 'تنظيف خزان المياه';

	function find(list, k) { for (var i = 0; i < list.length; i++) { if (list[i].k === k) { return list[i]; } } return list[0] || null; }
	function round(x) { return Math.floor(x + 0.5); }
	function monthsLabel(m) { return ZT.arCount(m, 'شهر', 'شهرين', 'شهور', 'شهر'); }

	/** Litres from the shape and the three numbers (rect: a×b×c · cyl_v: Ø a, height b · cyl_h: Ø a, length b). unit 'm' | 'cm'. null = invalid. */
	function litres(shape, a, b, c, unit) {
		var f = unit === 'cm' ? 0.01 : 1, A = a * f, B = b * f, C = c * f, m3;
		if (shape === 'rect') { if (!(A > 0 && B > 0 && C > 0) || A > 100 || B > 100 || C > 100) { return null; } m3 = A * B * C; }
		else { if (!(A > 0 && B > 0) || A > 100 || B > 100) { return null; } m3 = Math.PI * (A / 2) * (A / 2) * B; }
		return round(m3 * 1000);
	}

	function priceFor(cfg, l) {
		for (var i = 0; i < cfg.tiers.length; i++) { if (l <= cfg.tiers[i].max) { return { kind: 'tier', value: cfg.tiers[i].price }; } }
		return cfg.tiers.length === 0 && cfg.fromPrice ? { kind: 'from', value: cfg.fromPrice } : null;
	}

	/** i = { loc, shape, a, b, c, unit, last:'YYYY-MM-DD'|'', people } ; today = 'YYYY-MM-DD' */
	function calc(cfg, i, today) {
		var shape = i.shape === 'cyl_v' || i.shape === 'cyl_h' ? i.shape : 'rect';
		var l = litres(shape, i.a, i.b, i.c, i.unit);
		if (l === null) { return { error: 'اكتب أبعاد الخزان بأرقام صحيحة (وبالوحدة المختارة).' }; }
		var loc = find(cfg.locs, i.loc);
		var last = ZT.parseYmd(i.last) ? i.last : '';
		if (last && last > today) { return { error: 'تاريخ آخر تنظيف لا يمكن أن يكون في المستقبل.' }; }
		var next = last && loc ? ZT.addMonths(last, loc.m) : '';
		var people = i.people > 0 ? Math.floor(i.people) : 0;
		var days = cfg.lpp && people > 0 ? Math.floor(l / (people * cfg.lpp)) : null;
		return { shape: shape, litres: l, m3: l / 1000, loc: loc, last: last, next: next, overdue: !!next && next < today, people: people, days: days, price: priceFor(cfg, l) };
	}

	function view(cfg, i, today) {
		var r = calc(cfg, i, today);
		if (r.error) { return r; }
		var F = ZT.fmt, shapes = { rect: 'مستطيل', cyl_v: 'أسطواني رأسي', cyl_h: 'أسطواني أفقي' };
		var lines = ['الحجم: ' + F(r.m3) + ' م³', 'النوع: ' + (r.loc ? r.loc.l : '') + ' — ' + shapes[r.shape]];
		if (r.days !== null) { lines.push(r.days >= 1 ? 'يكفي ' + F(r.people) + ' أشخاص نحو ' + ZT.arCount(r.days, 'يوم', 'يومين', 'أيام', 'يوم') + ' إذا امتلأ.' : 'يكفي ' + F(r.people) + ' أشخاص أقل من يوم إذا امتلأ.'); }
		if (r.price) { lines.push(r.price.kind === 'tier' ? 'تكلفة التنظيف التقديرية: ' + F(r.price.value) + ' ريال' : 'تكلفة التنظيف تبدأ من ' + F(r.price.value) + ' ريال'); }
		else { lines.push('تكلفة التنظيف تتحدد بعد المعاينة.'); }
		var notes = [], links = [], optin = null;
		if (r.loc) {
			if (r.next && !r.overdue) {
				lines.push('موعد التنظيف القادم: ' + ZT.arDate(r.next) + ' (كل ' + monthsLabel(r.loc.m) + ' من آخر تنظيف)');
				links.push({ href: ZT.icsUrl(cfg.ics, r.next, SERVICE), label: 'أضف الموعد للتقويم (.ics)', event: 'tool_ics' });
				optin = { date: r.next, service: SERVICE, title: 'ذكّرني بموعد التنظيف' };
			} else if (r.next) {
				lines.push('موعد التنظيف الدوري كان في ' + ZT.arDate(r.next) + ' — يُنصح بحجز التنظيف الآن.');
			} else {
				lines.push('يُنصح بتنظيف الخزان كل ' + monthsLabel(r.loc.m) + '. أدخل تاريخ آخر تنظيف لنحسب لك الموعد القادم.');
			}
		}
		notes.push('الأرقام تقديرية من الأبعاد التي كتبتها؛ المعاينة تحدد السعة والسعر النهائيين.');
		var sum = 'حاسبة الخزان: ' + (r.loc ? r.loc.l : 'خزان') + ' سعته ' + F(r.litres) + ' لتر' + (r.last ? '، وآخر تنظيف ' + ZT.arDate(r.last) : '') + '. أرغب في تنظيفه ومعرفة السعر.';
		return { badge: 'تقديري', big: F(r.litres) + ' لتر', lines: lines, notes: notes, wa: ZT.waMessage(sum, i.hood || ''), links: links, optin: optin, ga: { litres: r.litres, overdue: r.overdue ? 1 : 0 } };
	}

	var API = { calc: calc, view: view, litres: litres };
	if (typeof module === 'object' && module.exports) { module.exports = API; return; }

	/* ----------------------------- browser ----------------------------- */
	function readForm(form) {
		var v = ZT.formVals(form), n = function (k) { return ZT.num(v[k]); };
		return { loc: v.loc, shape: v.shape, a: n('a'), b: n('b'), c: n('c'), unit: v.unit, last: v.last || '', people: n('people'), hood: String(v.hood || '').trim().slice(0, 60) };
	}
	function params(i) { return { loc: i.loc, shape: i.shape, a: i.a, b: i.b, c: i.c, unit: i.unit, last: i.last, people: i.people, hood: i.hood }; }
	function fill(form, q) { [].forEach.call(form.elements, function (el) { if (el.name && q[el.name] != null) { el.value = q[el.name]; } }); syncShape(form); }
	/* show only the dimension fields the chosen shape needs (the server prints all three; hidden ones are simply not required) */
	function syncShape(form) {
		var s = form.elements.shape && form.elements.shape.value, lab = { rect: ['الطول', 'العرض', 'العمق (ارتفاع الماء)'], cyl_v: ['القطر', 'الارتفاع', ''], cyl_h: ['القطر', 'الطول', ''] }[s] || ['', '', ''];
		['a', 'b', 'c'].forEach(function (k, idx) {
			var el = form.elements[k]; if (!el) { return; }
			var wrap = el.closest('.fld'); if (wrap) { wrap.hidden = lab[idx] === ''; var sp = wrap.querySelector('span'); if (sp && lab[idx]) { sp.textContent = lab[idx]; } }
		});
	}
	if (ZT) {
		ZT.mount({ calc: view, read: readForm, params: params, fill: fill, ga: function (v) { return v.ga || {}; } });
		var form = document.querySelector('form[data-zt-form]');
		if (form && form.elements.shape) { form.elements.shape.addEventListener('change', function () { syncShape(form); }); syncShape(form); }
	}
})(typeof window !== 'undefined' ? window : globalThis);
