import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { extname, resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { backendJson } from './backend-runtime';

const root = resolve(process.cwd(), '..');
const read = (path: string) => readFileSync(resolve(root, path), 'utf8');
const phpFiles = (directory: string): string[] => readdirSync(resolve(root, directory), { withFileTypes: true }).flatMap((entry) => {
  const path = `${directory}/${entry.name}`;
  return entry.isDirectory() ? phpFiles(path) : extname(entry.name) === '.php' ? [path] : [];
});

describe('M7 Laravel 字段与旧入口收缩契约', () => {
  it('管理应用从 backend 完整硬切为 admin', () => {
    expect(existsSync(resolve(root, 'admin/app/backend'))).toBe(false);
    expect(existsSync(resolve(root, 'admin/app/admin/authentication/controller/AdminAuth.php'))).toBe(true);
    const rootRoute = read('admin/config/route.php');
    expect(rootRoute).toContain('use Webman\\Route;');
    expect(rootRoute).toContain('Route::disableDefaultRoute();');
    const namespaces = backendJson<string[]>(String.raw`
      \Webman\Route::load([config_path()]);
      echo json_encode(array_values(array_unique(array_map(
          static fn ($route) => is_array($route->getCallback()) ? $route->getCallback()[0] : '',
          \Webman\Route::getRoutes()
      ))));
    `);
    expect(namespaces).toContain('app\\admin\\authentication\\controller\\AdminAuth');
    expect(namespaces.some((name) => name.startsWith('app\\backend\\'))).toBe(false);
    expect(read('web/src/shared/config/index.ts')).toContain("baseApi: env.VITE_APP_BASE_API || '/admin'");
    const productionPhp = ['admin/app', 'admin/config', 'admin/route', 'admin/plugins'].filter((directory) => existsSync(resolve(root, directory))).flatMap(phpFiles);
    for (const file of productionPhp) {
      expect(read(file), file).not.toContain('app\\\\backend\\\\');
    }
    const cutover = read('admin/database/core/migrations/archive/050_backend_to_console_cutover.sql');
    expect(cutover).toContain("SET `module` = 'console'");
    expect(cutover).toContain("REPLACE(`code`, 'backend/', 'console/')");
    expect(cutover).toContain("REPLACE(`obj`, 'backend/', 'console/')");
    expect(cutover).toContain("REPLACE(`v2`, 'backend/', 'console/')");
    expect(cutover).toContain('`rule_hash` = SHA2(');
  });

  it('业务 PHP 不读写 legacy 公共字段', () => {
    const allowed = new Set([
      'admin/app/common/crud/DefinitionValidator.php',
      'admin/app/common/crud/FieldInference.php',
      'admin/app/common/plugin/marketplace/LegacyCloudMarketplaceAdapter.php',
      'admin/app/admin/development/service/FormCrudDefinitionFactory.php',
    ]);
    for (const file of ['admin/app', 'admin/config', 'admin/route', 'admin/plugins'].filter((directory) => existsSync(resolve(root, directory))).flatMap(phpFiles).filter((path) => !allowed.has(path))) {
      expect(read(file), file).not.toMatch(/\b(create_time|update_time|delete_time)\b/);
    }
  });

  it('Eloquent 模型默认使用自增整型 id 与 Laravel 时间字段', () => {
    const metadata = backendJson(String.raw`
      $model = new class extends \app\common\model\BaseModel {
          use \app\common\model\concern\LaravelSoftDelete;
          protected $table = 'contract_models';
      };
      echo json_encode([
          'key' => $model->getKeyName(), 'keyType' => $model->getKeyType(),
          'incrementing' => $model->getIncrementing(), 'timestamps' => $model->usesTimestamps(),
          'createdAt' => $model->getCreatedAtColumn(), 'updatedAt' => $model->getUpdatedAtColumn(),
          'deletedAt' => $model->getDeletedAtColumn(), 'dateFormat' => $model->getDateFormat(),
          'sql' => $model->newQuery()->toSql(),
      ]);
    `);
    expect(metadata).toMatchObject({
      key: 'id', keyType: 'int', incrementing: true, timestamps: true,
      createdAt: 'created_at', updatedAt: 'updated_at', deletedAt: 'deleted_at', dateFormat: 'Y-m-d H:i:s'
    });
    expect((metadata as { sql: string }).sql).toMatch(/"[^"]*contract_models"\."deleted_at" is null/);
  });

  it('M8 删除全部旧后台入口与认证兼容层', () => {
    for (const file of [
      'admin/app/admin/controller/Index.php',
      'admin/app/admin/controller/Login.php',
      'admin/app/admin/controller/Ajax.php',
      'admin/app/admin/controller/Error.php',
      'admin/app/admin/middleware/CheckRole.php',
      'admin/app/admin/service/AuthService.php',
    ]) expect(existsSync(resolve(root, file)), file).toBe(false);
    expect(read('admin/app/admin/authentication/controller/AdminAuth.php')).not.toContain('AdminAuthorizationService');
  });

  it('插件菜单与权限仅从 adminWeb 契约注册', () => {
    const pluginService = read('admin/app/admin/plugins/service/PluginService.php');
    const infrastructure = read('admin/app/admin/plugins/service/PluginInfrastructureService.php');
    expect(pluginService).toContain("$manifestData['adminWeb']['permissions']");
    expect(pluginService).toContain("$manifestData['adminWeb']['menu']");
    expect(pluginService).not.toContain("$manifestData['permissions']");
    expect(pluginService).not.toContain("$manifestData['menus']");
    expect(infrastructure).not.toContain("'admin_web'");
    expect(infrastructure).toMatch(/registerTree\([^;]*'plugin'/s);
  });

  it('普通 MigrationService 不扫描 maintenance contract 且 legacy contract 已移除', () => {
    const service = read('admin/app/common/service/database/MigrationService.php');
    expect(service).toContain("DIRECTORY_SEPARATOR . '*.sql'");
    expect(service).not.toMatch(/maintenance/i);
    expect(existsSync(resolve(root, 'admin/database/maintenance/001_drop_legacy_time_columns.sql'))).toBe(false);
    expect(existsSync(resolve(root, 'admin/app/admin/common/command/MaintenanceContractMigrate.php'))).toBe(false);
    expect(existsSync(resolve(root, 'admin/app/common/service/MaintenanceContractService.php'))).toBe(false);
  });
});
