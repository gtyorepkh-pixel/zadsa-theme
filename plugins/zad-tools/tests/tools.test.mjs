import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
const req = createRequire(import.meta.url);
const ZT = req('../assets/js/zt-core.js'), AC = req('../assets/js/ac-size.js'), SP = req('../assets/js/after-spray.js'), TK = req('../assets/js/tank.js');

/* numbers below are worked out by hand (comments), not copied from the code's output */
const ac = { base: 700, refH: 3, personBtu: 600, peopleFree: 2, topPct: 15, tonBtu: 12000, maxUnits: 4, sizes: [12000, 18000, 24000, 30000, 36000, 48000, 60000],
  rooms: [{ k: 'bed', l: 'غرفة نوم', p: 0 }, { k: 'liv', l: 'صالة', p: 10 }], sun: [{ k: 'n', l: 'عادي', p: 0 }, { k: 's', l: 'شمس', p: 10 }],
  win: [{ k: 'n', l: 'عادية', p: 0 }], ins: [{ k: 'n', l: 'عادي', p: 0 }] };

test('AC: 16 m² plain = 16×700 = 11,200 → 12,000 BTU (1 ton)', () => { const r = AC.calc(ac, { a: 16 }); assert.equal(r.total, 11200); assert.equal(r.fit.size, 12000); assert.equal(AC.view(ac, { a: 16 }).big, 'مكيف 1 طن'); });
test('AC: 4×4 top floor, 4 people = 11,200×1.15 + 2×600 = 14,080 → 18,000 (1.5 ton)', () => { const r = AC.calc(ac, { l: 4, w: 4, top: true, p: 4 }); assert.equal(r.total, 14080); assert.equal(AC.view(ac, { l: 4, w: 4, top: true, p: 4 }).big, 'مكيف 1.5 طن'); });
test('AC: 30 m² living(+10) sunny(+10) 4 people = 21,000×1.2 + 1,200 = 26,400 → 30,000', () => { const r = AC.calc(ac, { a: 30, rt: 'liv', sn: 's', p: 4 }); assert.equal(r.total, 26400); assert.equal(r.fit.size, 30000); });
test('AC: a load exactly equal to a size takes that size (not the next)', () => { const r = AC.calc({ ...ac, base: 750 }, { a: 16 }); assert.equal(r.total, 12000); assert.equal(r.fit.size, 12000); });
test('AC: ceiling 3.5 m scales the base by 3.5/3', () => { assert.equal(AC.calc(ac, { a: 12, h: 3.5 }).base, Math.round(12 * 700 * 3.5 / 3)); });
test('AC: above the biggest size → several units (150 m² = 105,000 → 2 × 60,000)', () => { const r = AC.calc(ac, { a: 150 }); assert.deepEqual(r.fit, { units: 2, size: 60000 }); assert.equal(AC.view(ac, { a: 150 }).big, 'مكيفان، كل واحد 5 طن'); });
test('AC: too big even for max units → asks for a visit, no invented size', () => { const v = AC.view(ac, { a: 400 }); assert.equal(AC.calc(ac, { a: 400 }).fit, null); assert.match(v.big, /معاينة/); });
test('AC: invalid input gives an inline error', () => { assert.ok(AC.view(ac, {}).error); assert.ok(AC.view(ac, { a: 16, h: 25 }).error); assert.ok(AC.view(ac, { l: 4 }).error); });
test('AC: WhatsApp text carries the hood', () => assert.match(AC.view(ac, { a: 16, hood: 'حي النرجس' }).wa, /في حي النرجس\.$/));

const sp = { pests: [{ k: 'r', l: 'صراصير' }], methods: [{ k: 's', l: 'رش سائل' }, { k: 'g', l: 'جل' }], matrix: { 'r|s': 6 }, notes: {},
  factors: [{ k: 'kids', l: 'أطفال', min: 12, txt: 'يبعدون' }, { k: 'pets', l: 'حيوانات', min: null, txt: 'تخرج' }], before: ['أ.'], during: [], after: ['ب.'] };
