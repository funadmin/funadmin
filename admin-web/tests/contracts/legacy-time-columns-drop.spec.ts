import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const projectRoot = resolve(process.cwd(), '..');
const readProjectFile = (relativePath: string) => readFileSync(resolve(projectRoot, relativePath), 'utf8');
const migration = readProjectFile('database/migrations/034_drop_legacy_time_columns.sql');

describe('034 删除 legacy int 时间列迁移契约', () => {
  const samples: Array<[string, string]> = [
    ['fun_admin', 'create_time'],
    ['fun_admin', 'delete_time'],
    ['fun_admin_menu', 'update_time'],
    ['fun_member', 'delete_time'],
    ['fun_permission', 'create_time'],
    ['fun_provinces', 'delete_time'],
    ['fun_field_verify', 'delete_time']
  ];

  it.each(samples)('%s 的 %s 删除必须由 information_schema 守卫', (table, column) => {
    expect(migration).toContain(`TABLE_NAME='${table}' AND COLUMN_NAME='${column}'`);
    expect(migration).toContain(`DROP COLUMN \`${column}\``);
  });

  it('覆盖全部 62 个 legacy 时间列删除', () => {
    const drops = migration.match(/DROP COLUMN `/g) ?? [];
    expect(drops.length).toBe(62);
  });

  it('含 create_time 的旧时间索引先删除再以 created_at 重建', () => {
    expect(migration).toContain('DROP INDEX `idx_admin_log_admin_time`');
    expect(migration).toContain('ADD INDEX `idx_admin_log_admin_time` (`admin_id`,`created_at`)');
    expect(migration).toContain('DROP INDEX `idx_admin_log_create_time`');
    expect(migration).toContain('ADD INDEX `idx_admin_log_created_at` (`created_at`)');
    expect(migration).toContain('ADD INDEX `idx_plugin_operation_name_time` (`plugin_name`,`created_at`)');
    expect(migration).toContain('ADD INDEX `idx_plugin_version_name_time` (`plugin_name`,`created_at`)');
  });

  it('迁移不包含裸破坏性语句', () => {
    expect(migration).not.toMatch(/^(DROP|ALTER|TRUNCATE|RENAME)\s/mi);
  });

  it('运行时代码不再引用 legacy 时间列', () => {
    const files = [
      'app/backend/controller/system/SystemOperationLog.php',
      'app/backend/controller/system/SystemAdmin.php',
      'app/backend/controller/system/SystemMember.php',
      'app/common/model/BaseModel.php'
    ];
    for (const file of files) {
      expect(readProjectFile(file)).not.toMatch(/\b(?:create_time|update_time|delete_time)\b/);
    }
  });
});
