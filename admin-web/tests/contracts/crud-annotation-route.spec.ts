import { describe, expect, it } from 'vitest';
import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const adminRoot = resolve(import.meta.dirname, '../..');
const projectRoot = resolve(adminRoot, '..');
const legacyGeneratorPath = resolve(adminRoot, 'scripts/crud-gen.mjs');
const phpGenerator = readFileSync(resolve(projectRoot, 'app/common/crud/CrudGenerator.php'), 'utf8');
const productionTemplateContext = readFileSync(resolve(projectRoot, 'app/common/crud/ProductionTemplateContext.php'), 'utf8');
const workbenchView = readFileSync(resolve(adminRoot, 'src/views/development/crud/index.vue'), 'utf8');
const crudTypes = readFileSync(resolve(adminRoot, 'src/types/development/crud.ts'), 'utf8');
const definitionValidator = readFileSync(resolve(projectRoot, 'app/common/crud/DefinitionValidator.php'), 'utf8');
const definitionSchema = JSON.parse(readFileSync(resolve(projectRoot, 'app/common/crud/schema/crud-definition-v1.schema.json'), 'utf8'));
const routeRegistryTemplate = resolve(projectRoot, 'app/common/crud/templates/v1/console/route-registry.php.tpl');
const backendTemplate = readFileSync(
  resolve(projectRoot, 'app/common/crud/templates/v1/console/controller.php.tpl'),
  'utf8'
);
const composer = JSON.parse(readFileSync(resolve(projectRoot, 'composer.json'), 'utf8'));
const consoleRoutes = readFileSync(resolve(projectRoot, 'app/console/route/app.php'), 'utf8');
const devCrudController = readFileSync(resolve(projectRoot, 'app/console/controller/development/DevCrud.php'), 'utf8');
const funadminConfig = readFileSync(resolve(projectRoot, 'config/funadmin.php'), 'utf8');
const crudTrait = readFileSync(resolve(projectRoot, 'app/common/traits/Crud.php'), 'utf8');
const legacyBackendPath = resolve(projectRoot, 'app/common/controller/Backend.php');
const adminApiBase = readFileSync(resolve(projectRoot, 'app/console/controller/base/AdminApiController.php'), 'utf8');
const memberGroupController = readFileSync(resolve(projectRoot, 'app/console/controller/system/SystemMemberGroup.php'), 'utf8');
const memberLevelController = readFileSync(resolve(projectRoot, 'app/console/controller/system/SystemMemberLevel.php'), 'utf8');
const menuEncodingMigration = readFileSync(resolve(projectRoot, 'database/migrations/038_crud_menu_encoding.sql'), 'utf8');

