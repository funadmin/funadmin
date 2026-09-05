import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const source = readFileSync(resolve(process.cwd(), 'src/views/login/index.vue'), 'utf8');

describe('登录页品牌区纵向间距', () => {
  it('版权区与上方功能标签保留稳定间距', () => {
    expect(source).toMatch(
      /\.login__brand-bottom\s*\{[^}]*margin-top:\s*28px;[^}]*flex-shrink:\s*0;/s
    );
  });
});
