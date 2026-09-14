import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { createI18n } from 'vue-i18n';
import zhCN from '@/locales/zh-CN';
import GenerationPlanView from './GenerationPlanView.vue';
import type { BusinessGenerationPlan } from '@/api/development/business';

const i18n = createI18n({ legacy: false, locale: 'zh-CN', messages: { 'zh-CN': zhCN } });
const stubs = {
  ElTag: defineComponent({ template: '<span class="tag"><slot /></span>' }),
  ElCollapse: defineComponent({ template: '<div class="collapse"><slot /></div>' }),
  ElCollapseItem: defineComponent({ props: ['title'], template: '<details><summary>{{ title }}</summary><slot /></details>' })
};

const plan: BusinessGenerationPlan = {
  blocked: true,
  summary: { create: 1, update: 1, 'auto-merged': 1, delete: 1, 'keep-local': 1, conflict: 1 },
  files: [
    { path: 'app/Create.php', status: 'create' },
    { path: 'app/Update.php', status: 'update' },
    { path: 'app/Merged.php', status: 'auto-merged' },
    { path: 'app/Delete.php', status: 'delete' },
    { path: 'app/Local.php', status: 'keep-local' },
    {
      path: 'app/Conflict.php', status: 'conflict',
      baseContent: 'base-content', localContent: 'local-content', remoteContent: 'remote-content'
    }
  ]
};

function preview(baseContent: string | null, localContent: string | null, remoteContent: string | null) {
  return mount(GenerationPlanView, {
    props: { plan: { blocked: true, files: [{ path: 'sample.ts', status: 'conflict', baseContent, localContent, remoteContent }] } },
    global: { plugins: [i18n], stubs }
  });
}

