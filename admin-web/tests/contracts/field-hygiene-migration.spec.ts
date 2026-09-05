import { existsSync, readFileSync, readdirSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { basename, resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const migrationPath = resolve(process.cwd(), '../database/migrations/013_field_hygiene.sql');
const sql = readFileSync(migrationPath, 'utf8');
const migrationService = readFileSync(resolve(process.cwd(), '../app/common/service/MigrationService.php'), 'utf8');
const fieldVerifyModel = readFileSync(resolve(process.cwd(), '../app/common/model/FieldVerify.php'), 'utf8');
const projectRoot = resolve(process.cwd(), '..');
const migrationDir = resolve(projectRoot, 'database/migrations');
const followupPath = resolve(migrationDir, '020_schema_integrity_finalize.sql');
const readProjectFile = (relativePath: string) => readFileSync(resolve(projectRoot, relativePath), 'utf8');

describe('013 字段规范化迁移契约', () => {
  it('会员手机号治理归属 006，013 不重复处理', () => {
    expect(sql).not.toMatch(/UPDATE `fun_member` SET `mobile`/);
  });

  it('省份表邮编与区号改为字符串避免前导零丢失', () => {
    expect(sql).toMatch(/`zipcode` varchar\(10\)/);
    expect(sql).toMatch(/`areacode` varchar\(10\)/);
  });

  it('附件表数值字段不再使用字符串存储', () => {
    expect(sql).toMatch(/`width` int unsigned NOT NULL DEFAULT 0/);
    expect(sql).toMatch(/`height` int unsigned NOT NULL DEFAULT 0/);
    expect(sql).toMatch(/`duration` int unsigned NOT NULL DEFAULT 0/);
  });

  it('FieldVerify 模型使用 id 主键', () => {
    expect(fieldVerifyModel).toMatch(/protected \$pk\s*=\s*['"]id['"]/);
    expect(fieldVerifyModel).not.toMatch(/protected \$pk\s*=\s*['"]verify['"]/);
  });

  it('013 新增 id 自增单列主键并拒绝冲突主键状态', () => {
    expect(sql).toContain('ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST');
    expect(sql).toMatch(/CONSTRAINT_NAME\s*=\s*['"]PRIMARY['"]\s+AND\s+COLUMN_NAME\s*<>\s*['"]id['"]/i);
    expect(sql).toContain('schema_integrity_error_013_field_verify_other_primary_key');
  });

  it('MyISAM 表统一转换为 InnoDB', () => {
    expect(sql).toMatch(/ALTER TABLE `fun_field_type` ENGINE=InnoDB/);
    expect(sql).toMatch(/ALTER TABLE `fun_provinces` ENGINE=InnoDB/);
  });

  it('IP 与验证规则长度全库统一', () => {
    expect(sql).toMatch(/ALTER TABLE `fun_blacklist` MODIFY COLUMN `ip` varchar\(45\) CHARACTER SET ascii/);
    expect(sql).toMatch(/ALTER TABLE `fun_config` MODIFY COLUMN `verify` varchar\(50\)/);
  });

  it('插件需求版本脏默认值清理', () => {
    expect(sql).toMatch(/UPDATE `fun_plugin` SET `requires` = '' WHERE/);
    expect(sql).toMatch(/ALTER TABLE `fun_plugin` MODIFY COLUMN `requires` varchar\(50\)[^\n]*DEFAULT ''/);
  });

  it('保持 forward-only 与表前缀可替换', () => {
    expect(sql).not.toMatch(/\b(DROP|TRUNCATE|RENAME)\b/i);
    expect(sql).not.toMatch(/CONCAT\(\s*'fun_'/i);
  });

  it('MigrationService 仅白名单放行 guard 临时对象清理', () => {
    expect(migrationService).toContain('schema_integrity_guard_');
    expect(migrationService).toContain('DROP\\s+(?:TRIGGER|TABLE)\\s+IF\\s+EXISTS');
  });
});

describe('已执行迁移不可变与 020 最终修复契约', () => {
  it('006 与 013 保持已执行版本的固定 sha256', () => {
    const migration006 = readFileSync(resolve(migrationDir, '006_schema_integrity.sql'));
    const migration013 = readFileSync(migrationPath);

    expect(createHash('sha256').update(migration006).digest('hex')).toBe(
      '8bbac08ad5ac5b9e06e12634e2d1a87f809c959387fda51639d0e1c643b47998'
    );
    expect(createHash('sha256').update(migration013).digest('hex')).toBe(
      'fc108babb8949749a83b1e4c0daa42fdd26667d390b6468706dc532e7379e288'
    );
  });

  it('所有 migration 使用唯一数字版本', () => {
    const migrations = readdirSync(migrationDir).filter((name) => /^\d+_.*\.sql$/.test(name)).sort();
    const versions = migrations.map((name) => name.match(/^(\d+)_/)?.[1]);

    expect(new Set(versions).size).toBe(versions.length);
    expect(existsSync(followupPath)).toBe(true);
    expect(basename(followupPath)).toBe('020_schema_integrity_finalize.sql');
  });

  it('020 校验既有 relation schema 并清理旧 guard 对象', () => {
    const followup = readFileSync(followupPath, 'utf8').replace(/\s+/g, ' ');

    expect(followup).toMatch(/information_schema\.(?:COLUMNS|KEY_COLUMN_USAGE|REFERENTIAL_CONSTRAINTS)[\s\S]*fun_member_group_relation/i);
    expect(followup).toMatch(/DROP\s+(?:TRIGGER|TABLE)\s+IF\s+EXISTS\s+`?(?:fun_)?schema_integrity_guard_006`?/i);
  });

  it('020 最终复核 field_verify 使用 id 自增单列主键', () => {
    const followup = readFileSync(followupPath, 'utf8').replace(/\s+/g, ' ');

    expect(followup).toMatch(/information_schema\.COLUMNS[\s\S]*fun_field_verify[\s\S]*COLUMN_NAME\s*=\s*['"]id['"][\s\S]*COLUMN_TYPE\s*=\s*['"]int unsigned['"][\s\S]*EXTRA\s+LIKE\s+['"]%auto_increment%['"]/i);
    expect(followup).toMatch(/information_schema\.KEY_COLUMN_USAGE[\s\S]*CONSTRAINT_NAME\s*=\s*['"]PRIMARY['"][\s\S]*HAVING COUNT\(\*\)\s*=\s*1[\s\S]*COLUMN_NAME\s*=\s*['"]id['"]/i);
    expect(followup).toContain('schema_integrity_error_020_field_verify_id_auto_increment_primary_required');
  });

  it('020 将空白 mobile 归一为 NULL、允许 NULL 后再治理唯一性', () => {
    const followup = readFileSync(followupPath, 'utf8').replace(/\s+/g, ' ');
    const normalizeAt = followup.search(/UPDATE\s+`fun_member`\s+SET\s+`mobile`\s*\=\s*NULL\s+WHERE\s+TRIM\(COALESCE\(`mobile`,\s*['"]{2}\)\)\s*\=\s*['"]{2}/i);
    const uniqueAt = followup.search(/`mobile`\s+IS\s+NOT\s+NULL[\s\S]{0,300}GROUP BY\s+`mobile`|ADD\s+UNIQUE[\s\S]{0,120}`mobile`/i);

    const nullableAt = followup.search(/ALTER TABLE\s+`fun_member`\s+MODIFY COLUMN\s+`mobile`[\s\S]{0,160}\bNULL\s+DEFAULT\s+NULL/i);

    expect(normalizeAt).toBeGreaterThan(-1);
    expect(nullableAt).toBeGreaterThan(normalizeAt);
    expect(uniqueAt).toBeGreaterThan(nullableAt);
  });

  it('020 在添加 member.level_id 外键前校验孤儿且删除策略为 RESTRICT', () => {
    const followup = readFileSync(followupPath, 'utf8').replace(/\s+/g, ' ');
    const orphanCheckAt = followup.search(/(?:LEFT JOIN\s+`fun_member_level`|NOT EXISTS)[\s\S]{0,500}(?:schema_integrity_(?:guard|error)_020|SIGNAL SQLSTATE)/i);
    const foreignKeyAt = followup.search(/FOREIGN KEY\s*\(`level_id`\)\s*REFERENCES\s+`fun_member_level`\s*\(`id`\)\s*ON DELETE\s+RESTRICT/i);

    expect(orphanCheckAt).toBeGreaterThan(-1);
    expect(foreignKeyAt).toBeGreaterThan(orphanCheckAt);
  });
});

describe('字段类型、会员与唯一冲突源码契约', () => {
  it('数据库自动时间戳和项目规则统一为 Unix 秒 int', () => {
    const databaseConfig = readProjectFile('config/database.php');
    const projectRule = readProjectFile('.cursor/rules/funadmin.mdc');

    expect(databaseConfig).toMatch(/['"]auto_timestamp['"]\s*=>\s*['"]int['"]/);
    expect(projectRule).toMatch(/时间字段[^\n]*(?:Unix|UNIX)[^\n]*秒[^\n]*`?int`?/i);
    expect(projectRule).not.toMatch(/时间字段[^\n]*`?datetime`?\s*类型/i);
  });

  it('Member 注册邮箱限制 60、包含软删除判重并明确空值归一策略', () => {
    const member = readProjectFile('app/common/model/Member.php');
    const validator = readProjectFile('app/common/validate/MemberValidate.php');
    const registration = member.match(/public function reg\(\)[\s\S]*?(?=\n    public function )/)?.[0] ?? '';

    expect(validator).toMatch(/['"]email\|邮箱['"]\s*=>\s*['"][^'"]*max:60/);
    expect(registration).toMatch(/withTrashed\(\)[\s\S]*where\(\s*['"]email['"]/);
    expect(registration).toMatch(/(?:trim\([\s\S]*email|email[\s\S]*\=\=\=\s*['"]{2})[\s\S]{0,200}(?:null|NULL)/);
  });

  it('Member 登录从 request 获取并验证 IPv4/IPv6，不直接读取 $_SERVER', () => {
    const member = readProjectFile('app/common/model/Member.php');
    const login = member.match(/public function login\(\)[\s\S]*?(?=\n    public function )/)?.[0] ?? '';

    expect(login).toContain('request()->ip()');
    expect(login).not.toContain('$_SERVER');
    expect(login).toMatch(/(?:IpHelper|FILTER_VALIDATE_IP|normalizeIp)/);
  });

  it('SystemRole 对 name/code 使用 withTrashed 判重并捕获数据库唯一冲突', () => {
    const source = readProjectFile('app/backend/controller/system/SystemRole.php');
    const writes = source.match(/public function create\(\): Response[\s\S]*?(?=\n    public function delete)/)?.[0] ?? '';

    expect(writes).toMatch(/AuthGroup::withTrashed\(\)[\s\S]*['"]name['"]/);
    expect(writes).toMatch(/AuthGroup::withTrashed\(\)[\s\S]*['"]code['"]/);
    expect(writes).toMatch(/catch\s*\(\\Throwable\s+\$\w+\)/);
    expect(writes).toMatch(/(?:1062|Duplicate entry)[\s\S]*(?:角色名称或标识已存在|duplicateError)/);
  });

  it('SystemConfig 配置分组写入捕获数据库唯一冲突', () => {
    const source = readProjectFile('app/backend/controller/system/SystemConfig.php');
    const groupWrites = source.match(/public function createGroup\(\): Response[\s\S]*?(?=\n    public function deleteGroup)/)?.[0] ?? '';

    expect(groupWrites).toMatch(/catch\s*\(\\Throwable\s+\$\w+\)/);
    expect(groupWrites).toMatch(/(?:1062|Duplicate entry)[\s\S]*(?:配置分组编码已存在|duplicateError)/);
  });
});
