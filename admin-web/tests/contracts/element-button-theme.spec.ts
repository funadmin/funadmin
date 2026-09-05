import { readFileSync, readdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const styles = readFileSync(resolve(process.cwd(), 'src/styles/index.scss'), 'utf8');

describe('Element Plus 按钮主题契约', () => {
  it('默认按钮沿用 Element Plus 配色', () => {
    expect(styles).not.toMatch(/\.el-button--default\s*\{/);
    expect(styles).not.toMatch(/\.el-button--default\.is-plain/);
    expect(styles).not.toContain('.el-button.is-plain:not(.el-button--default)');
  });

  it('自定义 plain 配色只作用于明确语义类型', () => {
    ['primary', 'success', 'warning', 'danger', 'info'].forEach((type) => {
      expect(styles).toContain(`.el-button--${type}.is-plain`);
    });
  });

  it('业务模板不使用无类型 plain 按钮', () => {
    const viewFiles = readdirSync(resolve(process.cwd(), 'src/views'), { recursive: true })
      .filter((file): file is string => file.endsWith('.vue'));
    const invalidButtons = viewFiles.flatMap((file) => {
      const content = readFileSync(resolve(process.cwd(), 'src/views', file), 'utf8');
      return content.match(/<el-button\s+plain\b[^>]*>/g) || [];
    });
    expect(invalidButtons).toEqual([]);
  });

  it('动态 default 按钮不能固定启用 plain', () => {
    const viewFiles = readdirSync(resolve(process.cwd(), 'src/views'), { recursive: true })
      .filter((file): file is string => file.endsWith('.vue'));
    const invalidButtons = viewFiles.flatMap((file) => {
      const content = readFileSync(resolve(process.cwd(), 'src/views', file), 'utf8');
      return content.match(/<el-button\s+:type="[^"]*default[^"]*"\s+plain\b[^>]*>/g) || [];
    });
    expect(invalidButtons).toEqual([]);
  });

  it('保留按钮布局和图标对齐规则', () => {
    expect(styles).toMatch(/\.el-button\s*\{/);
    expect(styles).toContain(".el-button [class*='i-ep-']");
  });
});
