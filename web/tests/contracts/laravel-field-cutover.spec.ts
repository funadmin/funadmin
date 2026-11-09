import { readFileSync, readdirSync, statSync } from 'node:fs';
import { extname, resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const root = resolve(process.cwd(), '..');

const runtimeDirectories = ['admin/app/admin', 'admin/app/common', 'admin/app/api'];
const compatibilityFiles = new Set([
  'admin/app/common/model/BaseModel.php',
  'admin/app/common/model/concern/LaravelSoftDelete.php',
  'admin/app/common/service/mcp/McpService.php',
  'admin/app/common/traits/Crud.php',
]);
const phpFiles = (directory: string): string[] => readdirSync(resolve(root, directory)).flatMap((name) => {
  const relative = `${directory}/${name}`;
  const absolute = resolve(root, relative);
  return statSync(absolute).isDirectory() ? phpFiles(relative) : (extname(name) === '.php' ? [relative] : []);
});

const forbiddenRuntimeReferences = [
  /->(?:where|whereNull|whereOr|order|field|column)\([^\n]*(?:create_time|update_time|delete_time)/,
  /->(?:where|whereNull|whereOr|order|field|column)\(\s*['"]sort['"]/,
  /->(?:create_time|update_time|delete_time)\b/,
  /['"](?:create_time|update_time|delete_time)['"]\s*=>/,
];

describe('Laravel 公共字段 cutover 契约', () => {
  it('运行时代码不再直接查询或序列化旧公共时间字段', () => {
    const files = runtimeDirectories.flatMap(phpFiles).filter((path) => !compatibilityFiles.has(path));
    for (const file of files) {
      const source = readFileSync(resolve(root, file), 'utf8');
      for (const pattern of forbiddenRuntimeReferences) {
        expect(source, file).not.toMatch(pattern);
      }
    }
  });

  it('前端插件历史契约使用 camelCase 时间字段', () => {
    const api = readFileSync(resolve(root, 'web/src/admin/api/system/plugin.ts'), 'utf8');
    const drawer = readFileSync(resolve(root, 'web/src/admin/views/system/plugin/components/PluginHistoryDrawer.vue'), 'utf8');
    expect(api).toContain('createdAt: string');
    expect(api).not.toContain('create_time:');
    expect(drawer).toContain('prop="createdAt"');
  });
});
