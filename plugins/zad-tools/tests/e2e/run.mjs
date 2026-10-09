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
const posted = [], batch = [], images = [];
// per-tool setting overrides (the moving tool stays closed until the owner fills the rooms table)
const SET = { moving: { 'moving.rooms': '2 | 1 | 2 | 300-400\n4 | 2 | 4 | 600-800', 'moving.routes': 'riyadh | jeddah | 900', 'moving.floor_price': '20-30', 'moving.packing_price': '40-60' } };
const server = http.createServer((req, res) => {
	const u = new URL(req.url, 'http://x');
	const send = (code, type, body) => { res.writeHead(code, { 'Content-Type': type + '; charset=utf-8', 'Cache-Control': 'no-store' }); res.end(body); };
	let m;
	if ((m = u.pathname.match(/^\/t\/([a-z-]+)\/$/))) { return send(200, 'text/html', execFileSync('php', [root + '/tests/e2e/render-page.php', m[1], u.search.slice(1), '0', JSON.stringify(Object.assign({}, SET[m[1]] || {}, JSON.parse(u.searchParams.get('__set') || '{}')))], { encoding: 'utf8' })); }
	if (u.pathname === '/' && u.searchParams.has('zad_ics')) { res.writeHead(200, { 'Content-Type': 'text/calendar; charset=utf-8', 'Content-Disposition': 'attachment; filename="zad-reminder.ics"' }); return res.end(execFileSync('php', [root + '/tests/e2e/ics.php', u.search.slice(1)], { encoding: 'utf8' })); }
	if (u.pathname === '/wp-json/zad/v1/token') { return send(200, 'application/json', JSON.stringify({ t: 'tok.123' })); }
	if (u.pathname === '/wp-json/zad/v1/reminders' && req.method === 'POST') { let b = ''; req.on('data', (c) => b += c); req.on('end', () => { const j = JSON.parse(b); posted.push(j); send(j.consent ? 200 : 400, 'application/json', JSON.stringify(j.consent ? { ok: true } : { message: 'يلزم الموافقة' })); }); return; }
	if (u.pathname === '/wp-json/zad/v1/reminders/batch' && req.method === 'POST') { let b = ''; req.on('data', (c) => b += c); req.on('end', () => { const j = JSON.parse(b); batch.push(j); send(j.consent ? 200 : 400, 'application/json', JSON.stringify(j.consent ? { ok: true, added: j.items.length, skipped: 0 } : { message: 'يلزم الموافقة' })); }); return; }
	if (u.pathname === '/wp-json/zad/v1/pest-image' && req.method === 'POST') { const ch = []; req.on('data', (c) => ch.push(c)); req.on('end', () => { const b = Buffer.concat(ch); const t = b.toString('latin1'); images.push({ len: b.length, photo: /name="photo"/.test(t), type: (/name="photo"[^]*?Content-Type: ([\w\/]+)/.exec(t) || [])[1], consent: /name="consent_analyze"/.test(t), keep: /name="keep"/.test(t), tok: /name="t"\r\n\r\ntok\.123/.test(t) }); send(200, 'application/json', JSON.stringify({ ok: true, cards: [{ t: 'النمل الأبيض', u: '/pests/3/', p: 87, img: '', alt: '', svc: ['مكافحة النمل الأبيض', '/termite/'] }] })); }); return; }
	if (u.pathname === '/favicon.ico') { res.writeHead(204); return res.end(); }
	const f = path.join(root, u.pathname.replace(/^\/p\//, '/'));
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
	const cls0 = await p.evaluate(() => window.__cls); // shifts caused by the test's programmatic field changes are not the page's
	await p.click('form[data-zt-form] button[type=submit]'); await p.waitForSelector('#zt-result .zt-card, #zt-result .zt-error');
	const html = await p.$eval('#zt-result', (e) => e.innerHTML), url = p.url();
	const ev = await p.evaluate(() => window.dataLayer.map((d) => d.event));
	ok(errs.length === 0, label + ': no console/page errors ' + errs.join('|'));
	ok(ev.includes('tool_start') && ev.includes('tool_complete'), label + ': GA events tool_start + tool_complete (' + ev + ')');
	ok(!JSON.stringify(await p.evaluate(() => window.dataLayer)).match(/05\d{8}|first_name|phone/), label + ': GA payload has nothing personal');
	ok(await p.$eval('#zt-result', (e) => e.getAttribute('aria-live') === 'polite' && e.getAttribute('role') === 'status'), label + ': result is an aria-live region');
	ok(!!(await p.$('#zt-result [data-zt-share]')) && !!(await p.$('#zt-result [data-zt-print]')), label + ': share + print buttons');
	const cls = (await p.evaluate(() => window.__cls)) - cls0; ok(cls < 0.05, label + ': CLS caused by showing the answer ' + cls.toFixed(4));
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

/* -------- electricity -------- */
{
	const r = await jsRun('ac-power', async (p) => { await p.fill('[name=t1]', '٢'); await p.fill('[name=q1]', '1'); await p.fill('[name=h]', '١٠'); await p.fill('[name=d]', '30'); await p.fill('[name=hood]', 'الملقا'); }, 'Power');
	ok(/720 كيلوواط ساعة شهريًا/.test(r.html), 'Power: 1.2 × 2 × 10 × 30 = 720 kWh via JS');
	ok(/غير مفعّل حاليًا/.test(r.html) && !/تكلفة المكيفات التقديرية/.test(r.html), 'Power: empty tariff → kWh only, no riyal figure');
	const s = await serverRun(r.url, 'Power'); ok(norm(s.html) === norm(r.html), 'Power: server HTML == JS HTML');
	await noJs(r.url, 'Power', /720/);
	const nj = await browser.newContext({ javaScriptEnabled: false }); const q = await nj.newPage(); await q.goto(base + '/t/ac-power/'); await q.fill('[name=t1]', '2'); await q.fill('[name=h]', '10'); await q.fill('[name=d]', '30'); await q.click('button[type=submit]'); await q.waitForLoadState();
	ok(/720/.test(await q.$eval('#zt-result', (e) => e.textContent)), 'Power: no-JS submit → answer'); await nj.close();
	// the tariff, once the owner enters it, produces riyals with the source line
	const ctx = await browser.newContext(); const p = await ctx.newPage(); await p.goto(base + '/t/ac-power/?t1=2&q1=1&ty1=split&ag1=new&h=10&d=30&__set=' + encodeURIComponent(JSON.stringify({ 'ac-power.tariff': '1 | | 10', 'ac-power.tariff_source': 'https://example.test/tariff', 'ac-power.tariff_updated': '2026-02-01' })));
	const t = await p.$eval('#zt-result', (e) => e.textContent); ok(/72 ريال/.test(t) && /example\.test\/tariff — آخر تحديث: 2026-02-01/.test(t), 'Power: tariff set → 72 SAR + source + date shown'); await ctx.close();
	await r.ctx.close(); await s.ctx.close();
}
/* -------- moving -------- */
{
	const r = await jsRun('moving', async (p) => { await p.selectOption('[name=ty]', 'inter'); await p.selectOption('[name=fc]', 'riyadh'); await p.selectOption('[name=tc]', 'jeddah'); await p.fill('[name=r]', '٣'); await p.check('[name=pk]'); await p.fill('[name=ff]', '2'); await p.fill('[name=hood]', 'النرجس'); }, 'Moving');
	ok(/سيارتان و4 عمال/.test(r.html) && /بعد المعاينة/.test(r.html), 'Moving: 2 trucks + 4 workers; unpriced items say «بعد المعاينة»');
	ok(/600 – 800 ريال/.test(r.html) && /1,800 ريال/.test(r.html), 'Moving: table range + route 900 × 2 trucks');
	const s = await serverRun(r.url, 'Moving'); ok(norm(s.html) === norm(r.html), 'Moving: server HTML == JS HTML'); if (norm(s.html) !== norm(r.html)) { const a = norm(s.html), b = norm(r.html); let i = 0; while (a[i] === b[i]) { i++; } console.log('first difference at', i, '\nserver:', a.slice(i - 60, i + 140), '\njs    :', b.slice(i - 60, i + 140)); }
	await noJs(r.url, 'Moving', /سيارتان و4 عمال/);
	ok(await s.p.$eval('[name=tc]', (e) => !e.closest('.fld').hidden), 'Moving: destination city shown for inter-city');
	// checklist: ticks persist in this browser, print/download appear with JS
	await s.p.check('[data-k=c1]'); await s.p.check('[data-k=c4]'); await s.p.reload(); await s.p.waitForFunction(() => window.ZT);
	ok(await s.p.isChecked('[data-k=c1]') && await s.p.isChecked('[data-k=c4]') && !(await s.p.isChecked('[data-k=c2]')), 'Moving: checklist ticks survive a reload (localStorage)');
	ok(await s.p.locator('[data-zt-chk-actions]').isVisible(), 'Moving: print / download buttons shown once JS runs');
	const [dl] = await Promise.all([s.p.waitForEvent('download'), s.p.click('[data-act=download]')]); const txt = readFileSync(await dl.path(), 'utf8');
	ok(/\[x\] .*احجز شركة النقل/.test(txt) && /\[ \] /.test(txt) && /قبل بأسبوعين/.test(txt), 'Moving: downloaded checklist keeps the ticks');
	const nj = await browser.newContext({ javaScriptEnabled: false }); const q = await nj.newPage(); await q.goto(base + '/t/moving/'); ok((await q.locator('.zt-checklist li').count()) >= 10 && !(await q.locator('[data-zt-chk-actions]').isVisible()), 'Moving: no-JS shows the list (JS-only buttons hidden)'); await nj.close();
	await r.ctx.close(); await s.ctx.close();
}
/* -------- plan -------- */
{
	const r = await jsRun('plan', async (p) => { await p.selectOption('[name=tk]', 'both'); await p.fill('[name=acn]', '٣'); await p.fill('[name=hood]', 'النرجس'); }, 'Plan');
	ok(await r.p.locator('#zt-result .zt-cal__m').count() === 12, 'Plan: 12 calendar months'); ok(/تنظيف المكيفات/.test(r.html), 'Plan: AC cleaning scheduled');
	const s = await serverRun(r.url, 'Plan'); ok(norm(s.html) === norm(r.html), 'Plan: server HTML == JS HTML'); if (norm(s.html) !== norm(r.html)) { const a = norm(s.html), b = norm(r.html); let i = 0; while (a[i] === b[i]) { i++; } console.log('first difference at', i, '\nserver:', a.slice(i - 60, i + 140), '\njs    :', b.slice(i - 60, i + 140)); }
	await noJs(r.url, 'Plan', /خلال 12 شهرًا/);
	const nEv = await s.p.$$eval('#zt-result .zt-cal li li', (l) => l.length);
	const ics0 = await s.p.$eval('#zt-result a[href*="zad_ics"]', (a) => a.href), ics = base + new URL(ics0).pathname + new URL(ics0).search; const body = await (await s.ctx.request.get(ics)).text();
	ok((body.match(/BEGIN:VEVENT/g) || []).length === nEv && nEv > 0, 'Plan: .ics has one event per calendar item (' + nEv + ')');
	ok((await s.p.$eval('#zt-result a[href^="https://wa.me/?text="]', (a) => a.href)).includes('wa.me/?text='), 'Plan: «send to myself» link has no number');
	const f = s.p.locator('form[data-zt-optin]'); ok(await f.isVisible(), 'Plan: opt-in visible with JS'); ok(!(await f.locator('[name=consent]').isChecked()), 'Plan: consent not pre-checked');
	await f.locator('[name=first_name]').fill('أحمد'); await f.locator('[name=phone]').fill('0551234567'); await f.locator('button[type=submit]').click(); await s.p.waitForFunction(() => /يلزم الموافقة/.test(document.querySelector('.zt-optin__msg').textContent)); ok(batch.length === 0, 'Plan: nothing sent without consent');
	await f.locator('[name=consent]').check(); await f.locator('button[type=submit]').click(); await s.p.waitForFunction(() => /تم تفعيل/.test(document.querySelector('.zt-optin__msg').textContent));
	ok(batch.length === 1 && batch[0].consent === true && Array.isArray(batch[0].items) && batch[0].items.length > 0 && batch[0].items.every((x) => /^\d{4}-\d{2}-\d{2}$/.test(x.date) && x.service) && batch[0].tool === 'plan' && batch[0].t === 'tok.123' && batch[0].hood === 'النرجس', 'Plan: ONE batch request with all dates ' + JSON.stringify(batch[0]).slice(0, 160));
	await r.ctx.close(); await s.ctx.close();
}
/* -------- coverage -------- */
{
	const ctx = await browser.newContext({ viewport: { width: 390, height: 800 }, geolocation: { latitude: 24.801, longitude: 46.651 }, permissions: ['geolocation'] });
	const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64');
	let tiles = 0, external = []; await ctx.route('**/*', (route) => { const u = route.request().url(); if (/tile\.openstreetmap\.org/.test(u)) { tiles++; return route.fulfill({ status: 200, contentType: 'image/png', body: png }); } if (!u.startsWith(base)) { external.push(u); return route.abort(); } return route.continue(); });
	const p = await ctx.newPage(); const errs = []; p.on('pageerror', (e) => errs.push(String(e))); p.on('console', (m) => { if (m.type() === 'error') { errs.push(m.text()); } });
	const reqs = []; p.on('request', (r) => reqs.push(r.url()));
	await p.addInitScript(() => { delete window.IntersectionObserver; });             // force "load only on click"
	await p.goto(base + '/t/coverage/'); await p.waitForFunction(() => window.ZT);
	ok(!reqs.some((u) => /leaflet/.test(u)), 'Coverage: Leaflet is NOT loaded with the page'); ok(tiles === 0, 'Coverage: no tile requests before the map is asked for');
	ok(await p.locator('.zt-hoods li').count() === 4, 'Coverage: all 4 districts listed by the server'); ok(await p.locator('h3.zt-h3', { hasText: 'الرياض' }).count() === 1 && await p.locator('h3.zt-h3', { hasText: 'جدة' }).count() === 1, 'Coverage: grouped by city');
	ok((await p.locator('.zt-hoods a').count()) === 8, 'Coverage: every district lists its service links (8)');
	ok(/متوسط وقت الوصول نحو 35 دقيقة \(من 14 طلب مكتمل\)/.test(await p.textContent('#zt-hoodlist')) && /وقت الوصول المعتاد نحو 25 دقيقة/.test(await p.textContent('#zt-hoodlist')), 'Coverage: ETA from orders and the labelled manual figure');
	ok(await p.locator('.zt-map').evaluate((e) => e.getBoundingClientRect().height) >= 400, 'Coverage: map space reserved (no layout shift)');
	await p.click('[data-zt-showmap]'); await p.waitForSelector('.leaflet-container'); await p.waitForFunction(() => document.querySelectorAll('.leaflet-interactive').length === 3);
	ok(true, 'Coverage: 3 markers (the district without coordinates has none)'); ok(reqs.filter((u) => /leaflet\.js/.test(u)).length === 1 && tiles > 0, 'Coverage: Leaflet loaded locally once, tiles requested after the click');
	ok(external.every((u) => /tile\.openstreetmap\.org/.test(u)) && !reqs.some((u) => /unpkg|cdn|cloudflare|jsdelivr/.test(u)), 'Coverage: no CDN; only OpenStreetMap tiles are external');
	ok(await p.locator('.leaflet-control-attribution', { hasText: 'OpenStreetMap' }).count() === 1, 'Coverage: OSM attribution on the map');
	await p.click('.zt-chip[data-svc="تنظيف كنب"]'); ok(await p.locator('.zt-hoods li:visible').count() === 2 && await p.locator('.leaflet-interactive').count() === 2, 'Coverage: filter by service updates list AND map');
	ok(/عدد الأحياء التي تتوفر فيها خدمة «تنظيف كنب»: 2/.test(await p.textContent('.zt-cov-msg')), 'Coverage: filter result announced (aria-live)');
	await p.click('.zt-chip[data-svc=""]');
	await p.click('[data-zt-locate]'); await p.waitForFunction(() => /أقرب حي نخدمه: النرجس/.test(document.querySelector('.zt-cov-msg').textContent)); ok(true, 'Coverage: «حدد موقعي» → nearest served district (النرجس)');
	await p.waitForSelector('.leaflet-popup .zt-pop'); const pop = await p.textContent('.leaflet-popup .zt-pop'); ok(/النرجس/.test(pop) && /تنظيف مكيفات/.test(pop) && /نحو 35 دقيقة/.test(pop), 'Coverage: popup has name, services, ETA');
	ok(await p.locator('.zt-hoods li.is-hit').count() === 1, 'Coverage: nearest district highlighted in the list');
	ok(errs.length === 0, 'Coverage: no console/page errors ' + errs.join('|')); const cls = await p.evaluate(() => window.__cls); ok(cls < 0.05, 'Coverage: CLS ' + cls.toFixed(4));
	await p.screenshot({ path: '/tmp/zt-cov.png', fullPage: false });
	const nj = await browser.newContext({ javaScriptEnabled: false }); const q2 = await nj.newPage(); await q2.goto(base + '/t/coverage/');
	ok(await q2.locator('.zt-hoods li').count() === 4 && !(await q2.locator('.zt-cov-tools').isVisible()), 'Coverage: no-JS keeps the full list; JS-only controls hidden'); await nj.close(); await ctx.close();
}

/* -------- pest identifier -------- */
{
	const r = await jsRun('pest-id', async (p) => { await p.check('[name=pl][value=wood]'); await p.check('[name=sz][value=small]'); await p.check('[name=co][value=white]'); await p.check('[name=wg][value=no]'); await p.check('[name=sg][value=sawdust]'); await p.fill('[name=hood]', 'النرجس'); }, 'PestID');
	ok(/الأقرب: النمل الأبيض/.test(r.html) && /نسبة التطابق: 100%/.test(r.html), 'PestID: five answers → النمل الأبيض 100%'); ok(r.url.includes('pl=wood') && r.url.includes('sg=sawdust'), 'PestID: answers saved in the URL ' + r.url);
	ok(await r.p.locator('#zt-result .zt-cards__i').count() === 2, 'PestID: only pests that match something are listed (2 here, never padded to 3)'); ok(await r.p.locator('#zt-result .zt-cards a.btn[href="/termite/"]').count() === 1, 'PestID: each card has its service button');
	ok(decodeURIComponent((r.html.match(/wa\.me\/966555000111\?text=([^"]+)"/) || [])[1] || '').includes('بنسبة تطابق 100%'), 'PestID: WhatsApp text carries the result');
	const s = await serverRun(r.url, 'PestID'); ok(norm(s.html) === norm(r.html), 'PestID: server HTML == JS HTML'); if (norm(s.html) !== norm(r.html)) { const a = norm(s.html), b = norm(r.html); let i = 0; while (a[i] === b[i]) { i++; } console.log('first difference at', i, '\nserver:', a.slice(i - 60, i + 140), '\njs    :', b.slice(i - 60, i + 140)); }
	ok(await s.p.isChecked('[name=pl][value=wood]') && await s.p.isChecked('[name=wg][value=no]'), 'PestID: form refilled from the shared link'); await noJs(r.url, 'PestID', /الأقرب: النمل الأبيض/);
	// weak match → no claim, asks for a photo
	await r.p.check('[name=pl][value=bath]'); await r.p.check('[name=sz][value=tiny]'); await r.p.check('[name=co][value=white]'); await r.p.check('[name=wg][value=yes]'); await r.p.check('[name=sg][value=""]'); await r.p.click('button[type=submit]'); await r.p.waitForFunction(() => /لا يوجد تطابق قوي/.test(document.querySelector('#zt-result').textContent));
	ok(/صورة/.test(await r.p.textContent('#zt-result')), 'PestID: weak match → never claims, asks for a photo');
	ok(await r.p.locator('.zt-opt').count() >= 25 && (await r.p.locator('.zt-opt').first().evaluate((e) => e.getBoundingClientRect().height)) >= 44, 'PestID: option tiles are touch-sized');
	await r.ctx.close(); await s.ctx.close();
	const nj = await browser.newContext({ javaScriptEnabled: false }); const q = await nj.newPage(); await q.goto(base + '/t/pest-id/'); await q.check('[name=pl][value=wood]'); await q.click('button[type=submit]'); await q.waitForLoadState(); ok(/الأقرب|النمل الأبيض/.test(await q.$eval('#zt-result', (e) => e.textContent)), 'PestID: no-JS form submit → server answer'); await nj.close();
}
/* -------- seasonal report page -------- */
{
	const ctx = await browser.newContext({ viewport: { width: 390, height: 800 } }); const p = await ctx.newPage(); const errs = []; p.on('pageerror', (e) => errs.push(String(e))); p.on('console', (m) => { if (m.type() === 'error') { errs.push(m.text()); } });
	await p.goto(base + '/t/report/'); const t = await p.textContent('main');
	ok(await p.locator('figure.zt-chart svg').count() === 2, 'Report: two server-generated SVG charts'); ok(await p.locator('figure.zt-chart svg title').first().evaluate((e) => e.textContent.length > 5), 'Report: charts have an accessible title');
	ok(/ملاحظة المحرر الأولى/.test(t) && /نصيحة للقارئ/.test(t), 'Report: mandatory editor notes shown'); ok(/النرجس/.test(t) && /الشاطئ/.test(t) && !/الملقا/.test(t), 'Report: districts with ≥5 orders are named (النرجس 12, الشاطئ 6); الملقا (2) is not');
	ok(await p.locator('a[href*="zt_rep_csv"]').count() === 1 && await p.locator('textarea[readonly]').count() === 2, 'Report: CSV link + two embed codes');
	ok(await p.locator('.zt-tblwrap table').count() >= 4, 'Report: data tables next to the charts (readable data)'); ok(errs.length === 0, 'Report: no console errors ' + errs.join('|'));
	const scroll = await p.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1); ok(scroll, 'Report: no horizontal page scroll on a phone'); await p.screenshot({ path: '/tmp/zt-report.png', fullPage: true }); await ctx.close();
}

/* -------- image identification (locked feature, unlocked here with a mock provider) -------- */
{
	const OFF = base + '/t/pest-id/', ON = base + '/t/pest-id/?__set=' + encodeURIComponent(JSON.stringify({ 'pest-image.enabled': 1, 'pest-image.provider': 'e2e' }));
	const c0 = await browser.newContext(); const p0 = await c0.newPage(); await p0.goto(OFF); ok(await p0.locator('[data-zt-image]').count() === 0 && !(await p0.content()).includes('ارفع صورة'), 'Image: locked → the page prints nothing about images'); await c0.close();
	const nj = await browser.newContext({ javaScriptEnabled: false }); const pj = await nj.newPage(); await pj.goto(ON); ok(!(await pj.locator('[data-zt-image]').isVisible()), 'Image: JS-only section hidden without JS'); await nj.close();
	const ctx = await browser.newContext({ viewport: { width: 390, height: 800 } }); const p = await ctx.newPage(); const errs = []; p.on('pageerror', (e) => errs.push(String(e))); p.on('console', (m) => { if (m.type() === 'error') { errs.push(m.text()); } });
	await p.addInitScript(() => { const a = FormData.prototype.append; window.__up = []; FormData.prototype.append = function (k, v, n) { if (v instanceof Blob) { window.__up.push({ k, type: v.type, size: v.size, name: n }); createImageBitmap(v).then((b) => { window.__up[window.__up.length - 1].w = b.width; window.__up[window.__up.length - 1].h = b.height; }).catch(() => {}); } return a.apply(this, arguments); }; });
	await p.goto(ON); await p.waitForFunction(() => window.ZT && document.querySelector('[data-zt-image]'));
	ok(await p.locator('[data-zt-image]').isVisible(), 'Image: unlocked → section visible with JS'); ok(!(await p.isChecked('[data-zt-image] [name=consent_analyze]')) && !(await p.isChecked('[data-zt-image] [name=keep]')), 'Image: both consents unchecked');
	ok(/خدمة تحليل خارجية/.test(await p.textContent('[data-zt-image]')), 'Image: tells the visitor the photo goes to an external analysis service');
	// a big photo made in the browser (3000×2000 noisy PNG)
	const big = await p.evaluate(() => { const c = document.createElement('canvas'); c.width = 3000; c.height = 2000; const g = c.getContext('2d'); const d = g.createImageData(3000, 2000); for (let i = 0; i < d.data.length; i += 4) { d.data[i] = (i * 7) % 255; d.data[i + 1] = (i * 13) % 251; d.data[i + 2] = (i * 29) % 247; d.data[i + 3] = 255; } g.putImageData(d, 0, 0); return c.toDataURL('image/png').split(',')[1]; });
	const buf = Buffer.from(big, 'base64'); ok(buf.length > 1048576, 'Image: test photo is big (' + (buf.length / 1048576).toFixed(1) + ' MB)');
	await p.setInputFiles('[data-zt-image] [name=photo]', { name: 'bug.png', mimeType: 'image/png', buffer: buf });
	await p.click('[data-zt-image] button[type=submit]');await p.waitForSelector('[data-zt-image] .is-err'); ok(/يلزم الموافقة/.test(await p.textContent('.zt-img__msg')) && images.length === 0, 'Image: nothing is uploaded without consent');
	await p.check('[data-zt-image] [name=consent_analyze]'); await p.click('[data-zt-image] button[type=submit]'); await p.waitForSelector('#zt-result .zt-cards__i');
	ok(images.length === 1 && images[0].photo && images[0].consent && !images[0].keep && images[0].tok && images[0].type === 'image/jpeg', 'Image: one multipart upload — jpeg, token, consent, NO keep flag ' + JSON.stringify(images[0]));
	ok(images[0].len < buf.length / 4, 'Image: shrunk in the browser before upload (' + (buf.length / 1048576).toFixed(1) + ' MB → ' + (images[0].len / 1024).toFixed(0) + ' KB)');
	const up = await p.evaluate(() => window.__up[0]); ok(up && up.type === 'image/jpeg' && Math.max(up.w, up.h) <= 1600 && up.w / up.h > 1.45 && up.w / up.h < 1.55, 'Image: ≤1600px on the long side, aspect ratio kept (' + (up && up.w) + '×' + (up && up.h) + ')');
	const t = await p.textContent('#zt-result'); ok(/يبدو أقرب إلى: النمل الأبيض/.test(t) && /نسبة التطابق: 87%/.test(t) && /تقريبي/.test(t), 'Image: result shown as approximate with the matched pest'); ok(await p.locator('#zt-result a.btn--wa').count() === 1 && await p.locator('#zt-result a.btn[href="/termite/"]').count() === 1, 'Image: WhatsApp-to-technician + service button');
	ok(errs.length === 0, 'Image: no console errors ' + errs.join('|')); await ctx.close();
}
/* -------- weight -------- */
for (const f of ['zt-core.js', 'ac-size.js', 'after-spray.js', 'tank.js', 'ac-power.js', 'moving.js', 'plan.js', 'coverage.js', 'pest-id.js', 'pest-image.js']) { const kb = readFileSync(root + '/assets/js/' + f).length / 1024; ok(kb < 30, f + ' ' + kb.toFixed(1) + ' KB (<30)'); }
await browser.close(); server.close();
console.log(fail ? `\n${fail} of ${n} FAILED` : `e2e: all ${n} passed`); process.exit(fail ? 1 : 0);
