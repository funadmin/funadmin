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

  it('工具栏辅助操作统一使用 info plain', () => {
    const files = [
      'src/views/system/member/index.vue',
      'src/views/system/blacklist/index.vue',
      'src/views/system/member-group/index.vue',
      'src/views/system/member-level/index.vue',
      'src/views/system/config/index.vue',
      'src/views/system/attachment/index.vue'
    ];
    const toolbarSources = files.map((file) => readFileSync(resolve(process.cwd(), file), 'utf8')).join('\n');
    ['CSV 导入', 'CSV 导出', '配置分组', '移动'].forEach((label) => {
      expect(toolbarSources).toMatch(new RegExp(`<el-button[^>]*type="info"[^>]*plain[^>]*>[\\s\\S]{0,100}${label}`));
    });
  });

  it('回收站入口使用 warning plain', () => {
    const member = readFileSync(resolve(process.cwd(), 'src/views/system/member/index.vue'), 'utf8');
    expect(member).toContain(`:type="recycled ? 'warning' : 'info'" plain`);
  });

  it('保留按钮布局和图标对齐规则', () => {
    expect(styles).toMatch(/\.el-button\s*\{/);
    expect(styles).toContain(".el-button [class*='i-ep-']");
  });
});
