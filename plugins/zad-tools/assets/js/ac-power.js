/*! zad-tools · AC electricity calculator — pure module (PHP twin: zt_pow_calc / zt_pow_view in includes/tools/ac-power.php). */
(function (root) {
	'use strict';
	var ZT = typeof module === 'object' && module.exports ? require('./zt-core.js') : root.ZT;
	var ROWS = 8;

	function find(list, k) { for (var i = 0; i < list.length; i++) { if (list[i].k === k) { return list[i]; } } return list[0] || null; }
	function round(x) { return Math.floor(x + 0.5); }

	/** Bill cost (riyal) of `kwh` for a month under the tier table [{from,to|null,rate(halalas)}]; contiguous bands, from>=1. null when no tiers. */
	function cost(tiers, kwh) {
		if (!tiers.length) { return null; }
		var h = 0;
		tiers.forEach(function (t) { var hi = t.to === null ? kwh : Math.min(kwh, t.to), n = hi - (Math.max(t.from, 1) - 1); if (n > 0) { h += n * t.rate; } });
		return h / 100;
	}

	/** i = { acs: [{ t: tons, q: qty, ty, ag }], h: hours/day, d: days/month, cl: cleaning key, o: other kWh }. */
	function calc(cfg, i) {
		var h = i.h, d = i.d;
		if (!(h > 0) || h > 24) { return { error: 'اكتب ساعات التشغيل يوميًا (من 1 إلى 24).' }; }
		if (!(d > 0) || d > 31) { return { error: 'اكتب أيام التشغيل في الشهر (من 1 إلى 31).' }; }
		var rows = [], total = 0, units = 0, missing = false;
		(i.acs || []).forEach(function (a) {
			if (!(a.t > 0) || a.t > 20) { return; }
			var q = a.q > 0 ? Math.floor(a.q) : 1, ty = find(cfg.types, a.ty), ag = find(cfg.ages, a.ag);
			var kw = ty && ag ? cfg.kw[ty.k + '|' + ag.k] : undefined;
			if (kw === undefined || kw === null) { missing = true; return; }
			var kwh = kw * a.t * q * h * d;
			rows.push({ t: a.t, q: q, ty: ty, ag: ag, kwh: kwh }); total += kwh; units += q;
		});
		if (!rows.length) { return { error: missing ? 'هذا النوع والعمر غير مسجّلين بعد، اختر غيرهما أو تواصل معنا.' : 'اكتب مقاس مكيف واحد على الأقل بالطن.' }; }
		var cl = find(cfg.cleans, i.cl), pct = cl && cfg.dirt[cl.k] !== undefined ? cfg.dirt[cl.k] : null;
		var dirty = pct !== null ? total * (1 + pct / 100) : total, other = i.o > 0 ? i.o : 0;
		var base = cost(cfg.tiers, other), cClean = base === null ? null : cost(cfg.tiers, other + total) - base, cDirty = base === null ? null : cost(cfg.tiers, other + dirty) - base;
		var extraC = cClean === null || pct === null ? null : cDirty - cClean;
		return { rows: rows, units: units, kwh: total, dirty: dirty, pct: pct, cl: cl, other: other, costClean: cClean, costDirty: cDirty, extraKwh: dirty - total, extraCost: extraC, season: cfg.season && extraC !== null ? extraC * cfg.season : null };
	}

	function view(cfg, i) {
		var r = calc(cfg, i);
		if (r.error) { return r; }
		var F = ZT.fmt, big = F(round(r.pct !== null ? r.dirty : r.kwh)) + ' كيلوواط ساعة شهريًا';
		var lines = ['عدد المكيفات: ' + F(r.units) + ' — بمعدل ' + F(i.h) + ' ساعة يوميًا لمدة ' + F(i.d) + ' يومًا'];
		if (r.pct !== null) { lines.push('استهلاك مكيفاتها نظيفة: ' + F(round(r.kwh)) + ' ك.و.س — ومع حالة التنظيف («' + r.cl.l + '»): ' + F(round(r.dirty)) + ' ك.و.س'); }
		var money = r.pct !== null ? r.costDirty : r.costClean;
		if (money !== null) { lines.push('تكلفة المكيفات التقديرية في الفاتورة الشهرية: ' + F(money) + ' ريال'); }
		if (r.extraKwh > 0 && r.pct !== null) {
			lines.push('الزيادة التقديرية بسبب الاتساخ: ' + F(round(r.extraKwh)) + ' ك.و.س شهريًا' + (r.extraCost !== null ? ' (' + F(r.extraCost) + ' ريال)' : ''));
			if (r.season !== null) { lines.push('وفي موسم الصيف كامل (' + F(cfg.season) + ' شهور): ' + F(r.season) + ' ريال تقريبًا'); }
		}
		if (cfg.cleanFrom) { lines.push('للمقارنة: تنظيف المكيفات يبدأ من ' + F(cfg.cleanFrom) + ' ريال (راجع صفحة الخدمة لسعر عددك).'); }
		var rows = r.rows.map(function (x) { return [F(x.t) + ' طن × ' + F(x.q) + ' — ' + x.ty.l + '، ' + x.ag.l, F(round(x.kwh)) + ' ك.و.س']; });
		var notes = ['كل الأرقام تقديرية وتعتمد على بياناتك والمعاملات المعلنة في «إزاي بنحسب».'];
		if (money === null) { notes.push('نعرض الاستهلاك فقط؛ حساب الفاتورة بالريال غير مفعّل حاليًا.'); }
		else { notes.push('التعرفة: ' + (cfg.tariff.label || 'الشرائح المعلنة') + (cfg.tariff.src ? ' — المصدر: ' + cfg.tariff.src : '') + (cfg.tariff.updated ? ' — آخر تحديث: ' + cfg.tariff.updated : '') + '.'); }
		var sum = 'حاسبة كهرباء المكيفات: ' + F(r.units) + ' مكيفات ← استهلاك تقديري ' + F(round(r.pct !== null ? r.dirty : r.kwh)) + ' ك.و.س شهريًا' + (money !== null ? ' (حوالي ' + F(money) + ' ريال)' : '') + '. أحتاج تنظيف مكيفاتي.';
		return { badge: 'تقديري', big: big, lines: lines, table: { title: 'تفصيل الاستهلاك لكل مكيف', head: ['المكيف', 'الاستهلاك الشهري'], rows: rows }, notes: notes, wa: ZT.waMessage(sum, i.hood || ''), ga: { kwh: round(r.pct !== null ? r.dirty : r.kwh), units: r.units } };
	}

	var API = { calc: calc, view: view, cost: cost, ROWS: ROWS };
	if (typeof module === 'object' && module.exports) { module.exports = API; return; }

	/* ----------------------------- browser ----------------------------- */
	function readForm(form) {
		var v = ZT.formVals(form), n = function (k) { return ZT.num(v[k]); }, acs = [];
		for (var k = 1; k <= ROWS; k++) { acs.push({ t: n('t' + k), q: n('q' + k), ty: v['ty' + k], ag: v['ag' + k] }); }
		return { acs: acs, h: n('h'), d: n('d'), cl: v.cl, o: n('o'), hood: String(v.hood || '').trim().slice(0, 60) };
	}
	function params(i) { var o = { h: i.h, d: i.d, cl: i.cl, o: i.o, hood: i.hood }; i.acs.forEach(function (a, x) { var k = x + 1; if (a.t > 0) { o['t' + k] = a.t; o['q' + k] = a.q; o['ty' + k] = a.ty; o['ag' + k] = a.ag; } }); return o; }
	function fill(form, q) { [].forEach.call(form.elements, function (el) { if (el.name && q[el.name] != null) { el.value = q[el.name]; } }); [].forEach.call(form.querySelectorAll('details'), function (dt) { if (dt.querySelector('input[name^="t"]') && [].some.call(dt.querySelectorAll('input[name^="t"]'), function (x) { return x.value; })) { dt.open = true; } }); }
	if (ZT) { ZT.mount({ calc: view, read: readForm, params: params, fill: fill, ga: function (v) { return v.ga || {}; } }); }
})(typeof window !== 'undefined' ? window : globalThis);
