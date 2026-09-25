import { readFileSync, readdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const styles = readFileSync(resolve(process.cwd(), 'src/styles/index.scss'), 'utf8');

describe('Element Plus Message 原生外观契约', () => {
  it('全局样式不覆盖 Message 容器、状态、内容、图标或关闭按钮', () => {
    const css = styles.replace(/\/\*[\s\S]*?\*\//g, '');
    expect(css.match(/\.el-message(?=--|__|[^\w-]|$)/g) ?? []).toEqual([]);
    expect(css).not.toMatch(/--el-message-[\w-]+\s*:/);
  });

  it('保留 Element Plus 原生 Message 样式入口', () => {
    const main = readFileSync(resolve(process.cwd(), 'src/main.ts'), 'utf8');
    expect(main).toContain("import 'element-plus/es/components/message/style/css'");
  });
});

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
    const definition = readFileSync(resolve(process.cwd(), '../app/admin/service/MemberPageDefinition.php'), 'utf8');
    expect(definition).toContain("$action('recycled', $t('回收站', 'Recycle Bin'), 'warning'");
    expect(definition).toContain("'inactiveColor' => 'info'");
    expect(definition).toContain("'activeWhen' => $recycled");
    const actions = readFileSync(resolve(process.cwd(), 'src/components/DataTable/PageActions.vue'), 'utf8');
    expect(actions).toContain("action.activeWhen && !matchesPageCondition(action.activeWhen, props.context.values) ? action.inactiveColor : action.color");
  });

  it('CRUD 运行时语义色按钮默认 plain 浅色描边', () => {
    const bar = readFileSync(resolve(process.cwd(), 'src/views/form/components/ListButtonBar.vue'), 'utf8');
    expect(bar).toContain(
      ":plain=\"link ? item.button.plain : (item.button.plain ?? (item.button.color !== undefined && item.button.color !== 'default'))\""
    );
  });

  it('保留按钮布局和图标对齐规则', () => {
    expect(styles).toMatch(/\.el-button\s*\{/);
    expect(styles).toContain(".el-button [class*='i-ep-']");
  });
});
