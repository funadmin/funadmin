import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const read = (relativePath: string) => readFileSync(resolve(process.cwd(), '..', relativePath), 'utf8');

const migration = read('database/migrations/021_time_columns_no_default.sql');

/* 当前库中仍存在 DEFAULT 0 的时间列，迁移必须逐列移除默认值 */
const targets: Array<[string, string]> = [
  ['fun_admin_log', 'create_time'],
  ['fun_admin_menu', 'create_time'],
  ['fun_auth_group_department', 'create_time'],
  ['fun_auth_group_inherit', 'create_time'],
  ['fun_blacklist', 'create_time'],
  ['fun_blacklist', 'update_time'],
  ['fun_config', 'create_time'],
  ['fun_config', 'update_time'],
  ['fun_config_group', 'create_time'],
  ['fun_config_group', 'update_time'],
  ['fun_department', 'create_time'],
  ['fun_department', 'update_time'],
  ['fun_dict_item', 'create_time'],
  ['fun_dict_item', 'update_time'],
  ['fun_dict_type', 'create_time'],
  ['fun_dict_type', 'update_time'],
  ['fun_languages', 'create_time'],
  ['fun_languages', 'update_time'],
  ['fun_member', 'create_time'],
  ['fun_member', 'update_time'],
  ['fun_member_group_relation', 'create_time'],
  ['fun_permission', 'create_time']
];

describe('021 时间列移除默认值迁移', () => {
  it('逐列 MODIFY 且不带 DEFAULT 子句', () => {
    for (const [table, column] of targets) {
      expect(migration).toMatch(new RegExp(`ALTER TABLE \`${table}\` MODIFY COLUMN \`${column}\`[^;\\n]*;`));
    }
    expect(migration).not.toMatch(/DEFAULT\s+'?0'?/i);
  });

  it('保持 forward-only 与表前缀可替换', () => {
    expect(migration).not.toMatch(/\b(DROP|TRUNCATE|RENAME)\b/i);
    expect(migration).not.toMatch(/CONCAT\(\s*'fun_'/i);
  });

  it('统一 CRUD 建表模板对软删除时间列使用 nullable datetime 而非默认 0', () => {
    const template = read('app/common/crud/ProductionTemplateContext.php');
    expect(template).toContain("$columns[] = '  `deleted_at` datetime NULL';");
    expect(template).not.toMatch(/`(?:created|updated|deleted)_at`[^'\n]*DEFAULT\s+0/);
  });
});
