// 一键启动脚本的行为测试：用假的 php / npm 命令验证启动顺序、复用已运行后端与退出清理。
// 运行：node --test scripts/dev.test.mjs
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { mkdtempSync, writeFileSync, chmodSync, readFileSync, existsSync, appendFileSync, mkdirSync, truncateSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import http from 'node:http';
import { tailDailyLog, dailyLogName } from './log-tail.mjs';

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

function run(env, readyText, ms = 8000, onReady) {
  return new Promise((resolve, reject) => {
    // 默认把后端日志目录指向空的临时目录，避免读到真实的 admin/runtime/logs。
    const logDir = mkdtempSync(join(tmpdir(), 'dev-logs-'));
    const child = spawn(process.execPath, [script], { env: { ...process.env, DEV_BACKEND_LOG_DIR: logDir, ...env }, stdio: ['ignore', 'pipe', 'pipe'] });
    let out = '';
    const timer = setTimeout(() => { child.kill('SIGKILL'); reject(new Error('timeout; output:\n' + out)); }, ms);
    let sent = false;
    // 看到就绪提示后稍等，让假的 php / npm 真正启动并写日志，再模拟 Ctrl+C。
    const onData = d => { out += d; if (!sent && out.includes(readyText)) { sent = true; setTimeout(() => onReady?.(), 100); setTimeout(() => child.kill('SIGINT'), 600); } };
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

// ---- 后端日志跟随（scripts/log-tail.mjs）----

const day = (y, m, d) => new Date(y, m - 1, d, 12, 0, 0);

async function waitFor(check, ms = 3000) {
  const deadline = Date.now() + ms;
  while (Date.now() < deadline) {
    if (check()) return;
    await new Promise(r => setTimeout(r, 10));
  }
  throw new Error('waitFor timeout');
}

function tailer(dir, now) {
  const lines = [];
  const handle = tailDailyLog({ dir, now, interval: 15, onLine: line => lines.push(line) });
  return { lines, handle };
}

test('日志文件名按本地日期生成', () => {
  assert.equal(dailyLogName(day(2026, 1, 5)), 'webman-2026-01-05.log');
});

test('从文件末尾开始跟随：不输出历史，只输出新追加的行（含跨次写入的半行）', async () => {
  const dir = mkdtempSync(join(tmpdir(), 'dev-tail-'));
  const file = join(dir, dailyLogName(day(2026, 11, 8)));
  writeFileSync(file, 'old-1\nold-2\n');
  const { lines, handle } = tailer(dir, () => day(2026, 11, 8));
  try {
    await new Promise(r => setTimeout(r, 60));
    appendFileSync(file, 'new-1\nnew-');
    await waitFor(() => lines.length === 1);
    appendFileSync(file, '2\n\n');
    await waitFor(() => lines.length === 2);
    assert.deepEqual(lines, ['new-1', 'new-2']);
  } finally { handle.stop(); }
});

test('文件尚不存在时等待其创建，创建后从头输出', async () => {
  const dir = mkdtempSync(join(tmpdir(), 'dev-tail-'));
  const { lines, handle } = tailer(join(dir, 'logs'), () => day(2026, 11, 8));
  try {
    await new Promise(r => setTimeout(r, 60));
    mkdirSync(join(dir, 'logs'));
    writeFileSync(join(dir, 'logs', 'webman-2026-11-08.log'), 'first\n');
    await waitFor(() => lines.length === 1);
    assert.deepEqual(lines, ['first']);
  } finally { handle.stop(); }
});

test('文件被截断或替换后从头继续读取', async () => {
  const dir = mkdtempSync(join(tmpdir(), 'dev-tail-'));
  const file = join(dir, 'webman-2026-11-08.log');
  writeFileSync(file, 'a much longer historical line\n');
  const { lines, handle } = tailer(dir, () => day(2026, 11, 8));
  try {
    await new Promise(r => setTimeout(r, 60));
    truncateSync(file, 0);
    await new Promise(r => setTimeout(r, 60));
    appendFileSync(file, 'after-truncate\n');
    await waitFor(() => lines.length === 1);
    assert.deepEqual(lines, ['after-truncate']);
  } finally { handle.stop(); }
});

test('日期变化时切换到新一天的日志文件，旧文件不再跟随', async () => {
  const dir = mkdtempSync(join(tmpdir(), 'dev-tail-'));
  let today = day(2026, 11, 8);
  const oldFile = join(dir, 'webman-2026-11-08.log');
  writeFileSync(oldFile, 'history\n');
  const { lines, handle } = tailer(dir, () => today);
  try {
    await new Promise(r => setTimeout(r, 60));
    appendFileSync(oldFile, 'day-1\n');
    await waitFor(() => lines.length === 1);
    today = day(2026, 11, 9);
    writeFileSync(join(dir, 'webman-2026-11-09.log'), 'day-2 first\n');
    await waitFor(() => lines.length === 2);
    appendFileSync(oldFile, 'late write to old day\n');
    appendFileSync(join(dir, 'webman-2026-11-09.log'), 'day-2 second\n');
    await waitFor(() => lines.length === 3);
    await new Promise(r => setTimeout(r, 60));
    assert.deepEqual(lines, ['day-1', 'day-2 first', 'day-2 second']);
  } finally { handle.stop(); }
});

test('启动脚本以 [后端日志] 前缀输出后端日志新增内容，DEV_BACKEND_LOGS=0 时关闭', async () => {
  for (const enabled of [true, false]) {
    const logDir = mkdtempSync(join(tmpdir(), 'dev-logs-'));
    const file = join(logDir, dailyLogName(new Date()));
    writeFileSync(file, 'old history line\n');
    const log = join(mkdtempSync(join(tmpdir(), 'dev-log-')), 'calls.log');
    const bin = fakeBin(log);
    const backend = http.createServer((_, res) => res.end('ok')).listen(0, '127.0.0.1');
    await new Promise(r => backend.once('listening', r));
    const env = { PATH: `${bin}:${process.env.PATH}`, DEV_BACKEND_URL: `http://127.0.0.1:${backend.address().port}`, DEV_FRONTEND_PORT: String(await freePort()), DEV_BACKEND_LOG_DIR: logDir };
    if (!enabled) env.DEV_BACKEND_LOGS = '0';
    const result = await run(env, '前端已启动', 8000, () => appendFileSync(file, '[2026-11-08 13:00:00] default.ERROR: boom\n'));
    backend.close();
    assert.doesNotMatch(result.out, /old history line/, '不输出历史日志');
    if (enabled) assert.match(result.out, /\[后端日志\].*default\.ERROR: boom/);
    else assert.doesNotMatch(result.out, /后端日志|boom/);
  }
});
