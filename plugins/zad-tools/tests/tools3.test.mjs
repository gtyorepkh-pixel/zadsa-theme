import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { readFileSync } from 'node:fs';
const req = createRequire(import.meta.url);
const PW = req('../assets/js/ac-power.js'), MV = req('../assets/js/moving.js'), PL = req('../assets/js/plan.js'), CV = req('../assets/js/coverage.js'), ZT = req('../assets/js/zt-core.js');
const v = JSON.parse(readFileSync(new URL('./vectors-tools3.json', import.meta.url)));
const pw = v.power.cfg, mv = v.moving.cfg, pl = v.plan.cfg, T = v.plan.today;

/* ---- electricity: figures worked out by hand (synthetic tariff 10 hal ≤2000 kWh, 20 hal above) ---- */
test('Power: 1.2 kW/ton × 2 ton × 10 h × 30 d = 720 kWh → 720×10 hal = 72 SAR', () => { const r = PW.calc(pw, { acs: [{ t: 2, q: 1, ty: 'split', ag: 'new' }], h: 10, d: 30, cl: 'lt3' }); assert.equal(r.kwh, 720); assert.equal(r.costClean, 72); });
test('Power: tiers are marginal on top of the rest of the house — other 1,500 kWh + 2,988 kWh = 787.24 − 150 = 637.24 SAR (dirty ×1.15)', () => {
  const r = PW.calc(pw, { acs: [{ t: 2, q: 3, ty: 'split', ag: 'old' }, { t: 1.5, q: 1, ty: 'window', ag: 'new' }], h: 8, d: 30, cl: 'gt12', o: 1500 });
  assert.equal(r.kwh, 2448 + 540); assert.ok(Math.abs(r.dirty - 2988 * 1.15) < 1e-9); assert.ok(Math.abs(r.costDirty - 637.24) < 1e-9);
  assert.ok(Math.abs(r.extraCost - (r.costDirty - r.costClean)) < 1e-9); assert.ok(Math.abs(r.season - r.extraCost * 5) < 1e-9);
});
test('Power: no tiers → kWh only (no riyals, no season) ; no dirt % for the bucket → no dirt section', () => {
  const v2 = PW.view(v.power.cfg2, { acs: [{ t: 2, q: 1, ty: 'split', ag: 'new' }], h: 10, d: 30, cl: 'lt3' });
  assert.ok(!v2.lines.some((l) => /ريال/.test(l))); assert.ok(v2.notes.some((n) => /غير مفعّل/.test(n)));
  const v3 = PW.view({ ...pw, dirt: {} }, { acs: [{ t: 2, q: 1, ty: 'split', ag: 'new' }], h: 10, d: 30, cl: 'gt12' }); assert.ok(!v3.lines.some((l) => /اتساخ/.test(l)));
});
test('Power: an unregistered type/age is refused, never guessed', () => assert.match(PW.view(pw, { acs: [{ t: 3, q: 2, ty: 'window', ag: 'old' }], h: 10, d: 30 }).error, /غير مسجّلين/));
test('Power: tariff source + date are printed with the cost', () => assert.ok(PW.view(pw, { acs: [{ t: 2, q: 1, ty: 'split', ag: 'new' }], h: 10, d: 30, cl: 'lt3' }).notes.join(' ').includes('https://example.test/t — آخر تحديث: 2026-01-01')));
test('Power: cost() band maths — 2,500 kWh = 2,000×10 + 500×20 = 300 SAR', () => assert.equal(PW.cost(pw.tiers, 2500), 300));

/* ---- moving ---- */
test('Moving: 2 rooms in-city with lifts = the table row only (300–400)', () => { const r = MV.calc(mv, { ty: 'in', fc: 'r', r: 2, ef: true, et: true }); assert.deepEqual([r.lo, r.hi, r.trucks, r.workers], [300, 400, 1, 2]); });
test('Moving: inter-city route price × trucks; floors without a lift; unpriced items listed «بعد المعاينة» and left out of the range', () => {
  const r = MV.calc(mv, { ty: 'inter', fc: 'j', tc: 'r', r: 4, ff: 1, ft: 2 }); // 600–800 + 900×2 + 1×(20–30) + 2×(20–30)
  assert.deepEqual([r.lo, r.hi], [2460, 2690]);
  const u = MV.calc(mv, { ty: 'in', fc: 'r', r: 3, fr: 1, fu: true, ef: true, et: true }); assert.equal(u.manual.length, 2); assert.deepEqual([u.lo, u.hi], [600, 800]);
});
test('Moving: rows above the table → visit; nothing priced → no number', () => {
  assert.equal(MV.calc(mv, { ty: 'in', fc: 'r', r: 9 }).trucks, null); assert.equal(MV.view(mv, { ty: 'in', fc: 'r', r: 9 }).lines.at(-1), 'السعر يتحدد بعد المعاينة.');
  const r = MV.calc(v.moving.cfg2, { ty: 'in', fc: 'r', r: 3 }); assert.equal(r.lo, null); assert.equal(r.trucks, 1);
});
test('Moving: same city for an inter-city move is refused; rooms must be 1–30', () => { assert.ok(MV.view(mv, { ty: 'inter', fc: 'r', tc: 'r', r: 2 }).error); assert.ok(MV.view(mv, { ty: 'in', fc: 'r', r: 0 }).error); assert.ok(MV.view(mv, { ty: 'in', fc: 'r', r: 31 }).error); });
test('Moving: price ranges add (low+low, high+high)', () => { const r = MV.calc(mv, { ty: 'in', fc: 'r', r: 2, ef: true, et: true, pk: true, ac: 2 }); assert.deepEqual([r.lo, r.hi], [300 + 80 + 100, 400 + 120 + 100]); });

