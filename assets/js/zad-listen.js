(function () {
	'use strict';
	var box = document.querySelector('[data-listen]');
	if (!box || !('speechSynthesis' in window) || !window.SpeechSynthesisUtterance) { return; }
	var synth = window.speechSynthesis, voice = null, blocks = [], chunks = [], pos = 0, state = 'idle', rate = 1;
	var btn = box.querySelector('[data-l-play]'), label = box.querySelector('[data-l-label]'), ctl = box.querySelector('[data-l-ctl]'),
		stopBtn = box.querySelector('[data-l-stop]'), rateSel = box.querySelector('[data-l-rate]'), status = box.querySelector('[data-l-status]');
	function track(n, d) { try { if (window.dataLayer) { window.dataLayer.push({ event: n, zad: d || {} }); } if (window.gtag) { window.gtag('event', n, d || {}); } } catch (e) {} }
	try { var saved = parseFloat(localStorage.getItem('zad-listen-rate')); if (saved) { rate = saved; rateSel.value = String(saved); } } catch (e) {}

	function pickVoice() {
		var vs = synth.getVoices() || [];
		var ar = vs.filter(function (v) { return /^ar/i.test(v.lang); });
		voice = ar.filter(function (v) { return /SA/i.test(v.lang); })[0] || ar[0] || null;
		return !!voice;
	}
	function ready() { if (pickVoice()) { box.hidden = false; return true; } return false; }
	if (!ready()) {
		synth.addEventListener && synth.addEventListener('voiceschanged', ready);
		setTimeout(ready, 1200); setTimeout(ready, 3000);
	}

	function collect() {
		blocks = []; chunks = [];
		var h1 = document.querySelector('h1'); if (h1) { blocks.push(h1); }
		[].forEach.call(document.querySelectorAll('.entry-content h2, .entry-content h3, .entry-content h4, .entry-content p, .entry-content li'), function (el) {
			if (el.closest('.listen, .toc, nav, table, form, script, style, .ixw, .ixc, .faq-ext')) { return; }
			if ((el.textContent || '').trim().length > 1) { blocks.push(el); }
		});
		blocks.forEach(function (el, bi) {
			var txt = (el.textContent || '').replace(/\s+/g, ' ').trim();
			(txt.match(/[^.!؟?؛]+[.!؟?؛]?/g) || [txt]).forEach(function (s) {
				s = s.trim(); if (!s) { return; }
				while (s.length > 220) { var cut = s.lastIndexOf(' ', 200); if (cut < 80) { cut = 200; } chunks.push({ t: s.slice(0, cut), b: bi }); s = s.slice(cut).trim(); }
				if (s) { chunks.push({ t: s, b: bi }); }
			});
		});
	}
	function mark(bi) {
		blocks.forEach(function (b) { b.classList.remove('is-reading'); });
		var el = blocks[bi]; if (!el) { return; }
		el.classList.add('is-reading');
		var r = el.getBoundingClientRect();
		if (r.top < 80 || r.bottom > window.innerHeight - 80) { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
		status.textContent = (bi + 1) + ' / ' + blocks.length;
	}
	function speak() {
		if (pos >= chunks.length) { finish(); return; }
		var c = chunks[pos], u = new SpeechSynthesisUtterance(c.t);
		u.lang = voice ? voice.lang : 'ar-SA'; if (voice) { u.voice = voice; } u.rate = rate;
		u.onstart = function () { mark(c.b); };
		u.onend = function () { if (state === 'playing') { pos++; speak(); } };
		u.onerror = function (e) { if (state === 'playing' && e.error !== 'interrupted' && e.error !== 'canceled') { pos++; speak(); } };
		synth.speak(u);
	}
	function ui(playing) {
		btn.setAttribute('aria-pressed', playing ? 'true' : 'false');
		label.textContent = state === 'idle' ? 'استمع إلى الصفحة' : (playing ? 'إيقاف مؤقت' : 'متابعة الاستماع');
		ctl.hidden = state === 'idle';
	}
	function start() { collect(); if (!chunks.length) { return; } pos = 0; state = 'playing'; synth.cancel(); ui(true); speak(); track('listen_start'); }
	function finish() { state = 'idle'; blocks.forEach(function (b) { b.classList.remove('is-reading'); }); status.textContent = ''; ui(false); track('listen_end'); }
	btn.addEventListener('click', function () {
		if (state === 'idle') { start(); }
		else if (state === 'playing') { state = 'paused'; synth.pause(); ui(false); }
		else { state = 'playing'; synth.resume(); if (!synth.speaking) { speak(); } ui(true); }
	});
	stopBtn.addEventListener('click', function () { state = 'idle'; synth.cancel(); finish(); });
	rateSel.addEventListener('change', function () {
		rate = parseFloat(rateSel.value) || 1;
		try { localStorage.setItem('zad-listen-rate', String(rate)); } catch (e) {}
		if (state === 'playing') { synth.cancel(); speak(); }
	});
	window.addEventListener('pagehide', function () { synth.cancel(); });
})();
