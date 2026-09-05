import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const source = readFileSync(resolve(process.cwd(), 'src/views/system/log/operation.vue'), 'utf8');

describe('操作日志短标签列', () => {
  it('方法和状态标签列禁用单元格省略号', () => {
    expect(source).toMatch(/label="方法"[^>]*class-name="log-tag-column"/);
    expect(source).toMatch(/label="状态"[^>]*class-name="log-tag-column"/);
    expect(source).toMatch(/\.log-tag-column\s+:deep\(\.cell\)[^{]*\{[^}]*overflow:\s*visible;[^}]*text-overflow:\s*clip;/s);
  });
});
