import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const migrationPath = resolve(process.cwd(), '../database/migrations/013_field_hygiene.sql');
const sql = readFileSync(migrationPath, 'utf8');

describe('013 字段规范化迁移契约', () => {
  it('会员手机号空串归一为 NULL 并允许 NULL', () => {
    expect(sql).toMatch(/UPDATE `fun_member` SET `mobile` = NULL WHERE/);
    expect(sql).toMatch(/ALTER TABLE `fun_member` MODIFY COLUMN `mobile` varchar\(20\)[^\n]*NULL DEFAULT NULL/);
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

  it('验证规则表补充自增主键', () => {
    expect(sql).toMatch(/ALTER TABLE `fun_field_verify` ADD COLUMN `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY/);
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
});
