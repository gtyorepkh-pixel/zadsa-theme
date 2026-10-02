(function () {
	'use strict';
	var $ = function (s, r) { return (r || document).querySelector(s); };
	var $$ = function (s, r) { return [].slice.call((r || document).querySelectorAll(s)); };
	function track(name, data) { try { if (window.dataLayer) { window.dataLayer.push({ event: name, zad: data || {} }); } if (window.gtag) { window.gtag('event', name, data || {}); } } catch (e) {} }

	/* ---------- explore map ---------- */
	$$('[data-ixm]').forEach(function (box) {
		function show(i) {
			$$('.ixm__tab', box).forEach(function (t) { var on = t.getAttribute('data-i') === String(i); t.classList.toggle('is-on', on); t.setAttribute('aria-selected', on ? 'true' : 'false'); });
			$$('.ixm__panel', box).forEach(function (p) { p.hidden = p.getAttribute('data-i') !== String(i); });
			$$('.ixm__pin', box).forEach(function (p) { p.classList.toggle('is-on', p.getAttribute('data-i') === String(i)); });
			track('ix_map_open', { index: i });
		}
		box.addEventListener('click', function (e) {
			var b = e.target.closest('.ixm__tab, .ixm__pin');
			if (b) { show(b.getAttribute('data-i')); }
		});
		$$('.ixm__pin', box).forEach(function (p, n) { if (n === 0) { p.classList.add('is-on'); } });
	});

	/* ---------- lifecycle ---------- */
	$$('[data-ixl]').forEach(function (box) {
		box.addEventListener('click', function (e) {
			var b = e.target.closest('.ixl__step'); if (!b) { return; }
			var i = b.getAttribute('data-i');
			$$('.ixl__step', box).forEach(function (s) { s.classList.toggle('is-on', s === b); });
			$$('.ixl__panel', box).forEach(function (p) { p.hidden = p.getAttribute('data-i') !== i; });
		});
	});

	/* ---------- safety ---------- */
	$$('[data-ixs]').forEach(function (box) {
		var hint = $('[data-ixs-hint]', box);
		box.addEventListener('click', function (e) {
			var b = e.target.closest('.ixs__chip'); if (!b) { return; }
			var on = b.getAttribute('aria-pressed') !== 'true';
			b.setAttribute('aria-pressed', on ? 'true' : 'false');
			b.classList.toggle('is-on', on);
			var li = $('.ixs__list li[data-i="' + b.getAttribute('data-i') + '"]', box);
			if (li) { li.hidden = !on; }
			var any = $$('.ixs__chip.is-on', box).length > 0;
			if (hint) { hint.hidden = any; }
			if (on) { track('ix_safety_pick', { chip: b.textContent.trim() }); }
		});
	});

	/* ---------- print helper ---------- */
	document.addEventListener('click', function (e) {
		var b = e.target.closest('[data-ix-print]'); if (!b) { return; }
		var t = b.closest('.ix-printable'); if (!t) { return; }
		t.classList.add('ix-print-now'); document.body.classList.add('ix-print');
		window.print();
		setTimeout(function () { document.body.classList.remove('ix-print'); t.classList.remove('ix-print-now'); }, 500);
	});

	/* ---------- checklist ---------- */
	$$('[data-ixc]').forEach(function (box) {
		var key = box.getAttribute('data-key'), boxes = $$('input[type=checkbox]', box), saved = [];
		try { saved = JSON.parse(localStorage.getItem(key) || '[]'); } catch (e) {}
		boxes.forEach(function (c) { c.checked = saved.indexOf(+c.getAttribute('data-i')) > -1; });
		function upd() {
			var n = boxes.filter(function (c) { return c.checked; }).length;
			$('[data-ixc-n]', box).textContent = n;
			$('[data-ixc-fill]', box).style.width = Math.round(n / boxes.length * 100) + '%';
			try { localStorage.setItem(key, JSON.stringify(boxes.filter(function (c) { return c.checked; }).map(function (c) { return +c.getAttribute('data-i'); }))); } catch (e) {}
		}
		boxes.forEach(function (c) { c.addEventListener('change', function () { upd(); track('ix_check_toggle'); }); });
		upd();
		var wa = $('[data-ixc-wa]', box);
		if (wa) {
			wa.addEventListener('click', function () {
				var txt = 'قائمة التحضير — ' + box.getAttribute('data-svc') + '\n' + $$('li', box).map(function (li) { return (li.querySelector('input').checked ? '✅ ' : '⬜ ') + li.textContent.trim(); }).join('\n');
				var num = box.getAttribute('data-wa');
				window.open('https://wa.me/' + (num || '') + '?text=' + encodeURIComponent(txt), '_blank', 'noopener');
				track('ix_check_whatsapp');
			});
		}
	});

	/* ---------- self-check wizard ---------- */
	$$('[data-ixw]').forEach(function (box) {
		var qs = $$('.ixw__q', box), res = $('[data-ixw-res]', box), form = $('.ixw__form', box),
			prev = $('[data-ixw-prev]', box), next = $('[data-ixw-next]', box), step = 0, tiers = [];
		try { tiers = JSON.parse(box.getAttribute('data-tiers') || '[]'); } catch (e) {}
		function render() {
			qs.forEach(function (q, i) { q.hidden = i !== step; });
			prev.hidden = step === 0;
			next.textContent = step === qs.length - 1 ? 'اعرض النتيجة' : 'التالي';
		}
		function answered(q) { return $$('input:checked', q).length > 0; }
		function summary() {
			var score = 0, rows = [];
			qs.forEach(function (q) {
				var picked = $$('input:checked', q);
				picked.forEach(function (i) { score += +i.getAttribute('data-w') || 0; });
				rows.push([$('legend', q).childNodes[1] ? $('legend', q).textContent.replace(/^\d+/, '').replace(/\(.*?\)/, '').trim() : '', picked.map(function (i) { return i.parentNode.textContent.trim(); }).join('، ') || '—']);
			});
			var tier = tiers[0] || { title: '', text: '', plan: [] };
			tiers.forEach(function (t) { if (score >= t.min) { tier = t; } });
			return { score: score, tier: tier, rows: rows };
		}
		function finish() {
			var s = summary();
			$('[data-res-title]', box).textContent = s.tier.title;
			$('[data-res-text]', box).textContent = s.tier.text;
			$('[data-res-plan]', box).innerHTML = (s.tier.plan || []).map(function (p) { return '<li></li>'; }).join('');
			$$('[data-res-plan] li', box).forEach(function (li, i) { li.textContent = s.tier.plan[i]; });
			$('[data-res-ans]', box).innerHTML = s.rows.map(function () { return '<li><b></b> <span></span></li>'; }).join('');
			$$('[data-res-ans] li', box).forEach(function (li, i) { li.querySelector('b').textContent = s.rows[i][0] + ':'; li.querySelector('span').textContent = s.rows[i][1]; });
			var note = 'طلب من فحص الصفحة — ' + box.getAttribute('data-svc') + '\nنتيجة الفحص: ' + s.tier.title + '\n' + s.rows.map(function (r) { return r[0] + ' ' + r[1]; }).join('\n');
			box._note = note;
			var wa = $('[data-ixw-wa]', box);
			if (wa) { wa.href = 'https://wa.me/' + (box.getAttribute('data-wa') || '') + '?text=' + encodeURIComponent(note); }
			form.hidden = true; res.hidden = false;
			res.scrollIntoView({ behavior: 'smooth', block: 'start' });
			track('ix_wizard_result', { tier: s.tier.title, score: s.score });
		}
		next.addEventListener('click', function () {
			if (!answered(qs[step])) { qs[step].classList.add('is-err'); return; }
			qs[step].classList.remove('is-err');
			if (step < qs.length - 1) { step++; render(); track('ix_wizard_step', { step: step }); } else { finish(); }
		});
		prev.addEventListener('click', function () { if (step > 0) { step--; render(); } });
		$('[data-ixw-reset]', box).addEventListener('click', function () {
			$$('input', form).forEach(function (i) { i.checked = false; });
			step = 0; res.hidden = true; form.hidden = false; render();
		});
		var book = $('[data-ixw-book]', box);
		book.addEventListener('click', function () {
			var open = $('[data-open-wizard]');
			var ta = $('#zad-wizard textarea[name="message"]');
			if (ta && box._note) { ta.value = box._note; }
			if (open) { open.click(); }
			track('ix_wizard_book');
		});
		$$('.ixw__opt input', box).forEach(function (i) { i.addEventListener('change', function () { var q = i.closest('.ixw__q'); q.classList.remove('is-err'); if (q.getAttribute('data-type') === 'single' && step < qs.length - 1) { setTimeout(function () { if (answered(q) && qs[step] === q) { next.click(); } }, 250); } }); });
		render();
	});

	/* ---------- story player ---------- */
	$$('[data-ixst]').forEach(function (box) {
		var scenes = $$('.ixst__scene', box), segs = $$('.ixst__bars i b', box), playBtn = $('[data-ixst-play]', box),
			muteBtn = $('[data-ixst-mute]', box), replay = $('[data-ixst-replay]', box), idx = 0, t = 0, last = 0, playing = false, raf = 0, started = false,
			reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches, audio = null;
		if (box.getAttribute('data-audio')) { audio = new Audio(box.getAttribute('data-audio')); audio.preload = 'none'; }
		function dur(i) { return (+scenes[i].getAttribute('data-sec') || 8) * 1000; }
		function offset(i) { var s = 0; for (var k = 0; k < i; k++) { s += dur(k); } return s / 1000; }
		function show(i, seek) {
			idx = i; t = 0;
			scenes.forEach(function (sc, k) { sc.hidden = k !== i; });
			segs.forEach(function (b, k) { b.style.width = k < i ? '100%' : '0%'; });
			replay.hidden = true;
			if (audio && seek) { try { audio.currentTime = offset(i); } catch (e) {} }
		}
		function tick(ts) {
			if (!playing) { return; }
			if (!last) { last = ts; }
			t += ts - last; last = ts;
			segs[idx].style.width = Math.min(100, t / dur(idx) * 100) + '%';
			if (t >= dur(idx)) {
				if (idx < scenes.length - 1) { show(idx + 1, false); } else { end(); return; }
			}
			raf = requestAnimationFrame(tick);
		}
		function play() {
			if (playing) { return; }
			playing = true; last = 0; box.classList.add('is-playing');
			raf = requestAnimationFrame(tick);
			if (audio) { audio.play().catch(function () {}); }
			if (!started) { started = true; track('ix_story_play'); }
		}
		function pause() { playing = false; cancelAnimationFrame(raf); box.classList.remove('is-playing'); if (audio) { audio.pause(); } }
		function end() { pause(); segs.forEach(function (b) { b.style.width = '100%'; }); replay.hidden = false; track('ix_story_end'); }
		playBtn.addEventListener('click', function () { playing ? pause() : play(); });
		$('[data-ixst-next]', box).addEventListener('click', function () { var w = playing; pause(); show(Math.min(idx + 1, scenes.length - 1), true); if (w) { play(); } });
		$('[data-ixst-prev]', box).addEventListener('click', function () { var w = playing; pause(); show(Math.max(idx - 1, 0), true); if (w) { play(); } });
		replay.addEventListener('click', function () { show(0, true); play(); });
		if (muteBtn) {
			muteBtn.addEventListener('click', function () {
				var on = muteBtn.getAttribute('aria-pressed') !== 'true';
				muteBtn.setAttribute('aria-pressed', on ? 'true' : 'false'); muteBtn.classList.toggle('is-on', on);
				if (audio) { audio.muted = on; }
			});
		}
		$('[data-ixst-book]', box).addEventListener('click', function () {
			var open = $('[data-open-wizard]'), ta = $('#zad-wizard textarea[name="message"]');
			if (ta) { ta.value = 'طلب بعد مشاهدة قصة: ' + box.getAttribute('data-svc') + ' — المشهد: ' + (scenes[idx].querySelector('.ixst__lab') || {}).textContent; }
			if (open) { open.click(); }
			track('ix_story_book');
		});
		show(0, false);
		if ('IntersectionObserver' in window && !reduce) {
			new IntersectionObserver(function (es) {
				es.forEach(function (e) { if (e.isIntersecting && e.intersectionRatio > 0.6) { if (!started) { play(); } } else if (playing) { pause(); } });
			}, { threshold: [0, 0.6] }).observe(box);
		}
	});
})();
