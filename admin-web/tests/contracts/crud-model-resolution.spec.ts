import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const crudSource = readFileSync(resolve(process.cwd(), '../app/common/traits/Curd.php'), 'utf8');
const groupController = readFileSync(resolve(process.cwd(), '../app/backend/controller/system/SystemMemberGroup.php'), 'utf8');
const levelController = readFileSync(resolve(process.cwd(), '../app/backend/controller/system/SystemMemberLevel.php'), 'utf8');

describe('CRUD 模型类公共解析契约', () => {
  it('统一通过 crudModelClass 读取并校验模型类', () => {
    expect(crudSource).toContain('protected function crudModelClass(): string');
    expect(crudSource).toContain('is_subclass_of($modelClass, Model::class)');
    expect(crudSource).not.toContain('($this->model)::');
  });

  it('业务控制器只声明模型属性，不重复实现模型返回函数', () => {
    expect(groupController).toContain('protected string $model = MemberGroup::class;');
    expect(levelController).toContain('protected string $model = MemberLevel::class;');
    expect(groupController).not.toMatch(/function\s+(?:model|crudModelClass)\s*\(/);
    expect(levelController).not.toMatch(/function\s+(?:model|crudModelClass)\s*\(/);
  });
});
