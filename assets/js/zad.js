(function () {
	'use strict';
	var $ = function (s, c) { return (c || document).querySelector(s); };
	var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

	/* The booking sheet is printed in the footer (after this script may run): open it by delegation, always. */
	document.addEventListener('click', function (e) {
		var b = e.target.closest && e.target.closest('[data-open-wizard]');
		if (!b) return;
		e.preventDefault();
		if (window.zadOpenWizard) window.zadOpenWizard();
	});

	function init() {

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

	/* Booking wizard */
	var wiz = $('#zad-wizard');
	if (wiz) {
		var form = $('[data-wiz-form]', wiz), svcs = [], steps = $$('.wiz__step', wiz), cur = 1;
		try { svcs = JSON.parse(wiz.getAttribute('data-services') || '[]'); } catch (e) {}
		var sel = $('[data-wiz-service]', wiz), hidSvc = form.elements.service, cat = 0;
		function fillServices() {
			var keep = hidSvc.value;
			sel.innerHTML = '<option value="">اختر الخدمة</option>';
			svcs.forEach(function (s) {
				if (cat && s.cat !== cat) return;
				var o = document.createElement('option'); o.value = s.id; o.textContent = s.name;
				if (String(s.id) === String(keep)) o.selected = true;
				sel.appendChild(o);
			});
			renderSvcs(keep);
		}
		function renderSvcs(keep) {
			var box = $('[data-wiz-svcs]', wiz); if (!box) return;
			var hasCats = $$('[data-wiz-cats] .wiz__cat', wiz).length > 0;
			var list = svcs.filter(function (s) { return !cat || !s.cat || s.cat === cat; });
			if (hasCats && !cat && svcs.length > 8) { list = svcs.filter(function (s) { return String(s.id) === String(keep); }); }
			box.innerHTML = '';
			if (!list.length) { if (hasCats && svcs.length) { var h = document.createElement('p'); h.className = 'wz__hint'; h.textContent = 'اختر القسم لتظهر خدماته'; box.appendChild(h); } return; }
			list.forEach(function (s) {
				var b = document.createElement('button'); b.type = 'button'; b.className = 'wz__svc' + (String(s.id) === String(keep) ? ' is-on' : ''); b.textContent = s.name;
				b.addEventListener('click', function () {
					hidSvc.value = s.id; sel.value = s.id; err(1, '');
					$$('.wz__svc', box).forEach(function (x) { x.classList.toggle('is-on', x === b); });
				});
				box.appendChild(b);
			});
		}
		function open() {
			wiz.classList.add('is-open'); wiz.setAttribute('aria-hidden', 'false'); document.body.style.overflow = 'hidden';
			form.elements.source.value = location.href;
			var c = wiz.getAttribute('data-current');
			if (c && c !== '0' && !hidSvc.value) { hidSvc.value = c; }
			var cs = svcs.filter(function (x) { return String(x.id) === String(hidSvc.value); })[0];
			if (cs && !cat) { cat = cs.cat; $$('[data-wiz-cats] .wiz__cat', wiz).forEach(function (x) { x.classList.toggle('is-on', +x.getAttribute('data-cat') === cat); }); }
			fillServices();
			var ar = wiz.getAttribute('data-area');
			if (ar && form.elements.area && !form.elements.area.value) { $$('[data-wiz-city] button', wiz).forEach(function (b) { if (b.getAttribute('data-city') === ar) b.click(); }); }
			go(cur);
		}
		function close() { wiz.classList.remove('is-open'); wiz.setAttribute('aria-hidden', 'true'); document.body.style.overflow = ''; }
		function go(n) {
			cur = n;
			steps.forEach(function (st) { st.hidden = st.getAttribute('data-step') != n; });
			$$('[data-dot]', wiz).forEach(function (d) { var k = +d.getAttribute('data-dot'); d.classList.toggle('is-on', k <= n); });
			$('[data-wiz-back]', wiz).hidden = n === 1;
			$('[data-wiz-next]', wiz).hidden = n === 3;
			$('[data-wiz-submit]', wiz).hidden = n !== 3;
			var lb = $('[data-step-lbl]', wiz); if (lb) lb.textContent = 'الخطوة ' + n + ' من 3 · ' + ['اختر خدمتك', 'اختر وقتك', 'بياناتك'][n - 1];
			if (n === 3) summary();
		}
		function err(n, msg) { var e = $('[data-err="' + n + '"]', wiz); if (!e) return; e.hidden = !msg; e.textContent = msg || ''; }
		function summary() {
			var s = svcs.filter(function (x) { return String(x.id) === String(hidSvc.value); })[0];
			var rows = [['الخدمة', s ? s.name : '—'], ['المدينة', form.elements.area ? (form.elements.area.value || '—') : '—'],
				['الموعد', [form.elements.date.value, form.elements.time.value].filter(Boolean).join(' ') || 'أي وقت']];
			$('[data-wiz-sum]', wiz).innerHTML = rows.map(function (r) { return '<div><small>' + r[0] + '</small><b></b></div>'; }).join('');
			$$('[data-wiz-sum] b', wiz).forEach(function (b, i) { b.textContent = rows[i][1]; });
		}
		window.zadOpenWizard = function (note) {
			if (typeof note === 'string' && note) { var ta = $('textarea[name="message"]', wiz); if (ta) ta.value = note; }
			open();
		};
		$$('[data-wiz-close]', wiz).forEach(function (b) { b.addEventListener('click', close); });
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && wiz.classList.contains('is-open')) close(); });
		$$('[data-wiz-cats] .wiz__cat', wiz).forEach(function (b) {
			b.addEventListener('click', function () {
				var id = +b.getAttribute('data-cat'); cat = (cat === id) ? 0 : id;
				$$('[data-wiz-cats] .wiz__cat', wiz).forEach(function (x) { x.classList.toggle('is-on', +x.getAttribute('data-cat') === cat); });
				hidSvc.value = ''; fillServices();
			});
		});
		sel.addEventListener('change', function () { hidSvc.value = sel.value; });
		function chips(sel2, field, attr) {
			$$(sel2 + ' button', wiz).forEach(function (b) {
				b.addEventListener('click', function () {
					var on = b.classList.contains('is-on');
					$$(sel2 + ' button', wiz).forEach(function (x) { x.classList.remove('is-on'); });
					if (!on) b.classList.add('is-on');
					if (form.elements[field]) form.elements[field].value = on ? '' : b.getAttribute(attr);
				});
			});
		}
		chips('[data-wiz-city]', 'area', 'data-city'); chips('[data-wiz-time]', 'time', 'data-time');
		var dIn = form.elements.date;
		function iso(d) { return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2); }
		$$('[data-wiz-day] button', wiz).forEach(function (b) {
			b.addEventListener('click', function () {
				var on = b.classList.contains('is-on');
				$$('[data-wiz-day] button', wiz).forEach(function (x) { x.classList.remove('is-on'); });
				if (on) { dIn.value = ''; return; }
				b.classList.add('is-on'); var d = new Date(); d.setDate(d.getDate() + (+b.getAttribute('data-day'))); dIn.value = iso(d);
			});
		});
		dIn.addEventListener('change', function () { $$('[data-wiz-day] button', wiz).forEach(function (x) { x.classList.remove('is-on'); }); });
		var geo = $('[data-wiz-geo]', wiz);
		geo.addEventListener('click', function () {
			var lbl = $('span', geo);
			if (!navigator.geolocation) { lbl.textContent = 'المتصفح لا يدعم تحديد الموقع'; return; }
			lbl.textContent = 'جاري تحديد موقعك…';
			navigator.geolocation.getCurrentPosition(function (p) {
				form.elements.lat.value = p.coords.latitude.toFixed(6); form.elements.lng.value = p.coords.longitude.toFixed(6);
				geo.classList.add('is-on'); lbl.textContent = 'تم تحديد موقعك ✓';
			}, function () { lbl.textContent = 'تعذّر تحديد الموقع — اكتب الحي بدلاً منه'; }, { timeout: 10000 });
		});
		$('[data-wiz-next]', wiz).addEventListener('click', function () {
			if (cur === 1) { if (svcs.length && !hidSvc.value) { err(1, 'اختر الخدمة المطلوبة للمتابعة.'); return; } err(1, ''); }
			go(cur + 1);
		});
		$('[data-wiz-back]', wiz).addEventListener('click', function () { go(cur - 1); });
		form.addEventListener('submit', function (e) {
			var ph = (form.elements.phone.value || '').replace(/\D/g, '');
			if (ph.length < 8) { e.preventDefault(); err(3, 'أدخل رقم جوال صحيح.'); form.elements.phone.focus(); return; }
			if (!window.fetch || !window.ZAD) return;
			e.preventDefault(); err(3, '');
			var btn = $('[data-wiz-submit]', wiz); btn.disabled = true;
			fetch(ZAD.ajax, { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (j) {
					btn.disabled = false;
					if (j && j.success) {
						form.hidden = true; $('.wiz__progress', wiz).hidden = true;
						var d = $('[data-wiz-done]', wiz); d.hidden = false;
						$('[data-wiz-done-msg]', d).textContent = j.data.message;
						var w = $('[data-wiz-wa]', d); if (j.data.whatsapp) { w.href = j.data.whatsapp; } else { w.hidden = true; }
						if (window.gtag) gtag('event', 'generate_lead');
					} else { err(3, (j && j.data && j.data.message) || 'تعذر الإرسال، حاول مرة أخرى.'); }
				})
				.catch(function () { btn.disabled = false; err(3, 'تعذر الاتصال، حاول مرة أخرى أو اتصل بنا.'); });
		});
	}

	/* Knowledge-base live filter */
	var kbIn = $('[data-kb-search]');
	if (kbIn) {
		var norm = function (t) { return t.toLowerCase().replace(/[\u064B-\u065F\u0640]/g, '').replace(/[أإآ]/g, 'ا').replace(/ة/g, 'ه').replace(/ى/g, 'ي'); };
		var items = $$('[data-kb-item]'), groups = $$('[data-kb-group]'), cnt = $('[data-kb-count]'), emp = $('[data-kb-empty]');
		kbIn.addEventListener('input', function () {
			var q = norm(kbIn.value.trim()), words = q.split(/\s+/).filter(Boolean), shown = 0;
			items.forEach(function (it) {
				var hay = it.getAttribute('data-s') || '', ok = words.every(function (w) { return hay.indexOf(w) > -1; });
				it.hidden = !ok; if (ok) shown++; if (words.length && ok) it.open = false;
			});
			groups.forEach(function (g) { g.hidden = !$$('[data-kb-item]:not([hidden])', g).length; });
			if (cnt) cnt.textContent = shown;
			if (emp) emp.hidden = shown > 0;
		});
	}

	/* Pest identification */
	$$('[data-identify]').forEach(function (box) {
		var rules = []; try { rules = JSON.parse(box.getAttribute('data-rules') || '[]'); } catch (e) {}
		var w = '', s1 = $('[data-id-step="1"]', box), s2 = $('[data-id-step="2"]', box), res = $('[data-id-result]', box);
		$$('[data-id-where]', box).forEach(function (b) { b.addEventListener('click', function () { w = b.getAttribute('data-id-where'); s1.hidden = true; s2.hidden = false; }); });
		$('[data-id-back]', box).addEventListener('click', function () { s2.hidden = true; s1.hidden = false; });
		$$('[data-id-kind]', box).forEach(function (b) {
			b.addEventListener('click', function () {
				var k = b.getAttribute('data-id-kind');
				var r = rules.filter(function (x) { return x.where === w && x.kind === k; })[0] || rules.filter(function (x) { return x.kind === k; })[0] || rules.filter(function (x) { return x.where === w; })[0] || rules[0];
				s2.hidden = true; res.hidden = false;
				if (!r) { $('[data-id-title]', res).textContent = 'تعذّر تحديد الآفة'; $('[data-id-text]', res).textContent = 'اتصل بنا لفحص مجاني.'; return; }
				$('[data-id-title]', res).textContent = r.title; $('[data-id-text]', res).textContent = r.text;
				var a = $('[data-id-service]', res); a.hidden = !r.url; if (r.url) { a.href = r.url; a.textContent = 'الحل: ' + r.service; }
				var wa = $('[data-id-wa]', res); wa.hidden = !r.wa; if (r.wa) wa.href = r.wa;
			});
		});
		$('[data-id-reset]', box).addEventListener('click', function () { res.hidden = true; s1.hidden = false; });
	});

	/* Animated counters */
	var counters = $$('[data-count]');
	if (counters.length && 'IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
		var co = new IntersectionObserver(function (es) {
			es.forEach(function (e) {
				if (!e.isIntersecting) return;
				var el = e.target; co.unobserve(el);
				var txt = el.getAttribute('data-count'), m = txt.match(/^(\D*)([\d,\.]+)(.*)$/);
				if (!m) return;
				var end = parseFloat(m[2].replace(/,/g, '')), dec = (m[2].split('.')[1] || '').length, t0 = null, big = m[2].indexOf(',') > -1;
				function step(ts) {
					if (!t0) t0 = ts;
					var p = Math.min((ts - t0) / 1100, 1), v = end * (1 - Math.pow(1 - p, 3));
					var s2 = v.toFixed(dec); if (big) s2 = Math.round(v).toLocaleString('en-US');
					el.textContent = m[1] + s2 + m[3];
					if (p < 1) requestAnimationFrame(step); else el.textContent = txt;
				}
				requestAnimationFrame(step);
			});
		}, { threshold: 0.4 });
		counters.forEach(function (c) { co.observe(c); });
	}

	/* Service tabs */
	$$('[data-tabs]').forEach(function (tabs) {
		var items = $$('.tabitem', tabs.parentNode);
		tabs.addEventListener('click', function (e) {
			var b = e.target.closest('.tabs__b'); if (!b) return;
			$$('.tabs__b', tabs).forEach(function (x) { x.classList.toggle('is-on', x === b); });
			var id = b.getAttribute('data-tab');
			items.forEach(function (it) { var cs = (it.getAttribute('data-cats') || '').split(','); it.hidden = !(id === 'all' || cs.indexOf(id) > -1); });
		});
	});

	/* Testimonial slider */
	$$('[data-slider]').forEach(function (sl) {
		var tr = $('[data-track]', sl);
		$$('[data-slide]', sl).forEach(function (b) {
			b.addEventListener('click', function () {
				var dir = +b.getAttribute('data-slide'), w = tr.firstElementChild ? tr.firstElementChild.getBoundingClientRect().width + 18 : 300;
				tr.scrollBy({ left: dir * w * (document.dir === 'rtl' || getComputedStyle(tr).direction === 'rtl' ? -1 : 1), behavior: 'smooth' });
			});
		});
	});

	/* Floating contact button */
	var fab = $('[data-fab]');
	if (fab) {
		var ft = $('[data-fab-toggle]', fab);
		ft.addEventListener('click', function () { var on = fab.classList.toggle('is-open'); ft.setAttribute('aria-expanded', on ? 'true' : 'false'); });
		document.addEventListener('click', function (e) { if (!fab.contains(e.target)) { fab.classList.remove('is-open'); ft.setAttribute('aria-expanded', 'false'); } });
	}

	/* Reveal on scroll */
	if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
		var targets = $$('.sec:not(.cov) .sec__head, .icard, .scard, .hsteps li, .pkg, .wrow, .faq__item, .tcard, .b2b__s, .client, .cat, .stat, .featgrid li, .ctximg, .faqlinks a');
		var io = new IntersectionObserver(function (es) {
			es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); } });
		}, { rootMargin: '0px 0px -8% 0px', threshold: 0.05 });
		targets.forEach(function (t) { var r = t.getBoundingClientRect(); if (r.top > window.innerHeight) { t.classList.add('rv'); io.observe(t); } });
	}

	/* Lightbox */
	var lbItems = $$('.gal a, [data-lightbox]');
	if (lbItems.length) {
		var lb = document.createElement('div');
		lb.className = 'lb'; lb.hidden = true;
		lb.innerHTML = '<button type="button" class="lb__x" aria-label="إغلاق">×</button><button type="button" class="lb__n lb__n--prev" aria-label="السابق">‹</button><img alt=""><button type="button" class="lb__n lb__n--next" aria-label="التالي">›</button>';
		document.body.appendChild(lb);
		var img = $('img', lb), idx = 0;
		function show(i) { idx = (i + lbItems.length) % lbItems.length; img.src = lbItems[idx].getAttribute('href'); lb.hidden = false; document.body.style.overflow = 'hidden'; }
		function hide() { lb.hidden = true; img.removeAttribute('src'); document.body.style.overflow = ''; }
		lbItems.forEach(function (a, i) { a.addEventListener('click', function (e) { e.preventDefault(); show(i); }); });
		$('.lb__x', lb).addEventListener('click', hide);
		$('.lb__n--prev', lb).addEventListener('click', function () { show(idx + 1); });
		$('.lb__n--next', lb).addEventListener('click', function () { show(idx - 1); });
		lb.addEventListener('click', function (e) { if (e.target === lb) hide(); });
		document.addEventListener('keydown', function (e) { if (lb.hidden) return; if (e.key === 'Escape') hide(); if (e.key === 'ArrowLeft') show(idx + 1); if (e.key === 'ArrowRight') show(idx - 1); });
	}

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
		var opts = $$('[data-est-opt]', box), out = $('[data-est-price]', box), sub = $('[data-est-sub]', box), wa = $('[data-est-wa]', box);
		var area = $('[data-est-area]', box), qty = $('[data-est-qty]', box), cur = null;
		var fmt = function (n) { return n.toLocaleString('en-US'); };
		var q = function () { var v = parseInt(qty.value, 10); return v > 0 ? Math.min(v, 5000) : 1; };
		function render() {
			if (!cur) { return; }
			var price = cur.getAttribute('data-price'), num = parseInt(cur.getAttribute('data-num'), 10) || 0, per = cur.getAttribute('data-per') === '1';
			var nm = cur.parentNode.querySelector('.est__nm').textContent, g = cur.getAttribute('data-group'), detail = '';
			box.classList.add('is-set');
			if (per) {
				area.hidden = false;
				var n = q();
				out.textContent = 'حوالي ' + fmt(num * n) + ' ' + (box.dataset.unit || 'ريال');
				sub.textContent = n + ' م² × ' + price;
				detail = ' — المساحة: ' + n + ' م² — التقدير: ' + out.textContent;
			} else {
				area.hidden = true;
				out.textContent = price; sub.textContent = '';
				detail = ' (' + price + ')';
			}
			$$('.est__presets button', box).forEach(function (b) { b.classList.toggle('on', per && b.getAttribute('data-q') === String(q())); });
			if (wa && box.dataset.wa) {
				var text = 'مرحباً، أرغب بخدمة: ' + box.dataset.title + ' — ' + (g ? g + ': ' : '') + nm + detail;
				wa.setAttribute('href', 'https://wa.me/' + box.dataset.wa + '?text=' + encodeURIComponent(text));
			}
		}
		opts.forEach(function (o) { o.addEventListener('change', function () { if (o.checked) { cur = o; render(); } }); });
		qty.addEventListener('input', render);
		$('[data-est-dec]', box).addEventListener('click', function () { qty.value = Math.max(1, q() - 5); render(); });
		$('[data-est-inc]', box).addEventListener('click', function () { qty.value = Math.min(5000, q() + 5); render(); });
		$$('.est__presets button', box).forEach(function (b) { b.addEventListener('click', function () { qty.value = b.getAttribute('data-q'); render(); }); });
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

	/* ---- Services page: category filter + search ---- */
	$$('[data-svc-filter]').forEach(function (nav) {
		var groups = $$('.svcs__grp'), input = $('[data-svc-search]'), none = $('[data-svc-none]'), f = '*';
		function apply() {
			var q = input ? input.value.trim().toLowerCase() : '', any = false;
			groups.forEach(function (g) {
				var show = f === '*' || g.getAttribute('data-grp') === f, n = 0;
				$$('.svcs__it', g).forEach(function (it) {
					var ok = !q || (it.getAttribute('data-t') || '').indexOf(q) > -1;
					it.hidden = !ok; if (ok) { n++; }
				});
				g.hidden = !show || n === 0; if (!g.hidden) { any = true; }
			});
			if (none) { none.hidden = any; }
		}
		nav.addEventListener('click', function (e) {
			var b = e.target.closest('button'); if (!b) { return; }
			f = b.getAttribute('data-f');
			$$('button', nav).forEach(function (x) { x.classList.toggle('is-on', x === b); });
			apply();
		});
		if (input) { input.addEventListener('input', apply); }
	});

	/* ---- Our work page: filter + show more ---- */
	var wg = $('[data-work-grid]');
	if (wg) {
		var f2 = '*', more = $('[data-work-more]'), revealed = false;
		function paint() {
			var shown = 0;
			$$('.work__it', wg).forEach(function (it) {
				var okc = f2 === '*' || it.getAttribute('data-cat') === f2;
				var hide = !okc || (it.hasAttribute('data-more') && !revealed && f2 === '*');
				it.hidden = hide; if (!hide) { shown++; }
			});
			if (more) { more.parentNode.hidden = revealed || f2 !== '*'; }
		}
		var nav2 = $('[data-work-filter]');
		if (nav2) {
			nav2.addEventListener('click', function (e) {
				var b = e.target.closest('button'); if (!b) { return; }
				f2 = b.getAttribute('data-f');
				$$('button', nav2).forEach(function (x) { x.classList.toggle('is-on', x === b); });
				paint();
			});
		}
		if (more) { more.addEventListener('click', function () { revealed = true; paint(); }); }
		paint();
	}

	/* ---- Sitemap: live search ---- */
	$$('[data-smx]').forEach(function (box) {
		var input = $('[data-smx-search]', box), none = $('[data-smx-none]', box);
		if (!input) { return; }
		input.addEventListener('input', function () {
			var q = input.value.trim().toLowerCase(), any = false;
			$$('.smx__row', box).forEach(function (r) { var ok = !q || r.textContent.toLowerCase().indexOf(q) > -1; r.hidden = !ok; if (ok) { any = true; } });
			$$('.smx__sec', box).forEach(function (sec) { sec.hidden = !!q && !$$('.smx__row:not([hidden])', sec).length; });
			if (none) { none.hidden = any; }
		});
	});
	}
	if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
})();
