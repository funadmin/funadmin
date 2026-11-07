import { defineComponent } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { BusinessFormalGenerationPreview, BusinessModule } from '@/api/development/business';

const mocks = vi.hoisted(() => ({
  permissions: ['*'] as string[],
  previewFormalGeneration: vi.fn(),
  formalGeneration: vi.fn(),
  formalGenerationArchive: vi.fn(),
  generation: vi.fn(),
  module: vi.fn(),
  previewPublish: vi.fn(),
  publish: vi.fn(),
  refreshBusinessMenu: vi.fn(),
  message: { success: vi.fn(), error: vi.fn(), warning: vi.fn() },
  confirm: vi.fn()
}));

vi.mock('@/api/development/business', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/api/development/business')>();
  return {
    ...actual,
    businessDevelopmentApi: {
      ...actual.businessDevelopmentApi,
      previewFormalGeneration: mocks.previewFormalGeneration,
      formalGeneration: mocks.formalGeneration,
      formalGenerationArchive: mocks.formalGenerationArchive,
      generation: mocks.generation,
      module: mocks.module,
      previewPublish: mocks.previewPublish,
      publish: mocks.publish
    }
  };
});
vi.mock('@/store/modules/user', () => ({ useUserStore: () => ({ get permissions() { return mocks.permissions; } }) }));
vi.mock('vue-router', () => ({ useRouter: () => ({ push: vi.fn(), addRoute: vi.fn() }) }));
vi.mock('../composables/useBusinessMenuRefresh', () => ({
  useBusinessMenuRefresh: () => ({ refreshBusinessMenu: mocks.refreshBusinessMenu })
}));
vi.mock('element-plus', async (importOriginal) => {
  const actual = await importOriginal<typeof import('element-plus')>();
  return { ...actual, ElMessage: mocks.message, ElMessageBox: { confirm: mocks.confirm } };
});

import CodePreviewDialog from './CodePreviewDialog.vue';

const coreModule: BusinessModule = {
  id: 7,
  code: 'shop_goods',
  name: '商品',
  origin: 'database',
  lifecycle_status: 'draft',
  table_name: 'fa_shop_goods',
  form_id: 3,
  metadata: { target: { type: 'core', pluginCode: null, scope: 'admin' } }
};
const pluginModule: BusinessModule = {
  ...coreModule,
  metadata: { target: { type: 'plugin', pluginCode: 'demo', scope: 'admin' } }
};

const CONTROLLER = 'app/admin/generated/shop_goods/controller/ShopGoodsController.php';
const SQL = 'database/generated/shop-goods.sql';
const VIEW = 'web/src/admin/views/generated/shop-goods/index.vue';

function preview(overrides: Partial<BusinessFormalGenerationPreview> = {}): BusinessFormalGenerationPreview {
  return {
    generationId: 42,
    definitionHash: 'd',
    schemaHash: 's',
    plan: {
      blocked: false,
      files: [
        { path: CONTROLLER, status: 'update', remoteContent: '<?php echo 1;', localContent: '<?php echo 0;', baseContent: '<?php echo 0;' },
        { path: SQL, status: 'create', remoteContent: 'select 1;' },
        { path: VIEW, status: 'create', remoteContent: '<template />' }
      ]
    },
    conflicts: [],
    sensitive: { confirmToken: 'token-1' },
    ...overrides
  };
}

const stubs = {
  ElDialog: defineComponent({
    props: ['modelValue', 'fullscreen', 'width'],
    emits: ['update:modelValue'],
    template: '<aside v-if="modelValue" role="dialog" :data-fullscreen="fullscreen ? \'true\' : \'false\'"><header><slot name="header" /></header><slot /><footer><slot name="footer" /></footer></aside>'
  }),
  ElButton: defineComponent({
    inheritAttrs: false,
    props: ['disabled', 'loading', 'type', 'icon'],
    emits: ['click'],
    template: '<button v-bind="$attrs" type="button" :disabled="disabled || loading" @click="$emit(\'click\')"><slot /></button>'
  }),
  ElTag: defineComponent({ inheritAttrs: false, props: ['type'], template: '<span v-bind="$attrs" data-tag :data-type="type"><slot /></span>' }),
  ElAlert: defineComponent({ props: ['title', 'type'], template: '<div role="alert">{{ title }}<slot /></div>' }),
  CodeBlock: defineComponent({ props: ['code', 'language'], template: '<pre data-code-block :data-language="language">{{ code }}</pre>' }),
  GenerationFileDiff: defineComponent({ props: ['file'], template: '<section data-file-diff>{{ file.path }}</section>' })
};

