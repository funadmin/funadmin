import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const styles = readFileSync(resolve(process.cwd(), 'src/styles/index.scss'), 'utf8');

describe('表单校验提示排版契约', () => {
  it('全局校验提示使用紧凑的 11px 字号和行高', () => {
    expect(styles).toMatch(
      /\.el-form \.el-form-item__error\s*\{[^}]*font-size:\s*11px;[^}]*line-height:\s*1\.3;/s
    );
  });
});
