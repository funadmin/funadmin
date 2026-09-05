import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const source = readFileSync(resolve(process.cwd(), '../app/common/service/MigrationService.php'), 'utf8');
const runDirectory = source.match(/public function runDirectory[\s\S]*?(?=\n    \/\*\*)/)?.[0] ?? '';
const preflight = source.match(/private function preflightSchemaIntegrity006[\s\S]*?(?=\n    (?:private|public) function )/)?.[0] ?? '';

describe('MigrationService 不可变 006 legacy preflight 契约', () => {
  it('仅在已执行记录检查后、读取 006 主体前调用兼容桥', () => {
    const appliedCheckAt = runDirectory.indexOf('if ($record)');
    const preflightAt = runDirectory.indexOf('$this->preflightSchemaIntegrity006($scope, $version)');
    const readMigrationAt = runDirectory.indexOf('file_get_contents($file)');

    expect(appliedCheckAt).toBeGreaterThan(-1);
    expect(preflightAt).toBeGreaterThan(appliedCheckAt);
    expect(readMigrationAt).toBeGreaterThan(preflightAt);
  });

  it('仅精确匹配 core/006_schema_integrity 并以不可变迁移兼容桥说明原因', () => {
    expect(preflight).toContain("$scope !== 'core'");
    expect(preflight).toContain("$version !== '006_schema_integrity'");
    expect(preflight).toMatch(/(?:不可变.*migration.*兼容桥|兼容桥[\s\S]*migration[\s\S]*不可变)/i);
  });

  it('使用实际前缀、安全标识符、schema 探测及幂等空白归一', () => {
    expect(preflight).toContain("config('database.connections.mysql.prefix')");
    expect(preflight).not.toContain('`fun_member`');
    expect(preflight).toMatch(/getTables\(\)|information_schema|SHOW\s+COLUMNS/i);
    expect(preflight).toMatch(/IS_NULLABLE|nullable/i);
    expect(preflight).toMatch(/UPDATE[\s\S]*NULL[\s\S]*TRIM\([\s\S]*COALESCE/i);
    expect(preflight).toMatch(/Db::(?:execute|table)[\s\S]*(?:\[|where)/);
    expect(preflight).toMatch(/ALTER TABLE[\s\S]*MODIFY COLUMN[\s\S]*NULL DEFAULT NULL/i);
    expect(preflight).toMatch(/information_schema\.STATISTICS[\s\S]*uk_member_mobile[\s\S]*ADD UNIQUE KEY/i);
  });
});
