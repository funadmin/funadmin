import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const adminRoot = resolve(import.meta.dirname, '../..');
const projectRoot = resolve(adminRoot, '..');
const legacyGenerator = readFileSync(resolve(adminRoot, 'scripts/crud-gen.mjs'), 'utf8');
const phpGenerator = readFileSync(resolve(projectRoot, 'app/common/crud/CrudGenerator.php'), 'utf8');
const backendTemplate = readFileSync(
  resolve(projectRoot, 'app/common/crud/templates/v1/backend/controller.php.tpl'),
  'utf8'
);
const composer = JSON.parse(readFileSync(resolve(projectRoot, 'composer.json'), 'utf8'));
const annotationConfig = readFileSync(resolve(projectRoot, 'config/annotation.php'), 'utf8');
const crudTrait = readFileSync(resolve(projectRoot, 'app/common/traits/Crud.php'), 'utf8');
const backendBase = readFileSync(resolve(projectRoot, 'app/common/controller/Backend.php'), 'utf8');
const adminApiBase = readFileSync(resolve(projectRoot, 'app/backend/controller/base/AdminApiController.php'), 'utf8');
const memberGroupController = readFileSync(resolve(projectRoot, 'app/backend/controller/system/SystemMemberGroup.php'), 'utf8');
const memberLevelController = readFileSync(resolve(projectRoot, 'app/backend/controller/system/SystemMemberLevel.php'), 'utf8');
const menuEncodingMigration = readFileSync(resolve(projectRoot, 'database/migrations/038_crud_menu_encoding.sql'), 'utf8');

describe('统一 PHP CRUD Core 契约', () => {
  it('安装官方 ThinkPHP 8 注解扩展', () => {
    expect(composer.require['topthink/think-annotation']).toBe('^3.0');
  });

  it('PHP Core 是唯一权威实现且模板继承 AdminApiController', () => {
    expect(phpGenerator).toContain('final class CrudGenerator');
    expect(phpGenerator).toContain('new GenerationPlanner');
    expect(backendTemplate).toContain('extends AdminApiController');
  });

  it('新 API Crud 统一 REST 动作且由简单模块显式接入', () => {
    for (const action of ['index', 'detail', 'create', 'update', 'status', 'recycle', 'restore', 'destroy', 'import', 'export']) {
      expect(crudTrait).toMatch(new RegExp(`public function ${action}\\s*\\([^)]*\\): Response`));
    }
    expect(crudTrait).not.toContain('buildParames');
    expect(crudTrait).not.toContain('$modelClass');
    expect(crudTrait).not.toMatch(/function model\s*\(/);
    expect(backendBase).not.toContain('use Crud;');
    expect(adminApiBase).not.toContain('use Crud;');
    expect(memberGroupController).toContain('use Crud;');
    expect(memberGroupController).toContain('protected string $model = MemberGroup::class;');
    expect(memberLevelController).toContain('use Crud;');
    expect(memberLevelController).toContain('protected string $model = MemberLevel::class;');
  });

  it('Node 入口明确弃用且不再生成文件', () => {
    expect(legacyGenerator).toContain('已弃用');
    expect(legacyGenerator).not.toContain('writeFile');
    expect(legacyGenerator).not.toContain('backendControllerSource');
  });

  it('修复 CRUD Workbench 菜单历史乱码', () => {
    expect(menuEncodingMigration).toContain("SET `name` = '开发工具'");
    expect(menuEncodingMigration).toContain("`source_name` = 'development_tools'");
    expect(menuEncodingMigration).toContain("SET `name` = 'CRUD生成器'");
    expect(menuEncodingMigration).toContain("`source_name` = 'development_crud'");
  });

  it('启用路由 Attribute 扫描', () => {
    expect(annotationConfig).toContain("'route'  => [");
    expect(annotationConfig).toContain("'enable'      => true");
  });
});
