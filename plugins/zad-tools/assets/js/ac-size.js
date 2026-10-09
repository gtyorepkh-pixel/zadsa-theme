/*! zad-tools · AC size calculator — pure formula module (PHP twin: zt_ac_calc / zt_ac_view in includes/tools/ac-size.php). Vanilla JS, no dependencies. */
(function (root) {
	'use strict';
	var ZT = typeof module === 'object' && module.exports ? require('./zt-core.js') : root.ZT;

	function sel(list, k) { for (var i = 0; i < list.length; i++) { if (list[i].k === k) { return list[i]; } } return list[0] || { k: '', l: '', p: 0 }; }
	function pick(sizes, need, maxUnits) {
		for (var n = 1; n <= maxUnits; n++) {
			var per = Math.ceil(need / n);
			for (var i = 0; i < sizes.length; i++) { if (sizes[i] >= per) { return { units: n, size: sizes[i] }; } }
		}
		return null;
	}
	function round(x) { return Math.floor(x + 0.5); }

	/** i = { a, l, w, h, rt, sn, wn, in, top (bool), p } → numbers or null. */
	function calc(cfg, i) {
		var area = i.a > 0 ? i.a : (i.l > 0 && i.w > 0 ? i.l * i.w : 0);
		if (!(area > 0) || area > 1000) { return { error: 'اكتب مساحة الغرفة (م²) أو الطول والعرض.' }; }
		var h = i.h > 0 ? i.h : cfg.refH;
		if (h > 20) { return { error: 'ارتفاع السقف غير منطقي، اكتبه بالمتر.' }; }
		var base = area * cfg.base * (h / cfg.refH);
		var rm = sel(cfg.rooms, i.rt), sn = sel(cfg.sun, i.sn), wn = sel(cfg.win, i.wn), ins = sel(cfg.ins, i['in']);
		var pct = rm.p + sn.p + (i.top ? cfg.topPct : 0) + wn.p + ins.p;
		var people = i.p > 0 ? Math.floor(i.p) : 0;
		var extra = Math.max(0, people - cfg.peopleFree);
		var total = round(base * (1 + pct / 100) + extra * cfg.personBtu);
		var fit = pick(cfg.sizes, total, cfg.maxUnits);
		return { area: area, h: h, base: round(base), rm: rm, sn: sn, wn: wn, ins: ins, top: !!i.top, pct: pct, people: people, extra: extra, total: total, fit: fit };
	}

	function tons(cfg, size) { return ZT.fmt(size / cfg.tonBtu); }
	function sizeLabel(cfg, fit) {
		if (!fit) { return ''; }
		return fit.units === 1 ? 'مكيف ' + tons(cfg, fit.size) + ' طن' : ZT.arCount(fit.units, 'مكيف', 'مكيفان', 'مكيفات', 'مكيف') + '، كل واحد ' + tons(cfg, fit.size) + ' طن';
	}

	/** The answer as a "view" (rendered identically by ZT.resultHtml / zt_result_html). */
	function view(cfg, i) {
		var r = calc(cfg, i);
		if (r.error) { return r; }
		var F = ZT.fmt, big = r.fit ? sizeLabel(cfg, r.fit) : 'الحمل كبير ويحتاج معاينة';
		var rows = [['الأساس: ' + F(r.area) + ' م² × ' + F(cfg.base) + ' × (الارتفاع ' + F(r.h) + ' ÷ ' + F(cfg.refH) + ')', F(r.base) + ' وحدة']];
		[['نوع الغرفة', r.rm], ['التعرض للشمس', r.sn], ['النوافذ', r.wn], ['العزل', r.ins]].forEach(function (x) { if (x[1].p !== 0) { rows.push([x[0] + ': ' + x[1].l, ZT.pctLabel(x[1].p)]); } });
		if (r.top) { rows.push(['دور أخير (تحت السطح)', ZT.pctLabel(cfg.topPct)]); }
		if (r.extra > 0) { rows.push(['أشخاص إضافيون فوق ' + F(cfg.peopleFree) + ' (' + F(r.extra) + ')', '+' + F(r.extra * cfg.personBtu) + ' وحدة']); }
		rows.push(['الحمل الإجمالي بعد التقريب', F(r.total) + ' وحدة']);
		var lines = ['المساحة المحسوبة: ' + F(r.area) + ' م²' + (r.h !== cfg.refH ? ' — الارتفاع ' + F(r.h) + ' م' : ''), 'الحمل الحراري التقديري: ' + F(r.total) + ' وحدة حرارية/ساعة'];
		var notes = ['القيمة تقديرية؛ الفني يؤكد الحمل الفعلي في المعاينة.'];
		if (r.fit) {
			lines.push('السعة المقترحة: ' + F(r.fit.size) + ' وحدة حرارية' + (r.fit.units > 1 ? ' لكل مكيف' : ''));
			if (r.fit.units > 1) { notes.unshift('الحمل أكبر من أكبر مكيف متاح، فنقترح توزيعه على أكثر من مكيف.'); }
		} else { notes.unshift('الحمل أكبر مما تغطيه المقاسات المعتادة، تواصل معنا لنحدد التجهيز المناسب.'); }
		var sum = 'حاسبة المكيف: غرفة ' + F(r.area) + ' م² (' + r.rm.l + (r.top ? '، دور أخير' : '') + ') ← الحمل التقديري ' + F(r.total) + ' وحدة حرارية ← ' + (r.fit ? 'المقترح: ' + big : big) + '. أحتاج استشارة بخصوص التركيب.';
		return { badge: 'تقديري', big: big, lines: lines, table: { title: 'كيف وصلنا للرقم', head: ['البند', 'الأثر'], rows: rows }, notes: notes, wa: ZT.waMessage(sum, i.hood || ''), ga: { btu: r.total, units: r.fit ? r.fit.units : 0 } };
	}

	var API = { calc: calc, view: view, sizeLabel: sizeLabel };
	if (typeof module === 'object' && module.exports) { module.exports = API; return; }

	/* ----------------------------- browser ----------------------------- */
	function readForm(form) {
		var v = ZT.formVals(form), n = function (k) { return ZT.num(v[k]); };
		return { a: n('a'), l: n('l'), w: n('w'), h: n('h'), rt: v.rt, sn: v.sn, wn: v.wn, 'in': v['in'], top: !!v.top, p: n('p'), hood: String(v.hood || '').trim().slice(0, 60) };
	}
	function params(i) { return { a: i.a, l: i.l, w: i.w, h: i.h, rt: i.rt, sn: i.sn, wn: i.wn, 'in': i['in'], top: i.top ? '1' : '', p: i.p, hood: i.hood }; }
	function fill(form, q) { [].forEach.call(form.elements, function (el) { if (!el.name || q[el.name] == null) { return; } if (el.type === 'checkbox') { el.checked = q[el.name] === '1'; } else { el.value = q[el.name]; } }); }
	if (ZT) {
		ZT.mount({ calc: function (cfg, i) { return view(cfg, i); }, read: readForm, params: params, fill: fill, ga: function (v) { return v.ga || {}; } });
	}
})(typeof window !== 'undefined' ? window : globalThis);
