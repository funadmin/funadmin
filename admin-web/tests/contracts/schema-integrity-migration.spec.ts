import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const readProjectFile = (relativePath: string) => readFileSync(
  resolve(process.cwd(), relativePath),
  'utf8'
);

const migration = readProjectFile('../database/migrations/006_schema_integrity.sql');
const migrationService = readProjectFile('../app/common/service/MigrationService.php');
const systemMember = readProjectFile('../app/backend/controller/system/SystemMember.php');

const normalizedSql = migration.replace(/\s+/g, ' ');
const filteredQuery = systemMember.match(
  /private function filteredQuery\([\s\S]*?(?=\n    private function )/
)?.[0] ?? '';

describe('006_schema_integrity migration 源码契约', () => {
  it('旧会员分组数据非法、存在空分组或引用不存在会员组时明确中止迁移', () => {
    expect(normalizedSql).toMatch(/(?:SIGNAL SQLSTATE\s+''?45000''?|schema_integrity_error_006)/i);
    expect(normalizedSql).toMatch(/group_id[\s\S]*(?:REGEXP|JSON_VALID|invalid|非法)/i);
    expect(normalizedSql).toMatch(/group_id[\s\S]*(?:empty|空分组|,,|TRIM)/i);
    expect(normalizedSql).toMatch(/(?:LEFT JOIN|NOT EXISTS)[\s\S]*member_group[\s\S]*(?:SIGNAL|schema_integrity_guard|不存在)/i);
    expect(normalizedSql).not.toMatch(/INSERT\s+IGNORE\s+INTO\s+`fun_member_group_relation`/i);
  });

  it('会员分组关联表同时约束 member 与 member_group 外键及删除策略', () => {
    expect(normalizedSql).toMatch(
      /FOREIGN KEY\s*\(`member_id`\)\s*REFERENCES\s+`fun_member`\s*\(`id`\)\s*ON DELETE\s+(?:RESTRICT|CASCADE)/i
    );
    expect(normalizedSql).toMatch(
      /FOREIGN KEY\s*\(`group_id`\)\s*REFERENCES\s+`fun_member_group`\s*\(`id`\)\s*ON DELETE\s+(?:RESTRICT|CASCADE)/i
    );
  });

  it('唯一约束遇到历史重复数据时明确失败而不是静默跳过', () => {
    expect(normalizedSql).toMatch(
      /HAVING COUNT\(\*\) > 1[\s\S]*schema_integrity_(?:guard|error)_006/i
    );
    expect(normalizedSql).not.toMatch(
      /HAVING COUNT\(\*\) > 1[\s\S]{0,500}['"]SELECT 1['"]/i
    );
  });

  it('校验失败后可安全重跑且成功后不残留 guard 对象', () => {
    const createsGuardObject = /CREATE (?:TABLE|TRIGGER)[\s\S]*schema_integrity_guard_006/i.test(normalizedSql);
    if (!createsGuardObject) {
      expect(normalizedSql).toMatch(/schema_integrity_error_006/i);
      expect(normalizedSql).not.toMatch(/CREATE (?:TABLE|TRIGGER)[\s\S]*schema_integrity_guard_006/i);
      return;
    }

    const createTriggerAt = normalizedSql.search(/CREATE TRIGGER\s+`schema_integrity_guard_006`/i);
    const dropBeforeCreateAt = normalizedSql.search(/DROP TRIGGER IF EXISTS\s+`schema_integrity_guard_006`/i);
    const lastGuardWriteAt = normalizedSql.lastIndexOf('INSERT INTO `fun_schema_integrity_guard_006`');
    const cleanupTriggerAt = normalizedSql.lastIndexOf('DROP TRIGGER');
    const cleanupTableAt = normalizedSql.lastIndexOf('DROP TABLE');

    expect(dropBeforeCreateAt).toBeGreaterThan(-1);
    expect(dropBeforeCreateAt).toBeLessThan(createTriggerAt);
    expect(cleanupTriggerAt).toBeGreaterThan(lastGuardWriteAt);
    expect(cleanupTableAt).toBeGreaterThan(lastGuardWriteAt);
  });

  it('mobile 空字符串先转为 NULL 且重复检查排除 NULL', () => {
    const normalizeMobileAt = normalizedSql.search(
      /UPDATE\s+`fun_member`\s+SET\s+`mobile`\s*=\s*NULL\s+WHERE\s+TRIM\(COALESCE\(`mobile`,\s*['"]{2}\)\)\s*=\s*['"]{2}/i
    );
    const mobileDuplicateCheckAt = normalizedSql.search(
      /`mobile`\s+IS\s+NOT\s+NULL[\s\S]{0,200}GROUP BY\s+`mobile`\s+HAVING COUNT\(\*\)\s*>\s*1/i
    );

    expect(normalizeMobileAt).toBeGreaterThan(-1);
    expect(mobileDuplicateCheckAt).toBeGreaterThan(normalizeMobileAt);
  });

  it('已存在会员分组关联表时校验或补齐两端外键约束', () => {
    const constraintInspectionAt = normalizedSql.search(
      /information_schema\.(?:TABLE_CONSTRAINTS|KEY_COLUMN_USAGE|REFERENTIAL_CONSTRAINTS)[\s\S]{0,800}TABLE_NAME\s*=\s*['"]fun_member_group_relation['"]/i
    );
    const existingTableHandling = constraintInspectionAt >= 0
      ? normalizedSql.slice(constraintInspectionAt, constraintInspectionAt + 2400)
      : '';

    expect(constraintInspectionAt).toBeGreaterThan(-1);
    expect(existingTableHandling).toMatch(/(?:member_id|fk_member_group_relation_member)/i);
    expect(existingTableHandling).toMatch(/(?:group_id|fk_member_group_relation_group)/i);
    expect(existingTableHandling).toMatch(
      /(?:ALTER TABLE\s+`fun_member_group_relation`[\s\S]*ADD\s+CONSTRAINT|schema_integrity_(?:guard|error)_006|SIGNAL\s+SQLSTATE)/i
    );
  });

  it('pivot create_time 要么移除，要么所有 sync 写入真实时间', () => {
    const relationDefinition = normalizedSql.match(
      /CREATE TABLE IF NOT EXISTS\s+`fun_member_group_relation`\s*\([\s\S]*?\) ENGINE=/i
    )?.[0] ?? '';
    if (!/`create_time`/i.test(relationDefinition)) {
      return;
    }

    const syncCalls = systemMember.match(/groups\(\)->sync(?:WithPivotValues)?\([\s\S]{0,240}?\);/g) ?? [];
    expect(syncCalls.length).toBeGreaterThan(0);
    for (const syncCall of syncCalls) {
      expect(syncCall).toMatch(/['"]create_time['"]\s*=>\s*(?:time\(\)|Date::|Carbon::)/);
    }
  });

  it('所有 migration 表前缀均可由 MigrationService 统一替换', () => {
    expect(migrationService).toMatch(
      /str_replace\(config\('funadmin\.mysqlPrefix'\),\s*config\('database\.connections\.mysql\.prefix'\),\s*\$sql\)/
    );
    expect(migration).not.toMatch(/CONCAT\(\s*['"]fun_['"]\s*,/i);
  });

  it('attach.group_id 与 attach.path 分别可作为索引左前缀独立查询', () => {
    expect(normalizedSql).toMatch(
      /ALTER TABLE\s+`fun_attach`\s+ADD (?:KEY|INDEX)\s+`[^`]+`\s*\(`group_id`(?:\s*,\s*`[^`]+`)*\)/i
    );
    expect(normalizedSql).toMatch(
      /ALTER TABLE\s+`fun_attach`\s+ADD (?:KEY|INDEX)\s+`[^`]+`\s*\(`path`(?:\s*,\s*`[^`]+`)*\)/i
    );
  });
});

describe('SystemMember 会员分组查询源码契约', () => {
  it('分组筛选由数据库 EXISTS 或 JOIN 完成，不预取全量 member_id', () => {
    expect(filteredQuery).toContain('member_group_relation');
    expect(filteredQuery).toMatch(/(?:whereExists|\b(?:left|right|inner)?Join\s*\(|\bEXISTS\s*\()/i);
    expect(filteredQuery).not.toMatch(/column\(\s*['"]member_id['"]\s*\)/i);
    expect(filteredQuery).not.toMatch(/whereIn\(\s*['"]id['"]\s*,\s*\$memberIds/i);
  });
});
