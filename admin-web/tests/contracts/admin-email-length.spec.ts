import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const readProjectFile = (relativePath: string) => readFileSync(
  resolve(process.cwd(), relativePath),
  'utf8'
);

describe('会员分组关联后端契约', () => {
  it('SystemMember 通过关联表过滤并保存分组', () => {
    const source = readProjectFile('../app/backend/controller/SystemMember.php');

    expect(source).not.toContain('FIND_IN_SET');
    expect(source).not.toMatch(/'group_id'\s*=>\s*implode\(',',\s*\$groupIds\)/);
    expect(source).toContain('member_group_relation');
    expect(source).toContain('groupIds');
  });

  it('Member 登录不写入不存在的 login_time 字段', () => {
    const source = readProjectFile('../app/common/model/Member.php');

    expect(source).not.toContain('login_time');
  });

  it('SystemMemberGroup 通过关联表检查会员组引用', () => {
    const source = readProjectFile('../app/backend/controller/SystemMemberGroup.php');

    expect(source).not.toContain('FIND_IN_SET');
    expect(source).toContain('member_group_relation');
  });
});

describe('管理员邮箱最大长度契约', () => {
  it('前端管理员表单限制邮箱最多 60 个字符', () => {
    const source = readProjectFile('src/views/system/user/components/UserFormDialog.vue');

    expect(source).toMatch(/v-model="form\.email"[^>]*maxlength="60"/);
    expect(source).toMatch(/email:\s*\[[\s\S]*?max:\s*60/);
  });

  it('后端拒绝超过 60 个字符的管理员邮箱', () => {
    const source = readProjectFile('../app/backend/controller/SystemAdmin.php');

    expect(source).toMatch(/\$data\['email'\][\s\S]*?(?:mb_)?strlen\(\$data\['email'\]\)\s*>\s*60/);
  });
});
