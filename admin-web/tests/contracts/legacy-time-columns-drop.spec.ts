import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const projectRoot = resolve(process.cwd(), '..');
const readProjectFile = (relativePath: string) => readFileSync(resolve(projectRoot, relativePath), 'utf8');
const maintenancePath = 'database/maintenance/001_drop_legacy_time_columns.sql';
const migration = readProjectFile(maintenancePath);

describe('legacy 时间列 maintenance contract 契约', () => {
  it('普通 migration 目录不包含 contract drop 文件', () => {
    expect(existsSync(resolve(projectRoot, 'database/migrations/034_drop_legacy_time_columns.sql'))).toBe(false);
    expect(existsSync(resolve(projectRoot, maintenancePath))).toBe(true);
  });

  it('maintenance contract 明确要求备份、仓库审计且不可直接执行', () => {
    expect(migration).toContain('普通 MigrationService 不扫描本目录');
    expect(migration).toContain('备份');
    expect(migration).toContain('migration repository');
    expect(migration).toMatch(/不可直接执行[\s\S]*DROP COLUMN/i);
    expect(migration).not.toMatch(/^\s*(?:ALTER|DROP|TRUNCATE|RENAME)\s/mi);
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
