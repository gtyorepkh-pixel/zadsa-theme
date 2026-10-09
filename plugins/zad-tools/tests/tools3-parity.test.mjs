import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
const req = createRequire(import.meta.url);
const ZT = req('../assets/js/zt-core.js'), PW = req('../assets/js/ac-power.js'), MV = req('../assets/js/moving.js'), PL = req('../assets/js/plan.js');
const v = JSON.parse(readFileSync(new URL('./vectors-tools3.json', import.meta.url)));
const php = JSON.parse(execFileSync('php', [new URL('./tools3-parity.php', import.meta.url).pathname], { encoding: 'utf8' }));
const env = { wa: '966555000111', privacy: 'https://zadksa.com/privacy/' };
const j = (x) => JSON.parse(JSON.stringify(x));
test('Electricity: JS == PHP (tariff + cleaning) and (kWh only)', () => v.power.inputs.forEach((i, n) => { assert.deepEqual(j(PW.view(v.power.cfg, i)), php.power[n], 'power #' + n); assert.deepEqual(j(PW.view(v.power.cfg2, i)), php.power2[n], 'power2 #' + n); }));
test('Moving: JS == PHP (priced) and (nothing priced)', () => v.moving.inputs.forEach((i, n) => { assert.deepEqual(j(MV.view(v.moving.cfg, i)), php.moving[n], 'moving #' + n); assert.deepEqual(j(MV.view(v.moving.cfg2, i)), php.moving2[n], 'moving2 #' + n); }));
test('Plan: JS == PHP', () => v.plan.inputs.forEach((i, n) => { assert.deepEqual(j(PL.view(v.plan.cfg, i, v.plan.today)), php.plan[n], 'plan #' + n); assert.deepEqual(j(PL.view(v.plan.cfg2, i, v.plan.today)), php.plan2[n], 'plan2 #' + n); }));
test('Result HTML (incl. calendar + batch opt-in): byte-identical', () => {
  const views = [...v.power.inputs.map((i) => PW.view(v.power.cfg, i)), ...v.moving.inputs.map((i) => MV.view(v.moving.cfg, i)), ...v.plan.inputs.map((i) => PL.view(v.plan.cfg, i, v.plan.today))].map(j);
  assert.equal(views.length, php.html.length);
  views.forEach((x, n) => assert.equal(ZT.resultHtml(x, env), php.html[n], 'html #' + n));
});
test('multi-event .ics URL and the self WhatsApp link: same bytes', () => {
  const ev = [['2026-11-01', 'تنظيف المكيفات'], ['2027-04-01', 'عنوان ~ فيه | فواصل']];
  assert.equal(ZT.icsUrlMulti('https://zadksa.com/', ev), php.ics[0]);
  assert.equal(ZT.waSelf("السلام عليكم (اختبار) !*'"), php.ics[1]);
});
