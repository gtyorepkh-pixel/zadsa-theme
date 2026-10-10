// Screenshots a front-end FAQ page: NODE_PATH=$(npm root -g) node tools/wptest/faq-shot.mjs <slug> <out.png> [width]
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url); const { chromium } = require('playwright');
const [slug, out, w] = process.argv.slice(2); const BASE = process.env.WPX_URL || 'http://127.0.0.1:8099';
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox'] });
const p = await b.newPage({ viewport: { width: Number(w || 1200), height: 900 } });
await p.goto(`${BASE}/faq/${slug}/`, { waitUntil: 'networkidle' }); await p.locator('main#main').screenshot({ path: out }); await b.close();
