import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const read = (relativePath: string) => readFileSync(resolve(process.cwd(), relativePath), 'utf8');

describe('会员组图标字段', () => {
  it('migration 为会员组表新增 icon 列', () => {
    const sql = read('../database/migrations/019_member_group_icon.sql');

    expect(sql).toMatch(/ALTER TABLE `fun_member_group` ADD COLUMN `icon`/);
  });

  it('后端接收、校验并返回 icon', () => {
    const controller = read('../app/backend/controller/system/SystemMemberGroup.php');

    expect(controller).toMatch(/post\('icon'/);
    expect(controller).toContain("'icon' =>");
    expect(controller).toContain('会员组图标不能超过 50 个字符');
  });

  it('前端弹窗提供图标选择且列表展示图标', () => {
    const dialog = read('src/views/system/member-group/components/MemberGroupFormDialog.vue');
    const list = read('src/views/system/member-group/index.vue');
    const api = read('src/api/system/memberGroup.ts');

    expect(dialog).toContain('IconSelect');
    expect(dialog).toContain('form.icon');
    expect(list).toMatch(/row\.icon/);
    expect(api).toContain('icon: string');
  });
});
