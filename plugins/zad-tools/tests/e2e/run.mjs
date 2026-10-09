// End-to-end check in real Chromium: server-rendered HTML (stub WordPress) == what the JS renders; no-JS fallback; GA events; opt-in; CLS.
// Usage: NODE_PATH=$(npm root -g) node tests/e2e/run.mjs
import http from 'node:http';
import { readFileSync, existsSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const posted = [];
const server = http.createServer((req, res) => {
	const u = new URL(req.url, 'http://x');
	const send = (code, type, body) => { res.writeHead(code, { 'Content-Type': type + '; charset=utf-8', 'Cache-Control': 'no-store' }); res.end(body); };
	let m;
	if ((m = u.pathname.match(/^\/t\/([a-z-]+)\/$/))) { return send(200, 'text/html', execFileSync('php', [root + '/tests/e2e/render-page.php', m[1], u.search.slice(1), '0', u.searchParams.get('__set') || ''], { encoding: 'utf8' })); }
	if (u.pathname === '/' && u.searchParams.has('zad_ics')) { res.writeHead(200, { 'Content-Type': 'text/calendar; charset=utf-8', 'Content-Disposition': 'attachment; filename="zad-reminder.ics"' }); return res.end(execFileSync('php', [root + '/tests/e2e/ics.php', u.search.slice(1)], { encoding: 'utf8' })); }
	if (u.pathname === '/wp-json/zad/v1/token') { return send(200, 'application/json', JSON.stringify({ t: 'tok.123' })); }
	if (u.pathname === '/wp-json/zad/v1/reminders' && req.method === 'POST') { let b = ''; req.on('data', (c) => b += c); req.on('end', () => { const j = JSON.parse(b); posted.push(j); send(j.consent ? 200 : 400, 'application/json', JSON.stringify(j.consent ? { ok: true } : { message: 'يلزم الموافقة' })); }); return; }
	if (u.pathname === '/favicon.ico') { res.writeHead(204); return res.end(); }
	const f = path.join(root, u.pathname);
	if (f.startsWith(root + '/assets') && existsSync(f)) { return send(200, f.endsWith('.css') ? 'text/css' : 'application/javascript', readFileSync(f)); }
	send(404, 'text/plain', 'nf');
});
await new Promise((r) => server.listen(0, r));
const base = 'http://127.0.0.1:' + server.address().port;
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
let fail = 0, n = 0;
const ok = (c, m) => { n++; if (!c) { fail++; console.log('FAIL:', m); } };
const norm = (h) => h.replace(/\s+/g, ' ').trim();

async function jsRun(tool, fill, label) {
	const ctx = await browser.newContext({ viewport: { width: 390, height: 800 }, locale: 'ar-SA' }); const p = await ctx.newPage();
	const errs = []; p.on('pageerror', (e) => errs.push(String(e))); p.on('console', (m) => { if (m.type() === 'error') { errs.push(m.text()); } });
	await p.goto(base + '/t/' + tool + '/'); await p.waitForFunction(() => window.ZT && document.querySelector('form[data-zt-form]'));
	await fill(p);
	await p.click('form[data-zt-form] button[type=submit]'); await p.waitForSelector('#zt-result .zt-card, #zt-result .zt-error');
	const html = await p.$eval('#zt-result', (e) => e.innerHTML), url = p.url();
	const ev = await p.evaluate(() => window.dataLayer.map((d) => d.event));
	ok(errs.length === 0, label + ': no console/page errors ' + errs.join('|'));
	ok(ev.includes('tool_start') && ev.includes('tool_complete'), label + ': GA events tool_start + tool_complete (' + ev + ')');
	ok(!JSON.stringify(await p.evaluate(() => window.dataLayer)).match(/05\d{8}|first_name|phone/), label + ': GA payload has nothing personal');
	ok(await p.$eval('#zt-result', (e) => e.getAttribute('aria-live') === 'polite' && e.getAttribute('role') === 'status'), label + ': result is an aria-live region');
	ok(!!(await p.$('#zt-result [data-zt-share]')) && !!(await p.$('#zt-result [data-zt-print]')), label + ': share + print buttons');
	const cls = await p.evaluate(() => window.__cls); ok(cls < 0.05, label + ': CLS after submit ' + cls.toFixed(4));
	return { ctx, p, html, url };
}
async function serverRun(url, label) {
	const ctx = await browser.newContext({ viewport: { width: 390, height: 800 } }); const p = await ctx.newPage();
	await p.goto(url); await p.waitForFunction(() => window.ZT); await p.waitForSelector('#zt-result .zt-card, #zt-result .zt-error');
	const html = await p.$eval('#zt-result', (e) => e.innerHTML); const cls = await p.evaluate(() => window.__cls);
	ok(cls < 0.02, label + ': CLS on a shared link ' + cls.toFixed(4));
	return { ctx, p, html };
}
async function noJs(url, label, expect) {
	const ctx = await browser.newContext({ javaScriptEnabled: false }); const p = await ctx.newPage(); await p.goto(url);
	const t = await p.$eval('#zt-result', (e) => e.textContent); ok(expect.test(t), label + ': no-JS page still shows the answer'); await ctx.close();
}

/* -------- AC -------- */
{
	const r = await jsRun('ac-size', async (p) => { await p.fill('[name=l]', '٤'); await p.fill('[name=w]', '4'); await p.check('[name=top]'); await p.fill('[name=p]', '٤'); await p.fill('[name=hood]', 'النرجس'); }, 'AC');
	ok(/مكيف 1\.5 طن/.test(r.html), 'AC: 1.5 ton via JS'); ok(r.url.includes('l=4') && r.url.includes('top=1') && r.url.includes('hood='), 'AC: result saved in the URL ' + r.url);
	ok(decodeURIComponent((r.html.match(/wa\.me\/966555000111\?text=([^"]+)"/) || [])[1] || '').includes('في حي النرجس'), 'AC: WhatsApp text has the hood');
	const s = await serverRun(r.url, 'AC'); ok(norm(s.html) === norm(r.html), 'AC: server HTML == JS HTML'); if (norm(s.html) !== norm(r.html)) { console.log(norm(s.html).slice(0, 400), '\n', norm(r.html).slice(0, 400)); }
	ok(await s.p.$eval('form[data-zt-form] [name=top]', (e) => e.checked) && (await s.p.inputValue('[name=l]')) === '4', 'AC: form refilled from the shared link');
	await noJs(r.url, 'AC', /مكيف 1\.5 طن/);
	// no-JS: type and submit the GET form
	const nj = await browser.newContext({ javaScriptEnabled: false }); const q = await nj.newPage(); await q.goto(base + '/t/ac-size/'); await q.fill('[name=a]', '16'); await q.click('button[type=submit]'); await q.waitForLoadState();
	ok(/مكيف 1 طن/.test(await q.$eval('#zt-result', (e) => e.textContent)), 'AC: no-JS form submit → server answer'); await nj.close();
	const h = await s.p.evaluate(() => ({ canon: !!document.querySelector('link[rel=canonical]'), tables: document.querySelectorAll('.zt-table').length, faq: document.querySelectorAll('.zt-faq details').length, h2: [...document.querySelectorAll('h2')].map((x) => x.textContent) }));
	ok(h.h2.includes('إزاي بنحسب') && h.h2.includes('أمثلة محسوبة') && h.h2.includes('أسئلة شائعة'), 'AC: fixed page structure ' + h.h2); ok(h.tables >= 3, 'AC: parameter + examples + reference tables'); 
	await s.p.screenshot({ path: '/tmp/zt-ac.png', fullPage: true });
	await r.ctx.close(); await s.ctx.close();
}
/* -------- spray -------- */
{
	const r = await jsRun('after-spray', async (p) => { await p.selectOption('[name=ps]', 'general'); await p.selectOption('[name=md]', 'spray'); await p.check('[data-zt-factor=kids]'); await p.fill('[name=t]', '21:00'); }, 'Spray');
	ok(/ارجع بعد 6 ساعات/.test(r.html) && /3:00 ص \(اليوم التالي\)/.test(r.html), 'Spray: 6 h, back at 3:00 ص next day'); ok(r.url.includes('f_kids=1') && r.url.includes('ps=general'), 'Spray: URL state ' + r.url);
	const s = await serverRun(r.url, 'Spray'); ok(norm(s.html) === norm(r.html), 'Spray: server HTML == JS HTML'); ok(await s.p.$eval('[data-zt-factor=kids]', (e) => e.checked), 'Spray: checkbox refilled');
	await noJs(r.url, 'Spray', /ارجع بعد 6 ساعات/);
	await r.p.selectOption('[name=ps]', 'roach'); await r.p.click('button[type=submit]'); await r.p.waitForFunction(() => /الفني هيحدد/.test(document.querySelector('#zt-result').textContent));
	ok(true, 'Spray: empty cell → «الفني هيحدد»'); await r.ctx.close(); await s.ctx.close();
}
/* -------- tank -------- */
{
	const last = new Date(Date.now() - 60 * 864e5).toISOString().slice(0, 10);
	const r = await jsRun('tank', async (p) => { await p.selectOption('[name=shape]', 'cyl_v'); await p.fill('[name=a]', '1.5'); await p.fill('[name=b]', '٢'); await p.fill('[name=last]', last); await p.fill('[name=people]', '4'); await p.fill('[name=hood]', 'الملقا'); }, 'Tank');
	ok(/3,534 لتر/.test(r.html), 'Tank: cylinder 3,534 L'); ok(!(await r.p.$eval('[name=c]', (e) => e.closest('.fld').hidden === false)), 'Tank: 3rd dimension hidden for a cylinder');
	const s = await serverRun(r.url, 'Tank'); ok(norm(s.html) === norm(r.html), 'Tank: server HTML == JS HTML'); if (norm(s.html) !== norm(r.html)) { const a = norm(s.html), b = norm(r.html); let i = 0; while (a[i] === b[i]) { i++; } console.log('first difference at', i, '\nserver:', a.slice(i - 80, i + 160), '\njs    :', b.slice(i - 80, i + 160)); }
	// opt-in
	const f = s.p.locator('form[data-zt-optin]'); ok(await f.isVisible(), 'Tank: opt-in form visible once JS runs');
	ok(!(await f.locator('[name=consent]').isChecked()), 'Tank: consent is NOT pre-checked');
	await f.locator('[name=first_name]').fill('أحمد'); await f.locator('[name=phone]').fill('٠٥٥١٢٣٤٥٦٧'); await f.locator('button[type=submit]').click();
	await s.p.waitForFunction(() => /يلزم الموافقة/.test(document.querySelector('.zt-optin__msg').textContent)); ok(posted.length === 0, 'Tank: nothing is sent without consent');
	await f.locator('[name=consent]').check(); await f.locator('button[type=submit]').click(); await s.p.waitForFunction(() => /تم تفعيل التذكير/.test(document.querySelector('.zt-optin__msg').textContent));
	ok(posted.length === 1 && posted[0].consent === true && posted[0].phone === '0551234567' && posted[0].first_name === 'أحمد' && /^\d{4}-\d{2}-\d{2}$/.test(posted[0].due_date) && posted[0].service === 'تنظيف خزان المياه' && posted[0].t === 'tok.123' && posted[0].website === '' && posted[0].hood === 'الملقا', 'Tank: payload ' + JSON.stringify(posted[0]));
	const ics0 = await s.p.$eval('#zt-result a[href*="zad_ics"]', (a) => a.href), ics = base + new URL(ics0).pathname + new URL(ics0).search; const body = await (await s.ctx.request.get(ics)).text();
	ok(/BEGIN:VEVENT/.test(body) && /DTSTART;VALUE=DATE:\d{8}/.test(body) && !/0551234567|أحمد/.test(body), 'Tank: .ics downloads, holds only title + date');
	await noJs(r.url, 'Tank', /3,534 لتر/); await r.ctx.close(); await s.ctx.close();
	// setting overrides reach the page: consumption + tiers
	const ctx = await browser.newContext(); const p = await ctx.newPage(); await p.goto(base + '/t/tank/?loc=ground&shape=rect&a=2&b=1&c=1&unit=m&people=5&__set=' + encodeURIComponent(JSON.stringify({ 'tank.liters_per_person': 100, 'tank.price_tiers': '3000 | 220' })));
	const t = await p.$eval('#zt-result', (e) => e.textContent); ok(/نحو 4 أيام/.test(t) && /220 ريال/.test(t), 'Tank: settings (consumption 100 L, tier 220) shape the answer: ' + t.slice(0, 160)); await ctx.close();
}
/* -------- weight -------- */
for (const f of ['zt-core.js', 'ac-size.js', 'after-spray.js', 'tank.js']) { const kb = readFileSync(root + '/assets/js/' + f).length / 1024; ok(kb < 30, f + ' ' + kb.toFixed(1) + ' KB (<30)'); }
await browser.close(); server.close();
console.log(fail ? `\n${fail} of ${n} FAILED` : `e2e: all ${n} passed`); process.exit(fail ? 1 : 0);
