/*! zad-tools · question-based pest identifier — pure module (PHP twin: zt_pid_calc / zt_pid_view in includes/tools/pest-id.php). */
(function (root) {
	'use strict';
	var ZT = typeof module === 'object' && module.exports ? require('./zt-core.js') : root.ZT;
	function round(x) { return Math.floor(x + 0.5); }
	function has(a, v) { return Array.isArray(a) && a.indexOf(v) >= 0; }
	function isMatch(p, k, ans) {
		if (k === 'wings') { return p.a.wings !== '' && p.a.wings !== undefined && (p.a.wings === 'some' || p.a.wings === ans); }
		return has(p.a[k], ans);
	}

	/** i = { ans: { place, size, color, wings, sign } } ('' = not sure). Score = matched weight ÷ total weight of the questions that were answered. */
	function calc(cfg, i) {
		var tw = 0, answered = [];
		cfg.q.forEach(function (q) {
			var a = (i.ans || {})[q.k];
			if (!a || !q.o.some(function (o) { return o.k === a; })) { return; }
			tw += cfg.w[q.k]; answered.push(q);
		});
		if (!answered.length) { return { error: 'أجب عن سؤال واحد على الأقل (أو اختر «مش متأكد» لباقي الأسئلة).' }; }
		var res = [];
		cfg.pests.forEach(function (p) {
			var mw = 0, n = 0;
			answered.forEach(function (q) { if (isMatch(p, q.k, i.ans[q.k])) { mw += cfg.w[q.k]; n++; } });
			if (mw > 0) { res.push({ p: p, mw: mw, n: n, pct: round(mw / tw * 100) }); }
		});
		res.sort(function (a, b) { return b.pct - a.pct || b.n - a.n || a.p.id - b.p.id; });
		return { answered: answered, total: cfg.q.length, results: res.slice(0, cfg.max), strong: res.length > 0 && res[0].pct >= cfg.min };
	}

	function describe(cfg, i, r) {
		return r.answered.map(function (q) { var o = q.o.filter(function (x) { return x.k === i.ans[q.k]; })[0]; return q.s + ': ' + o.l; }).join('، ');
	}

	function view(cfg, i) {
		var r = calc(cfg, i);
		if (r.error) { return r; }
		var top = r.results[0], d = describe(cfg, i, r);
		var big = r.strong ? 'الأقرب: ' + top.p.n : 'لا يوجد تطابق قوي';
		var lines = ['أجبت عن ' + ZT.fmt(r.answered.length) + ' من ' + ZT.fmt(r.total) + ' أسئلة (' + d + ')'];
		if (!r.strong) { lines.push(r.results.length ? 'أقرب ما وجدناه أدناه، لكن الأفضل أن ترسل صورة للحشرة ليحددها فنيونا.' : 'لم نجد حشرة تطابق إجاباتك في الموسوعة؛ أرسل صورة للحشرة ليحددها فنيونا.'); }
		var cards = r.results.map(function (x) { return { t: x.p.n, u: x.p.u, p: x.pct, img: x.p.img, alt: x.p.alt, svc: x.p.svc }; });
		var sum = r.strong ? 'استخدمت معرّف الحشرات (' + d + '): أقرب نتيجة ' + top.p.n + ' بنسبة تطابق ' + ZT.fmt(top.pct) + '%. أحتاج فحصاً وعلاجاً.' : 'حاولت أعرف الحشرة بمعرّف الأسئلة (' + d + ') ولم أصل لتطابق قوي، وسأرسل لكم صورة لها.';
		return { badge: 'تقريبي', big: big, lines: lines, cards: cards, notes: ['التعريف تقريبي: يعتمد على إجاباتك وعلى بيانات الموسوعة، والفحص الميداني هو الحاسم. نسبة التطابق = عدد إجاباتك التي تنطبق على الحشرة (بأوزانها) ÷ كل إجاباتك.'], wa: ZT.waMessage(sum, i.hood || ''), ga: { top: top ? top.p.id : 0, pct: top ? top.pct : 0, strong: r.strong ? 1 : 0 } };
	}

	var API = { calc: calc, view: view };
	if (typeof module === 'object' && module.exports) { module.exports = API; return; }

	/* ----------------------------- browser ----------------------------- */
	var PARAMS = { place: 'pl', size: 'sz', color: 'co', wings: 'wg', sign: 'sg' };
	function readForm(form) {
		var ans = {}; Object.keys(PARAMS).forEach(function (k) { var el = form.querySelector('input[name="' + PARAMS[k] + '"]:checked'); ans[k] = el ? el.value : ''; });
		return { ans: ans, hood: String((form.elements.hood || {}).value || '').trim().slice(0, 60) };
	}
	function params(i) { var o = { hood: i.hood }; Object.keys(PARAMS).forEach(function (k) { o[PARAMS[k]] = i.ans[k]; }); return o; }
	function fill(form, q) {
		Object.keys(PARAMS).forEach(function (k) { var v = q[PARAMS[k]]; if (v == null) { return; } var el = form.querySelector('input[name="' + PARAMS[k] + '"][value="' + (window.CSS && CSS.escape ? CSS.escape(v) : v) + '"]'); if (el) { el.checked = true; } });
		if (form.elements.hood && q.hood) { form.elements.hood.value = q.hood; }
	}
	if (ZT) { ZT.mount({ calc: view, read: readForm, params: params, fill: fill, ga: function (v) { return v.ga || {}; } }); }
})(typeof window !== 'undefined' ? window : globalThis);
