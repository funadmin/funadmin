import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const migration = readFileSync(
  resolve(process.cwd(), '../database/migrations/014_user_management_menu.sql'),
  'utf8'
);

describe('用户管理菜单层级迁移', () => {
  it('创建独立的用户管理一级目录', () => {
    expect(migration).toMatch(/''用户管理'',\s*''\/users''/);
    expect(migration).toContain('component=Layout&name=UserManagement&type=M&redirect=/users/member');
    expect(migration).toMatch(/''admin_web'',\s*''user_management''/);
  });

  it('将会员组、会员等级和会员管理迁移为其子菜单', () => {
    expect(migration).toMatch(/source_name`\s+IN\s*\(''member_group'',\s*''member_level'',\s*''member''\)/);
    expect(migration).toContain('SET `pid` = @user_management_menu_id');
    expect(migration).toContain("WHEN ''member_group'' THEN ''member-group''");
    expect(migration).toContain("WHEN ''member_level'' THEN ''member-level''");
    expect(migration).toContain("WHEN ''member'' THEN ''member''");
  });

  it('将系统管理员菜单命名为管理员管理以避免重名', () => {
    expect(migration).toContain("= ''管理员管理''");
    expect(migration).toContain("`source_name` = ''user''");
    expect(migration).toContain("'`title`'");
    expect(migration).toContain("'`name`'");
  });
});
