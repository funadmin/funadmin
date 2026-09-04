import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const readProjectFile = (relativePath: string) => readFileSync(
  resolve(process.cwd(), relativePath),
  'utf8'
);

const migration = readProjectFile('../database/migrations/006_schema_integrity.sql');
const migrationService = readProjectFile('../app/common/service/MigrationService.php');

const normalizedSql = migration.replace(/\s+/g, ' ');

describe('006_schema_integrity migration 源码契约', () => {
  it('旧会员分组数据非法、存在空分组或引用不存在会员组时明确中止迁移', () => {
    expect(normalizedSql).toMatch(/SIGNAL SQLSTATE\s+''?45000''?/i);
    expect(normalizedSql).toMatch(/group_id[\s\S]*(?:REGEXP|JSON_VALID|invalid|非法)/i);
    expect(normalizedSql).toMatch(/group_id[\s\S]*(?:empty|空分组|,,|TRIM)/i);
    expect(normalizedSql).toMatch(/(?:LEFT JOIN|NOT EXISTS)[\s\S]*member_group[\s\S]*(?:SIGNAL|不存在)/i);
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

  it('唯一约束遇到历史重复数据时明确失败而不是降级为 SELECT 1', () => {
    expect(normalizedSql).toMatch(/HAVING COUNT\(\*\) > 1[\s\S]*SIGNAL SQLSTATE\s+''?45000''?/i);
    expect(normalizedSql).not.toMatch(
      /HAVING COUNT\(\*\) > 1[\s\S]{0,500}['"]SELECT 1['"]/i
    );
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
