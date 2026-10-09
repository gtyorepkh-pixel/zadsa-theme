import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
const ZT = createRequire(import.meta.url)('../assets/js/zt-core.js');

test('digits: Arabic-Indic and Persian → ASCII', () => {
  assert.equal(ZT.digits('٠١٢٣٤٥٦٧٨٩'), '0123456789');
  assert.equal(ZT.digits('۰۱۲'), '012');
  assert.equal(ZT.digits('٣٫٥ و ١٬٢٠٠'), '3.5 و 1,200');
});
test('num: reads a number out of what a person typed', () => {
  assert.equal(ZT.num('٢٠'), 20);
  assert.equal(ZT.num('3.5 م'), 3.5);
  assert.equal(ZT.num('1,200 ريال'), 1200);
  assert.equal(ZT.num('٣٫٥'), 3.5);
  assert.equal(ZT.num('abc'), null);
  assert.equal(ZT.num(''), null);
});
test('fmt: Latin digits, thousands comma, <=2 decimals', () => {
  assert.equal(ZT.fmt(0), '0');
  assert.equal(ZT.fmt(1234567.891), '1,234,567.89');
  assert.equal(ZT.fmt(1.005), '1.01');
  assert.equal(ZT.fmt(2.5), '2.5');
  assert.equal(ZT.fmt(1000), '1,000');
  assert.equal(ZT.fmt(null), '');
});
test('qsString: sorted, empty values dropped, round-trip', () => {
  assert.equal(ZT.qsString({ b: '2', a: '1', c: '' }), 'a=1&b=2');
  assert.deepEqual(ZT.qsParse('?a=1&b=%D8%A7'), { a: '1', b: 'ا' });
});
test('waMessage: result + hood', () => {
  assert.equal(ZT.waMessage('غرفة 20م² ← مكيف 2 طن.', 'النرجس'), 'السلام عليكم، غرفة 20م² ← مكيف 2 طن. في حي النرجس.');
  assert.equal(ZT.waMessage('نتيجة', 'حي الملقا'), 'السلام عليكم، نتيجة في حي الملقا.');
  assert.equal(ZT.waMessage('نتيجة', ''), 'السلام عليكم، نتيجة');
});
test('cleanParams: nothing personal reaches GA4', () => {
  assert.deepEqual(ZT.cleanParams({ phone: '0555', name: 'x', size: 2, mode: 'a', tool: 't', msg: 'm', email: 'e', long: 'x'.repeat(80) }), { size: 2, mode: 'a', tool: 't' });
});
test('waLink', () => {
  assert.equal(ZT.waLink('', 'x'), '');
  assert.equal(ZT.waLink('966555', 'مرحبا'), 'https://wa.me/966555?text=%D9%85%D8%B1%D8%AD%D8%A8%D8%A7');
});
