import { existsSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const projectRoot = resolve(process.cwd(), '..');
const readProjectFile = (relativePath: string) => readFileSync(resolve(projectRoot, relativePath), 'utf8');
const baseline = readProjectFile('database/migrations/001_baseline.sql');

describe('legacy 时间列收口契约', () => {
  it('最终基线直接使用 Laravel 字段且 legacy contract 已移除', () => {
    expect(existsSync(resolve(projectRoot, 'database/maintenance/001_drop_legacy_time_columns.sql'))).toBe(false);
    expect(existsSync(resolve(projectRoot, 'webman-admin/app/admin/common/command/MaintenanceContractMigrate.php'))).toBe(false);
    expect(existsSync(resolve(projectRoot, 'webman-admin/app/common/service/MaintenanceContractService.php'))).toBe(false);
    expect(baseline).not.toMatch(/\b(?:create_time|update_time|delete_time)\b/);
    expect(baseline).toContain('created_at');
    expect(baseline).toContain('updated_at');
    expect(baseline).toContain('deleted_at');
  });

  it('运行时代码不再引用 legacy 时间列', () => {
    const files = [
      'webman-admin/app/admin/controller/system/SystemOperationLog.php',
      'webman-admin/app/admin/authorization/controller/SystemAdmin.php',
      'webman-admin/app/admin/controller/system/SystemMember.php',
      'webman-admin/app/common/model/BaseModel.php'
    ];
    for (const file of files) {
      expect(readProjectFile(file)).not.toMatch(/\b(?:create_time|update_time|delete_time)\b/);
    }
  });
});