describe('GenerationPlanView 行级差异', () => {
  it.each([['', ''], ['same\n', 'same\n']])('等同和空文件明确无差异', (base, remote) => {
    const wrapper = preview(base, base, remote);
    expect(wrapper.get('[data-diff-status]').text()).toContain('内容相同');
    expect(wrapper.get('[data-diff-stats]').text()).toContain('+0 / −0');
    expect(wrapper.get('[data-next-diff]').attributes('disabled')).toBeDefined();
  });

  it.each([
    ['', 'new\n', 1, 0], ['old\n', '', 0, 1],
    ['a\nold\nz\n', 'a\nnew\nz\n', 1, 1],
    ['a\nb\n', 'x\na\nb\n', 1, 0]
  ])('准确计算新增、删除、修改及插入后的对应行', (base, remote, added, removed) => {
    const wrapper = preview(base, base, remote);
    expect(wrapper.get('[data-diff-stats]').text()).toContain(`+${added} / −${removed}`);
    expect(wrapper.findAll('[data-line-kind="add"]')).toHaveLength(added);
    expect(wrapper.findAll('[data-line-kind="delete"]')).toHaveLength(removed);
    for (const row of wrapper.findAll('[data-line-kind="equal"]')) {
      expect(row.get('[data-old-line]').text()).not.toBe('');
      expect(row.get('[data-new-line]').text()).not.toBe('');
    }
    if (remote.startsWith('x')) {
      const row = wrapper.findAll('[data-line-kind="equal"]')[0];
      expect(row.get('[data-old-line]').text()).toBe('1');
      expect(row.get('[data-new-line]').text()).toBe('2');
    }
  });

  it.each([['a\r\n', 'a\n', 'CRLF'], ['a', 'a\n', '无末尾换行']])('保留换行差异，不假报相同', (base, remote, marker) => {
    const wrapper = preview(base, base, remote);
    expect(wrapper.get('[data-diff-stats]').text()).toContain('+1 / −1');
    expect(wrapper.get('[data-diff-scroll]').text()).toContain(marker);
  });

  it('导航差异、切 Tab 和切文件不修改计划或触发业务事件', async () => {
    const wrapper = preview('a\nb\nc\n', 'local\n', 'x\nb\ny\n');
    const original = JSON.stringify(wrapper.props('plan'));
    expect(wrapper.get('[data-diff-position]').text()).toContain('1 / 2');
    await wrapper.get('[data-next-diff]').trigger('click');
    expect(wrapper.get('[data-diff-position]').text()).toContain('2 / 2');
    await wrapper.get('[data-prev-diff]').trigger('click');
    expect(wrapper.get('[data-diff-position]').text()).toContain('1 / 2');
    await wrapper.get('[data-comparison="local"]').trigger('click');
    expect(wrapper.get('[data-diff-scroll]').text()).toContain('local');
    expect(wrapper.get('[data-diff-position]').text()).toContain('1 / 1');
    await wrapper.get('[data-wrap-lines]').setValue(true);
    expect(wrapper.get('[data-diff-scroll]').classes()).toContain('is-wrapped');
    expect(JSON.stringify(wrapper.props('plan'))).toBe(original);
    expect(Object.keys(wrapper.emitted()).filter((event) => !['click', 'input', 'change'].includes(event))).toEqual([]);
    await wrapper.setProps({ plan: { blocked: false, files: [{ path: 'other.ts', status: 'update', baseContent: '', localContent: '', remoteContent: 'other' }] } });
    expect(wrapper.get('[data-comparison="remote"]').attributes('aria-selected')).toBe('true');
    expect(wrapper.get('[data-diff-scroll]').text()).toContain('other');
  });

  it('注入字符串只作为文本输出', () => {
    const injection = '<img src=x onerror="alert(1)"><script>alert(2)</script>';
    const wrapper = preview('', '', injection);
    expect(wrapper.get('[data-diff-scroll]').text()).toContain(injection);
    expect(wrapper.find('img,script').exists()).toBe(false);
  });

  it.each(['x'.repeat(200001), 'x\n'.repeat(5001), Array.from({ length: 1500 }, (_, i) => `${i}\n`).join('')])('大文本或复杂计算明确降级', (text) => {
    const wrapper = preview(text, text, text.replaceAll('x', 'y').split('').reverse().join(''));
    expect(wrapper.get('[data-diff-status]').text()).toContain('未计算');
    expect(wrapper.get('[data-diff-status]').text()).not.toContain('内容相同，无差异');
    expect(wrapper.find('[data-diff-stats]').exists()).toBe(false);
    expect(wrapper.findAll('[data-line-kind]')).toHaveLength(0);
  });

  it('缺失基线不当成空文件，允许明确的本地与待生成对比', async () => {
    const wrapper = preview(null, 'old', 'new');
    expect(wrapper.get('[data-diff-stats]').text()).toContain('+1 / −1');
    expect(wrapper.get('[data-comparison="direct"]').attributes('aria-selected')).toBe('true');
    await wrapper.get('[data-comparison="remote"]').trigger('click');
    expect(wrapper.get('[data-diff-status]').text()).toContain('未提供');
    expect(wrapper.find('[data-diff-stats]').exists()).toBe(false);
  });

  it('二进制冲突不执行文本对比', () => {
    const binary = mount(GenerationPlanView, { props: { plan: { blocked: true, files: [{ path: 'a.bin', status: 'binary-conflict', contentKind: 'binary', baseContent: '', remoteContent: 'binary' }] } }, global: { plugins: [i18n], stubs } });
    expect(binary.get('[data-diff-status]').text()).toContain('二进制');
    expect(binary.find('[data-diff-stats]').exists()).toBe(false);
  });
});

