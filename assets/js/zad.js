(function () {
	'use strict';
	var $ = function (s, c) { return (c || document).querySelector(s); };
	var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

	/* Mobile nav */
	var nav = $('#primary-nav'), overlay = $('.nav-overlay'), burger = $('[data-nav-open]');
	function setNav(open) {
		if (!nav) return;
		nav.classList.toggle('is-open', open);
		if (overlay) overlay.classList.toggle('is-open', open);
		if (burger) burger.setAttribute('aria-expanded', open ? 'true' : 'false');
		document.body.style.overflow = open ? 'hidden' : '';
	}
	if (burger) burger.addEventListener('click', function () { setNav(true); });
	$$('[data-nav-close]').forEach(function (b) { b.addEventListener('click', function () { setNav(false); }); });
	document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setNav(false); });
	$$('.submenu-toggle').forEach(function (b) {
		b.addEventListener('click', function () {
			var sub = b.parentNode.querySelector('.sub-menu');
			if (!sub) return;
			var on = sub.classList.toggle('active');
			b.setAttribute('aria-expanded', on ? 'true' : 'false');
		});
	});

	/* Light / dark theme */
	$$('[data-theme-toggle]').forEach(function (b) {
		b.addEventListener('click', function () {
			var cur = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
			document.documentElement.setAttribute('data-theme', cur);
			try { localStorage.setItem('zad-theme', cur); } catch (e) {}
		});
	});

	/* Back to top */
	var top = $('[data-totop]');
	if (top) {
		window.addEventListener('scroll', function () { top.classList.toggle('is-on', window.scrollY > 700); }, { passive: true });
		top.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });
	}

	/* "Get a quote" buttons scroll to the first visible quote form */
	$$('[data-scroll-quote]').forEach(function (a) {
		a.addEventListener('click', function (e) {
			var t = $('#quote');
			if (t) {
				e.preventDefault();
				t.scrollIntoView({ behavior: 'smooth', block: 'center' });
				var f = $('input[name=name]', t);
				if (f) setTimeout(function () { f.focus({ preventScroll: true }); }, 500);
			}
		});
	});

	if (!$('#quote')) { $$('[data-scroll-quote]').forEach(function (a) { a.style.display = 'none'; }); var d = $('.dock'); if (d) d.style.gridTemplateColumns = '1fr 1fr'; }

	/* Quote form (AJAX with no-JS fallback to admin-post) */
	$$('[data-zad-form]').forEach(function (form) {
		var result = $('[data-result]', form);
		function show(msg, ok, wa) {
			result.hidden = false;
			result.className = 'qform__result ' + (ok ? 'is-ok' : 'is-err');
			result.textContent = msg;
			if (ok && wa) {
				var a = document.createElement('a');
				a.href = wa; a.target = '_blank'; a.rel = 'noopener';
				a.className = 'btn btn--wa'; a.textContent = 'تأكيد عبر واتساب';
				result.appendChild(document.createElement('br'));
				result.appendChild(a);
			}
		}
		form.addEventListener('submit', function (e) {
			var bad = null;
			['name', 'phone', 'service'].forEach(function (n) {
				var el = form.elements[n];
				if (!el) return;
				var v = (el.value || '').trim();
				var invalid = !v || (n === 'phone' && v.replace(/\D/g, '').length < 8);
				el.setAttribute('aria-invalid', invalid ? 'true' : 'false');
				if (invalid && !bad) bad = el;
			});
			if (bad) { e.preventDefault(); bad.focus(); show('تأكد من تعبئة الاسم ورقم الجوال والخدمة.', false); return; }
			if (!window.fetch || !window.ZAD) return; // fall back to normal post
			e.preventDefault();
			form.classList.add('is-loading');
			var data = new FormData(form);
			fetch(ZAD.ajax, { method: 'POST', body: data, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (j) {
					form.classList.remove('is-loading');
					if (j && j.success) {
						show(j.data.message, true, j.data.whatsapp);
						$$('input[name=name],input[name=phone],textarea', form).forEach(function (i) { i.value = ''; });
						if (window.gtag) { gtag('event', 'generate_lead'); }
					} else {
						show((j && j.data && j.data.message) || 'تعذر الإرسال، حاول مرة أخرى.', false);
					}
				})
				.catch(function () { form.classList.remove('is-loading'); show('تعذر الاتصال، حاول مرة أخرى أو اتصل بنا.', false); });
		});
	});

	/* Instant price estimator */
	$$('[data-est]').forEach(function (box) {
		var sel = $('[data-est-select]', box), out = $('[data-est-price]', box), wa = $('[data-est-wa]', box);
		var base = wa ? wa.getAttribute('href') : '';
		sel.addEventListener('change', function () {
			var opt = sel.options[sel.selectedIndex];
			if (!sel.value) { out.textContent = 'اختر خدمة أعلاه'; if (wa) wa.setAttribute('href', base); return; }
			var price = opt.getAttribute('data-price');
			out.textContent = price;
			if (wa && box.dataset.wa) {
				var text = 'مرحباً، أرغب بخدمة: ' + box.dataset.title + ' — ' + opt.text + ' (' + price + ')';
				wa.setAttribute('href', 'https://wa.me/' + box.dataset.wa + '?text=' + encodeURIComponent(text));
			}
		});
	});

	/* Video poster -> load on click */
	$$('.vframe').forEach(function (f) {
		var btn = $('.vframe__play', f);
		btn.addEventListener('click', function () {
			var src = f.getAttribute('data-video'), el, m;
			if ((m = src.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w-]{6,})/))) {
				el = document.createElement('iframe');
				el.src = 'https://www.youtube-nocookie.com/embed/' + m[1] + '?autoplay=1&rel=0';
				el.allow = 'autoplay; encrypted-media; picture-in-picture'; el.allowFullscreen = true; el.title = 'فيديو';
			} else {
				el = document.createElement('video');
				el.src = src; el.controls = true; el.autoplay = true; el.playsInline = true;
			}
			f.appendChild(el); f.classList.add('is-on');
		});
	});

	/* Before / after sliders */
	$$('[data-ba]').forEach(function (fig) {
		var stage = $('.ba__stage', fig), input = $('input', fig);
		input.addEventListener('input', function () { stage.style.setProperty('--p', input.value + '%'); });
	});
})();
