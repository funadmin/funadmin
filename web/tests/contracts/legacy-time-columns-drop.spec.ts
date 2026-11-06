import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const projectRoot = resolve(process.cwd(), '..');
const readProjectFile = (relativePath: string) => readFileSync(resolve(projectRoot, relativePath), 'utf8');
const baseline = readProjectFile('admin/database/core/migrations/001_baseline.sql');

describe('legacy 时间列收口契约', () => {
  it('最终基线直接使用 Laravel 字段且 legacy contract 已移除', () => {
    expect(existsSync(resolve(projectRoot, 'admin/database/maintenance/001_drop_legacy_time_columns.sql'))).toBe(false);
    expect(existsSync(resolve(projectRoot, 'admin/app/admin/common/command/MaintenanceContractMigrate.php'))).toBe(false);
    expect(existsSync(resolve(projectRoot, 'admin/app/common/service/MaintenanceContractService.php'))).toBe(false);
    // Historical comments and index names may mention legacy columns; DDL must not define them.
    const tableDefinitions = baseline.match(/CREATE TABLE[\s\S]*?;(?=\s*(?:CREATE|INSERT|SET|$))/g) ?? [];
    expect(tableDefinitions.length).toBeGreaterThan(20);
    for (const table of tableDefinitions) {
      expect(table).not.toMatch(/^\s*`(?:create_time|update_time|delete_time)`\s/m);
    }
    expect(baseline).toContain('created_at');
    expect(baseline).toContain('updated_at');
    expect(baseline).toContain('deleted_at');
  });

  it('运行时代码不再引用 legacy 时间列', () => {
    const files = [
      'admin/app/admin/system/controller/SystemOperationLog.php',
      'admin/app/admin/authorization/controller/SystemAdmin.php',
      'admin/app/admin/system/controller/SystemMember.php',
      'admin/app/common/model/BaseModel.php'
    ];
    for (const file of files) {
      expect(readProjectFile(file)).not.toMatch(/\b(?:create_time|update_time|delete_time)\b/);
    }
  });
});
