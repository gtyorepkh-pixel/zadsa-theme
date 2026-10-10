// Opens the real tool page (Tools ← دمج الأدلة في الأقسام) and checks the report textarea. NODE_PATH=$(npm root -g) node tools/wptest/gm-ui.mjs
import { createRequire } from 'node:module'; const require = createRequire(import.meta.url); const { chromium } = require('playwright'); const BASE = process.env.WPX_URL || 'http://127.0.0.1:8099';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] }); const p = await (await b.newContext()).newPage();
await p.goto(BASE + '/wp-login.php'); await p.fill('#user_login', 'admin'); await p.fill('#user_pass', 'pass1234'); await p.click('#wp-submit'); await p.waitForLoadState();
await p.goto(BASE + '/wp-admin/tools.php?page=zad-guide-merge'); const t = await p.inputValue('#zad-gm-md'); await p.click('#zad-gm-copy');
console.log('textarea chars:', t.length, '| has conflict row:', t.includes('shared-slug'), '| has terms table:', t.includes('مقابل'), '| button:', (await p.textContent('#zad-gm-copy')).trim()); await b.close();
