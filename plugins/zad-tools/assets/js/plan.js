/*! zad-tools · yearly home-maintenance plan — pure module (PHP twin: zt_plan_calc / zt_plan_view in includes/tools/plan.php). */
(function (root) {
	'use strict';
	var ZT = typeof module === 'object' && module.exports ? require('./zt-core.js') : root.ZT;

	function find(list, k) { for (var i = 0; i < list.length; i++) { if (list[i].k === k) { return list[i]; } } return list[0] || null; }
	function nz(n) { return n > 0 ? Math.floor(n) : 0; }
	function applies(when, c) {
		for (var i = 0; i < when.length; i++) {
			var t = when[i], ok;
			if (t === 'always') { ok = true; }
			else if (t === 'tank_ground') { ok = c.tank === 'ground' || c.tank === 'both'; }
			else if (t === 'tank_roof') { ok = c.tank === 'roof' || c.tank === 'both'; }
			else if (t === 'tank_any') { ok = c.tank !== 'none' && c.tank !== ''; }
			else if (t === 'ac') { ok = c.ac > 0; }
			else if (t === 'sofa') { ok = c.sofa > 0; }
			else if (t === 'garden') { ok = c.garden; } else if (t === 'pets') { ok = c.pets; } else if (t === 'pest') { ok = c.pest; }
			else if (t.indexOf('h_') === 0) { ok = c.hs === t.slice(2); }
			else { ok = false; } // an unknown condition never matches (a typo cannot add a task by accident)
			if (!ok) { return false; }
		}
		return true;
	}
	function mult(per, c) { return per === 'ac' ? c.ac : (per === 'sofa' ? c.sofa : 1); }

	/** i = { hs, ct, tk: none|ground|roof|both, acn, gd, pt, pe, sf, sm: 'YYYY-MM'|'', last: {taskKey: 'YYYY-MM-DD'} } ; today = 'YYYY-MM-DD' */
	function calc(cfg, i, today) {
		var hs = find(cfg.housing, i.hs), ct = find(cfg.cities, i.ct);
		var tk = i.tk === 'ground' || i.tk === 'roof' || i.tk === 'both' ? i.tk : 'none';
		var c = { tank: tk, ac: nz(i.acn), sofa: nz(i.sf), garden: !!i.gd, pets: !!i.pt, pest: !!i.pe, hs: hs ? hs.k : '' };
		var start = /^\d{4}-\d{2}$/.test(i.sm || '') && ZT.parseYmd(i.sm + '-01') ? i.sm : today.slice(0, 7);
		var startDay = start + '-01', end = ZT.addMonths(startDay, 12), occ = [], tasks = [];
		cfg.tasks.forEach(function (t) {
			if (!applies(t.when, c)) { return; }
			var last = i.last && i.last[t.k] && ZT.parseYmd(i.last[t.k]) && i.last[t.k] <= today ? i.last[t.k] : '';
			var base, overdue = false;
			if (last) { base = ZT.addMonths(last, t.every); overdue = base < today; }
			else if (t.month) { base = startDay.slice(0, 4) + '-' + (t.month < 10 ? '0' : '') + t.month + '-01'; if (base < startDay) { base = ZT.addMonths(base, 12); } }
			else { base = startDay; }
			var n = 0, dates = [];
			for (var k = 0; k < 400; k++) {
				var raw = ZT.addMonths(base, k * t.every);
				if (raw >= end) { break; }
				if (raw < today && k > 0) { continue; }
				var d = raw < today ? today : raw;
				if (d >= end) { break; }
				dates.push(d); occ.push({ k: t.k, l: t.l, date: d, url: t.url, overdue: k === 0 && overdue });
			}
			var q = mult(t.per, c), cost = t.price !== null && dates.length ? t.price * q * dates.length : null;
			tasks.push({ k: t.k, l: t.l, url: t.url, dates: dates, qty: q, price: t.price, cost: cost });
		});
		occ.sort(function (a, b) { return a.date < b.date ? -1 : (a.date > b.date ? 1 : (a.k < b.k ? -1 : (a.k > b.k ? 1 : 0))); });
		var months = [];
		for (var m = 0; m < 12; m++) { var ms = ZT.addMonths(startDay, m), ym = ms.slice(0, 7); months.push({ ym: ym, y: +ms.slice(0, 4), m: +ms.slice(5, 7), items: occ.filter(function (o) { return o.date.slice(0, 7) === ym; }) }); }
		var cost = 0, priced = false, unpriced = [];
		tasks.forEach(function (t) { if (t.dates.length) { if (t.cost !== null) { cost += t.cost; priced = true; } else { unpriced.push(t.l); } } });
		return { hs: hs, ct: ct, tk: tk, c: c, start: start, occ: occ, tasks: tasks, months: months, cost: priced ? cost : null, unpriced: unpriced };
	}

	function view(cfg, i, today) {
		var r = calc(cfg, i, today), F = ZT.fmt;
		if (!r.occ.length) { return { error: 'لا توجد مهام لبيتك حاليًا بحسب ما اخترته. جرّب إضافة مكيفات أو خزانًا، أو تواصل معنا لنرتب لك جدولًا.' }; }
		var big = ZT.arCount(r.occ.length, 'مهمة واحدة', 'مهمتان', 'مهام', 'مهمة') + ' خلال 12 شهرًا';
		var lines = [(r.hs ? r.hs.l : '') + (r.ct ? ' — ' + r.ct.l : ''), 'تبدأ الخطة من ' + ZT.arMonth(+r.start.slice(0, 4), +r.start.slice(5, 7))];
		if (r.cost !== null) { lines.push('التكلفة السنوية التقديرية: تبدأ من ' + F(r.cost) + ' ريال' + (r.unpriced.length ? ' (بدون: ' + r.unpriced.join('، ') + ' — بعد المعاينة)' : '') + ' — بدون أي خصم'); }
		else { lines.push('تكلفة الخطة تتحدد بعد المعاينة.'); }
		var plans = (cfg.plans || []).filter(function (p) { return applies(p.when, r.c); }).map(function (p) { return p.l; });
		if (plans.length) { lines.push('باقة مناسبة لبيتك: ' + plans.join('، ')); }
		var first = r.occ[0];
		var rows = r.tasks.filter(function (t) { return t.dates.length; }).map(function (t) { return [t.l, t.dates.map(function (d) { return ZT.arDate(d); }).join('، ')]; });
		var cal = r.months.map(function (mo) { return { m: ZT.arMonth(mo.y, mo.m), items: mo.items.map(function (o) { return [o.l + (o.overdue ? ' (متأخرة)' : ''), o.url]; }) }; });
		var events = r.occ.map(function (o) { return [o.date, o.l]; });
		var sumList = r.tasks.filter(function (t) { return t.dates.length; }).map(function (t) { return t.l + ' (' + ZT.arDate(t.dates[0]) + ')'; }).join('، ');
		var sum = 'جدول صيانة بيتي (' + (r.hs ? r.hs.l : '') + (r.ct ? ' في ' + r.ct.l : '') + '): ' + sumList + '. أحتاج ترتيب المواعيد.';
		var future = r.occ.slice(0, 20).map(function (o) { return { date: o.date, service: o.l }; });
		return {
			badge: 'تقديري', big: big, lines: lines, table: { title: 'مهامك ومواعيدها', head: ['المهمة', 'المواعيد'], rows: rows }, cal: cal,
			notes: ['الخطة تقديرية مبنية على القواعد المعلنة في «إزاي بنحسب»؛ تعديل مواعيدك متاح عند الحجز.'],
			wa: ZT.waMessage(sum, i.hood || ''), links: [{ href: ZT.icsUrlMulti(cfg.ics, events.slice(0, 60)), label: 'حمّل المواعيد (.ics)', event: 'tool_ics' }, { href: ZT.waSelf(ZT.waMessage(sum, i.hood || '')), label: 'أرسل الجدول لنفسي على واتساب', event: 'tool_share' }],
			optin: { items: future, title: 'فعّل تذكيرات المواعيد' }, ga: { tasks: r.occ.length, first: first.k }
		};
	}

	var API = { calc: calc, view: view, applies: applies };
	if (typeof module === 'object' && module.exports) { module.exports = API; return; }

	/* ----------------------------- browser ----------------------------- */
	function readForm(form) {
		var v = ZT.formVals(form), n = function (k) { return ZT.num(v[k]); }, last = {};
		[].forEach.call(form.querySelectorAll('input[data-last]'), function (el) { if (el.value) { last[el.getAttribute('data-last')] = el.value; } });
		return { hs: v.hs, ct: v.ct, tk: v.tk, acn: n('acn'), gd: !!v.gd, pt: !!v.pt, pe: !!v.pe, sf: n('sf'), sm: v.sm || '', last: last, hood: String(v.hood || '').trim().slice(0, 60) };
	}
	function params(i) { var o = { hs: i.hs, ct: i.ct, tk: i.tk, acn: i.acn, gd: i.gd ? '1' : '', pt: i.pt ? '1' : '', pe: i.pe ? '1' : '', sf: i.sf, sm: i.sm, hood: i.hood }; Object.keys(i.last).forEach(function (k) { o['l_' + k] = i.last[k]; }); return o; }
	function fill(form, q) { [].forEach.call(form.elements, function (el) { if (!el.name || q[el.name] == null) { return; } if (el.type === 'checkbox') { el.checked = q[el.name] === '1'; } else { el.value = q[el.name]; } }); [].forEach.call(form.querySelectorAll('input[data-last]'), function (el) { var v = q['l_' + el.getAttribute('data-last')]; if (v) { el.value = v; } }); }
	if (ZT) { ZT.mount({ calc: view, read: readForm, params: params, fill: fill, ga: function (v) { return v.ga || {}; } }); }
})(typeof window !== 'undefined' ? window : globalThis);
