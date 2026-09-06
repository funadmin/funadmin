import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const crudSource = readFileSync(resolve(process.cwd(), '../app/common/traits/Crud.php'), 'utf8');
const groupController = readFileSync(resolve(process.cwd(), '../app/console/controller/system/SystemMemberGroup.php'), 'utf8');
const levelController = readFileSync(resolve(process.cwd(), '../app/console/controller/system/SystemMemberLevel.php'), 'utf8');
const groupApi = readFileSync(resolve(process.cwd(), 'src/api/system/memberGroup.ts'), 'utf8');
const levelApi = readFileSync(resolve(process.cwd(), 'src/api/system/memberLevel.ts'), 'utf8');
const authController = readFileSync(resolve(process.cwd(), '../app/console/controller/auth/AdminAuth.php'), 'utf8');
const permissionMigrationPath = resolve(process.cwd(), '../database/migrations/036_crud_import_permissions.sql');
const permissionMigration = existsSync(permissionMigrationPath) ? readFileSync(permissionMigrationPath, 'utf8') : '';
const groupPage = readFileSync(resolve(process.cwd(), 'src/views/system/member-group/index.vue'), 'utf8');
const levelPage = readFileSync(resolve(process.cwd(), 'src/views/system/member-level/index.vue'), 'utf8');

describe('CRUD 模型类公共解析契约', () => {
  it('受保护扩展点使用职责名称，不保留 crud 前缀', () => {
    expect(crudSource).not.toMatch(/protected function crud[A-Z]/);
    for (const hook of [
      'payload', 'validatePayload', 'transformData', 'resourceName', 'searchFields',
      'exactFilters', 'rangeFilters', 'sortFields', 'primaryKey', 'primaryKeyType',
      'primaryKeyPattern', 'query', 'baseQuery', 'order', 'importFields',
      'exportFields', 'importPayload', 'importLimit', 'exportLimit', 'beforeDelete',
      'afterSave', 'applyFilters', 'mapImportRow'
    ]) {
      expect(crudSource).toMatch(new RegExp(`protected function ${hook}\\s*\\(`));
    }
  });

  it('统一直接通过 model 属性访问模型类', () => {
    expect(crudSource).toContain('($this->model)::');
    expect(crudSource).not.toContain('$modelClass');
    expect(crudSource).not.toMatch(/function\s+(?:model|crudModelClass)\s*\(/);
  });

  it('业务控制器只声明模型属性，不重复实现模型返回函数', () => {
    expect(groupController).toContain('protected string $model = MemberGroup::class;');
    expect(levelController).toContain('protected string $model = MemberLevel::class;');
    expect(groupController).not.toMatch(/function\s+(?:model|crudModelClass)\s*\(/);
    expect(levelController).not.toMatch(/function\s+(?:model|crudModelClass)\s*\(/);
  });

  it('公共查询构造支持白名单搜索、等值、区间和排序', () => {
    expect(crudSource).toContain('protected function rangeFilters(): array');
    expect(crudSource).toContain('protected function sortFields(): array');
    expect(crudSource).toContain('protected function applyFilters(');
    expect(crudSource).toContain('private function crudApplyOrder(');
    expect(crudSource).toContain("$this->request->get('sort', '')");
    expect(crudSource).toContain("$this->request->get('order', 'asc')");
  });

  it('公共导入导出通过字段映射限制数据边界', () => {
    expect(crudSource).toContain('protected function importFields(): array');
    expect(crudSource).toContain('protected function exportFields(): array');
    expect(crudSource).toContain('protected function mapImportRow(array $row): array');
    expect(crudSource).toContain('private function crudExportData(Model $model): array');
    expect(groupController).toContain("'name' => 'name'");
    expect(levelController).toContain("'sort' => 'sort_order'");
  });

  it('会员组与会员等级开放 REST 导入路由和 API', () => {
    expect(groupController).toContain("#[Group('system/member-group')]");
    expect(groupController).toMatch(/#\[Post\('import'\)\]\s+public function import/);
    expect(groupController).toMatch(/#\[Get\('export'\)\]\s+public function export/);
    expect(levelController).toContain("#[Group('system/member-level')]");
    expect(levelController).toMatch(/#\[Post\('import'\)\]\s+public function import/);
    expect(levelController).toMatch(/#\[Get\('export'\)\]\s+public function export/);
    expect(groupApi).toContain('list: (params: MemberGroupQuery) => http.get<API.PageResult<MemberGroupModel>>(PREFIX, params)');
    expect(levelApi).toContain('list: (params: MemberLevelQuery) => http.get<API.PageResult<MemberLevelModel>>(PREFIX, params)');
    expect(groupApi).toContain('importRows: (rows:');
    expect(levelApi).toContain('importRows: (rows:');
    expect(authController).toContain("'backend/systemmembergroup:import' => 'system:member-group:import'");
    expect(authController).toContain("'backend/systemmemberlevel:import' => 'system:member-level:import'");
    expect(permissionMigration).toContain('backend/systemmembergroup:import');
    expect(permissionMigration).toContain('backend/systemmemberlevel:import');
    expect(groupPage).toContain("v-perm=\"'system:member-group:import'\"");
    expect(levelPage).toContain("v-perm=\"'system:member-level:import'\"");
  });
});
