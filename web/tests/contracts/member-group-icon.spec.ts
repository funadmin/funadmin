import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const read = (relativePath: string) => readFileSync(resolve(process.cwd(), relativePath), 'utf8');

describe('会员组图标字段', () => {
  it('后端接收、校验并返回 icon', () => {
    const controller = read('../admin/app/admin/system/controller/SystemMemberGroup.php');

    expect(controller).toMatch(/post\('icon'/);
    expect(controller).toContain("'icon' =>");
    expect(controller).toContain('会员组图标不能超过 50 个字符');
  });

  it('前端弹窗提供图标选择且列表展示图标', () => {
    const dialog = read('src/admin/views/system/member-group/components/MemberGroupFormDialog.vue');
    const list = read('src/admin/views/system/member-group/index.vue');
    const api = read('src/admin/api/system/memberGroup.ts');

    expect(dialog).toContain('IconSelect');
    expect(dialog).toContain('form.icon');
    expect(list).toMatch(/row\.icon/);
    expect(api).toContain('icon: string');
  });
});
