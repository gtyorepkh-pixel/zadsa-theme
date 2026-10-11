// «أعمالنا» in Chromium at phone width: no horizontal scroll, clip buttons seek the video, #t=70 opens at 70 s, YouTube clip → iframe with start=, lightbox, archive. NODE_PATH=$(npm root -g) node tools/wptest/work-ui.mjs [shotdir]
import { createRequire } from 'node:module';
const require = createRequire(import.meta.url); const { chromium } = require('playwright');
const BASE = process.env.WPX_URL || 'http://127.0.0.1:8099', dir = process.argv[2] || '/tmp';
let fail = 0, n = 0; const ok = (x, m) => { n++; if (!x) { fail++; console.log('FAIL:', m); } };
const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args: ['--no-sandbox', '--autoplay-policy=no-user-gesture-required'] });
const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true }); const p = await ctx.newPage();
const errs = []; p.on('pageerror', (e) => errs.push(String(e))); p.on('console', (m) => { if (m.type() === 'error' && !/Failed to load resource|youtube|ytimg/.test(m.text())) errs.push(m.text()); });
const overflow = async () => p.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
await p.goto(BASE + '/works/story-leak-under-tile/', { waitUntil: 'networkidle' });
ok((await overflow()) <= 1, 'single: no horizontal scroll at 390px (' + (await overflow()) + 'px)');
await p.waitForSelector('video'); await p.waitForFunction(() => document.querySelector('video').readyState >= 1, null, { timeout: 15000 }).catch(() => {});
const dur = await p.evaluate(() => document.querySelector('video').duration); ok(dur > 140 && dur < 160, 'the test mp4 loads (duration ' + dur + ')');
const clips = await p.$$('.zw-clip'); ok(clips.length === 3, '3 clip buttons');
const box = await clips[1].boundingBox(); ok(box.height >= 40, 'clip buttons are touch-sized (' + box.height + 'px)');
await clips[1].click(); await p.waitForTimeout(600); const t1 = await p.evaluate(() => document.querySelector('video').currentTime); ok(Math.abs(t1 - 25) < 2, 'clip 2 → video at ~25 s (' + t1.toFixed(1) + ')');
ok(await p.evaluate(() => document.querySelectorAll('.zw-clip.is-on').length) === 1, 'the active clip is highlighted');
await clips[2].click(); await p.waitForTimeout(600); const t2 = await p.evaluate(() => document.querySelector('video').currentTime); ok(Math.abs(t2 - 70) < 2, 'clip 3 → ~70 s (' + t2.toFixed(1) + ')');
await p.screenshot({ path: dir + '/work-mobile-top.png' });
await p.goto(BASE + '/works/story-leak-under-tile/#t=100', { waitUntil: 'networkidle' }); await p.waitForFunction(() => document.querySelector('video').readyState >= 1, null, { timeout: 15000 }).catch(() => {}); await p.waitForTimeout(600);
const t3 = await p.evaluate(() => document.querySelector('video').currentTime); ok(Math.abs(t3 - 100) < 2, '#t=100 opens the page at 100 s (' + t3.toFixed(1) + ')');
await p.goto(BASE + '/works/story-leak-under-tile/#t=1:10', { waitUntil: 'networkidle' }); await p.waitForTimeout(1200); ok(Math.abs((await p.evaluate(() => document.querySelector('video').currentTime)) - 70) < 2, '#t=1:10 (mm:ss) works too');
await p.goto(BASE + '/works/story-leak-under-tile/', { waitUntil: 'networkidle' });
const slider = await p.$('.ba input[type=range]'); ok(!!slider, 'before/after slider (.ba) present'); 
const g = await p.$('.gal a[data-lightbox]'); await g.scrollIntoViewIfNeeded(); await g.click(); await p.waitForTimeout(300); ok(await p.evaluate(() => { const l = document.querySelector('.lb'); return l && !l.hidden; }), 'gallery photo opens the lightbox');
await p.keyboard.press('Escape'); await p.screenshot({ path: dir + '/work-mobile-full.png', fullPage: true });
/* youtube */
await p.goto(BASE + '/works/youtube-work/', { waitUntil: 'domcontentloaded' });
ok((await p.$$('iframe')).length === 0, 'youtube: no iframe before the visitor presses play');
await p.click('.zw-clip:nth-child(2)'); await p.waitForTimeout(300);
const src = await p.evaluate(() => (document.querySelector('.zw-player iframe') || {}).src || ''); ok(/youtube-nocookie\.com\/embed\/dQw4w9WgXcQ\?.*start=40/.test(src) && /autoplay=1/.test(src), 'youtube: clip click → iframe start=40 (' + src + ')');
/* archive + service page */
await p.goto(BASE + '/works/', { waitUntil: 'networkidle' }); ok((await overflow()) <= 1, 'archive: no horizontal scroll'); await p.screenshot({ path: dir + '/work-mobile-archive.png' });
await p.goto(BASE + '/service/leak-detection/', { waitUntil: 'networkidle' }); ok((await overflow()) <= 1, 'service page: no horizontal scroll'); const sec = await p.$('.zw-field'); ok(!!sec, 'service page shows «أعمال من الميدان»'); if (sec) { await sec.scrollIntoViewIfNeeded(); await sec.screenshot({ path: dir + '/work-service-section.png' }); }
await p.goto(BASE + '/old-gallery/', { waitUntil: 'networkidle' }); ok((await p.textContent('body')).length > 200, 'old «أعمالنا» page renders');
ok(!errs.length, 'no JS errors: ' + errs.slice(0, 3).join(' | '));
console.log(`${n} checks, ${fail} failed`); await b.close(); process.exit(fail ? 1 : 0);
