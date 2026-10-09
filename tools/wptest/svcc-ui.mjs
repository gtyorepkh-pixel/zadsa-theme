// Drives the REAL admin screen (Tools ← تحويل صفحات الخدمات) in Chromium against the test WordPress. Usage: NODE_PATH=$(npm root -g) node tools/wptest/svcc-ui.mjs
import { createRequire } from 'node:module'; import { execFileSync } from 'node:child_process';
const require = createRequire(import.meta.url); const { chromium } = require('playwright');
const WPX = process.env.WPX || '/tmp/wpsite', BASE = process.env.WPX_URL || 'http://127.0.0.1:8099';
const c = JSON.parse(execFileSync('php', [new URL('./svcc-test.php', import.meta.url).pathname], { env: { ...process.env, FIXTURES_ONLY: '1', WPX }, encoding: 'utf8' }));
const php = (code) => execFileSync('php', ['-r', `require "${WPX}/wp-load.php"; ${code}`], { encoding: 'utf8' }).trim();
const head = (u) => { const o = execFileSync('curl', ['-s', '-I', '-m', '15', u], { encoding: 'utf8' }); return [Number((/HTTP\/\S+ (\d+)/.exec(o) || [])[1]), decodeURIComponent((/^location:\s*(\S+)/mi.exec(o) || [])[1] || '')]; };
let fail = 0, n = 0; const ok = (x, m) => { n++; if (!x) { fail++; console.log('FAIL:', m); } };
const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
const ctx = await browser.newContext({ viewport: { width: 1500, height: 1000 } }); const p = await ctx.newPage(); const errs = []; p.on('pageerror', (e) => errs.push(String(e))); p.on('dialog', (d) => d.accept());
await p.goto(BASE + '/wp-login.php'); await p.fill('#user_login', 'admin'); await p.fill('#user_pass', 'pass1234'); await p.click('#wp-submit'); await p.waitForLoadState();
await p.goto(BASE + '/wp-admin/tools.php?page=zad-svcconv'); await p.waitForSelector('#zadsv-tbl');
const sel = (id) => `select[name="act[${id}]"]`;
ok((await p.locator('#zadsv-tbl tbody tr').count()) === 5, 'screen lists the 5 source articles');
ok((await p.inputValue(sel(c.a))) === 'cleaning' && (await p.inputValue(sel(c.b))) === 'pest' && (await p.inputValue(sel(c.c))) === 'service' && (await p.inputValue(sel(c.e))) === 'none', 'suggested actions: cleaning / pest / service / none');
ok((await p.inputValue(`select[name="parent[${c.a}]"]`)) === String(c.p_clean), 'parent suggested for the cleaning row (تنظيف مكيفات بالرياض)');
ok((await p.inputValue(`select[name="parent[${c.b}]"]`)) === String(c.p_pest), 'parent suggested for the pest row (مكافحة حشرات بالدمام)');
ok(await p.locator(`tr:has(select[name="act[${c.a}]"]) .zadsv-prev`).innerText().then((t) => /ac-cleaning-riyadh\/old-ac-clean/.test(t) && /old-ac-clean/.test(t)), 'row shows the redirect (from ← to) BEFORE running');
// switch the pest-row action to service: the parent cell hides, the city cell shows
await p.selectOption(sel(c.b), 'service'); ok(await p.locator(`tr:has(select[name="act[${c.b}]"]) .zadsv-c-parent`).evaluate((e) => getComputedStyle(e).visibility === 'hidden') && await p.locator(`tr:has(select[name="act[${c.b}]"]) .zadsv-c-city`).evaluate((e) => getComputedStyle(e).visibility === 'visible'), 'parent hidden / city shown for a service row'); await p.selectOption(sel(c.b), 'pest');
ok((await p.locator(`select[name="parent[${c.b}]"] option`).count()) >= 3, 'parent list is filled with pest_control pages');
// filters
await p.selectOption('#zadsv-f-act', 'cleaning'); ok((await p.locator('#zadsv-tbl tbody tr:visible').count()) === 1, 'filter by action'); await p.selectOption('#zadsv-f-act', ''); await p.check('#zadsv-f-lab'); ok((await p.locator('#zadsv-tbl tbody tr:visible').count()) === 0, 'filter «ليها صفحة جديدة» (none yet)'); await p.uncheck('#zadsv-f-lab');
// bulk: set c to none
await p.locator(`tr:has(select[name="act[${c.c}]"]) .zadsv-sel`).check(); await p.selectOption('#zadsv-bulk', 'none'); await p.click('#zadsv-apply'); ok((await p.inputValue(sel(c.c))) === 'none', 'bulk apply: «لا شيء» to the selected row'); await p.selectOption(sel(c.c), 'service');
// delete with redirect: search for the target
await p.selectOption(sel(c.d), 'trash'); const q = p.locator(`tr:has(select[name="act[${c.d}]"]) .zadsv-to-q`); await q.fill(''); await q.type('النمل');
await p.waitForSelector(`tr:has(select[name="act[${c.d}]"]) .zadsv-res button`); await p.locator(`tr:has(select[name="act[${c.d}]"]) .zadsv-res button`).first().click();
ok((await p.inputValue(`input[name="to[${c.d}]"]`)) === String(c.p_newant), 'search + pick sets the redirect target: ' + await p.inputValue(`input[name="to[${c.d}]"]`));
// preview
await p.click('button[value=preview]'); await p.waitForSelector('text=معاينة فقط');
const prev = await p.innerText('.wrap'); ok(/ستُضاف في Redirection/.test(prev) && /old-ants/.test(prev) && /cleaning\/ac-cleaning-riyadh\/old-ac-clean/.test(prev), 'preview screen: counts + every redirect from ← to');
ok(/حذف مع تحويل\s*\n?\s*1/.test(prev) || /حذف مع تحويل[\s\S]{0,20}1/.test(prev), 'preview: counts per action'); ok(php('echo get_post_type(' + c.a + ');') === 'post', 'preview changed nothing');
// run
await p.click('button[value=run]'); await p.waitForSelector('text=اكتمل التنفيذ'); const rep = await p.innerText('.wrap');
ok(/3 صفحة نُقلت/.test(rep) && /1 نُقلت لسلة المهملات/.test(rep), 'report: 3 moved, 1 trashed: ' + rep.slice(0, 160).replace(/\n/g, ' ')); ok(/Redirection/.test(rep), 'report mentions Redirection');
ok(php('echo get_post_type(' + c.a + '),"|",get_post_status(' + c.d + ');') === 'cleaning|trash', 'DB: A moved to cleaning, D in the trash');
let [s1, l1] = head(BASE + '/old-ac-clean/'); ok(s1 === 301 && /cleaning\/ac-cleaning-riyadh\/old-ac-clean\/$/.test(l1), 'curl -I old → 301 ' + l1); [s1] = head(BASE + '/cleaning/ac-cleaning-riyadh/old-ac-clean/'); ok(s1 === 200, 'curl -I new → 200');
let [s2, l2] = head(BASE + '/old-ants/'); ok(s2 === 301 && /pest-control\/ants-riyadh-new\/$/.test(l2), 'curl -I trashed page → 301 to the replacement');
// undo
await p.click('button[value=undo]'); await p.waitForSelector('text=تم التراجع'); const und = await p.innerText('.wrap'); ok(/3 صفحة أُعيدت/.test(und) && /1 أُخرجت من سلة المهملات/.test(und), 'undo report: ' + und.slice(0, 200).replace(/\n/g, ' '));
ok(php('echo get_post_type(' + c.a + '),"|",get_post_status(' + c.d + '),"|",$GLOBALS["wpdb"]->get_var("select count(*) from wp_redirection_items");') === 'post|publish|0', 'DB after undo: back to post / publish, 0 redirects');
[s1] = head(BASE + '/old-ac-clean/'); ok(s1 === 200, 'curl: old URL is a page again'); [s2] = head(BASE + '/old-ants/'); ok(s2 === 200, 'curl: the trashed page is back');
const mine = errs.filter((e) => !/Unexpected token '<'|wp is not defined/.test(e)); // the trimmed WP test core has no admin JS files (404 pages) — not ours
ok(mine.length === 0, 'no JS errors from the tool\'s own script ' + mine.join('|'));
await p.screenshot({ path: '/tmp/svcc-ui.png', fullPage: false }); await browser.close();
console.log(fail ? `\n${fail} of ${n} FAILED` : `svcc admin UI: all ${n} passed`); process.exit(fail ? 1 : 0);
