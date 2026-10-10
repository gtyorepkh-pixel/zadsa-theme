// Drives the REAL admin screen (Tools ← فصل الإجابة المباشرة): the "3 for trial" button, run, undo. Usage: NODE_PATH=$(npm root -g) node tools/wptest/faq-ui.mjs [run|undo] [shotdir]
import { createRequire } from 'node:module'; import { execFileSync } from 'node:child_process';
const require = createRequire(import.meta.url); const { chromium } = require('playwright');
const BASE = process.env.WPX_URL || 'http://127.0.0.1:8099', mode = process.argv[2] || 'run', dir = process.argv[3] || '/tmp';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
const p = await (await b.newContext({ viewport: { width: 1500, height: 1000 } })).newPage(); const errs = []; p.on('pageerror', (e) => errs.push(String(e))); p.on('dialog', (d) => d.accept());
await p.goto(BASE + '/wp-login.php'); await p.fill('#user_login', 'admin'); await p.fill('#user_pass', 'pass1234'); await p.click('#wp-submit'); await p.waitForLoadState();
await p.goto(BASE + '/wp-admin/tools.php?page=zad-faq-split'); await p.waitForSelector('#zad-fas-t');
if (mode === 'run') {
  await p.screenshot({ path: dir + '/tool-preview.png', fullPage: true });
  await p.click('[data-sel=trial]'); const sel = await p.$$eval('#zad-fas-t tbody tr', (rs) => rs.filter((r) => r.querySelector('input:checked')).map((r) => r.getAttribute('data-slug')));
  console.log('trial selection:', sel.join(', '), '| counter:', await p.textContent('#zad-fas-n'));
  await p.click('button[value=run]'); await p.waitForLoadState(); console.log((await p.textContent('.notice-success, .notice-warning:not(.inline)')).trim().slice(0, 120)); await p.screenshot({ path: dir + '/tool-result.png', fullPage: true });
} else { await p.click('button[value=undo]'); await p.waitForLoadState(); console.log((await p.textContent('.notice-success, .notice-warning:not(.inline)')).trim().slice(0, 160)); }
console.log('js errors:', errs.length ? errs : 'none'); await b.close();
