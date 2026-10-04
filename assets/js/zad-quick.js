/* Quick-request cards (أ) q30 and (ب) dx. Vanilla JS, no dependencies. */
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
	function wa(root, text) { return root.dataset.wa ? 'https://wa.me/' + root.dataset.wa + '?text=' + encodeURIComponent(text) : '#'; }
	/* No WhatsApp number configured: the final button opens the booking sheet with the same message. */
	function send(root, go, text, e) { go._note = text; if (!root.dataset.wa) { e.preventDefault(); if (window.zadOpenWizard) { window.zadOpenWizard(text); } return false; } return true; }

	/* ---------- (أ) ---------- */
	$$('[data-q30]').forEach(function (root) {
		var go = $('[data-go]', root), pop = $('[data-pop]', root), pbtn = $('[data-pbtn]', root), pick = { label: '', price: '' };
		var hoodIn = $('[data-hood-in]', root);
		function sel(g) { var b = $('[data-g="' + g + '"] .on', root); return b ? b.textContent.trim() : ''; }
		function build() {
			var hood = (hoodIn && !hoodIn.hidden && hoodIn.value.trim()) ? hoodIn.value.trim() : (sel('hood') === 'حي آخر +' ? '' : sel('hood'));
			var t = 'مرحباً، أريد خدمة: ' + (sel('svc') || root.dataset.title);
			if (hood) { t += '\nالحي: ' + hood; }
			t += '\nالوقت المفضل: ' + sel('when');
			if (pick.label) { t += '\nالباقة: ' + pick.label + (pick.price ? ' (' + pick.price + ')' : ''); }
			t += '\nالصفحة: ' + root.dataset.url;
			go.href = wa(root, t); go._note = t;
		}
		$$('[data-g] button', root).forEach(function (b) {
			b.addEventListener('click', function () {
				if (b.hasAttribute('data-other')) { $$('[data-g="hood"] button', root).forEach(function (x) { x.classList.remove('on'); }); b.classList.add('on'); if (hoodIn) { hoodIn.hidden = false; hoodIn.focus(); } build(); return; }
				$$('button', b.parentNode).forEach(function (x) { x.classList.remove('on'); }); b.classList.add('on');
				if (hoodIn && b.parentNode.getAttribute('data-g') === 'hood') { hoodIn.hidden = true; }
				build();
			});
		});
		if (hoodIn) { hoodIn.addEventListener('input', build); }
		if (pbtn && pop) {
			pbtn.addEventListener('click', function () { var open = pop.hidden; pop.hidden = !open; pbtn.setAttribute('aria-expanded', open ? 'true' : 'false'); });
			$$('.q30__po', pop).forEach(function (b) {
				b.addEventListener('click', function () {
					$$('.q30__po', pop).forEach(function (x) { x.classList.remove('on'); }); b.classList.add('on');
					pick.label = b.getAttribute('data-l'); pick.price = b.getAttribute('data-s');
					$('[data-plabel]', root).textContent = pick.label; $('[data-psub]', root).textContent = pick.price;
					pop.hidden = true; pbtn.setAttribute('aria-expanded', 'false'); build();
				});
			});
			document.addEventListener('click', function (e) { if (!pop.hidden && !root.contains(e.target)) { pop.hidden = true; pbtn.setAttribute('aria-expanded', 'false'); } });
		}
		go.addEventListener('click', function (e) { build(); if (send(root, go, go._note, e)) { beacon(root); } });
		build();
	});

	/* ---------- (ب) ---------- */
	$$('[data-dx]').forEach(function (root) {
		var data = []; try { data = JSON.parse($('[data-dx-json]', root).textContent); } catch (e) {}
		var bar = $$('.dx__bar i', root), go = $('[data-go]', root), more = $('[data-more]', root), cur = null, size = '';
		function step(n) { $$('.dx__step', root).forEach(function (s) { s.classList.toggle('on', s.getAttribute('data-s') == n); }); bar.forEach(function (b, i) { b.classList.toggle('on', i < n); }); }
		$$('.dx__step[data-s="1"] .dx__tile', root).forEach(function (t) { t.addEventListener('click', function () { cur = data[parseInt(t.getAttribute('data-i'), 10)]; step(2); }); });
		$$('.dx__step[data-s="2"] .dx__tile', root).forEach(function (t) {
			t.addEventListener('click', function () {
				if (!cur) { step(1); return; }
				size = t.getAttribute('data-size');
				$('[data-rsv]', root).textContent = cur.svc;
				var pr = $('[data-rpr]', root); pr.hidden = !cur.price; pr.textContent = cur.price;
				var sm = $('[data-rsm]', root); sm.innerHTML = ''; [cur.label, size].forEach(function (x) { var s = document.createElement('span'); s.textContent = x; sm.appendChild(s); });
				if (cur.url) { more.href = cur.url; more.hidden = false; } else { more.hidden = true; }
				var txt = 'مرحباً، ألاحظ: ' + cur.label + '\nالمكان: ' + size + '\nالخدمة المقترحة: ' + cur.svc + (cur.price ? ' (' + cur.price + ')' : '') + '\nالصفحة: ' + root.dataset.url;
				go.href = wa(root, txt); go._note = txt; step(3);
			});
		});
		$$('[data-back]', root).forEach(function (b) { b.addEventListener('click', function () { step(1); }); });
		$$('[data-reset]', root).forEach(function (b) { b.addEventListener('click', function () { cur = null; step(1); }); });
		go.addEventListener('click', function (e) { if (send(root, go, go._note || '', e)) { beacon(root); } });
	});
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
