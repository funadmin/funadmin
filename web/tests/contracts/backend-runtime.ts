import { spawnSync } from 'node:child_process';
import { resolve } from 'node:path';

const backendRoot = resolve(process.cwd(), '../admin');

/** Execute contracts against the migrated runtime, never the developer's database. */
export function runBackendPhp(args: string[]): string {
  const result = spawnSync('php', ['-d', 'display_errors=stderr', ...args], {
    cwd: backendRoot,
    encoding: 'utf8',
    timeout: 60_000,
    maxBuffer: 4 * 1024 * 1024,
    env: { ...process.env, TEST_MYSQL: '0', DB_CONNECTION: 'sqlite', DB_DATABASE: ':memory:' }
  });
  if (result.error || result.status !== 0) {
    throw new Error(`Backend contract failed (${result.status}): ${result.error ?? ''}\n${result.stdout}\n${result.stderr}`);
  }
  return result.stdout;
}

export function backendJson<T = unknown>(source: string): T {
  return JSON.parse(runBackendPhp(['-r', `require 'tests/bootstrap.php'; ${source}`])) as T;
}

/** Reuse SQLite execution fixtures rather than reconstructing framework internals in JS. */
export function runBackendTests(file: string, filter?: string): string {
  const output = runBackendPhp([
    'vendor/bin/phpunit', '--do-not-record-test-run-history', '--colors=never',
    ...(filter ? ['--filter', filter] : []), `tests/${file}`
  ]);
  if (!/OK \(\d+ tests?, \d+ assertions?\)/.test(output)) {
    throw new Error(`Backend contract did not execute a passing test: ${output}`);
  }
  return output;
}
