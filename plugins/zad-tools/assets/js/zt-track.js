/* Tracking page: refreshes the status while the technician is on the way, and sends the two small forms (feedback, reminder opt-in). */
(function () {
	'use strict';
	var ZT = window.ZT, box = document.querySelector('[data-zt-track]');
	if (!ZT || !box) { return; }
	var cur = box.getAttribute('data-zt-track'), token = (ZT.cfg.token || '').replace(/[^a-f0-9]/g, '');

	/* auto refresh: only while "on the way"; a changed status reloads the page (server-rendered, so certificate / review buttons appear) */
	if (token && cur === 'on_the_way' && ZT.cfg.poll >= 15) {
		var timer = setInterval(function () {
			if (document.hidden) { return; }
			fetch(ZT.cfg.rest + 'track/' + token, { cache: 'no-store', credentials: 'omit' }).then(function (r) { return r.ok ? r.json() : null; }).then(function (j) {
				if (j && j.status && j.status !== cur) { clearInterval(timer); location.reload(); }
			}).catch(function () {});
		}, ZT.cfg.poll * 1000);
	}

	function wire(sel, path, build, okText) {
		var f = document.querySelector(sel); if (!f) { return; }
		f.addEventListener('submit', function (e) {
			e.preventDefault();
			var msg = f.querySelector('.zt-msg'), data = build(f);
			if (!data) { msg.textContent = 'أكمل الحقول المطلوبة.'; return; }
			data.token = token; data.website = (f.querySelector('[name=website]') || {}).value || '';
			ZT.post(path, data).then(function (res) {
				msg.textContent = res.ok ? okText : ((res.body && res.body.message) || 'تعذر الإرسال، حاول مرة أخرى.');
				if (res.ok) { ZT.ev(path === 'feedback' ? 'tool_feedback' : 'reminder_optin', {}); f.querySelector('button').disabled = true; }
			}).catch(function () { msg.textContent = 'تعذر الاتصال، حاول مرة أخرى.'; });
		});
	}
	wire('[data-zt-feedback]', 'feedback', function (f) { var t = f.querySelector('[name=text]').value.trim(); return t.length > 1 ? { text: t } : null; }, 'وصلتنا ملاحظتك، شكراً لك.');
	wire('[data-zt-optin]', 'track/optin', function (f) { return f.querySelector('[name=consent]').checked ? { consent: true } : null; }, 'تم تفعيل التذكير.');
})();
