import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

function source(path: string) {
  return readFileSync(resolve(process.cwd(), path), 'utf8');
}

describe('配置 JSON 字段类型契约', () => {
  it('前端兜底类型包含 JSON', () => {
    const dialog = source('src/views/system/config/components/ConfigFormDialog.vue');
    expect(dialog).toContain("{ name: 'json', title: 'JSON', requiresOptions: false }");
  });

  it('Mock 配置类型包含 JSON', () => {
    expect(source('src/mock/modules/config.ts')).toContain("['json', 'JSON', false]");
  });

  it('JSON 配置值使用多行编辑器', () => {
    const dialog = source('src/views/system/config/components/ConfigValueDialog.vue');
    expect(dialog).toContain("['textarea', 'array', 'json', 'editor']");
  });

  it('后端提供内置 JSON 类型并校验格式', () => {
    const controller = source('../app/backend/controller/SystemConfig.php');
    expect(controller).toContain("'json' => ['title' => 'JSON', 'requiresOptions' => false]");
    expect(controller).toContain("if ($type === 'json' && $value !== '')");
    expect(controller).toContain("json_decode($value, true)");
  });
});
