import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
const req = createRequire(import.meta.url);
const ZT = req('../assets/js/zt-core.js'), AC = req('../assets/js/ac-size.js'), SP = req('../assets/js/after-spray.js'), TK = req('../assets/js/tank.js');
const v = JSON.parse(readFileSync(new URL('./vectors-tools.json', import.meta.url)));
const php = JSON.parse(execFileSync('php', [new URL('./tools-parity.php', import.meta.url).pathname], { encoding: 'utf8' }));
const env = { wa: '966555000111', privacy: 'https://zadksa.com/privacy/' };
const j = (x) => JSON.parse(JSON.stringify(x)); // drop undefined like json_encode does

// the input objects of the vectors are shared; JS numbers come as numbers already
test('AC: JS view == PHP view for every vector', () => v.ac.inputs.forEach((i, n) => assert.deepEqual(j(AC.view(v.ac.cfg, i)), php.ac[n], 'ac #' + n)));
test('Spray: JS view == PHP view for every vector', () => v.spray.inputs.forEach((i, n) => assert.deepEqual(j(SP.view(v.spray.cfg, i)), php.spray[n], 'spray #' + n)));
test('Tank: JS view == PHP view (tiers + no consumption)', () => v.tank.inputs.forEach((i, n) => assert.deepEqual(j(TK.view(v.tank.cfg, i, v.tank.today)), php.tank[n], 'tank #' + n)));
test('Tank: JS view == PHP view (consumption + fallback price)', () => v.tank.inputs.forEach((i, n) => assert.deepEqual(j(TK.view(v.tank.cfg2, i, v.tank.today)), php.tank2[n], 'tank2 #' + n)));
test('Result HTML: ZT.resultHtml == zt_result_html (byte for byte)', () => {
  const views = [
    ...v.ac.inputs.map((i) => AC.view(v.ac.cfg, i)),
    ...v.spray.inputs.map((i) => SP.view(v.spray.cfg, i)),
    ...v.tank.inputs.map((i) => TK.view(v.tank.cfg, i, v.tank.today)),
    ...v.views,
  ].map(j);
  assert.equal(views.length, php.html.length);
  views.forEach((x, n) => assert.equal(ZT.resultHtml(x, env), php.html[n], 'html #' + n));
});
test('dates: addMonths / arDate / addDays', () => v.dates.forEach((d, n) => {
  const m = ZT.addMonths(d[0], d[1]);
  assert.deepEqual([m, ZT.arDate(m), ZT.addDays(d[0], 40), ZT.addDays(d[0], -40)], php.dates[n], 'date #' + n);
}));
test('arCount / pctLabel', () => v.counts.forEach((c, n) => assert.deepEqual([ZT.arCount(c[0], 'ساعة', 'ساعتين', 'ساعات', 'ساعة'), ZT.pctLabel(c[0]), ZT.pctLabel(-c[0])], php.counts[n], 'count #' + n)));