test('Spray: matrix hours; empty cell is never guessed', () => { assert.equal(SP.view(sp, { ps: 'r', md: 's', f: [] }).big, 'ارجع بعد 6 ساعات'); assert.equal(SP.view(sp, { ps: 'r', md: 'g', f: [] }).big, 'الفني هيحدد المدة بعد المعاينة'); });
test('Spray: a household factor can only raise the time, never lower it', () => {
  assert.equal(SP.calc(sp, { ps: 'r', md: 's', f: ['kids'] }).hours, 12);
  assert.equal(SP.calc({ ...sp, factors: [{ k: 'kids', l: 'أ', min: 3, txt: '' }] }, { ps: 'r', md: 's', f: ['kids'] }).hours, 6);
  assert.equal(SP.calc(sp, { ps: 'r', md: 'g', f: ['kids'] }).hours, null); // no base → still the technician
});
test('Spray: return clock — 22:30 + 6h = 4:30 ص next day; + 12h = 10:30 ص', () => {
  assert.deepEqual(SP.calc(sp, { ps: 'r', md: 's', f: [], t: '22:30' }).ret, { at: 270, day: 1 });
  assert.ok(SP.view(sp, { ps: 'r', md: 's', f: [], t: '22:30' }).lines.includes('تقدر ترجع الساعة 4:30 ص (اليوم التالي)'));
  assert.ok(SP.view(sp, { ps: 'r', md: 's', f: [], t: '٠٩:٠٥' }).lines.includes('تقدر ترجع الساعة 3:05 م'));
  assert.equal(SP.calc(sp, { ps: 'r', md: 's', f: [], t: '25:00' }).ret, null);
});
test('Spray: Arabic labels', () => { assert.equal(SP.hoursLabel(1), 'ساعة'); assert.equal(SP.hoursLabel(2), 'ساعتين'); assert.equal(SP.hoursLabel(3), '3 ساعات'); assert.equal(SP.hoursLabel(11), '11 ساعة'); assert.equal(SP.hoursLabel(24), 'يوم'); assert.equal(SP.hoursLabel(48), 'يومين'); assert.equal(SP.hoursLabel(72), '3 أيام'); assert.equal(SP.hoursLabel(1.5), '1.5 ساعة'); });
test('Spray: must choose pest and method', () => assert.ok(SP.view(sp, { ps: '', md: 's' }).error));

