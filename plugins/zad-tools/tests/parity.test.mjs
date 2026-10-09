import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
const ZT = createRequire(import.meta.url)('../assets/js/zt-core.js');
const v = JSON.parse(readFileSync(new URL('./vectors.json', import.meta.url)));
const php = JSON.parse(execFileSync('php', [new URL('./parity.php', import.meta.url).pathname], { encoding: 'utf8' }));

test('digits: JS == PHP', () => assert.deepEqual(v.text.map(ZT.digits), php.digits));
test('num: JS == PHP', () => assert.deepEqual(v.text.map(ZT.num), php.num));
test('fmt: JS == PHP', () => assert.deepEqual(v.numbers.map(ZT.fmt), php.fmt));
test('qsString: JS == PHP', () => assert.deepEqual(v.qs.map(ZT.qsString), php.qs));
test('waMessage: JS == PHP', () => assert.deepEqual(v.wa.map(w => ZT.waMessage(w[0], w[1], w[2])), php.wa));
test('cleanParams: JS == PHP', () => assert.deepEqual(v.clean.map(ZT.cleanParams), php.clean.map(o => (Array.isArray(o) ? {} : o))));
