// 一键启动脚本的行为测试：用假的 php / npm 命令验证启动顺序、复用已运行后端与退出清理。
// 运行：node --test scripts/dev.test.mjs
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { mkdtempSync, writeFileSync, chmodSync, readFileSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import http from 'node:http';

const root = dirname(dirname(fileURLToPath(import.meta.url)));
const script = join(root, 'scripts', 'dev.mjs');

function fakeBin(log) {
  const dir = mkdtempSync(join(tmpdir(), 'dev-bin-'));
  // 假 php：记录参数；start 时常驻，stop 时立即退出。
  writeFileSync(join(dir, 'php'), `#!/bin/sh\necho "php $*" >> "${log}"\ncase "$*" in *" start"*) trap 'echo "php-term" >> "${log}"; exit 0' TERM INT; while true; do sleep 0.1; done;; esac\nexit 0\n`);
  // 假 npm：记录参数后常驻，直到被终止。
  writeFileSync(join(dir, 'npm'), `#!/bin/sh\necho "npm $*" >> "${log}"\ntrap 'echo "npm-term" >> "${log}"; exit 0' TERM INT\nwhile true; do sleep 0.1; done\n`);
  for (const f of ['php', 'npm']) chmodSync(join(dir, f), 0o755);
  return dir;
}

async function freePort() {
  const server = http.createServer().listen(0, '127.0.0.1');
  await new Promise(r => server.once('listening', r));
  const port = server.address().port;
  await new Promise(r => server.close(r));
  return port;
}

function run(env, readyText, ms = 8000) {
  return new Promise((resolve, reject) => {
    const child = spawn(process.execPath, [script], { env: { ...process.env, ...env }, stdio: ['ignore', 'pipe', 'pipe'] });
    let out = '';
    const timer = setTimeout(() => { child.kill('SIGKILL'); reject(new Error('timeout; output:\n' + out)); }, ms);
    let sent = false;
    // 看到就绪提示后稍等，让假的 php / npm 真正启动并写日志，再模拟 Ctrl+C。
    const onData = d => { out += d; if (!sent && out.includes(readyText)) { sent = true; setTimeout(() => child.kill('SIGINT'), 600); } };
    child.stdout.on('data', onData); child.stderr.on('data', onData);
    child.on('exit', code => { clearTimeout(timer); resolve({ code, out }); });
  });
}

test('后端未运行时先启动 webman，再启动前端，Ctrl+C 两者都停止', async () => {
  const log = join(mkdtempSync(join(tmpdir(), 'dev-log-')), 'calls.log');
  const bin = fakeBin(log);
  // 先拿到一个空闲端口，后端在 webman "启动" 一段时间后才在该端口就绪，模拟真实的延迟就绪。
  const probe = http.createServer().listen(0, '127.0.0.1');
  await new Promise(r => probe.once('listening', r));
  const port = probe.address().port;
  await new Promise(r => probe.close(r));
  const backend = http.createServer((_, res) => res.end('ok'));
  setTimeout(() => backend.listen(port, '127.0.0.1'), 900);
  const result = await run({ PATH: `${bin}:${process.env.PATH}`, DEV_BACKEND_URL: `http://127.0.0.1:${port}`, DEV_FRONTEND_PORT: String(await freePort()) }, '前端已启动');
  backend.close();
  const calls = readFileSync(log, 'utf8');
  assert.match(calls, /php start\.php start/);
  assert.match(calls, /npm run dev/);
  assert.ok(calls.indexOf('php start.php start') < calls.indexOf('npm run dev'), '先启动后端');
  assert.match(calls, /npm-term/, '退出时停止前端');
  assert.match(calls, /php start\.php stop|php-term/, '退出时停止本脚本启动的后端');
  assert.equal(result.code, 0, result.out);
});

test('后端已在运行时直接复用，不重复启动，退出时也不停止它', async () => {
  const log = join(mkdtempSync(join(tmpdir(), 'dev-log-')), 'calls.log');
  const bin = fakeBin(log);
  const backend = http.createServer((_, res) => res.end('ok')).listen(0, '127.0.0.1');
  await new Promise(r => backend.once('listening', r));
  const result = await run({ PATH: `${bin}:${process.env.PATH}`, DEV_BACKEND_URL: `http://127.0.0.1:${backend.address().port}`, DEV_FRONTEND_PORT: String(await freePort()) }, '前端已启动');
  backend.close();
  const calls = existsSync(log) ? readFileSync(log, 'utf8') : '';
  assert.doesNotMatch(calls, /php start\.php start/, '不重复启动');
  assert.doesNotMatch(calls, /php start\.php stop/, '不停止别人启动的后端');
  assert.match(calls, /npm run dev/);
  assert.match(result.out, /复用/);
});

test('前端已在运行时直接复用，不再启动第二个 Vite', async () => {
  const log = join(mkdtempSync(join(tmpdir(), 'dev-log-')), 'calls.log');
  const bin = fakeBin(log);
  const backend = http.createServer((_, res) => res.end('ok')).listen(0, '127.0.0.1');
  const frontend = http.createServer((_, res) => res.end('<html>vite</html>')).listen(0, '127.0.0.1');
  await Promise.all([new Promise(r => backend.once('listening', r)), new Promise(r => frontend.once('listening', r))]);
  const frontendPort = frontend.address().port;
  const result = await run({ PATH: `${bin}:${process.env.PATH}`, DEV_BACKEND_URL: `http://127.0.0.1:${backend.address().port}`, DEV_FRONTEND_PORT: String(frontendPort) }, '__never__');
  backend.close(); frontend.close();
  const calls = existsSync(log) ? readFileSync(log, 'utf8') : '';
  assert.doesNotMatch(calls, /npm run dev/, '不启动第二个前端');
  assert.doesNotMatch(calls, /php start\.php start/);
  assert.match(result.out, new RegExp(`http://localhost:${frontendPort}`));
  assert.equal(result.code, 0);
});
