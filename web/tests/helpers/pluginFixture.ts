import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';

/** Use the current scaffolder and schema validator instead of a deleted legacy plugin. */
export function scaffoldedPluginManifest() {
  const fixture = resolve(import.meta.dirname, '../../../admin/tests/fixtures/frontend-plugin.php');
  return JSON.parse(execFileSync(process.env.PHP_BINARY || 'php', [fixture], { encoding: 'utf8' }));
}
