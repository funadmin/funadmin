import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { extname, resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const root = resolve(process.cwd(), '..');
const read = (path: string) => readFileSync(resolve(root, path), 'utf8');
const phpFiles = (directory: string): string[] => readdirSync(resolve(root, directory), { withFileTypes: true }).flatMap((entry) => {
  const path = `${directory}/${entry.name}`;
  return entry.isDirectory() ? phpFiles(path) : extname(entry.name) === '.php' ? [path] : [];
});

describe('M7 Laravel 字段与旧入口收缩契约', () => {
  it('管理应用从 backend 完整硬切为 console', () => {
    expect(existsSync(resolve(root, 'app/backend'))).toBe(false);
    expect(existsSync(resolve(root, 'app/console/controller/auth/AdminAuth.php'))).toBe(true);
    const rootRoute = read('route/app.php');
    expect(rootRoute).toContain("root_path('app/console/controller')");
    expect(rootRoute).toContain("'namespace' => 'app\\\\console\\\\controller'");
    expect(rootRoute).toContain("'name' => 'console'");
    expect(read('admin-web/src/config/index.ts')).toContain("baseApi: env.VITE_APP_BASE_API || '/console'");
    const productionPhp = ['app', 'config', 'extend', 'route'].flatMap(phpFiles);
    for (const file of productionPhp) {
      expect(read(file), file).not.toContain('app\\\\backend\\\\');
    }
    const cutover = read('database/migrations/050_backend_to_console_cutover.sql');
    expect(cutover).toContain("SET `module` = 'console'");
    expect(cutover).toContain("REPLACE(`code`, 'backend/', 'console/')");
    expect(cutover).toContain("REPLACE(`obj`, 'backend/', 'console/')");
    expect(cutover).toContain("REPLACE(`v2`, 'backend/', 'console/')");
    expect(cutover).toContain('`rule_hash` = SHA2(');
  });

  it('业务 PHP 不读写 legacy 公共字段', () => {
    const allowed = new Set([
      'app/common/crud/DefinitionValidator.php',
      'app/common/crud/FieldInference.php',
      'app/common/plugin/marketplace/LegacyCloudMarketplaceAdapter.php',
      'app/common/service/MaintenanceContractService.php',
    ]);
    for (const file of ['app', 'config', 'extend', 'plugins'].flatMap(phpFiles).filter((path) => !allowed.has(path))) {
      expect(read(file), file).not.toMatch(/\b(create_time|update_time|delete_time)\b/);
    }
  });

  it('模型基类明确使用 bigint id 与 Laravel 时间字段', () => {
    const model = read('app/common/model/BaseModel.php');
    expect(model).toContain("'keyType' => 'int'");
    expect(model).toContain("'createTime' => 'created_at'");
    expect(model).toContain("'updateTime' => 'updated_at'");
    expect(model).toContain("'deleteTime' => 'deleted_at'");
  });

  it('M8 删除全部旧后台入口与认证兼容层', () => {
    for (const file of [
      'app/console/controller/Index.php',
      'app/console/controller/Login.php',
      'app/console/controller/Ajax.php',
      'app/console/controller/Error.php',
      'app/console/middleware/CheckRole.php',
      'app/console/service/AuthService.php',
    ]) expect(existsSync(resolve(root, file)), file).toBe(false);
    expect(read('app/console/controller/auth/AdminAuth.php')).toContain('AdminAuthorizationService');
  });

  it('插件菜单与权限仅从 adminWeb 契约注册', () => {
    const pluginService = read('app/console/service/PluginService.php');
    const infrastructure = read('app/console/service/PluginInfrastructureService.php');
    expect(pluginService).toContain("$manifestData['adminWeb']['permissions']");
    expect(pluginService).toContain("$manifestData['adminWeb']['menu']");
    expect(pluginService).not.toContain("$manifestData['permissions']");
    expect(pluginService).not.toContain("$manifestData['menus']");
    expect(infrastructure).not.toContain("'admin_web'");
    expect(infrastructure).toMatch(/registerTree\([^;]*'plugin'/s);
  });

  it('普通 MigrationService 只扫描 migrations 根目录且 contract drop 仅存在于 maintenance', () => {
    const service = read('app/common/service/MigrationService.php');
    expect(service).toContain("DIRECTORY_SEPARATOR . '*.sql'");
    expect(service).not.toMatch(/maintenance/i);
    expect(existsSync(resolve(root, 'database/migrations/034_drop_legacy_time_columns.sql'))).toBe(false);
    expect(existsSync(resolve(root, 'database/maintenance/001_drop_legacy_time_columns.sql'))).toBe(true);
    expect(read('database/maintenance/001_drop_legacy_time_columns.sql')).toMatch(/DROP COLUMN/i);
    expect(read('database/migrations/039_plugin_resource_schema_convergence.sql')).not.toMatch(/DROP\s+(?:COLUMN|TABLE)/i);
  });
});
