import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const root = resolve(process.cwd(), '..');
const read = (path: string) => readFileSync(resolve(root, path), 'utf8');
const migrationPath = resolve(root, 'database/migrations/017_legacy_naming_and_member_tags.sql');

describe('017 历史命名与会员标签治理', () => {
  it('新增规范管理员字段并回填旧数据', () => {
    const sql = readFileSync(migrationPath, 'utf8');
    expect(sql).toContain("COLUMN_NAME = 'real_name'");
    expect(sql).toContain("COLUMN_NAME = 'last_login_ip'");
    expect(sql).toMatch(/UPDATE `fun_admin`[\s\S]*`real_name`\s*=\s*COALESCE\(`realname`/);
    expect(sql).toMatch(/UPDATE `fun_admin`[\s\S]*`last_login_ip`\s*=\s*COALESCE\(`lastloginip`/);
  });

  it('创建单数语言表和语义准确的地区表并迁移数据', () => {
    const sql = readFileSync(migrationPath, 'utf8');
    expect(sql).toContain('CREATE TABLE IF NOT EXISTS `fun_language`');
    expect(sql).toContain('CREATE TABLE IF NOT EXISTS `fun_region`');
    expect(sql).toMatch(/INSERT INTO `fun_language`[\s\S]*FROM `fun_languages`/);
    expect(sql).toMatch(/INSERT INTO `fun_region`[\s\S]*FROM `fun_provinces`/);
  });

  it('语言数据回填兼容 legacy 与 Laravel 时间字段', () => {
    const sql = readFileSync(migrationPath, 'utf8');
    expect(sql).toMatch(/SET @lang_c = IF\([^\n]*fun_languages[^\n]*create_time[^\n]*created_at/i);
    expect(sql).toMatch(/SET @lang_u = IF\([^\n]*fun_languages[^\n]*update_time[^\n]*updated_at/i);
    expect(sql).toMatch(/SET @lang_d = IF\([^\n]*fun_languages[^\n]*delete_time[^\n]*deleted_at/i);
    expect(sql).toMatch(/CONCAT\(.*INSERT INTO `fun_language`.*@lang_c.*@lang_u.*@lang_d/i);
  });

  it('会员标签使用标签表和关联表承载', () => {
    const sql = readFileSync(migrationPath, 'utf8');
    expect(sql).toContain('CREATE TABLE IF NOT EXISTS `fun_member_tag`');
    expect(sql).toContain('CREATE TABLE IF NOT EXISTS `fun_member_tag_relation`');
    expect(sql).toMatch(/FOREIGN KEY \(`member_id`\)[\s\S]*REFERENCES `fun_member`/);
    expect(sql).toMatch(/FOREIGN KEY \(`tag_id`\)[\s\S]*REFERENCES `fun_member_tag`/);
    expect(sql).toMatch(/INSERT IGNORE INTO `fun_member_tag`[\s\S]*`fun_member`/);
  });

  it('应用代码切换到规范管理员字段', () => {
    for (const path of [
      'app/console/service/AdminSessionService.php',
      'app/console/service/AdminAuthorizationService.php',
      'app/console/controller/auth/AdminAuth.php',
      'app/console/controller/auth/AdminProfile.php',
      'app/console/controller/system/SystemAdmin.php',
      'app/common/traits/Crud.php'
    ]) {
      const source = read(path);
      expect(source, path).not.toMatch(/\brealname\b|\blastloginip\b/);
    }
  });

  it('语言和地区模型显式绑定规范表', () => {
    expect(existsSync(resolve(root, 'app/common/model/Language.php'))).toBe(true);
    expect(existsSync(resolve(root, 'app/common/model/Region.php'))).toBe(true);
    expect(read('app/common/model/Language.php')).toContain("protected $name = 'language';");
    expect(read('app/common/model/Region.php')).toContain("protected $name = 'region';");
  });

  it('会员 API 与前端通过 tagIds 管理标签关系', () => {
    const controller = read('app/console/controller/system/SystemMember.php');
    const api = read('admin-web/src/api/system/member.ts');
    const form = read('admin-web/src/views/system/member/components/MemberFormDialog.vue');
    expect(controller).toContain('tagIds');
    expect(controller).toContain('syncWithPivotValues($tagIds');
    expect(controller).toContain("'tagNames'");
    expect(api).toContain('tagIds: number[]');
    expect(api).toContain('tagNames: string[]');
    expect(form).toContain('v-model="form.tagIds"');
  });
});
