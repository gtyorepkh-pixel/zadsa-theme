import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
const PI = createRequire(import.meta.url)('../assets/js/pest-image.js');
test('fitSize: never enlarges, keeps the ratio, long side = max', () => {
  assert.deepEqual(PI.fitSize(4000, 3000, 1600), [1600, 1200]); assert.deepEqual(PI.fitSize(3000, 4000, 1600), [1200, 1600]);
  assert.deepEqual(PI.fitSize(800, 600, 1600), [800, 600]); assert.deepEqual(PI.fitSize(1, 1, 1600), [1, 1]);
});
test('check: needs a file of an allowed type', () => {
  assert.ok(PI.check(null, 5)); assert.ok(PI.check({ type: 'application/pdf', name: 'a.pdf', size: 10 }, 5));
  assert.equal(PI.check({ type: 'image/jpeg', name: 'a.jpg', size: 10 }, 5), ''); assert.equal(PI.check({ type: '', name: 'IMG_1.HEIC', size: 10 }, 5), '');
  assert.ok(PI.check({ type: 'image/png', name: 'a.png', size: 5 * 1048576 * 5 }, 5));
});
test('view: approximate, names the nearest pest, always offers the technician; no match → asks for a photo on WhatsApp', () => {
  const v = PI.view([{ t: 'النمل الأبيض', u: '/p', p: 87, img: '', alt: '', svc: null }], 'النرجس'); assert.equal(v.badge, 'تقريبي'); assert.equal(v.big, 'يبدو أقرب إلى: النمل الأبيض'); assert.match(v.wa, /87%/); assert.match(v.wa, /في حي النرجس\.$/);
  const none = PI.view([], ''); assert.equal(none.big, 'لم نتعرف على الحشرة'); assert.ok(none.lines[0].includes('واتساب')); assert.ok(none.wa.length > 10);
});
