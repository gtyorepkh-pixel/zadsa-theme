// Lighthouse (mobile, default throttling) on the stub-rendered tool pages. Needs lighthouse installed outside the repo:  LH_DIR=/tmp/lh node tests/e2e/lh.mjs
import http from 'node:http'; import { readFileSync, existsSync } from 'node:fs'; import { execFileSync } from 'node:child_process'; import path from 'node:path'; import { fileURLToPath } from 'node:url'; import { createRequire } from 'node:module';
const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..'); const req = createRequire(process.env.LH_DIR + '/');
const lighthouse = (await import(req.resolve('lighthouse'))).default; const { launch } = req('chrome-launcher');
const server = http.createServer((rq, rs) => {
	const u = new URL(rq.url, 'http://x'); const m = u.pathname.match(/^\/t\/([a-z-]+)\/$/);
	if (m) { rs.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' }); return rs.end(execFileSync('php', [root + '/tests/e2e/render-page.php', m[1], u.search.slice(1), '0', JSON.stringify(m[1] === 'moving' ? { 'moving.rooms': '2 | 1 | 2 | 300-400\n4 | 2 | 4 | 600-800' } : {})], { encoding: 'utf8' })); }
	const f = path.join(root, u.pathname.replace(/^\/p\//, '/')); if (u.pathname === '/favicon.ico') { rs.writeHead(204); return rs.end(); }
	if (u.pathname.startsWith('/p/')) { u.pathname = u.pathname.slice(2); }
	if (f.startsWith(root + '/assets') && existsSync(f)) { rs.writeHead(200, { 'Content-Type': f.endsWith('.css') ? 'text/css' : 'application/javascript' }); return rs.end(readFileSync(f)); } rs.writeHead(404); rs.end();
});
await new Promise((r) => server.listen(0, r)); const base = 'http://127.0.0.1:' + server.address().port;
const chrome = await launch({ chromePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', chromeFlags: ['--headless=new', '--no-sandbox'] });
for (const [tool, q] of [['ac-size', 'l=4&w=4&top=1'], ['after-spray', 'ps=general&md=spray&t=21:00'], ['tank', 'loc=ground&shape=rect&a=2&b=1&c=1&unit=m&last=2026-08-01'], ['ac-power', 't1=2&q1=1&ty1=split&ag1=new&h=10&d=30'], ['moving', 'ty=in&fc=riyadh&r=3'], ['plan', 'hs=apartment&ct=riyadh&tk=both&acn=3&sf=0'], ['coverage', '']]) {
	const r = await lighthouse(base + '/t/' + tool + '/?' + q, { port: chrome.port, output: 'json', logLevel: 'error', onlyCategories: ['performance', 'accessibility', 'best-practices', 'seo'] });
	const c = r.lhr.categories, a = r.lhr.audits;
	console.log(tool, JSON.stringify(Object.fromEntries(Object.entries(c).map(([k, v]) => [k, Math.round(v.score * 100)]))), 'LCP', a['largest-contentful-paint'].displayValue, 'CLS', a['cumulative-layout-shift'].displayValue, 'TBT', a['total-blocking-time'].displayValue);
	if (a['errors-in-console'].score !== 1) { console.log('   console:', (a['errors-in-console'].details.items || []).map((i) => (i.description || '').slice(0, 110)).join(' | ')); }
	for (const [id, au] of Object.entries(a)) { if (au.score !== null && au.score < 0.9 && au.scoreDisplayMode === 'binary') { console.log('   ✗', id, au.title); } }
}
await chrome.kill(); server.close();