describe('统一 PHP CRUD Core 契约', () => {
  it('安装官方 ThinkPHP 8 注解扩展', () => {
    expect(composer.require['topthink/think-annotation']).toBe('^3.0');
  });

  it('PHP Core 是唯一权威实现且后台模板生成 Attribute 路由', () => {
    expect(phpGenerator).toContain('final class CrudGenerator');
    expect(phpGenerator).toContain('new GenerationPlanner');
    expect(backendTemplate.trim()).toBe('{{controllerContent}}');
    for (const dependency of [
      'use app\\\\common\\\\traits\\\\Crud;',
      'use think\\\\annotation\\\\route\\\\Delete;',
      'use think\\\\annotation\\\\route\\\\Get;',
      'use think\\\\annotation\\\\route\\\\Group;',
      'use think\\\\annotation\\\\route\\\\Pattern;',
      'use think\\\\annotation\\\\route\\\\Post;',
      'use think\\\\annotation\\\\route\\\\Put;',
      'use think\\\\Response;'
    ]) expect(productionTemplateContext).toContain(dependency);
    expect(productionTemplateContext).toContain("#[Group('");
    expect(productionTemplateContext).toContain('Controller extends AdminApiController');
    for (const alias of [
      'crudIndex', 'crudDetail', 'crudCreate', 'crudUpdate', 'crudStatus', 'crudRemove',
      'crudRestoreOne', 'crudDestroyOne', 'crudRecycle', 'crudRestoreMany', 'crudDestroyMany',
      'crudImport', 'crudExport', 'crudUnscopedBaseQuery'
    ]) {
      expect(productionTemplateContext).toContain(`as private ${alias};`);
    }
    for (const route of ["#[Get('')]", "#[Get(':id')]", "#[Post('')]", "#[Put(':id')]", "#[Post(':id/status')]", "#[Delete('')]", "#[Post('restore')]", "#[Delete('destroy')]", "#[Post('import')]", "#[Get('export')]"]) {
      expect(productionTemplateContext).toContain(route);
    }
    for (const [route, action] of [
      ["#[Get(':id')]", 'detail'],
      ["#[Put(':id')]", 'update'],
      ["#[Post(':id/status')]", 'status'],
      ["#[Delete(':id')]", 'remove'],
      ["#[Post(':id/restore')]", 'restore'],
      ["#[Delete(':id/destroy')]", 'destroy']
    ]) {
      expect(productionTemplateContext).toContain(`${route}\\n    #[Pattern('id', '[A-Za-z0-9_-]+')]\\n    public function ${action}`);
    }
    expect(productionTemplateContext).not.toContain('Route::');
  });

  it('CRUD Definition 顶层同时声明 API 前缀与页面路由', () => {
    for (const field of ['apiPrefix', 'routePath']) {
      expect(definitionSchema.required).toContain(field);
      expect(definitionSchema.properties[field]).toBeDefined();
      expect(crudTypes).toMatch(new RegExp(`CrudDefinition[\\s\\S]*?\\b${field}: string`));
      expect(definitionValidator).toMatch(new RegExp(`ROOT_KEYS[\\s\\S]*?'${field}'`));
    }
    expect(productionTemplateContext).toContain("$data['apiPrefix']");
    expect(productionTemplateContext).toContain("$data['routePath']");
  });

  it('CRUD Definition 与模板上下文生成 PHP 和 Vitest 测试制品', () => {
    for (const artifact of ['phpTest', 'vitestTest']) {
      expect(definitionSchema.$defs.artifactNames.enum).toContain(artifact);
      expect(definitionSchema.$defs.artifacts.required).toContain(artifact);
      expect(crudTypes).toMatch(new RegExp(`CrudArtifactMap[\\s\\S]*?\\b${artifact}\\??:`));
      expect(definitionValidator).toMatch(new RegExp(`ARTIFACT_KEYS[\\s\\S]*?'${artifact}'`));
    }
    expect(productionTemplateContext).toContain("'phpTestContent'");
    expect(productionTemplateContext).toContain("'vitestTestContent'");
  });

  it('CRUD Definition 与模板上下文彻底移除独立 route 制品', () => {
    expect(definitionValidator).not.toMatch(/ARTIFACT_KEYS[\s\S]*?'route'/);
    expect(definitionSchema.$defs.artifactNames.enum).not.toContain('route');
    expect(definitionSchema.$defs.artifacts.required).not.toContain('route');
    expect(definitionSchema.$defs.templates.required).not.toContain('route');
    expect(productionTemplateContext).not.toContain("'routeContent'");
    expect(productionTemplateContext).not.toContain('function routeRegistry(');
    expect(workbenchView).not.toMatch(/\broute:\s*`app\/console\/route/);
    expect(workbenchView).not.toContain("route: 'console/route-registry.php.tpl'");
    expect(crudTypes).toContain('CrudArtifactMap');
    expect(crudTypes).not.toMatch(/CrudArtifactMap[\s\S]*?\broute\??:/);
    expect(existsSync(routeRegistryTemplate)).toBe(false);
  });

  it('新 API Crud 统一 REST 动作且由简单模块显式接入', () => {
    for (const action of ['index', 'detail', 'create', 'update', 'status', 'recycle', 'restore', 'destroy', 'import', 'export']) {
      expect(crudTrait).toMatch(new RegExp(`public function ${action}\\s*\\([^)]*\\): Response`));
    }
    expect(crudTrait).not.toContain('buildParames');
    expect(crudTrait).not.toContain('$modelClass');
    expect(crudTrait).not.toMatch(/function model\s*\(/);
    expect(existsSync(legacyBackendPath)).toBe(false);
    expect(adminApiBase).not.toContain('use Crud;');
    expect(memberGroupController).toMatch(/use Crud\s*\{/);
    expect(memberGroupController).toContain('protected string $model = MemberGroup::class;');
    expect(memberLevelController).toMatch(/use Crud\s*\{/);
    expect(memberLevelController).toContain('protected string $model = MemberLevel::class;');
  });

  it('Node CRUD 生成入口已删除', () => {
    expect(existsSync(legacyGeneratorPath)).toBe(false);
  });

  it('使用固定 UTF-8 字节修复 CRUD Workbench 菜单历史乱码', () => {
    expect(menuEncodingMigration).toContain('CONVERT(0xE5BC80E58F91E5B7A5E585B7 USING utf8mb4)');
    expect(menuEncodingMigration).toContain("`source_name` = 'development_tools'");
    expect(menuEncodingMigration).toContain('CONVERT(0x43525544E7949FE68890E599A8 USING utf8mb4)');
    expect(menuEncodingMigration).toContain("`source_name` = 'development_crud'");
  });

  it('DevCrud 的 8 个端点使用官方 Attribute 且保留 URL、HTTP 与安全中间件', () => {
    expect(devCrudController).toContain("#[Group('development/crud')]");
    for (const route of [
      "#[Get('connections')]",
      "#[Get('tables')]",
      "#[Get('tables/:table/schema')]",
      "#[Post('infer')]",
      "#[Post('definitions/validate')]",
      "#[Post('preview')]",
      "#[Post('generate')]",
      "#[Get('generations/:id')]"
    ]) expect(devCrudController).toContain(route);
    expect(devCrudController).toContain("#[Pattern('table', '[a-z_][a-z0-9_]*')]");
    expect(devCrudController).toContain("#[Pattern('id', '\\\\d+')]");
    expect(devCrudController).toContain('function validateDefinition(');
    expect(devCrudController).not.toContain('function validate(');
    expect(funadminConfig).toContain("'development/crud/validate' => 'devcrud/validate'");
    expect(consoleRoutes).not.toContain('development/crud/');
    for (const middleware of ['CheckAdminApiRole::class', 'CheckAdminApiCsrf::class', 'SystemLog::class']) {
      expect(devCrudController).toContain(middleware);
    }
  });
});