/* ---- plan ---- */
const occ = (r, k) => r.occ.filter((o) => o.k === k).map((o) => o.date);
test('Plan: AC cleaning in the preferred month (April), six-monthly, inside 12 months only', () => { assert.deepEqual(occ(PL.calc(pl, { hs: 'apartment', ct: 'riyadh', tk: 'none', acn: 2, sm: '2027-01', last: {} }, T), 'ac'), ['2027-04-01', '2027-10-01']); });
test('Plan: without a preferred month the first visit is today (not a past date)', () => assert.equal(occ(PL.calc(pl, { hs: 'apartment', ct: 'riyadh', tk: 'ground', acn: 0, last: {} }, T), 'tg')[0], '2026-10-09'));
test('Plan: last service + interval; an overdue one is moved to today and flagged', () => {
  const r = PL.calc(pl, { hs: 'villa', ct: 'riyadh', tk: 'ground', acn: 1, last: { tg: '2026-09-20', ac: '2026-03-01' } }, T);
  assert.deepEqual(occ(r, 'tg'), ['2027-03-20', '2027-09-20']); assert.deepEqual(occ(r, 'ac'), ['2026-10-09', '2027-03-01', '2027-09-01']); assert.equal(r.occ.find((o) => o.k === 'ac').overdue, true);
});
test('Plan: month-end clamping — Jan 31 + 4 months = May 31, + 8 = Sep 30', () => assert.deepEqual(occ(PL.calc(pl, { hs: 'apartment', ct: 'riyadh', tk: 'roof', acn: 0, last: { tr: '2026-01-31' } }, T), 'tr'), ['2026-10-09', '2027-01-31', '2027-05-31', '2027-09-30']));
test('Plan: conditions are ANDed; an unknown condition never matches; no tasks → a clear message', () => {
  const k = (i) => PL.calc(pl, { hs: 'apartment', ct: 'riyadh', tk: 'none', acn: 0, last: {}, ...i }, T).tasks.map((t) => t.k);
  assert.ok(!k({ pe: true }).includes('pest')); assert.ok(k({ pe: true, hs: 'villa' }).includes('pest')); assert.ok(!k({ acn: 1 }).includes('typo')); assert.ok(PL.view(pl, { hs: 'apartment', ct: 'riyadh', tk: 'none', acn: 0, last: {} }, T).error);
});
test('Plan: yearly cost = lowest price × quantity × visits; unpriced tasks are named, never priced', () => {
  const r = PL.calc(pl, { hs: 'apartment', ct: 'riyadh', tk: 'both', acn: 3, last: {} }, T); // ac: 50×3×1 ; tr (roof, 4-monthly): 120×3 visits; tg unpriced
  assert.equal(r.cost, 150 + 360); assert.deepEqual(r.unpriced, ['تنظيف الخزان الأرضي']);
});
test('Plan: 12 calendar months starting at the chosen month; .ics lists every date; reminders carry future dates only', () => {
  const x = PL.view(pl, { hs: 'apartment', ct: 'riyadh', tk: 'ground', acn: 3, last: {} }, T);
  assert.equal(x.cal.length, 12); assert.equal(x.cal[0].m, 'أكتوبر 2026'); assert.equal(x.cal[11].m, 'سبتمبر 2027');
  assert.ok(x.links[0].href.includes('ev=2026-10-09')); assert.ok(x.optin.items.every((i) => i.date >= T)); assert.ok(x.links[1].href.startsWith('https://wa.me/?text='));
});

/* ---- coverage (client helpers) ---- */
const hoods = [{ id: 1, n: 'النرجس', c: 'الرياض', lat: 24.8, lng: 46.65, v: [['تنظيف مكيفات', '/a'], ['تنظيف خزانات', '/b']], eta: { m: 35, n: 14, src: 'orders' } }, { id: 2, n: 'الشاطئ', c: 'جدة', lat: 21.6, lng: 39.1, v: [['تنظيف كنب', '/c']], eta: null }];
test('Coverage: nearest district and the service filter', () => { assert.equal(CV.nearest(hoods, 24.7, 46.7).h.n, 'النرجس'); assert.equal(CV.nearest(hoods, 21.5, 39.2).h.n, 'الشاطئ'); assert.equal(CV.filter(hoods, 'تنظيف كنب').length, 1); assert.equal(CV.filter(hoods, '').length, 2); assert.equal(CV.nearest([{ n: 'x', lat: null, lng: null }], 1, 1), null); });
test('Coverage: marker size by number of services; popup escapes everything and shows the ETA only when present', () => {
  assert.deepEqual([1, 2, 3, 4, 7].map(CV.radius), [6, 9, 9, 12, 12]);
  const h = CV.popupHtml({ ...hoods[0], n: '<img src=x>' }, '', '966555'); assert.ok(!h.includes('<img')); assert.ok(h.includes('نحو 35 دقيقة (من 14 طلب مكتمل)'));
  assert.ok(!CV.popupHtml(hoods[1], '', '').includes('وقت الوصول'));
});