describe('GenerationPlanView', () => {
  it('真实 PHP 授权预览响应能显示 SQL 和受管 Manifest 增量', () => {
    const response = JSON.parse(execFileSync('/opt/homebrew/opt/php@8.1/bin/php',
      [resolve(process.cwd(), '../tests/business_plugin_managed_test.php'), '--preview-json'], { encoding: 'utf8' }));
    const wrapper = mount(GenerationPlanView, { props: { plan: response.plan }, global: { plugins: [i18n], stubs } });
    expect(response.bundleDigest).toMatch(/^[a-f0-9]{64}$/);
    expect(wrapper.get('[data-file-diff="plugins/closeout/plugin.json"]').text()).toContain('模块 A 再次更新');
    expect(wrapper.text()).toContain('CREATE TABLE');
    expect(wrapper.text()).not.toContain('人工说明');
    expect(wrapper.text()).not.toContain('模块 B');
  });
  it('非冲突源码、Manifest 和迁移也提供只读三方差异', () => {
    const files = ['plugins/demo/app/console/Order.php', 'plugins/demo/plugin.json', 'plugins/demo/database/migrations/001.sql'].map((path) => ({ path, status: 'update' as const, localContent: '旧内容', remoteContent: '新内容' }));
    const wrapper = mount(GenerationPlanView, { props: { plan: { blocked: false, files } }, global: { plugins: [i18n], stubs } });
    expect(wrapper.findAll('[data-file-diff]')).toHaveLength(3);
    expect(wrapper.text()).toContain('Manifest 变化');
    expect(wrapper.text()).toContain('数据库迁移');
    expect(wrapper.get('[data-file-diff]').text()).toContain('新内容');
  });

  it('服务端只返回文件决策时明确说明无法核对内容差异', () => {
    const wrapper = mount(GenerationPlanView, { props: { plan: { blocked: false, files: [{ path: 'plugins/demo/plugin.json', status: 'update' }] } }, global: { plugins: [i18n], stubs } });
    expect(wrapper.get('[data-diff-unavailable]').text()).toContain('服务端未提供');
    expect(wrapper.get('[data-diff-unavailable]').text()).toContain('Manifest');
    expect(wrapper.findAll('[data-file-diff]')).toHaveLength(0);
  });

  it('结构化显示摘要和所有文件决策', () => {
    const wrapper = mount(GenerationPlanView, { props: { plan }, global: { plugins: [i18n], stubs } });
    expect(wrapper.get('[data-section="summary"]').text()).toContain('新建');
    for (const file of plan.files) expect(wrapper.text()).toContain(file.path);
  });

  it('未提供摘要时根据文件决策计算摘要', () => {
    const wrapper = mount(GenerationPlanView, {
      props: { plan: { blocked: false, files: [{ path: 'app/New.php', status: 'create' }] } },
      global: { plugins: [i18n], stubs }
    });
    expect(wrapper.get('[data-section="summary"]').text()).toContain('新建: 1');
  });

  it('冲突使用只读对比 Tab，不再显示三栏原文', async () => {
    const conflictFile = plan.files[5];
    const withoutConflict = { ...plan, files: plan.files.slice(0, 5) };
    const wrapper = mount(GenerationPlanView, { props: { plan: withoutConflict, conflicts: [conflictFile] }, global: { plugins: [i18n], stubs } });
    const conflict = wrapper.get('[data-conflict-path="app/Conflict.php"]');
    expect(conflict.text()).toContain('Base');
    expect(conflict.text()).toContain('base-content');
    expect(conflict.text()).toContain('Local');
    expect(conflict.findAll('[role="tab"]')).toHaveLength(2);
    await conflict.get('[data-comparison="local"]').trigger('click');
    expect(conflict.text()).toContain('local-content');
    await conflict.get('[data-comparison="remote"]').trigger('click');
    expect(conflict.text()).toContain('Remote');
    expect(conflict.text()).toContain('remote-content');
  });

  it('原始 JSON 只位于折叠的高级区域', () => {
    const wrapper = mount(GenerationPlanView, { props: { plan }, global: { plugins: [i18n], stubs } });
    const advanced = wrapper.get('details');
    expect(advanced.attributes('open')).toBeUndefined();
    expect(advanced.text()).toContain('"blocked": true');
    expect(wrapper.findAll('pre')).toHaveLength(1);
  });
});
