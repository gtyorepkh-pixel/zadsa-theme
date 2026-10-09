import test from 'node:test';
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
const req = createRequire(import.meta.url);
const ZT = req('../assets/js/zt-core.js'), PI = req('../assets/js/pest-id.js');
const v = JSON.parse(readFileSync(new URL('./vectors-tools4.json', import.meta.url)));
const php = JSON.parse(execFileSync('php', [new URL('./tools4-parity.php', import.meta.url).pathname], { encoding: 'utf8' }));
const env = { wa: '966555000111', privacy: 'https://zadksa.com/privacy/' };
const j = (x) => JSON.parse(JSON.stringify(x));
test('Identifier: JS view == PHP view (4 pests, 5 questions) and (2 pests, weights)', () => {
  v.pid.inputs.forEach((i, n) => assert.deepEqual(j(PI.view(v.pid.cfg, i)), php.pid[n], 'pid #' + n));
  v.pid.inputs2.forEach((i, n) => assert.deepEqual(j(PI.view(v.pid.cfg2, i)), php.pid2[n], 'pid2 #' + n));
});
test('Identifier: result HTML with image cards is byte-identical', () => v.pid.inputs.map((i) => j(PI.view(v.pid.cfg, i))).forEach((x, n) => assert.equal(ZT.resultHtml(x, env), php.html[n], 'html #' + n)));
