#!/usr/bin/env node
// 一键启动本地开发环境：webman 后端（admin/）+ Vite 前端（web/）。
//
// 用法（在项目根目录）：npm run dev
//
// - 后端已经在运行时直接复用，不重复启动，退出时也不会停止它。
// - 后端未运行时以前台模式启动 webman，Ctrl+C 时一并停止。
// - 前端使用 web/package.json 的 dev 脚本（webman 模式，接口代理到后端）。
// - 同时跟随 admin/runtime/logs/webman-<当天>.log（应用日志不在 stdout 上），以 [后端日志] 前缀输出；
//   DEV_BACKEND_LOGS=0 关闭，DEV_BACKEND_LOG_DIR 可改日志目录。
import { spawn } from 'node:child_process';
import { existsSync, readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { tailDailyLog } from './log-tail.mjs';

const root = dirname(dirname(fileURLToPath(import.meta.url)));
const backendUrl = process.env.DEV_BACKEND_URL || 'http://127.0.0.1:8787';

// 前端端口与 Vite 一致：.env.webman.local 覆盖 .env.webman，缺省 5173。
function frontendPort() {
  if (process.env.DEV_FRONTEND_PORT) return Number(process.env.DEV_FRONTEND_PORT);
  let port = 5173;
  for (const name of ['.env.webman', '.env.webman.local']) {
    const file = join(root, 'web', name);
    if (!existsSync(file)) continue;
    const match = readFileSync(file, 'utf8').match(/^\s*VITE_APP_PORT\s*=\s*["']?(\d+)/m);
    if (match) port = Number(match[1]);
  }
  return port;
}
const frontendUrl = `http://localhost:${frontendPort()}/`;
const children = [];
let stopping = false;

const label = (name, color) => `\x1b[${color}m[${name}]\x1b[0m`;
const BACKEND = label('后端', 36);
const FRONTEND = label('前端', 35);
const BACKEND_LOG = label('后端日志', 33);

function pipe(child, prefix, onLine) {
  for (const stream of [child.stdout, child.stderr]) {
    let buffer = '';
    stream.on('data', chunk => {
      buffer += chunk;
      const lines = buffer.split('\n');
      buffer = lines.pop();
      for (const line of lines) {
        if (line.trim() === '') continue;
        console.log(`${prefix} ${line}`);
        onLine?.(line);
      }
    });
  }
}

function start(command, args, cwd, prefix, onLine) {
  const child = spawn(command, args, { cwd, stdio: ['ignore', 'pipe', 'pipe'], env: process.env });
  pipe(child, prefix, onLine);
  children.push(child);
  child.on('exit', code => {
    if (!stopping) {
      console.log(`${prefix} 已退出（code ${code ?? 'signal'}），正在停止其余进程`);
      shutdown(code ?? 1);
    }
  });
  return child;
}

async function reachable(url) {
  try {
    const response = await fetch(url, { signal: AbortSignal.timeout(1500) });
    return response.status > 0;
  } catch {
    return false;
  }
}
const backendUp = () => reachable(backendUrl);

async function waitForBackend(timeoutMs = 30000) {
  const deadline = Date.now() + timeoutMs;
  while (Date.now() < deadline) {
    if (await backendUp()) return true;
    await new Promise(resolve => setTimeout(resolve, 300));
  }
  return false;
}

async function shutdown(code = 0) {
  if (stopping) return;
  stopping = true;
  for (const child of children) {
    if (child.exitCode === null && child.signalCode === null) child.kill('SIGTERM');
  }
  // 给子进程一点时间做清理（webman 需要关闭 worker）。
  await new Promise(resolve => setTimeout(resolve, 800));
  for (const child of children) {
    if (child.exitCode === null && child.signalCode === null) child.kill('SIGKILL');
  }
  process.exit(code);
}

process.on('SIGINT', () => shutdown(0));
process.on('SIGTERM', () => shutdown(0));

// 后端日志从当前末尾开始跟随，不回放历史；复用已运行的后端时也能看到它的报错。
if (process.env.DEV_BACKEND_LOGS !== '0') {
  const dir = process.env.DEV_BACKEND_LOG_DIR || join(root, 'admin', 'runtime', 'logs');
  await tailDailyLog({ dir, onLine: line => console.log(`${BACKEND_LOG} ${line}`) }).ready;
}

if (await backendUp()) {
  console.log(`${BACKEND} 检测到 ${backendUrl} 已在运行，直接复用（退出时不会停止它）`);
} else {
  console.log(`${BACKEND} 启动 webman：php start.php start`);
  start('php', ['start.php', 'start'], join(root, 'admin'), BACKEND);
  if (!(await waitForBackend())) {
    console.error(`${BACKEND} 30 秒内没有在 ${backendUrl} 就绪，请查看上面的后端输出`);
    await shutdown(1);
  }
  console.log(`${BACKEND} 已就绪：${backendUrl}`);
}

if (await reachable(frontendUrl)) {
  // 已有 Vite 在该端口运行：再启动一个只会抢端口或换到别的端口，直接复用。
  console.log(`${FRONTEND} 检测到 ${frontendUrl} 已在运行，直接复用`);
  console.log(`前后端都已在运行，打开 ${frontendUrl}#/admin/login`);
  process.exit(0);
}
console.log(`${FRONTEND} 启动 Vite：npm run dev`);
start('npm', ['run', 'dev'], join(root, 'web'), FRONTEND);
console.log(`${FRONTEND} 前端已启动：${frontendUrl}#/admin/login（按 Ctrl+C 全部停止）`);
