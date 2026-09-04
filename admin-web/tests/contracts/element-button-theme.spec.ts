import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const styles = readFileSync(resolve(process.cwd(), 'src/styles/index.scss'), 'utf8');

describe('Element Plus 按钮主题契约', () => {
  it('默认按钮沿用 Element Plus 配色', () => {
    expect(styles).not.toMatch(/\.el-button--default\s*\{/);
    expect(styles).not.toMatch(/\.el-button--default\.is-plain/);
  });

  it('保留按钮布局和图标对齐规则', () => {
    expect(styles).toMatch(/\.el-button\s*\{/);
    expect(styles).toContain(".el-button [class*='i-ep-']");
  });
});
