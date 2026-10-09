/* Draws the verification QR (library: assets/js/vendor/qrcode.js, MIT) — certificate page only. */
(function () {
	var el = document.getElementById('zt-qr');
	if (!el || typeof qrcode !== 'function') { return; }
	var q = qrcode(0, 'M'); q.addData(el.getAttribute('data-url')); q.make();
	el.innerHTML = q.createSvgTag({ cellSize: 4, scalable: true });
})();
