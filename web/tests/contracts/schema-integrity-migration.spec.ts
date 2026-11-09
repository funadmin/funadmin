import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

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
  it('pivot 关联写入统一使用 created_at，不再写 legacy create_time', () => {
    const syncCalls = systemMember.match(/groups\(\)->sync(?:WithPivotValues)?\([\s\S]{0,240}?\);/g) ?? [];
    expect(syncCalls.length).toBeGreaterThan(0);
    for (const syncCall of syncCalls) {
      expect(syncCall).toMatch(/['"]created_at['"]\s*=>\s*date\(['"]Y-m-d H:i:s['"]\)/);
      expect(syncCall).not.toMatch(/['"]create_time['"]\s*=>/);
    }
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
