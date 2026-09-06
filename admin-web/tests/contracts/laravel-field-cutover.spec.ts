import { readFileSync, readdirSync, statSync } from 'node:fs';
import { extname, resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const root = resolve(process.cwd(), '..');
const migrationDir = resolve(root, 'database/migrations');
const migrations = readdirSync(migrationDir).filter((name) => name.endsWith('.sql')).sort();
const cutoverName = migrations.find((name) => name.endsWith('_laravel_field_cutover.sql')) ?? '';
const cutover = cutoverName ? readFileSync(resolve(migrationDir, cutoverName), 'utf8') : '';
const migrationService = readFileSync(resolve(root, 'app/common/service/MigrationService.php'), 'utf8');

const runtimeDirectories = ['app/console', 'app/common', 'app/api', 'extend/fun'];
const compatibilityFiles = new Set([
  'app/common/model/BaseModel.php',
  'app/common/model/concern/LaravelSoftDelete.php',
  'app/common/service/McpService.php',
  'app/common/traits/Crud.php',
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
  it('cutover 使用大于 021 的唯一编号', () => {
    const versions = migrations.map((name) => name.match(/^(\d+)_/)?.[1] ?? '');
    expect(versions).not.toContain('');
    expect(new Set(versions).size).toBe(versions.length);
    expect(Number(cutoverName.slice(0, 3))).toBeGreaterThan(21);
    expect(migrations).toContain('021_time_columns_no_default.sql');
    expect(migrations).toContain('022_laravel_field_cutover.sql');
    expect(migrationService).toContain('migrationSortKey');
  });

  it('时间列使用 nullable datetime，0 回填 NULL，正 Unix 秒显式转换', () => {
    expect(cutover).toMatch(/ADD COLUMN `created_at` datetime NULL/i);
    expect(cutover).toMatch(/ADD COLUMN `updated_at` datetime NULL/i);
    expect(cutover).toMatch(/ADD COLUMN `deleted_at` datetime NULL/i);
    expect(cutover).toMatch(/NULLIF\(`create_time`,\s*0\)/i);
    expect(cutover).toMatch(/FROM_UNIXTIME\(NULLIF\(`create_time`,\s*0\)\)/i);
    expect(cutover).toMatch(/FROM_UNIXTIME\(NULLIF\(`delete_time`,\s*0\)\)/i);
  });

  it('保留旧列、迁移排序字段并为软删除与排序查询补充索引', () => {
    expect(cutover).not.toMatch(/\bDROP\s+(?:COLUMN|TABLE)\b/i);
    expect(cutover).toMatch(/ADD COLUMN `sort_order`/i);
    expect(cutover).toMatch(/`sort_order`\s*=\s*`sort`/i);
    expect(cutover).toMatch(/ADD (?:KEY|INDEX) `[^`]*(?:deleted|sort)[^`]*`/i);
    expect(cutover).toContain('information_schema.COLUMNS');
    expect(cutover).toContain('information_schema.STATISTICS');
  });

  it('每个新增列都有独立守卫，可从部分执行状态安全恢复', () => {
    const additionLines = cutover.split('\n').filter((line) => line.includes('ADD COLUMN'));
    for (const line of additionLines) {
      const column = line.match(/ADD COLUMN `([^`]+)`/)?.[1] ?? '';
      expect(column).not.toBe('');
      expect(line).toContain('information_schema.COLUMNS');
      expect(line).toContain(`COLUMN_NAME='${column}'`);
    }
  });

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
    const api = readFileSync(resolve(root, 'admin-web/src/api/system/plugin.ts'), 'utf8');
    const drawer = readFileSync(resolve(root, 'admin-web/src/views/system/plugin/components/PluginHistoryDrawer.vue'), 'utf8');
    expect(api).toContain('createdAt: string');
    expect(api).not.toContain('create_time:');
    expect(drawer).toContain('prop="createdAt"');
  });
});
