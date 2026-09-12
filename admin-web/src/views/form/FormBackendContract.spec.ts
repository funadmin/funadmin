import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const projectRoot = resolve(process.cwd(), '..');
const formDataService = () => readFileSync(resolve(projectRoot, 'app/console/form/service/FormDataService.php'), 'utf8');

describe('表单后端领域契约', () => {
  it('Repeatable 子表与父表使用同连接事务并校验子行归属', () => {
    const source = formDataService();
    expect(source).toContain('$connection->transaction');
    expect(source).toContain('子表数据不属于当前父记录');
    expect(source).toContain("relation_type !== 'has_many'");
  });

  it('数据列表支持完整筛选操作符', () => {
    const source = formDataService();
    for (const filter of ['ne', 'not_like', 'starts_with', 'ends_with', 'gt', 'gte', 'lt', 'lte', 'in', 'not_in', 'is_null', 'not_null']) {
      expect(source).toContain(`'${filter}'`);
    }
  });
});