function render(module: BusinessModule = coreModule) {
  return mount(CodePreviewDialog, {
    props: { modelValue: true, module },
    global: { stubs }
  });
}

function button(wrapper: ReturnType<typeof render>, action: string) {
  return wrapper.find(`[data-action="${action}"]`);
}

beforeEach(() => {
  vi.clearAllMocks();
  mocks.permissions = ['*'];
  mocks.previewFormalGeneration.mockResolvedValue(preview());
  mocks.refreshBusinessMenu.mockResolvedValue([]);
  mocks.confirm.mockResolvedValue('confirm');
  vi.stubGlobal('crypto', { randomUUID: vi.fn(() => 'nonce-0001') });
});

afterEach(() => {
  vi.unstubAllGlobals();
});

describe('CodePreviewDialog', () => {
  it('打开时请求预览，按类别分组展示文件树和状态计数标签', async () => {
    const wrapper = render();
    await flushPromises();
    expect(mocks.previewFormalGeneration).toHaveBeenCalledWith(7, 'nonce-0001');
    expect(wrapper.text()).toContain('fa_shop_goods');
    const tags = wrapper.findAll('[data-status-count]');
    expect(tags.map((tag) => tag.text())).toEqual(['新建 2', '更新 1']);
    const groups = wrapper.findAll('[data-file-group]').map((group) => group.attributes('data-file-group'));
    expect(groups).toEqual(['backend', 'database', 'frontend']);
    expect(wrapper.find(`[data-file="${CONTROLLER}"] [data-status-dot="update"]`).exists()).toBe(true);
    expect(wrapper.find('[data-current-path]').text()).toBe(CONTROLLER);
    const code = wrapper.find('[data-code-block]');
    expect(code.text()).toBe('<?php echo 1;');
    expect(code.attributes('data-language')).toBe('php');
  });

  it('点击文件树切换当前文件并打开页签', async () => {
    const wrapper = render();
    await flushPromises();
    await wrapper.find(`[data-file="${SQL}"]`).trigger('click');
    expect(wrapper.find('[data-current-path]').text()).toBe(SQL);
    expect(wrapper.find('[data-code-block]').attributes('data-language')).toBe('sql');
    expect(wrapper.findAll('[data-file-tab]').map((tab) => tab.attributes('data-file-tab'))).toEqual([CONTROLLER, SQL]);
    await wrapper.find(`[data-file-tab="${CONTROLLER}"]`).trigger('click');
    expect(wrapper.find('[data-current-path]').text()).toBe(CONTROLLER);
  });

  it('没有生成权限时只显示文件列表与状态，代码区提示需要生成权限', async () => {
    mocks.permissions = ['development:business:preview-formal-generation'];
    mocks.previewFormalGeneration.mockResolvedValue(preview({
      sensitive: undefined,
      plan: { blocked: false, files: [{ path: CONTROLLER, status: 'update' }, { path: SQL, status: 'create' }] }
    }));
    const wrapper = render();
    await flushPromises();
    expect(wrapper.findAll('[data-file]')).toHaveLength(2);
    expect(wrapper.text()).toContain('需要生成权限才能查看内容');
    expect(wrapper.find('[data-code-block]').exists()).toBe(false);
    expect(button(wrapper, 'generate').exists()).toBe(false);
    expect(button(wrapper, 'download').exists()).toBe(false);
    await wrapper.find('[data-view="diff"]').trigger('click');
    expect(wrapper.find('[data-file-diff]').exists()).toBe(false);
    expect(wrapper.text()).toContain('需要生成权限才能查看内容');
  });

  it('有生成权限但服务端未返回内容时提示文件过大', async () => {
    mocks.previewFormalGeneration.mockResolvedValue(preview({
      plan: { blocked: false, files: [{ path: CONTROLLER, status: 'update' }] }
    }));
    const wrapper = render();
    await flushPromises();
    expect(wrapper.text()).toContain('文件过大，未提供预览');
  });

  it('计划被阻断时「生成到项目」禁用，提示冲突并可重新预览', async () => {
    const conflict = { path: VIEW, status: 'conflict' as const, remoteContent: 'r', localContent: 'l', baseContent: 'b' };
    mocks.previewFormalGeneration.mockResolvedValue(preview({
      sensitive: undefined,
      plan: { blocked: true, files: [{ path: CONTROLLER, status: 'update', remoteContent: 'x' }, conflict] },
      conflicts: [conflict]
    }));
    const wrapper = render();
    await flushPromises();
    expect(button(wrapper, 'generate').attributes('disabled')).toBeDefined();
    expect(wrapper.find('[data-blocked-hint]').text()).toContain('与本地对比');
    expect(wrapper.findAll('[data-status-count]').map((tag) => tag.text())).toContain('文本冲突 1');
    mocks.previewFormalGeneration.mockResolvedValue(preview());
    await button(wrapper, 'repreview').trigger('click');
    await flushPromises();
    expect(mocks.previewFormalGeneration).toHaveBeenCalledTimes(2);
    expect(button(wrapper, 'generate').attributes('disabled')).toBeUndefined();
    expect(wrapper.find('[data-blocked-hint]').exists()).toBe(false);
  });

  it('切换「与本地对比」渲染 GenerationFileDiff', async () => {
    const wrapper = render();
    await flushPromises();
    expect(wrapper.find('[data-file-diff]').exists()).toBe(false);
    await wrapper.find('[data-view="diff"]').trigger('click');
    expect(wrapper.find('[data-file-diff]').text()).toBe(CONTROLLER);
    expect(wrapper.find('[data-code-block]').exists()).toBe(false);
    await wrapper.find('[data-view="remote"]').trigger('click');
    expect(wrapper.find('[data-code-block]').exists()).toBe(true);
  });

  it('插件目标不显示「动态发布」，核心目标显示', async () => {
    const plugin = render(pluginModule);
    await flushPromises();
    expect(button(plugin, 'dynamic-publish').exists()).toBe(false);
    const core = render();
    await flushPromises();
    expect(button(core, 'dynamic-publish').exists()).toBe(true);
  });

  it('「下载 zip」调用 formalGenerationArchive 并以业务标识命名下载文件', async () => {
    const blob = new Blob(['zip']);
    mocks.formalGenerationArchive.mockResolvedValue(blob);
    const createObjectURL = vi.fn(() => 'blob:zip');
    const revokeObjectURL = vi.fn();
    vi.stubGlobal('URL', Object.assign(URL, { createObjectURL, revokeObjectURL }));
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function (this: HTMLAnchorElement) {
      expect(this.href).toBe('blob:zip');
      expect(this.download).toMatch(/^shop_goods-\d{14}\.zip$/);
    });
    const wrapper = render();
    await flushPromises();
    await button(wrapper, 'download').trigger('click');
    await flushPromises();
    expect(mocks.formalGenerationArchive).toHaveBeenCalledWith(7);
    expect(createObjectURL).toHaveBeenCalledWith(blob);
    expect(click).toHaveBeenCalledTimes(1);
    click.mockRestore();
  });

  it('「生成到项目」使用 generationId 与 confirmToken 正式生成并刷新菜单', async () => {
    const result = { generationId: 42, state: 'completed', resourceApplyStatus: 'applied', routePath: '/generated/shop-goods' };
    mocks.formalGeneration.mockResolvedValue(result);
    const wrapper = render();
    await flushPromises();
    await button(wrapper, 'generate').trigger('click');
    await flushPromises();
    expect(mocks.formalGeneration).toHaveBeenCalledWith(7, 42, 'token-1');
    expect(mocks.refreshBusinessMenu).toHaveBeenCalled();
    expect(wrapper.emitted('generated')?.[0]).toEqual([result]);
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([false]);
  });

  it('「动态发布」读取已保存 Schema，先预览再发布', async () => {
    mocks.module.mockResolvedValue({
      module: coreModule,
      fields: [],
      form: { id: 3, schema_document: { key: 'shop_goods' }, schema_hash: 'hash-1', publish_config: { menuName: '商品' } }
    });
    mocks.previewPublish.mockResolvedValue({ formDependencyHash: 'dep-1' });
    mocks.publish.mockResolvedValue({});
    const wrapper = render();
    await flushPromises();
    await button(wrapper, 'dynamic-publish').trigger('click');
    await flushPromises();
    const payload = { schema_document: { key: 'shop_goods' }, schemaHash: 'hash-1', expected_schema_hash: 'hash-1', publish_config: { menuName: '商品' } };
    expect(mocks.previewPublish).toHaveBeenCalledWith(7, payload);
    expect(mocks.publish).toHaveBeenCalledWith(7, { ...payload, formDependencyHash: 'dep-1' });
    expect(wrapper.emitted('published')).toHaveLength(1);
  });

  it('全屏按钮切换 el-dialog 的 fullscreen', async () => {
    const wrapper = render();
    await flushPromises();
    expect(wrapper.find('[role="dialog"]').attributes('data-fullscreen')).toBe('false');
    await button(wrapper, 'fullscreen').trigger('click');
    expect(wrapper.find('[role="dialog"]').attributes('data-fullscreen')).toBe('true');
  });
});
