/*! zad-tools · image-based pest identification (locked feature). The photo is shrunk in the browser, sent with the cache-safe token, and the answer is
 *  shown as «تقريبي» with the technician fallback. No image is stored by this script. */
(function (root) {
	'use strict';
	var ZT = typeof module === 'object' && module.exports ? require('./zt-core.js') : root.ZT;
	var TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/heic'];

	/** Largest size that fits in max×max keeping the aspect ratio (never enlarges). */
	function fitSize(w, h, max) { var s = Math.min(1, max / Math.max(w, h)); return [Math.max(1, Math.round(w * s)), Math.max(1, Math.round(h * s))]; }
	/** Client-side checks before any upload → error text or ''. */
	function check(file, maxMb) {
		if (!file) { return 'اختر صورة أولاً.'; }
		if (TYPES.indexOf(file.type) < 0 && !/\.(jpe?g|png|webp|heic)$/i.test(file.name || '')) { return 'نوع الملف غير مدعوم (jpg / png / webp / heic).'; }
		if (file.size > maxMb * 1048576 * 4) { return 'الصورة كبيرة جدًا.'; }
		return '';
	}
	/** The «result» view: cards + the ever-present technician fallback. */
	function view(cards, hood) {
		var top = cards[0], sum = top ? 'أرسلت صورة لحشرة وحدّدها المعرّف تقريبيًا: ' + top.t + ' (' + ZT.fmt(top.p) + '%). أرجو تأكيد الفني.' : 'أرسلت صورة لحشرة ولم يحددها المعرّف، وأرفقها لكم هنا.';
		return { badge: 'تقريبي', big: top ? 'يبدو أقرب إلى: ' + top.t : 'لم نتعرف على الحشرة', lines: top ? [] : ['أرسل الصورة لفنيينا على واتساب ليحددوها.'], cards: cards, notes: ['التحديد بالصورة تقريبي؛ الفحص الميداني هو الحاسم.'], wa: ZT.waMessage(sum, hood || '') };
	}
	var API = { fitSize: fitSize, check: check, view: view };
	if (typeof module === 'object' && module.exports) { module.exports = API; return; }

	/* ----------------------------- browser ----------------------------- */
	var box = document.querySelector('[data-zt-image]'); if (!box || !ZT) { return; }
	var cfg = {}; try { cfg = JSON.parse(box.getAttribute('data-cfg') || '{}'); } catch (e) {}
	var form = box.querySelector('form'), msg = box.querySelector('.zt-img__msg'), out = document.getElementById('zt-result');

	function shrink(file, maxPx) {
		return new Promise(function (resolve) {
			var done = function (w, h, draw) { var s = fitSize(w, h, maxPx), c = document.createElement('canvas'); c.width = s[0]; c.height = s[1]; draw(c.getContext('2d'), s[0], s[1]); c.toBlob(function (b) { resolve(b && b.size < file.size ? b : file); }, 'image/jpeg', 0.82); };
			var url = URL.createObjectURL(file), img = new Image();
			img.onload = function () { done(img.naturalWidth, img.naturalHeight, function (g, w, h) { g.drawImage(img, 0, 0, w, h); }); URL.revokeObjectURL(url); };
			img.onerror = function () { URL.revokeObjectURL(url); resolve(file); }; // e.g. HEIC the browser cannot decode: send the original (the server re-checks)
			img.src = url;
		});
	}
	form.addEventListener('submit', function (e) {
		e.preventDefault(); msg.textContent = ''; msg.className = 'zt-img__msg';
		var file = form.elements.photo.files[0], err = check(file, cfg.maxMb || 5);
		if (err) { msg.textContent = err; msg.classList.add('is-err'); return; }
		if (!form.elements.consent_analyze.checked) { msg.textContent = 'يلزم الموافقة على إرسال الصورة للتحليل.'; msg.classList.add('is-err'); return; }
		var btn = form.querySelector('button[type=submit]'); btn.disabled = true; msg.textContent = 'جارٍ تصغير الصورة وتحليلها…'; ZT.ev('tool_start', {});
		shrink(file, cfg.maxPx || 1600).then(function (blob) {
			if (blob.size > (cfg.maxMb || 5) * 1048576) { throw { friendly: 'الصورة أكبر من الحجم المسموح حتى بعد التصغير.' }; }
			var fd = new FormData(); fd.append('photo', blob, 'photo.' + (blob.type === 'image/jpeg' ? 'jpg' : 'bin')); fd.append('consent_analyze', '1'); if (form.elements.keep.checked) { fd.append('keep', '1'); } fd.append('website', form.elements.website.value);
			return ZT.postForm('pest-image', fd);
		}).then(function (r) {
			btn.disabled = false;
			if (!r.ok) { msg.textContent = (r.body && r.body.message) || 'تعذر التحليل، أرسل الصورة لفنيينا على واتساب.'; msg.classList.add('is-err'); out.innerHTML = ZT.resultHtml({ badge: 'تقريبي', big: 'لم نتمكن من التحليل', lines: ['أرسل الصورة لفنيينا على واتساب ليحددوها.'], wa: ZT.waMessage('أحتاج تحديد حشرة من صورة سأرسلها.', '') }, ZT.env()); return; }
			msg.textContent = ''; var hood = (document.querySelector('#zt-tool [name="hood"]') || {}).value || '';
			out.innerHTML = ZT.resultHtml(view(r.body.cards || [], hood), ZT.env()); ZT.ev('tool_complete', { image: 1, n: (r.body.cards || []).length });
		}).catch(function (x) { btn.disabled = false; msg.textContent = (x && x.friendly) || 'تعذر الاتصال، حاول مرة أخرى.'; msg.classList.add('is-err'); });
	});
})(typeof window !== 'undefined' ? window : globalThis);
