import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const source = readFileSync(resolve(process.cwd(), 'src/views/system/menu/components/MenuFormDialog.vue'), 'utf8');

describe('菜单权限资源绑定契约', () => {
  it('可从已启用路由或能力权限资源中选择并提交 permissionId', () => {
    expect(source).toContain('menuApi.permissionOptions()');
    expect(source).toContain("['route', 'capability'].includes(item.resourceType)");
    expect(source).toContain('v-model="form.permissionId"');
    expect(source).toContain('permissionOptions');
    expect(source).toContain(':value="option.id"');
    expect(source).not.toContain('v-model="form.permission"');
    expect(source).not.toContain('placeholder="如 system:user:add"');
  });
});
