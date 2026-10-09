/*! zad-tools · coverage map — pure helpers + lazy Leaflet (vendored locally, loaded only when the map is visible or requested). No CDN. */
(function (root) {
	'use strict';
	var ZT = typeof module === 'object' && module.exports ? require('./zt-core.js') : root.ZT;

	/** Great-circle distance in km. */
	function haversine(a, b, c, d) {
		var R = 6371, rad = Math.PI / 180, dLat = (c - a) * rad, dLng = (d - b) * rad;
		var x = Math.sin(dLat / 2) * Math.sin(dLat / 2) + Math.cos(a * rad) * Math.cos(c * rad) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
		return 2 * R * Math.asin(Math.sqrt(x));
	}
	/** Nearest district that has coordinates → { h, km } or null. */
	function nearest(hoods, lat, lng) {
		var best = null;
		hoods.forEach(function (h) { if (h.lat == null || h.lng == null) { return; } var km = haversine(lat, lng, h.lat, h.lng); if (!best || km < best.km) { best = { h: h, km: km }; } });
		return best;
	}
	/** Districts that have the service (svc '' = all). */
	function filter(hoods, svc) { return svc ? hoods.filter(function (h) { return h.v.some(function (v) { return v[0] === svc; }); }) : hoods.slice(); }
	/** Marker size by how many services the district has. */
	function radius(n) { return n >= 4 ? 12 : (n >= 2 ? 9 : 6); }
	function etaText(e) { return !e ? '' : (e.src === 'orders' ? 'متوسط وقت الوصول نحو ' + ZT.fmt(e.m) + ' دقيقة (من ' + ZT.fmt(e.n) + ' طلب مكتمل)' : 'وقت الوصول المعتاد نحو ' + ZT.fmt(e.m) + ' دقيقة'); }
	/** Popup HTML — every value escaped. */
	function popupHtml(h, svc, wa) {
		var links = h.v.filter(function (v) { return !svc || v[0] === svc; }).map(function (v) { return '<a href="' + ZT.esc(v[1]) + '">' + ZT.esc(v[0]) + '</a>'; }).join('<br>');
		var w = wa ? '<br><a target="_blank" rel="noopener" data-zt-event="tool_whatsapp_click" href="' + ZT.esc(ZT.waLink(wa, ZT.waMessage('أحتاج خدمة', h.n))) + '">اطلب في حي ' + ZT.esc(h.n) + ' على واتساب</a>' : '';
		return '<div class="zt-pop"><strong>' + ZT.esc(h.n) + (h.c ? ' — ' + ZT.esc(h.c) : '') + '</strong>' + links + (h.eta ? '<span class="zt-eta">' + ZT.esc(etaText(h.eta)) + '</span>' : '') + w + '</div>';
	}

	var API = { haversine: haversine, nearest: nearest, filter: filter, radius: radius, popupHtml: popupHtml, etaText: etaText };
	if (typeof module === 'object' && module.exports) { module.exports = API; return; }

	/* ----------------------------- browser ----------------------------- */
	var box = document.querySelector('[data-zt-coverage]');
	if (!box || !ZT) { return; }
	var cfg = {}; try { cfg = JSON.parse(box.getAttribute('data-cfg') || '{}'); } catch (e) {}
	var svc = '', map = null, layer = null, markers = {}, started = false, msg = box.querySelector('.zt-cov-msg'), mapEl = document.getElementById('zt-map'), loading = false;
	function start() { if (!started) { started = true; ZT.ev('tool_start', {}); } }

	function applyFilter() {
		var keep = {}; filter(cfg.hoods, svc).forEach(function (h) { keep[h.id] = 1; });
		// the list keeps every district with ANY service (for filters on services a district lacks the item is hidden)
		[].forEach.call(box.querySelectorAll('.zt-hoods li'), function (li) { var s = (li.getAttribute('data-services') || '').split('|'); li.hidden = !!svc && s.indexOf(svc) < 0; });
		[].forEach.call(box.querySelectorAll('.zt-chip'), function (c) { c.classList.toggle('is-on', c.getAttribute('data-svc') === svc); });
		if (map) { drawMarkers(); }
	}
	function drawMarkers() {
		layer.clearLayers(); markers = {};
		var pts = filter(cfg.hoods, svc), bounds = [];
		pts.forEach(function (h) {
			var m = root.L.circleMarker([h.lat, h.lng], { radius: radius(h.v.length), color: '#0b2e3a', weight: 2, fillColor: '#2a9d8f', fillOpacity: 0.75 });
			m.bindPopup(popupHtml(h, svc, ZT.cfg.wa)); m.addTo(layer); markers[h.id] = m; bounds.push([h.lat, h.lng]);
		});
		if (bounds.length) { map.fitBounds(bounds, { padding: [30, 30], maxZoom: 13 }); }
	}
	function loadLeaflet(cb) {
		if (root.L) { return cb(); }
		if (loading) { return; } loading = true;
		var l = document.createElement('link'); l.rel = 'stylesheet'; l.href = cfg.leafletCss; document.head.appendChild(l);
		var s = document.createElement('script'); s.src = cfg.leaflet; s.onload = cb; s.onerror = function () { loading = false; if (msg) { msg.textContent = 'تعذر تحميل الخريطة، استخدم القائمة أدناه.'; } }; document.head.appendChild(s);
	}
	function showMap() {
		if (!mapEl || map || !cfg.hoods.length) { return; }
		loadLeaflet(function () {
			mapEl.innerHTML = ''; mapEl.classList.add('leaflet-container-ready');
			map = root.L.map(mapEl, { scrollWheelZoom: false }).setView([cfg.hoods[0].lat, cfg.hoods[0].lng], 11);
			root.L.tileLayer(cfg.tiles, { maxZoom: 18, attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>' }).addTo(map);
			layer = root.L.layerGroup().addTo(map); drawMarkers();
			var q = ZT.state.read(); if (q.hood) { var hit = cfg.hoods.filter(function (h) { return h.n === q.hood; })[0]; if (hit && markers[hit.id]) { map.setView([hit.lat, hit.lng], 14); markers[hit.id].openPopup(); } }
		});
	}
	var btn = box.querySelector('[data-zt-showmap]'); if (btn) { btn.addEventListener('click', function () { start(); showMap(); }); }
	if (mapEl && 'IntersectionObserver' in root) { var io = new IntersectionObserver(function (es) { if (es[0].isIntersecting) { io.disconnect(); showMap(); } }, { rootMargin: '200px' }); io.observe(mapEl); }

	box.addEventListener('click', function (e) {
		var c = e.target.closest && e.target.closest('.zt-chip'); if (!c) { return; }
		start(); svc = c.getAttribute('data-svc') || ''; applyFilter();
		if (msg) { var n = filter(cfg.hoods, svc).length; msg.textContent = svc ? 'عدد الأحياء التي تتوفر فيها خدمة «' + svc + '»: ' + ZT.fmt(n) : ''; }
	});
	var loc = box.querySelector('[data-zt-locate]');
	if (loc) { loc.addEventListener('click', function () {
		start();
		if (!navigator.geolocation) { msg.textContent = 'المتصفح لا يدعم تحديد الموقع.'; return; }
		msg.textContent = 'جارٍ تحديد موقعك…';
		navigator.geolocation.getCurrentPosition(function (p) {
			var r = nearest(filter(cfg.hoods, svc), p.coords.latitude, p.coords.longitude);
			if (!r) { msg.textContent = 'لا توجد أحياء مطابقة.'; return; }
			msg.textContent = 'أقرب حي نخدمه: ' + r.h.n + ' (يبعد نحو ' + ZT.fmt(Math.round(r.km * 10) / 10) + ' كم)';
			ZT.ev('tool_complete', { km: Math.round(r.km) });
			[].forEach.call(box.querySelectorAll('.zt-hoods li'), function (li) { li.classList.toggle('is-hit', li.getAttribute('data-hood-id') === String(r.h.id)); });
			showMap(); var go = function () { if (map && markers[r.h.id]) { map.setView([r.h.lat, r.h.lng], 14); markers[r.h.id].openPopup(); } else { setTimeout(go, 300); } }; go();
		}, function () { msg.textContent = 'لم نتمكن من تحديد موقعك (ربما رفضت الإذن). اختر حيّك من القائمة.'; }, { timeout: 10000 });
	}); }
})(typeof window !== 'undefined' ? window : globalThis);
