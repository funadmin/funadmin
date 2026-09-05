import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const source = readFileSync(resolve(process.cwd(), 'src/components/SearchForm/index.vue'), 'utf8');

describe('公共查询表单按钮顺序', () => {
  it('查询按钮位于重置按钮之前', () => {
    const searchPosition = source.indexOf("{{ t('table.search') }}");
    const resetPosition = source.indexOf("{{ t('table.reset') }}");

    expect(searchPosition).toBeGreaterThan(-1);
    expect(resetPosition).toBeGreaterThan(-1);
    expect(searchPosition).toBeLessThan(resetPosition);
  });
});
