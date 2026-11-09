import { describe, expect, it, vi, beforeEach } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import ElementPlus from 'element-plus';
import { createI18n } from 'vue-i18n';

const api = vi.hoisted(() => ({ options: vi.fn(), previewCreate: vi.fn(), create: vi.fn(), validate: vi.fn(), package: vi.fn() }));
vi.mock('@/api/development/plugin', () => ({ pluginDevelopmentApi: api }));
vi.mock('@/utils/permission', () => ({ hasPermission: () => true }));

import PluginDevelopmentDialog from './PluginDevelopmentDialog.vue';

const plan = {
  auditId: 1, conflicts: [],
  plan: { operation: 'create', target: 'plugins/shop', files: [
    { path: 'plugins/shop/plugin.json', status: 'create' },
    { path: 'plugins/shop/database/migrations/.gitkeep', status: 'create' },
    { path: 'plugins/shop/app/shop/controller/Index.php', status: 'create' },
    { path: 'plugins/shop/app/admin/plugins/shop/controller/Index.php', status: 'create' },
    { path: 'plugins/shop/web/src/admin/plugins/shop/pages/Index.vue', status: 'create' }
  ] }
};

const mountDialog = () => mount(PluginDevelopmentDialog, {
  props: { modelValue: true },
  attachTo: document.body,
  global: { plugins: [ElementPlus, createI18n({ legacy: false, locale: 'zh-CN', missingWarn: false, fallbackWarn: false, messages: {} })], directives: { perm: {} } }
});

describe('插件开发工具 · 创建插件', () => {
  beforeEach(() => { Object.values(api).forEach((fn) => fn.mockReset()); api.options.mockResolvedValue([]); api.previewCreate.mockResolvedValue(plan); });

  it('预览文件按插件内位置分组并去掉公共前缀', async () => {
    const wrapper = mountDialog();
    await flushPromises();
    const preview = document.body.querySelectorAll('.el-dialog .actions')[0].querySelector('button') as HTMLButtonElement;
    preview.click();
    await flushPromises();
    const groups = [...document.body.querySelectorAll('.plan__group')].map((group) => ({
      title: group.querySelector('.plan__group-title')?.firstChild?.textContent?.trim(),
      files: [...group.querySelectorAll('.plan__path')].map((el) => el.textContent)
    }));
    expect(groups).toEqual([
      { title: '插件根目录', files: ['plugin.json', 'database/migrations/.gitkeep'] },
      { title: '独立应用', files: ['controller/Index.php'] },
      { title: '管理后台', files: ['controller/Index.php'] },
      { title: '管理前端', files: ['pages/Index.vue'] }
    ]);
    expect(document.body.querySelector('.plan__summary')?.textContent).toBe('将在 plugins/shop 生成 5 个文件');
    wrapper.unmount();
  });

  it('修改表单后清空旧预览，必须重新预览才能创建', async () => {
    const wrapper = mountDialog();
    await flushPromises();
    (document.body.querySelector('.el-dialog .actions button') as HTMLButtonElement).click();
    await flushPromises();
    const create = () => document.body.querySelectorAll('.el-dialog .actions')[0].querySelectorAll('button')[1] as HTMLButtonElement;
    expect(create().disabled).toBe(false);
    const input = document.body.querySelector('.create-form input') as HTMLInputElement;
    input.value = 'mall';
    input.dispatchEvent(new Event('input'));
    await flushPromises();
    expect(document.body.querySelector('.plan__group')).toBeNull();
    expect(document.body.querySelector('.plan__empty')?.textContent).toBe('表单已修改，请重新预览');
    expect(create().disabled).toBe(true);
    wrapper.unmount();
  });
});
