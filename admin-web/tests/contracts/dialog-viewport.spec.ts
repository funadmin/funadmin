import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const styles = readFileSync(resolve(process.cwd(), 'src/styles/index.scss'), 'utf8');
const declarations = (selector: string) => styles.slice(styles.indexOf(`${selector} {`)).split('}')[0];
const dialog = '.el-dialog:not(.is-fullscreen)';

describe('公共编辑弹窗视口约束', () => {
  it('限制整体高度且不强撑短表单，保留全屏模式', () => {
    const rule = declarations(dialog);
    expect(rule).toContain('display: flex');
    expect(rule).toContain('flex-direction: column');
    expect(rule).toContain('max-height: 85vh');
    expect(rule).toContain('max-height: 85dvh');
    expect(rule).not.toMatch(/(?:^|[;\n])\s*(?:height|min-height):/);
  });

  it('仅直接 body 可收缩并纵向滚动，头尾保持可见', () => {
    const body = declarations(`${dialog} > .el-dialog__body`);
    expect(body).toContain('min-height: 0');
    expect(body).toContain('overflow-y: auto');
    expect(body).toContain('flex: 0 1 auto');
    expect(styles).toContain(`${dialog} > .el-dialog__header,`);
    expect(declarations(`${dialog} > .el-dialog__footer`)).toContain('flex-shrink: 0');
  });

  it('像素宽度在窄屏收敛，顶部留白与最大高度共同落在视口内', () => {
    const rule = declarations(dialog);
    expect(rule).toContain('max-width: calc(100vw - 24px)');
    expect(rule).toContain('margin-top: 7.5vh');
    expect(rule).toContain('margin-top: 7.5dvh');
    expect(rule).toContain('box-sizing: border-box');
  });

  it('普通 CRUD、Schema 生成页与运行时继续复用原生公共弹窗', () => {
    const generator = readFileSync(resolve(process.cwd(), '../app/common/crud/ProductionTemplateContext.php'), 'utf8');
    const runtime = readFileSync(resolve(process.cwd(), 'src/views/form/data.vue'), 'utf8');
    expect(generator).toContain("? 'el-drawer' : 'el-dialog'");
    expect(generator).toContain('return "<template><el-dialog');
    expect(runtime).toContain('<el-dialog v-model="dialogVisible"');
  });
});
