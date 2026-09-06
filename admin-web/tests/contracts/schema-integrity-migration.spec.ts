import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const readProjectFile = (relativePath: string) => readFileSync(
  resolve(process.cwd(), relativePath),
  'utf8'
);

const migration = readProjectFile('../database/migrations/006_schema_integrity.sql');
const integrityFollowup = readProjectFile('../database/migrations/018_schema_integrity_followup.sql');
const followup = readProjectFile('../database/migrations/020_schema_integrity_finalize.sql');
const migrationService = readProjectFile('../app/common/service/MigrationService.php');
const systemMember = readProjectFile('../app/console/controller/system/SystemMember.php');

const normalizedSql = migration.replace(/\s+/g, ' ');
const normalizedIntegrityFollowup = integrityFollowup.replace(/\s+/g, ' ');
const normalizedFollowup = followup.replace(/\s+/g, ' ');
const filteredQuery = systemMember.match(
  /private function filteredQuery\([\s\S]*?(?=\n    private function )/
)?.[0] ?? '';

describe('006_schema_integrity migration 源码契约', () => {
  it('旧会员分组数据非法、存在空分组或引用不存在会员组时明确中止迁移', () => {
    expect(normalizedSql).toMatch(/(?:SIGNAL SQLSTATE\s+''?45000''?|schema_integrity_error_006)/i);
    expect(normalizedSql).toMatch(/group_id[\s\S]*(?:REGEXP|JSON_VALID|invalid|非法)/i);
    expect(normalizedSql).toMatch(/group_id[\s\S]*(?:empty|空分组|,,|TRIM)/i);
    expect(normalizedSql).toMatch(/(?:LEFT JOIN|NOT EXISTS)[\s\S]*member_group[\s\S]*(?:SIGNAL|schema_integrity_(?:guard|error)|不存在)/i);
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

  it('006 guard 对象由后续迁移按白名单安全清理', () => {
    expect(normalizedSql).toMatch(/CREATE TRIGGER\s+`schema_integrity_guard_006`/i);
    expect(normalizedFollowup).toMatch(/DROP TRIGGER IF EXISTS\s+`schema_integrity_guard_006`/i);
    expect(normalizedFollowup).toMatch(/DROP TABLE IF EXISTS\s+`fun_schema_integrity_guard_006`/i);
  });

  it('mobile 空字符串先转为 NULL 且重复检查排除 NULL', () => {
    const normalizeMobileAt = normalizedFollowup.search(
      /UPDATE\s+`fun_member`\s+SET\s+`mobile`\s*=\s*NULL\s+WHERE\s+TRIM\(COALESCE\(`mobile`,\s*['"]{2}\)\)\s*=\s*['"]{2}/i
    );
    const mobileDuplicateCheckAt = normalizedFollowup.search(
      /`mobile`\s+IS\s+NOT\s+NULL[\s\S]{0,200}GROUP BY\s+`mobile`\s+HAVING COUNT\(\*\)\s*>\s*1/i
    );

    expect(normalizeMobileAt).toBeGreaterThan(-1);
    expect(mobileDuplicateCheckAt).toBeGreaterThan(normalizeMobileAt);
  });

  it('复合主键与 group-first 索引按顺序正确聚合且保持严格列数', () => {
    for (const source of [normalizedIntegrityFollowup, normalizedFollowup]) {
      expect(source).toMatch(/HAVING COUNT\(\*\) = 2 AND MAX\(IF\((?:ORDINAL_POSITION|SEQ_IN_INDEX) = 1 AND COLUMN_NAME = ['"]member_id['"], 1, 0\)\) = 1 AND MAX\(IF\((?:ORDINAL_POSITION|SEQ_IN_INDEX) = 2 AND COLUMN_NAME = ['"]group_id['"], 1, 0\)\) = 1/i);
      expect(source).toMatch(/GROUP BY INDEX_NAME HAVING MAX\(IF\(SEQ_IN_INDEX = 1 AND COLUMN_NAME = ['"]group_id['"], 1, 0\)\) = 1/i);
    }
  });

  it('已存在会员分组关联表时严格校验两端外键删除规则', () => {
    const constraintInspectionAt = normalizedFollowup.search(
      /information_schema\.(?:TABLE_CONSTRAINTS|KEY_COLUMN_USAGE|REFERENTIAL_CONSTRAINTS)[\s\S]{0,800}TABLE_NAME\s*=\s*['"]fun_member_group_relation['"]/i
    );
    const existingTableHandling = constraintInspectionAt >= 0
      ? normalizedFollowup.slice(constraintInspectionAt, constraintInspectionAt + 2400)
      : '';

    expect(constraintInspectionAt).toBeGreaterThan(-1);
    expect(existingTableHandling).toMatch(/(?:member_id|fk_member_group_relation_member)/i);
    expect(existingTableHandling).toMatch(/(?:group_id|fk_member_group_relation_group)/i);
    expect(existingTableHandling).toMatch(/DELETE_RULE\s*=\s*['"]CASCADE['"]/i);
    expect(existingTableHandling).toMatch(/DELETE_RULE\s*=\s*['"]RESTRICT['"]/i);
    expect(existingTableHandling).toMatch(
      /(?:schema_integrity_(?:guard|error)_020|SIGNAL\s+SQLSTATE)/i
    );
  });

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