const tk = { locs: [{ k: 'g', l: 'خزان أرضي', m: 6 }, { k: 'r', l: 'خزان علوي', m: 4 }], lpp: null, tiers: [{ max: 1000, price: 150 }, { max: 3000, price: 220 }], fromPrice: null, ics: 'https://zadksa.com/' };
const TODAY = '2026-10-09';
test('Tank: volumes — rect 2×1×1 = 2,000 L; cm gives the same; cylinders', () => {
  assert.equal(TK.litres('rect', 2, 1, 1, 'm'), 2000); assert.equal(TK.litres('rect', 200, 100, 100, 'cm'), 2000);
  assert.equal(TK.litres('cyl_v', 1.5, 2, 0, 'm'), 3534);   // π × 0.75² × 2 = 3.5343 m³
  assert.equal(TK.litres('cyl_h', 120, 250, 0, 'cm'), 2827); // π × 0.6² × 2.5 = 2.8274 m³
  assert.equal(TK.litres('rect', 2, 1, 0, 'm'), null); assert.equal(TK.litres('rect', 500, 1, 1, 'm'), null);
});
test('Tank: price tiers → first tier that fits; above the last tier → after inspection (never invented)', () => {
  assert.equal(TK.calc(tk, { loc: 'g', shape: 'rect', a: 2, b: 0.5, c: 1, unit: 'm' }, TODAY).price.value, 150);
  assert.equal(TK.calc(tk, { loc: 'g', shape: 'rect', a: 2, b: 1, c: 1, unit: 'm' }, TODAY).price.value, 220);
  assert.equal(TK.calc(tk, { loc: 'g', shape: 'rect', a: 4, b: 3, c: 2, unit: 'm' }, TODAY).price, null);
  assert.ok(TK.view(tk, { loc: 'g', shape: 'rect', a: 4, b: 3, c: 2, unit: 'm' }, TODAY).lines.includes('تكلفة التنظيف تتحدد بعد المعاينة.'));
});
test('Tank: next cleaning date = last + interval (clamped to month end), overdue detected', () => {
  assert.equal(TK.calc(tk, { loc: 'r', shape: 'rect', a: 2, b: 1.5, c: 1.5, unit: 'm', last: '2026-08-31' }, TODAY).next, '2026-12-31');
  assert.equal(TK.calc({ ...tk, locs: [{ k: 'g', l: 'x', m: 6 }] }, { loc: 'g', shape: 'rect', a: 2, b: 1, c: 1, unit: 'm', last: '2026-08-31' }, TODAY).next, '2027-02-28');
  const o = TK.calc(tk, { loc: 'g', shape: 'rect', a: 2, b: 1, c: 1, unit: 'm', last: '2025-12-31' }, TODAY);
  assert.equal(o.next, '2026-06-30'); assert.equal(o.overdue, true);
  assert.equal(TK.view(tk, { loc: 'g', shape: 'rect', a: 2, b: 1, c: 1, unit: 'm', last: '2025-12-31' }, TODAY).optin, null); // nothing to remind in the past
});
test('Tank: reminder opt-in + .ics only for a future date; date today counts as due later', () => {
  const v = TK.view(tk, { loc: 'r', shape: 'rect', a: 2, b: 1.5, c: 1.5, unit: 'm', last: '2026-08-31' }, TODAY);
  assert.deepEqual(v.optin, { date: '2026-12-31', service: 'تنظيف خزان المياه', title: 'ذكّرني بموعد التنظيف' });
  assert.match(v.links[0].href, /^https:\/\/zadksa\.com\/\?d=2026-12-31&t=.+&zad_ics=1$/);
});
test('Tank: days of supply only when the consumption setting is filled (200 L: 4,500 L / (5×200) = 4 days)', () => {
  const a = TK.view(tk, { loc: 'g', shape: 'rect', a: 3, b: 3, c: 0.5, unit: 'm', people: 5 }, TODAY);
  assert.ok(!a.lines.some((l) => /يكفي/.test(l)));
  const b = TK.view({ ...tk, lpp: 200 }, { loc: 'g', shape: 'rect', a: 3, b: 3, c: 0.5, unit: 'm', people: 5 }, TODAY);
  assert.ok(b.lines.includes('يكفي 5 أشخاص نحو 4 أيام إذا امتلأ.'));
});
test('Tank: a future "last cleaning" date is refused', () => assert.ok(TK.view(tk, { loc: 'g', shape: 'rect', a: 2, b: 1, c: 1, unit: 'm', last: '2030-01-01' }, TODAY).error));
test('Tank: fallback "starts from" price only when there are no tiers', () => {
  assert.ok(TK.view({ ...tk, tiers: [], fromPrice: 120 }, { loc: 'g', shape: 'rect', a: 2, b: 1, c: 1, unit: 'm' }, TODAY).lines.includes('تكلفة التنظيف تبدأ من 120 ريال'));
  assert.ok(!TK.view(tk, { loc: 'g', shape: 'rect', a: 4, b: 3, c: 2, unit: 'm' }, TODAY).lines.some((l) => /تبدأ من/.test(l)));
});
test('dates: leap year and month end', () => { assert.equal(ZT.addMonths('2024-02-29', 12), '2025-02-28'); assert.equal(ZT.addMonths('2026-01-31', 1), '2026-02-28'); assert.equal(ZT.addDays('2026-12-30', 3), '2027-01-02'); assert.equal(ZT.addMonths('2026-02-30', 1), ''); });
test('renderer: escapes everything it prints', () => {
  const h = ZT.resultHtml({ big: '<b>x</b>', lines: ['"q" & \'s\''], wa: 'a' }, { wa: '966' });
  assert.ok(!h.includes('<b>')); assert.ok(h.includes('&lt;b&gt;')); assert.ok(h.includes('&quot;q&quot; &amp; &#039;s&#039;'));
});
