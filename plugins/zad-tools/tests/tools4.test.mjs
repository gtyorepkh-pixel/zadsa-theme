import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';
const req = createRequire(import.meta.url);
const PI = req('../assets/js/pest-id.js');
const v = JSON.parse(readFileSync(new URL('./vectors-tools4.json', import.meta.url)));
const cfg = v.pid.cfg, cfg2 = v.pid.cfg2;
const pct = (i, c = cfg) => PI.calc(c, i).results.map((r) => [r.p.n, r.pct]);

test('Identifier: all five answers match one pest fully = 100%; others by how many of the 5 answers they share', () => {
  assert.deepEqual(pct({ ans: { place: 'kitchen', size: 'medium', color: 'brown', wings: 'yes', sign: 'droppings' } }), [['الصراصير', 100], ['النمل', 40], ['النمل الأبيض', 40]]); // ant: place+color = 2/5 ; termite: wings «some» + sign = 2/5
});
test('Identifier: «not sure» answers leave the denominator — one answered question out of five is 100% when it matches', () => {
  assert.deepEqual(pct({ ans: { place: 'kitchen' } }), [['الصراصير', 100], ['النمل', 100]]);
});
test('Identifier: wings «sometimes» matches any answer; an empty attribute never matches (incomplete drafts do not inflate)', () => {
  assert.deepEqual(pct({ ans: { wings: 'no' } }), [['النمل', 100], ['النمل الأبيض', 100]]);   // roach: yes ≠ no ; termite: some ; bedbug: no data → absent
  assert.ok(!pct({ ans: { wings: 'yes' } }).some((r) => r[0] === 'بق الفراش'));
});
test('Identifier: weights — place counts double: kitchen (w2) + wings yes (w1) → 100% vs 67% for a pest matching only the place', () => {
  assert.deepEqual(pct({ ans: { place: 'kitchen', wings: 'yes' } }, cfg2), [['حشرة تجريبية', 100]]);       // max=1 result
  assert.deepEqual(PI.calc({ ...cfg2, max: 2 }, { ans: { place: 'kitchen', wings: 'yes' } }).results.map((r) => r.pct), [100, 67]); // 2/3 → 67
});
test('Identifier: below the threshold it never claims — asks for a photo; at/above it names the pest', () => {
  const weak = PI.view(cfg, { ans: { place: 'bath', size: 'tiny', color: 'white', wings: 'yes' } }); // best = 50% < 60
  assert.equal(weak.big, 'لا يوجد تطابق قوي'); assert.match(weak.wa, /وسأرسل لكم صورة/); assert.ok(weak.lines.some((l) => /صورة/.test(l)));
  const strong = PI.view(cfg, { ans: { place: 'wood', size: 'small', color: 'white', wings: 'no', sign: 'sawdust' }, hood: 'النرجس' }); assert.equal(strong.big, 'الأقرب: النمل الأبيض'); assert.match(strong.wa, /بنسبة تطابق 100%/); assert.match(strong.wa, /في حي النرجس\.$/);
  assert.equal(PI.calc({ ...cfg, min: 50 }, { ans: { place: 'bath', size: 'tiny', color: 'white', wings: 'yes' } }).strong, true);
});
test('Identifier: no answers / invalid answers → an inline error; result limited to the setting; cards carry the service link', () => {
  assert.ok(PI.view(cfg, { ans: {} }).error); assert.ok(PI.view(cfg, { ans: { place: 'nope' } }).error);
  assert.equal(PI.calc(cfg, { ans: { size: 'small' } }).results.length, 3);
  assert.deepEqual(PI.view(cfg, { ans: { place: 'wood' } }).cards[0].svc, ['مكافحة النمل الأبيض', 'https://zadksa.com/termite/']);
});
