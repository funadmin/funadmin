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

describe('GenerationPlanView', () => {
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

  it('冲突按 Base、Local、Remote 三栏显示', () => {
    const conflictFile = plan.files[5];
    const withoutConflict = { ...plan, files: plan.files.slice(0, 5) };
    const wrapper = mount(GenerationPlanView, { props: { plan: withoutConflict, conflicts: [conflictFile] }, global: { plugins: [i18n], stubs } });
    const conflict = wrapper.get('[data-conflict-path="app/Conflict.php"]');
    expect(conflict.text()).toContain('Base');
    expect(conflict.text()).toContain('base-content');
    expect(conflict.text()).toContain('Local');
    expect(conflict.text()).toContain('local-content');
    expect(conflict.text()).toContain('Remote');
    expect(conflict.text()).toContain('remote-content');
  });

  it('原始 JSON 只位于折叠的高级区域', () => {
    const wrapper = mount(GenerationPlanView, { props: { plan }, global: { plugins: [i18n], stubs } });
    const advanced = wrapper.get('details');
    expect(advanced.attributes('open')).toBeUndefined();
    expect(advanced.text()).toContain('"blocked": true');
    expect(wrapper.findAll('pre')).toHaveLength(4);
  });
});
