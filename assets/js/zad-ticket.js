/* Hero card «تذكرة الحجز»: tile → price + button text + WhatsApp link; day → message. Vanilla JS. */
(function () {
	'use strict';
	function init() {
		function $(s, r) { return (r || document).querySelector(s); }
		function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }
		function ajax() { return (window.ZAD && window.ZAD.ajax) || '/wp-admin/admin-ajax.php'; }
		function beacon(root) {
			try {
				var fd = new FormData(); fd.append('action', 'zad_wa_click'); fd.append('src', root.dataset.src || ''); fd.append('post', root.dataset.post || 0);
				if (navigator.sendBeacon) { navigator.sendBeacon(ajax(), fd); } else { fetch(ajax(), { method: 'POST', body: fd, keepalive: true, credentials: 'same-origin' }); }
			} catch (e) {}
		}
		$$('[data-tk]').forEach(function (root) {
			var go = $('[data-tk-go]', root), lbl = $('[data-tk-lbl]', root), price = $('[data-tk-price]', root), lab = $('[data-tk-lab]', root), link = $('[data-tk-link]', root);
			var tiles = $$('.tk__tile[aria-pressed]', root), days = $$('[data-day]', root);
			function cur() { return tiles.filter(function (t) { return t.getAttribute('aria-pressed') === 'true'; })[0] || tiles[0]; }
			function day() { var d = days.filter(function (b) { return b.getAttribute('aria-pressed') === 'true'; })[0]; return d ? d.getAttribute('data-day') : ''; }
			function msg() { var t = cur(); return 'السلام عليكم، أبغى ' + (t.dataset.svc || t.dataset.name) + ' — ' + root.dataset.title + ' — الموعد: ' + day(); }
			function paint() {
				var t = cur(), p = t.dataset.price || '';
				price.textContent = p || 'بعد المعاينة'; if (lab) { lab.textContent = p ? 'يبدأ من' : 'السعر'; }
				lbl.textContent = 'اطلب ' + (t.dataset.svc || t.dataset.name) + ' على واتساب';
				if (link) { var u = t.dataset.url || ''; link.hidden = !u; if (u) { link.href = u; } }
				go.href = root.dataset.wa ? 'https://wa.me/' + root.dataset.wa + '?text=' + encodeURIComponent(msg()) : '#';
			}
			tiles.forEach(function (t) {
				t.addEventListener('click', function () { tiles.forEach(function (x) { x.setAttribute('aria-pressed', x === t ? 'true' : 'false'); }); paint(); });
			});
			days.forEach(function (b) {
				b.addEventListener('click', function () { days.forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); }); paint(); });
			});
			var more = $('[data-tk-more]', root);
			if (more) { more.addEventListener('click', function () { if (window.zadOpenWizard) { window.zadOpenWizard(msg()); } }); }
			go.addEventListener('click', function (e) {
				paint();
				if (!root.dataset.wa) { e.preventDefault(); if (window.zadOpenWizard) { window.zadOpenWizard(msg()); } return; }
				beacon(root);
			});
			paint();
		});
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
