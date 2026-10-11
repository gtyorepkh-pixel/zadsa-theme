/* «أعمالنا»: the clip buttons seek the video (file or YouTube); #t=90 / #t=1:30 opens the page at that second. No libraries. */
(function () {
	'use strict';
	var box = document.querySelector('[data-zw-player]'); if (!box) { return; }
	var kind = box.getAttribute('data-kind'), yt = box.getAttribute('data-yt'), video = box.querySelector('video'), frame = null;
	var clips = [].slice.call(document.querySelectorAll('.zw-clip'));

	function secs(v) {
		v = String(v || '').replace(/^#?t=/, '');
		if (/^\d+$/.test(v)) { return parseInt(v, 10); }
		var m = v.match(/^(?:(\d+):)?(\d{1,2}):(\d{1,2})$/);
		return m ? (parseInt(m[1] || 0, 10) * 3600 + parseInt(m[2], 10) * 60 + parseInt(m[3], 10)) : null;
	}
	function mark(t) {
		clips.forEach(function (b) { var s = +b.dataset.start, e = +b.dataset.end; b.classList.toggle('is-on', t >= s && t < e); });
	}
	function openYT(start, play) {
		var src = 'https://www.youtube-nocookie.com/embed/' + yt + '?rel=0&start=' + (start || 0) + (play ? '&autoplay=1' : '');
		if (!frame) {
			frame = document.createElement('iframe');
			frame.allow = 'autoplay; encrypted-media; picture-in-picture'; frame.allowFullscreen = true; frame.title = 'فيديو العمل'; frame.loading = 'lazy';
			var ph = box.querySelector('.zw-yt'); if (ph) { ph.remove(); }
			box.appendChild(frame);
		}
		frame.src = src;
	}
	function seek(t, play) {
		if (t === null || isNaN(t)) { return; }
		if (video) {
			var go = function () { try { video.currentTime = t; } catch (e) {} if (play) { var p = video.play(); if (p && p.catch) { p.catch(function () {}); } } };
			if (video.readyState >= 1) { go(); } else { video.addEventListener('loadedmetadata', go, { once: true }); video.load(); }
		} else if (kind === 'yt') { openYT(t, play); }
		mark(t);
	}
	clips.forEach(function (b) {
		b.addEventListener('click', function () { seek(+b.dataset.start, true); box.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); });
	});
	if (video) { video.addEventListener('timeupdate', function () { mark(video.currentTime); }); }
	var ph = box.querySelector('.zw-yt__play');
	if (ph) { ph.addEventListener('click', function () { openYT(0, true); }); }
	function fromHash() { var h = location.hash; if (/^#t=/.test(h)) { seek(secs(h), false); } }
	fromHash();
	window.addEventListener('hashchange', fromHash);
})();
