/*! zad-tools · moving-cost calculator — pure module (PHP twin: zt_mv_calc / zt_mv_view in includes/tools/moving.php) + the interactive checklist. */
(function (root) {
	'use strict';
	var ZT = typeof module === 'object' && module.exports ? require('./zt-core.js') : root.ZT;

	function find(list, k) { for (var i = 0; i < list.length; i++) { if (list[i].k === k) { return list[i]; } } return list[0] || null; }
	function nz(n) { return n > 0 ? Math.floor(n) : 0; }
	function rng(p) { return p === null ? null : [p[0], p[1]]; }
	function mul(p, n) { return p === null ? null : [p[0] * n, p[1] * n]; }
	var C = function (n, a) { return ZT.arCount(n, a[0], a[1], a[2], a[3]); };
	var W = { room: ['غرفة', 'غرفتين', 'غرف', 'غرفة'], truck: ['سيارة', 'سيارتين', 'سيارات', 'سيارة'], app: ['جهاز كبير', 'جهازين كبيرين', 'أجهزة كبيرة', 'جهازًا كبيرًا'], ac: ['مكيف', 'مكيفين', 'مكيفات', 'مكيفًا'], month: ['شهر', 'شهرين', 'شهور', 'شهر'] };
	function pr(p) { return p[0] === p[1] ? ZT.fmt(p[0]) : ZT.fmt(p[0]) + ' – ' + ZT.fmt(p[1]); }

	/** i = { ty: 'in'|'inter', fc, tc, r (rooms), fr, wm, ac, ff, ef (bool: lift), ft, et, fu (bool), pk (bool), st (months) } */
	function calc(cfg, i) {
		var rooms = nz(i.r);
		if (rooms < 1 || rooms > 30) { return { error: 'اكتب عدد الغرف (من 1 إلى 30).' }; }
		var inter = i.ty === 'inter', fc = find(cfg.cities, i.fc), tc = find(cfg.cities, i.tc);
		if (inter && fc && tc && fc.k === tc.k) { return { error: 'اختر مدينتين مختلفتين للنقل بين المدن.' }; }
		var row = null;
		for (var x = 0; x < cfg.rooms.length; x++) { if (cfg.rooms[x].max === null || rooms <= cfg.rooms[x].max) { row = cfg.rooms[x]; break; } }
		var items = [], manual = [];
		function add(label, p) { if (p === null) { manual.push(label); items.push([label, null]); } else { items.push([label, p]); } }
		if (!row) { return { rooms: rooms, inter: inter, fc: fc, tc: tc, trucks: null, workers: null, items: [['نقل ' + C(rooms, W.room), null]], manual: ['نقل ' + C(rooms, W.room)], lo: null, hi: null, big: true }; }
		add('نقل ' + C(rooms, W.room), row.price);
		if (inter && fc && tc) {
			var rp = null;
			for (var y = 0; y < cfg.routes.length; y++) { var t = cfg.routes[y]; if ((t.a === fc.k && t.b === tc.k) || (t.a === tc.k && t.b === fc.k)) { rp = t.price; break; } }
			add('النقل بين ' + fc.l + ' و' + tc.l + ' (' + C(row.trucks, W.truck) + ')', mul(rp, row.trucks));
		}
		[['من', nz(i.ff), !i.ef], ['إلى', nz(i.ft), !i.et]].forEach(function (s) {
			if (s[1] > 0 && s[2]) { add('الأدوار بدون أسانسير (' + s[0] + ': الدور ' + s[1] + ')', mul(cfg.floor, s[1])); }
		});
		var app = nz(i.fr) + nz(i.wm); if (app > 0) { add('نقل ' + C(app, W.app) + ' (ثلاجة/غسالة)', mul(cfg.appliance, app)); }
		var ac = nz(i.ac); if (ac > 0) { add('فك وتركيب ' + C(ac, W.ac), mul(cfg.acInstall, ac)); }
		if (i.fu) { add('فك وتركيب الأثاث (' + C(rooms, W.room) + ')', mul(cfg.furniture, rooms)); }
		if (i.pk) { add('التغليف (' + C(rooms, W.room) + ')', mul(cfg.packing, rooms)); }
		var st = nz(i.st); if (st > 0) { add('التخزين ' + C(st, W.month), mul(cfg.storage, st)); }
		var lo = 0, hi = 0, any = false;
		items.forEach(function (it) { if (it[1] !== null) { lo += it[1][0]; hi += it[1][1]; any = true; } });
		return { rooms: rooms, inter: inter, fc: fc, tc: tc, trucks: row.trucks, workers: row.workers, items: items, manual: manual, lo: any ? lo : null, hi: any ? hi : null, big: false };
	}

	function view(cfg, i) {
		var r = calc(cfg, i);
		if (r.error) { return r; }
		var F = ZT.fmt;
		var big = r.trucks === null ? 'يحتاج معاينة لتحديد السيارات والعمال' : ZT.arCount(r.trucks, 'سيارة', 'سيارتان', 'سيارات', 'سيارة') + ' و' + ZT.arCount(r.workers, 'عامل', 'عاملان', 'عمال', 'عامل');
		var lines = [r.inter && r.fc && r.tc ? 'النقل بين ' + r.fc.l + ' و' + r.tc.l : 'النقل داخل المدينة' + (r.fc ? ' (' + r.fc.l + ')' : ''), 'عدد الغرف: ' + F(r.rooms)];
		if (r.lo !== null) { lines.push('نطاق السعر التقديري: ' + pr([r.lo, r.hi]) + ' ريال' + (r.manual.length ? ' (للبنود المسعّرة فقط)' : '')); }
		else { lines.push('السعر يتحدد بعد المعاينة.'); }
		var rows = r.items.map(function (it) { return [it[0], it[1] === null ? 'بعد المعاينة' : pr(it[1]) + ' ريال']; });
		var notes = ['النطاق تقديري ولا يشمل أي بند مكتوب «بعد المعاينة»؛ السعر النهائي بعد المعاينة والاتفاق.'];
		var sum = 'حاسبة نقل العفش: ' + (r.inter && r.fc && r.tc ? 'من ' + r.fc.l + ' إلى ' + r.tc.l : 'داخل المدينة') + '، ' + C(r.rooms, W.room) + (i.ac > 0 ? '، ' + C(nz(i.ac), W.ac) + ' للفك والتركيب' : '') + (nz(i.fr) + nz(i.wm) > 0 ? '، ' + C(nz(i.fr) + nz(i.wm), W.app) : '') + (i.fu ? '، مع فك وتركيب الأثاث' : '') + (i.pk ? '، مع التغليف' : '') + (nz(i.st) > 0 ? '، وتخزين ' + C(nz(i.st), W.month) : '') + (r.trucks !== null ? ' ← التقدير: ' + big : '') + (r.lo !== null ? '، السعر التقديري ' + pr([r.lo, r.hi]) + ' ريال' : '') + '. أحتاج معاينة وعرض سعر.';
		return { badge: 'تقديري', big: big, lines: lines, table: { title: 'تفصيل البنود', head: ['البند', 'التقدير'], rows: rows }, notes: notes, wa: ZT.waMessage(sum, i.hood || ''), ga: { rooms: r.rooms, trucks: r.trucks === null ? -1 : r.trucks, inter: r.inter ? 1 : 0 } };
	}

	var API = { calc: calc, view: view };
	if (typeof module === 'object' && module.exports) { module.exports = API; return; }

	/* ----------------------------- browser ----------------------------- */
	function readForm(form) {
		var v = ZT.formVals(form), n = function (k) { return ZT.num(v[k]); };
		return { ty: v.ty, fc: v.fc, tc: v.tc, r: n('r'), fr: n('fr'), wm: n('wm'), ac: n('ac'), ff: n('ff'), ef: !!v.ef, ft: n('ft'), et: !!v.et, fu: !!v.fu, pk: !!v.pk, st: n('st'), hood: String(v.hood || '').trim().slice(0, 60) };
	}
	function params(i) { return { ty: i.ty, fc: i.fc, tc: i.ty === 'inter' ? i.tc : '', r: i.r, fr: i.fr, wm: i.wm, ac: i.ac, ff: i.ff, ef: i.ef ? '1' : '', ft: i.ft, et: i.et ? '1' : '', fu: i.fu ? '1' : '', pk: i.pk ? '1' : '', st: i.st, hood: i.hood }; }
	function fill(form, q) { [].forEach.call(form.elements, function (el) { if (!el.name || q[el.name] == null) { return; } if (el.type === 'checkbox') { el.checked = q[el.name] === '1'; } else { el.value = q[el.name]; } }); syncTy(form); }
	function syncTy(form) { var t = form.elements.tc, inter = form.elements.ty && form.elements.ty.value === 'inter'; if (t) { var w = t.closest('.fld'); if (w) { w.hidden = !inter; } } }

	/* the checklist: ticks are kept in this browser only (localStorage inside try/catch) */
	function initChecklist() {
		var box = document.querySelector('[data-zt-checklist]'); if (!box) { return; }
		var KEY = 'zt_move_chk', saved = {};
		try { saved = JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch (e) {}
		[].forEach.call(box.querySelectorAll('input[type=checkbox][data-k]'), function (cb) { if (saved[cb.getAttribute('data-k')]) { cb.checked = true; } });
		box.addEventListener('change', function (e) {
			var cb = e.target; if (!cb.matches || !cb.matches('input[data-k]')) { return; }
			saved[cb.getAttribute('data-k')] = cb.checked ? 1 : 0;
			try { localStorage.setItem(KEY, JSON.stringify(saved)); } catch (x) {}
		});
		var acts = box.querySelector('[data-zt-chk-actions]'); if (!acts) { return; }
		acts.querySelector('[data-act=print]').addEventListener('click', function () { ZT.ev('tool_print', {}); root.print(); });
		acts.querySelector('[data-act=download]').addEventListener('click', function () {
			var out = [];
			[].forEach.call(box.querySelectorAll('[data-group]'), function (g) { out.push(g.getAttribute('data-group')); [].forEach.call(g.querySelectorAll('li'), function (li) { out.push((li.querySelector('input').checked ? '[x] ' : '[ ] ') + li.textContent.trim()); }); out.push(''); });
			var a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([out.join('\n')], { type: 'text/plain;charset=utf-8' })); a.download = 'moving-checklist.txt'; document.body.appendChild(a); a.click(); a.remove();
			ZT.ev('tool_download', {});
		});
	}
	if (ZT) {
		ZT.mount({ calc: view, read: readForm, params: params, fill: fill, ga: function (v) { return v.ga || {}; } });
		var form = document.querySelector('form[data-zt-form]');
		if (form && form.elements.ty) { form.elements.ty.addEventListener('change', function () { syncTy(form); }); syncTy(form); }
		initChecklist();
	}
})(typeof window !== 'undefined' ? window : globalThis);
