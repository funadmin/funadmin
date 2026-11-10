import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { runBackendTests } from './backend-runtime';

const readProjectFile = (relativePath: string) => readFileSync(
  resolve(process.cwd(), relativePath),
  'utf8'
);

const migrationService = readProjectFile('../admin/app/common/service/database/MigrationService.php');
const systemMember = readProjectFile('../admin/app/admin/system/controller/SystemMember.php');

const filteredQuery = systemMember.match(
  /private function filteredQuery\([\s\S]*?(?=\n    private function )/
)?.[0] ?? '';

describe('006_schema_integrity migration 源码契约', () => {
  it('差异同步只给新增关系写 created_at，保留不变行与已删历史', () => {
    expect(systemMember).toContain('RelationRowSync::sync(MemberGroupRelation::class');
    expect(systemMember).toContain('RelationRowSync::sync(MemberTagRelation::class');
    for (const kind of ['group', 'tag']) {
      expect(runBackendTests('RemainingSoftDeleteGapTest.php',
        `test_member_${kind}_relation_sync_preserves_ids_timestamps_and_deleted_history`)).toContain('OK (1 test');
    }
    expect(systemMember).not.toMatch(/['"]create_time['"]\s*=>/);
  });

  it('所有 migration 表前缀均可由 MigrationService 统一替换', () => {
    expect(migrationService).toMatch(
      /rewritePrefix\(\$sql,\s*config\('funadmin\.mysqlPrefix', 'fun_'\),\s*Db::connection\(\)->getTablePrefix\(\)\)/
    );
  });
});

describe('SystemMember 会员分组查询源码契约', () => {
  it('分组筛选由数据库子查询完成，不预取全量 member_id', () => {
    expect(filteredQuery).toContain('(new MemberGroupRelation())->getTable()');
    expect(filteredQuery).toMatch(/whereIn\('id', function \(\$relationQuery\) use \(\$groupId\)/);
    expect(filteredQuery).not.toContain("pluck('member_id')");
    expect(filteredQuery).not.toMatch(/column\(\s*['"]member_id['"]\s*\)/i);
    expect(filteredQuery).not.toMatch(/whereIn\(\s*['"]id['"]\s*,\s*\$memberIds/i);
  });
});
