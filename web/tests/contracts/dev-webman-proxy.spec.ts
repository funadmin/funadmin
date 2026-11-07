import { spawn, type ChildProcess } from 'node:child_process';
import { createServer, type Server } from 'node:http';
import { copyFile, mkdtemp, readFile, rm, symlink, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { expect, it } from 'vitest';

async function listen(server: Server): Promise<number> {
  await new Promise<void>(resolve => server.listen(0, '127.0.0.1', resolve));
  return (server.address() as { port: number }).port;
}

it('the default dev command proxies login initialization instead of returning SPA HTML', async () => {
  const webRoot = process.cwd();
  const root = await mkdtemp(join(tmpdir(), 'funadmin-dev-proxy-'));
  const backend = createServer((request, response) => {
    response.setHeader('Content-Type', 'application/json');
    response.end(JSON.stringify({ code: 200, data: { csrfToken: 'fixture-csrf', captchaEnabled: false, path: request.url } }));
  });
  let child: ChildProcess | undefined;
  try {
    const backendPort = await listen(backend);
    const portReservation = createServer();
    const frontendPort = await listen(portReservation);
    await new Promise<void>(resolve => portReservation.close(() => resolve()));
    await copyFile(join(webRoot, 'vite.config.ts'), join(root, 'vite.config.ts'));
    await symlink(join(webRoot, 'node_modules'), join(root, 'node_modules'), 'dir');
    await writeFile(join(root, 'package.json'), '{"type":"module"}');
    await writeFile(join(root, 'index.html'), '<html><body>frontend fallback</body></html>');
    await writeFile(join(root, '.env.webman'), `VITE_APP_PROXY_TARGET=http://127.0.0.1:${backendPort}\nVITE_APP_BASE_API=/admin\n`);
    const script = JSON.parse(await readFile(join(webRoot, 'package.json'), 'utf8')).scripts.dev as string;
    const [command, ...args] = script.trim().split(/\s+/);
    if (command !== 'vite') throw new Error('The integration fixture expects a Vite dev command');
    const env = Object.fromEntries(Object.entries(process.env).filter(([key]) => !key.startsWith('VITE_')));
    child = spawn(process.execPath, [resolve(webRoot, 'node_modules/vite/bin/vite.js'), ...args, '--host', '127.0.0.1', '--port', String(frontendPort), '--strictPort'], { cwd: root, env, stdio: ['ignore', 'pipe', 'pipe'] });
    await new Promise<void>((resolve, reject) => {
      let output = '';
      const timeout = setTimeout(() => reject(new Error(`Vite startup timed out: ${output}`)), 15_000);
      child!.stdout!.on('data', chunk => { output += chunk.toString(); if (/ready in/.test(output)) { clearTimeout(timeout); resolve(); } });
      child!.stderr!.on('data', chunk => { output += chunk.toString(); });
      child!.once('error', error => { clearTimeout(timeout); reject(error); });
      child!.once('exit', code => { clearTimeout(timeout); reject(new Error(`Vite exited ${code}: ${output}`)); });
    });
    const response = await fetch(`http://127.0.0.1:${frontendPort}/admin/auth/csrf`);
    expect(response.headers.get('content-type')).toContain('application/json');
    expect(await response.json()).toEqual({ code: 200, data: { csrfToken: 'fixture-csrf', captchaEnabled: false, path: '/admin/auth/csrf' } });
  } finally {
    if (child && child.exitCode === null) {
      const stopped = new Promise<void>(resolve => child!.once('exit', () => resolve()));
      child.kill('SIGTERM');
      await stopped;
    }
    backend.closeAllConnections();
    await new Promise<void>(resolve => backend.close(() => resolve()));
    await rm(root, { recursive: true, force: true });
  }
}, 25_000);
