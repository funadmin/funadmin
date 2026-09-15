import { describe, it, expect } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
describe('统一页面入口', () => {
  it.each(['data', 'published'])('%s 列表只使用公共表格与按钮', name => {
    const source = readFileSync(resolve(process.cwd(), `src/views/form/${name}.vue`), 'utf8');
    expect(source).toContain('<SchemaTablePage');
    expect(source).not.toContain('<DataTableShell');
    expect(source).toContain('<ListButtonBar');
    expect(source).toContain('buttonLock.busy');
    expect(source).toContain('formatFieldValue');
    expect(source).toContain('resolveFieldOptions');
  });

  it('published 详情复用共享展示并保持敏感字段过滤', () => {
    const source = readFileSync(resolve(process.cwd(), 'src/views/form/published.vue'), 'utf8');
    expect(source).toContain('presentField(field, detail?.row ?? {})');
    expect(source).not.toContain('detail.row[field.field_name] ?? \'-\'');
    expect(source).toContain("field.type !== 'password' && !field.control_props?.sensitive && !field.control_props?.writeOnly");
  });
});
