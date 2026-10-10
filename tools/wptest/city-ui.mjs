// Real admin screens: Tools ← مدن الخدمات (select + apply + undo), the city box on the edit screen, the list column + filter. NODE_PATH=$(npm root -g) node tools/wptest/city-ui.mjs [shotdir]
import { createRequire } from 'node:module'; import { execFileSync } from 'node:child_process';
const require = createRequire(import.meta.url); const { chromium } = require('playwright');
const WPX = process.env.WPX || '/tmp/wpsite', BASE = process.env.WPX_URL || 'http://127.0.0.1:8099', dir = process.argv[2] || '/tmp';
const php = (c) => execFileSync('php', ['-r', `require "${WPX}/wp-load.php"; ${c}`], { encoding: 'utf8' }).replace(/^.*Deprecated.*$/gm, '').trim();
let fail = 0, n = 0; const ok = (x, m) => { n++; if (!x) { fail++; console.log('FAIL:', m); } };
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
const p = await (await b.newContext({ viewport: { width: 1500, height: 1000 } })).newPage(); const errs = []; p.on('pageerror', (e) => { if (!/Unexpected token '<'|wp is not defined|jQuery|\$ is not defined|tinymce|reading 'editor'/.test(String(e))) errs.push(String(e)); }); p.on('dialog', (d) => d.accept());
await p.goto(BASE + '/wp-login.php'); await p.fill('#user_login', 'admin'); await p.fill('#user_pass', 'pass1234'); await p.click('#wp-submit'); await p.waitForLoadState();
const term = php('echo get_posts(array("post_type"=>"pest_control","name"=>"termites","numberposts"=>1,"zad_all"=>true))[0]->ID;');
/* tool */
await p.goto(BASE + '/wp-admin/tools.php?page=zad-city-scope'); await p.waitForSelector('#zad-ct');
const rows = await p.$$eval('#zad-ct tbody tr', (r) => r.length), review = await p.$$eval('#zad-ct tbody tr[data-review="1"]', (r) => r.length);
ok(rows >= 20 && review >= 1, `tool lists the pages (${rows}) with review rows (${review})`);
ok((await p.$eval('#zad-ct tbody tr:first-child', (r) => r.dataset.review)) === '1', 'review rows come first');
ok((await p.textContent('body')).includes('الخدمات × المدن'), 'matrix present');
await p.click('[data-ct=review]'); const sel = await p.$$eval('#zad-ct tbody tr input:checked', (r) => r.length); ok(sel === review, 'select «review» ticks exactly the review rows');
await p.click('[data-ct=none]');
const row = p.locator(`#zad-ct tbody tr:has(input[value="${term}"])`); await row.locator('input[type=checkbox]').check(); await row.locator('select').selectOption('jeddah');
await p.click('button[value=save]'); await p.waitForLoadState(); ok(php(`echo get_post_meta(${term},"_zad_city",true);`) === 'jeddah', 'tool: saved the chosen city for the ticked row');
ok(/تم تحديث\s+1/.test(await p.textContent('.notice-success')), 'tool: confirmation shows the count');
await p.screenshot({ path: dir + '/city-tool.png', fullPage: true });
await p.click('button[value=undo]'); await p.waitForLoadState(); ok(php(`echo metadata_exists("post",${term},"_zad_city")?"yes":"no";`) === 'no', 'tool: undo removes it again');
/* edit screen box */
await p.goto(`${BASE}/wp-admin/post.php?post=${term}&action=edit`); await p.waitForSelector('#zad_city_box');
ok(!!(await p.$('select[name=zad_city]')), 'edit screen: city select in the side box'); ok((await p.textContent('#zad_city_box')).includes('غير محددة'), 'box says the city is undetermined');
await p.selectOption('select[name=zad_city]', 'dammam'); await Promise.all([p.waitForNavigation(), p.evaluate(() => document.getElementById('post').submit())]).catch(() => {});
ok(php(`echo get_post_meta(${term},"_zad_city",true);`) === 'dammam', 'edit screen: form submit saves the city');
/* list screen */
await p.goto(BASE + '/wp-admin/edit.php?post_type=pest_control'); ok((await p.textContent('table.wp-list-table')).includes('المدينة'), 'list: «المدينة» column');
await p.goto(BASE + '/wp-admin/edit.php?post_type=pest_control&zad_city_f=dammam'); const dm = await p.$$eval('table.wp-list-table tbody tr.type-pest_control', (r) => r.length); ok(dm >= 4, 'list: filter by Dammam shows its pages (' + dm + ')');
await p.goto(BASE + '/wp-admin/edit.php?post_type=page'); ok(!(await p.textContent('table.wp-list-table')).includes('zad_city'), 'pages list has no city filter/column hooks');
php(`delete_post_meta(${term},"_zad_city"); zad_city_bump();`);
console.log(`${n} checks, ${fail} failed`, errs.length ? errs : ''); await b.close(); process.exit(fail ? 1 : 0);
