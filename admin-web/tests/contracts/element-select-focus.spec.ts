import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const styles = readFileSync(resolve(process.cwd(), 'src/styles/index.scss'), 'utf8');

describe('Element Plus 下拉框焦点样式契约', () => {
  it('可搜索下拉框内部输入不叠加原生 focus-visible 外圈', () => {
    expect(styles).toContain('input:not(.el-input__inner):not(.el-select__input):not(.el-range-input):focus-visible');
  });

  it('下拉框外层保留单层聚焦描边', () => {
    expect(styles).toContain('.el-select__wrapper.is-focused');
    expect(styles).toContain('box-shadow: 0 0 0 1px var(--el-color-primary) inset !important;');
  });
});
