import { defineComponent } from 'vue';
import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { BusinessFormalGenerationPreview, BusinessModule } from '@/api/development/business';

const mocks = vi.hoisted(() => ({
  permissions: ['*'] as string[],
  previewFormalGeneration: vi.fn(),
  formalGeneration: vi.fn(),
  formalGenerationArchive: vi.fn(),
  adoptResolvedBaseline: vi.fn(),
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
      adoptResolvedBaseline: mocks.adoptResolvedBaseline,
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
  GenerationFileDiff: defineComponent({ props: ['file'], template: '<section data-file-diff>{{ file.path }}</section>' }),
  ElCollapse: defineComponent({ template: '<div data-collapse><slot /></div>' }),
  ElCollapseItem: defineComponent({ props: ['title', 'name'], template: '<section><h5>{{ title }}</h5><slot /></section>' })
};

function deferred<T>() {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>((done) => { resolve = done; });
  return { promise, resolve };
}

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
    expect(mocks.formalGenerationArchive).toHaveBeenCalledWith(7, 'nonce-0001');
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

  it('核心目标后端已请求重载：提示稍后可用，约 1.5 秒后再刷新菜单', async () => {
    vi.useFakeTimers({ toFake: ['setTimeout'] });
    try {
      mocks.formalGeneration.mockResolvedValue({ generationId: 42, state: 'completed', resourceApplyStatus: 'applied', reloadRequested: true, reloadReason: null });
      const wrapper = render();
      await flushPromises();
      await button(wrapper, 'generate').trigger('click');
      await flushPromises();
      expect(mocks.message.success).toHaveBeenCalledWith('后端已重新加载，新页面稍后可用');
      expect(mocks.refreshBusinessMenu).not.toHaveBeenCalled();
      vi.advanceTimersByTime(1499);
      await flushPromises();
      expect(mocks.refreshBusinessMenu).not.toHaveBeenCalled();
      vi.advanceTimersByTime(1);
      await flushPromises();
      expect(mocks.refreshBusinessMenu).toHaveBeenCalledTimes(1);
      expect(mocks.message.warning).not.toHaveBeenCalled();
      expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([false]);
    } finally {
      vi.useRealTimers();
    }
  });

  it('核心目标后端未能重载：警告需要重启并附原因，立即刷新菜单', async () => {
    mocks.formalGeneration.mockResolvedValue({ generationId: 42, state: 'completed', resourceApplyStatus: 'applied', reloadRequested: false, reloadReason: 'pid_file_missing' });
    const wrapper = render();
    await flushPromises();
    await button(wrapper, 'generate').trigger('click');
    await flushPromises();
    expect(mocks.message.warning).toHaveBeenCalledWith('请重启后端服务使新接口生效（原因：pid_file_missing）');
    expect(mocks.refreshBusinessMenu).toHaveBeenCalledTimes(1);
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

  it('无确认令牌时「生成到项目」禁用且不提交正式生成', async () => {
    mocks.previewFormalGeneration.mockResolvedValue(preview({ sensitive: undefined }));
    const wrapper = render();
    await flushPromises();
    expect(button(wrapper, 'generate').attributes('disabled')).toBeDefined();
    await button(wrapper, 'generate').trigger('click');
    await flushPromises();
    expect(mocks.formalGeneration).not.toHaveBeenCalled();
  });

  it('插件目标生成完成提示待安装／更新发布，不刷新菜单', async () => {
    const result = { generationId: 42, state: 'completed', resourceApplyStatus: 'pending_publication', routePath: '/plugin/demo/orders' };
    mocks.formalGeneration.mockResolvedValue(result);
    const wrapper = render(pluginModule);
    await flushPromises();
    await button(wrapper, 'generate').trigger('click');
    await flushPromises();
    expect(mocks.message.success).toHaveBeenCalledWith('源码已生成，待安装／更新发布');
    expect(mocks.refreshBusinessMenu).not.toHaveBeenCalled();
    expect(wrapper.emitted('generated')?.[0]).toEqual([result]);
  });

  it('提交时网络中断按 generationId 查询生成记录收敛结果，不重复提交', async () => {
    mocks.formalGeneration.mockRejectedValue(new Error('连接中断'));
    mocks.generation.mockResolvedValue({ id: 42, status: 'completed', result: { state: 'completed', resourceApplyStatus: 'applied', routePath: '/generated/shop-goods' } });
    const wrapper = render();
    await flushPromises();
    await button(wrapper, 'generate').trigger('click');
    await flushPromises();
    expect(mocks.formalGeneration).toHaveBeenCalledTimes(1);
    expect(mocks.generation).toHaveBeenCalledWith(42);
    expect(wrapper.emitted('generated')?.[0]?.[0]).toMatchObject({ generationId: 42, state: 'completed' });
  });

  it('对外暴露 dynamicPublish：未打开弹窗也可直接走动态发布流程', async () => {
    mocks.module.mockResolvedValue({ module: coreModule, fields: [], form: { id: 3, schema_document: { key: 'shop_goods' }, schema_hash: 'hash-1', publish_config: {} } });
    mocks.previewPublish.mockResolvedValue({ formDependencyHash: 'dep-1' });
    mocks.publish.mockResolvedValue({});
    const onPublished = vi.fn();
    const Host = defineComponent({
      components: { CodePreviewDialog },
      setup: () => ({ module: coreModule, onPublished }),
      template: '<CodePreviewDialog ref="dialog" :model-value="false" :module="module" @published="onPublished" />'
    });
    const host = mount(Host, { global: { stubs } });
    await flushPromises();
    expect(mocks.previewFormalGeneration).not.toHaveBeenCalled();
    const exposed = host.vm.$refs.dialog as { dynamicPublish?: () => Promise<void> };
    expect(typeof exposed.dynamicPublish).toBe('function');
    await exposed.dynamicPublish!();
    await flushPromises();
    expect(mocks.publish).toHaveBeenCalledWith(7, expect.objectContaining({ formDependencyHash: 'dep-1' }));
    expect(onPublished).toHaveBeenCalledTimes(1);
  });

  it('资源应用失败（partial）时显示 resourceApplyError，弹窗保持打开', async () => {
    const result = { generationId: 42, state: 'partial', resourceApplyStatus: 'failed', resourceApplyError: '菜单写入失败：父级不存在' };
    mocks.formalGeneration.mockResolvedValue(result);
    const wrapper = render();
    await flushPromises();
    await button(wrapper, 'generate').trigger('click');
    await flushPromises();
    expect(mocks.message.warning).toHaveBeenCalledWith(expect.stringContaining('菜单写入失败：父级不存在'));
    expect(wrapper.find('[data-resource-apply-error]').text()).toContain('菜单写入失败：父级不存在');
    expect(wrapper.emitted('generated')?.[0]).toEqual([result]);
    expect(wrapper.emitted('update:modelValue')).toBeUndefined();
  });

  it('「生成基线」折叠区显示预览返回的 Schema Hash 与 Definition Hash', async () => {
    mocks.previewFormalGeneration.mockResolvedValue(preview({ schemaHash: 'schema-hash-1', definitionHash: 'definition-hash-1' }));
    const wrapper = render();
    await flushPromises();
    const baseline = wrapper.find('[data-generation-baseline]');
    expect(baseline.text()).toContain('生成基线');
    expect(baseline.find('[data-baseline="schemaHash"]').text()).toContain('schema-hash-1');
    expect(baseline.find('[data-baseline="definitionHash"]').text()).toContain('definition-hash-1');
  });

  it('确认生成前显示菜单名称、父级菜单、图标与会员端 API 摘要（来自模块发布设置）', async () => {
    mocks.module.mockResolvedValue({
      module: coreModule, fields: [],
      form: { id: 3, schema_hash: 'h', publish_config: { menuEnabled: true, menuName: '商品管理', parentSourceName: 'shop', icon: 'i-ep-goods', memberApiEnabled: true, memberApiOwnerField: 'member_id' } }
    });
    const wrapper = render();
    await flushPromises();
    expect(mocks.module).toHaveBeenCalledWith(7);
    const summary = wrapper.find('[data-generate-summary]');
    expect(summary.find('[data-summary="menuName"]').text()).toContain('商品管理');
    expect(summary.find('[data-summary="parentMenu"]').text()).toContain('shop');
    expect(summary.find('[data-summary="icon"]').text()).toContain('i-ep-goods');
    expect(summary.find('[data-summary="memberApi"]').text()).toContain('已启用（member_id）');
  });

  it('发布设置未保存时摘要按正式生成的默认值解析：菜单名取表单名、默认图标、顶级菜单、未启用', async () => {
    mocks.module.mockResolvedValue({ module: coreModule, fields: [], form: { id: 3, form_key: 'shop_goods', name: '商品', schema_hash: 'h', publish_config: null } });
    const wrapper = render();
    await flushPromises();
    const summary = wrapper.find('[data-generate-summary]');
    expect(summary.find('[data-summary="menuName"]').text()).toContain('商品');
    expect(summary.find('[data-summary="parentMenu"]').text()).toContain('顶级菜单');
    expect(summary.find('[data-summary="icon"]').text()).toContain('i-ep-document');
    expect(summary.find('[data-summary="memberApi"]').text()).toContain('未启用');
    expect(summary.text()).not.toContain('未填写');
  });

  it('空菜单名回退表单名；启用会员端 API 但未保存归属字段时显示默认 member_id', async () => {
    mocks.module.mockResolvedValue({ module: coreModule, fields: [], form: { id: 3, form_key: 'shop_goods', name: '商品', schema_hash: 'h', publish_config: { menuName: '', memberApiEnabled: true } } });
    const wrapper = render();
    await flushPromises();
    const summary = wrapper.find('[data-generate-summary]');
    expect(summary.find('[data-summary="menuName"]').text()).toContain('商品');
    expect(summary.find('[data-summary="memberApi"]').text()).toContain('已启用（member_id）');
  });

  it('关闭生成菜单时摘要说明不会创建菜单，不再显示父级菜单与图标', async () => {
    mocks.module.mockResolvedValue({ module: coreModule, fields: [], form: { id: 3, form_key: 'shop_goods', name: '商品', schema_hash: 'h', publish_config: { menuEnabled: false, menuName: '商品管理' } } });
    const wrapper = render();
    await flushPromises();
    const summary = wrapper.find('[data-generate-summary]');
    expect(summary.find('[data-summary="menuName"]').text()).toContain('不生成菜单');
    expect(summary.find('[data-summary="parentMenu"]').exists()).toBe(false);
    expect(summary.find('[data-summary="icon"]').exists()).toBe(false);
  });

  it('生成完成后令牌已消费：「生成到项目」禁用；重新预览成功后清除资源应用失败提示', async () => {
    mocks.formalGeneration.mockResolvedValue({ generationId: 42, state: 'partial', resourceApplyStatus: 'failed', resourceApplyError: '菜单写入失败' });
    const wrapper = render();
    await flushPromises();
    await button(wrapper, 'generate').trigger('click');
    await flushPromises();
    expect(wrapper.find('[data-resource-apply-error]').exists()).toBe(true);
    expect(button(wrapper, 'generate').attributes('disabled')).toBeDefined();
    await button(wrapper, 'generate').trigger('click');
    expect(mocks.formalGeneration).toHaveBeenCalledTimes(1);
    mocks.previewFormalGeneration.mockResolvedValue(preview({ generationId: 50, sensitive: { confirmToken: 'token-2' } }));
    await button(wrapper, 'repreview').trigger('click');
    await flushPromises();
    expect(wrapper.find('[data-resource-apply-error]').exists()).toBe(false);
    expect(button(wrapper, 'generate').attributes('disabled')).toBeUndefined();
  });

  it('预览请求未返回时切换模块：迟到的旧计划不显示，「生成到项目」保持禁用', async () => {
    const first = deferred<BusinessFormalGenerationPreview>();
    const second = deferred<BusinessFormalGenerationPreview>();
    mocks.previewFormalGeneration.mockReturnValueOnce(first.promise).mockReturnValueOnce(second.promise);
    const wrapper = render();
    await flushPromises();
    await wrapper.setProps({ module: { ...coreModule, id: 8, code: 'other' } });
    expect(mocks.previewFormalGeneration).toHaveBeenLastCalledWith(8, expect.any(String));
    first.resolve(preview({ generationId: 41 }));
    await flushPromises();
    expect(wrapper.find('[data-file]').exists()).toBe(false);
    expect(wrapper.find('[data-preview-loading]').exists()).toBe(true);
    expect(button(wrapper, 'generate').attributes('disabled')).toBeDefined();
    await button(wrapper, 'generate').trigger('click');
    expect(mocks.formalGeneration).not.toHaveBeenCalled();
    second.resolve(preview({ generationId: 43, sensitive: { confirmToken: 'token-8' } }));
    await flushPromises();
    await button(wrapper, 'generate').trigger('click');
    await flushPromises();
    expect(mocks.formalGeneration).toHaveBeenCalledWith(8, 43, 'token-8');
  });

  it('预览请求未返回时关闭弹窗：迟到的计划不恢复，重新打开后需等待新预览', async () => {
    const first = deferred<BusinessFormalGenerationPreview>();
    const second = deferred<BusinessFormalGenerationPreview>();
    mocks.previewFormalGeneration.mockReturnValueOnce(first.promise).mockReturnValueOnce(second.promise);
    const wrapper = render();
    await flushPromises();
    await wrapper.setProps({ modelValue: false });
    first.resolve(preview());
    await flushPromises();
    await wrapper.setProps({ modelValue: true });
    await flushPromises();
    expect(mocks.previewFormalGeneration).toHaveBeenCalledTimes(2);
    expect(wrapper.find('[data-file]').exists()).toBe(false);
    expect(button(wrapper, 'generate').attributes('disabled')).toBeDefined();
    await button(wrapper, 'generate').trigger('click');
    expect(mocks.formalGeneration).not.toHaveBeenCalled();
    second.resolve(preview({ generationId: 44 }));
    await flushPromises();
    expect(wrapper.find('[data-file]').exists()).toBe(true);
    expect(button(wrapper, 'generate').attributes('disabled')).toBeUndefined();
  });

  // 后端 BusinessApiErrorMapper 把 FormSchemaException 映射为 VALIDATION_ERROR 并透出原文；同时兼容直接透传的 FORM_SCHEMA_NOT_PUBLISHED。
  const notPublishedErrors = {
    mapped: { code: 422, msg: '表单尚未发布', data: { error: { code: 'VALIDATION_ERROR', requestId: 'req-np', retryable: false, details: {} } } },
    direct: { code: 422, msg: '表单尚未发布', data: { error: { code: 'FORM_SCHEMA_NOT_PUBLISHED', requestId: 'req-np', retryable: false, details: {} } } }
  };

  it.each(Object.entries(notPublishedErrors))('表单从未发布（%s）：显示说明提示而非原始错误，「动态发布」可用，保留「重新预览」', async (_name, error) => {
    mocks.previewFormalGeneration.mockRejectedValueOnce(error);
    const wrapper = render();
    await flushPromises();
    const alert = wrapper.find('[data-preview-unpublished]');
    expect(alert.exists()).toBe(true);
    expect(alert.text()).toContain('这张表还没有发布过。正式生成使用已发布的版本：请先点击下方「动态发布」发布一次，再预览或生成代码。');
    expect(wrapper.text()).not.toContain('请求 ID');
    expect(wrapper.text()).not.toContain('表单尚未发布');
    expect(button(wrapper, 'repreview').exists()).toBe(true);
    expect(button(wrapper, 'repreview').attributes('disabled')).toBeUndefined();
    expect(button(wrapper, 'dynamic-publish').attributes('disabled')).toBeUndefined();
    expect(button(wrapper, 'generate').attributes('disabled')).toBeDefined();
  });

  it('表单从未发布：在弹窗内动态发布成功后自动重新预览', async () => {
    mocks.previewFormalGeneration.mockRejectedValueOnce(notPublishedErrors.mapped);
    mocks.module.mockResolvedValue({ module: coreModule, form: { schema_document: { key: 'shop_goods' }, schema_hash: 'h1', publish_config: {} } });
    mocks.previewPublish.mockResolvedValue({ formDependencyHash: 'dep' });
    mocks.publish.mockResolvedValue({});
    const wrapper = render();
    await flushPromises();
    await button(wrapper, 'dynamic-publish').trigger('click');
    await flushPromises();
    expect(mocks.publish).toHaveBeenCalledTimes(1);
    expect(mocks.previewFormalGeneration).toHaveBeenCalledTimes(2);
    expect(wrapper.find('[data-preview-unpublished]').exists()).toBe(false);
    expect(wrapper.find('[data-file]').exists()).toBe(true);
  });

  it('表单从未发布的插件目标：提示先在表单设计器保存并发布', async () => {
    mocks.previewFormalGeneration.mockRejectedValueOnce(notPublishedErrors.mapped);
    const wrapper = render(pluginModule);
    await flushPromises();
    expect(wrapper.find('[data-preview-unpublished]').text()).toContain('请先在表单设计器保存并发布后再预览。');
    expect(button(wrapper, 'dynamic-publish').exists()).toBe(false);
  });

  it('其他 VALIDATION_ERROR 仍显示原始错误与请求 ID', async () => {
    mocks.previewFormalGeneration.mockRejectedValueOnce({ code: 422, msg: '字段配置不合法', data: { error: { code: 'VALIDATION_ERROR', requestId: 'req-x', retryable: false, details: {} } } });
    const wrapper = render();
    await flushPromises();
    expect(wrapper.find('[data-preview-unpublished]').exists()).toBe(false);
    expect(wrapper.text()).toContain('字段配置不合法（请求 ID：req-x）');
    expect(button(wrapper, 'dynamic-publish').attributes('disabled')).toBeDefined();
  });

  it('「下载 zip」复用当前预览的 nonce，重新预览后改用新 nonce（不让已打开的生成计划失效）', async () => {
    mocks.formalGenerationArchive.mockResolvedValue(new Blob(['zip']));
    vi.stubGlobal('URL', Object.assign(URL, { createObjectURL: vi.fn(() => 'blob:zip'), revokeObjectURL: vi.fn() }));
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => undefined);
    const nonces = ['nonce-a', 'nonce-b'];
    vi.stubGlobal('crypto', { randomUUID: vi.fn(() => nonces.shift()) });
    const wrapper = render();
    await flushPromises();
    await button(wrapper, 'download').trigger('click');
    await flushPromises();
    expect(mocks.formalGenerationArchive).toHaveBeenLastCalledWith(7, 'nonce-a');
    await button(wrapper, 'repreview').trigger('click');
    await flushPromises();
    await button(wrapper, 'download').trigger('click');
    await flushPromises();
    expect(mocks.previewFormalGeneration).toHaveBeenLastCalledWith(7, 'nonce-b');
    expect(mocks.formalGenerationArchive).toHaveBeenLastCalledWith(7, 'nonce-b');
    click.mockRestore();
  });

  it('「下载 zip」同时需要生成权限与下载权限', async () => {
    mocks.permissions = ['development:business:preview-formal-generation', 'development:business:generate'];
    const withoutArchive = render();
    await flushPromises();
    expect(button(withoutArchive, 'download').exists()).toBe(false);
    mocks.permissions = ['development:business:preview-formal-generation', 'development:business:formal-generation-archive'];
    const withoutGenerate = render();
    await flushPromises();
    expect(button(withoutGenerate, 'download').exists()).toBe(false);
    mocks.permissions = ['development:business:preview-formal-generation', 'development:business:generate', 'development:business:formal-generation-archive'];
    const both = render();
    await flushPromises();
    expect(button(both, 'download').exists()).toBe(true);
  });

  it('下载失败（服务端返回业务错误）时提示消息且不触发文件下载', async () => {
    mocks.formalGenerationArchive.mockRejectedValue({ code: 422, msg: '没有可打包的生成文件', data: { error: { code: 'VALIDATION_ERROR', requestId: 'req-z', retryable: false, details: {} } } });
    const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => undefined);
    const wrapper = render();
    await flushPromises();
    await button(wrapper, 'download').trigger('click');
    await flushPromises();
    expect(mocks.message.error).toHaveBeenCalledWith('没有可打包的生成文件（请求 ID：req-z）');
    expect(click).not.toHaveBeenCalled();
    click.mockRestore();
  });

  describe('核心目标预览基于已发布版本', () => {
    const STALE = '预览基于已发布的版本；已保存但未发布的字段改动需要先「动态发布」后才会出现在生成代码里。';

    it('已保存 Schema 与预览所用的已发布 Schema 不同：显示说明与「动态发布」，发布成功后重新预览', async () => {
      mocks.module.mockResolvedValue({ module: coreModule, fields: [], form: { id: 3, schema_document: { key: 'shop_goods' }, schema_hash: 'saved-2', published_schema_hash: 's', publish_config: {} } });
      mocks.previewPublish.mockResolvedValue({ formDependencyHash: 'dep' });
      mocks.publish.mockResolvedValue({});
      const wrapper = render();
      await flushPromises();
      const banner = wrapper.find('[data-preview-stale]');
      expect(banner.exists()).toBe(true);
      expect(banner.text()).toContain(STALE);
      expect(banner.find('[data-action="stale-dynamic-publish"]').exists()).toBe(true);
      mocks.module.mockResolvedValue({ module: coreModule, fields: [], form: { id: 3, schema_document: { key: 'shop_goods' }, schema_hash: 'saved-2', published_schema_hash: 'saved-2', publish_config: {} } });
      mocks.previewFormalGeneration.mockResolvedValue(preview({ schemaHash: 'saved-2' }));
      await banner.find('[data-action="stale-dynamic-publish"]').trigger('click');
      await flushPromises();
      expect(mocks.publish).toHaveBeenCalledTimes(1);
      expect(mocks.previewFormalGeneration).toHaveBeenCalledTimes(2);
      expect(wrapper.find('[data-preview-stale]').exists()).toBe(false);
    });

    it('已保存 Schema 即预览所用版本时不显示说明', async () => {
      mocks.module.mockResolvedValue({ module: coreModule, fields: [], form: { id: 3, schema_hash: 's', published_schema_hash: 's', publish_config: {} } });
      const wrapper = render();
      await flushPromises();
      expect(wrapper.find('[data-preview-stale]').exists()).toBe(false);
    });

    it('计划被阻断（无 schemaHash）时按已发布 hash 判断', async () => {
      mocks.module.mockResolvedValue({ module: coreModule, fields: [], form: { id: 3, schema_hash: 'saved-2', published_schema_hash: 'old-1', publish_config: {} } });
      mocks.previewFormalGeneration.mockResolvedValue(preview({ schemaHash: undefined, sensitive: undefined, plan: { blocked: true, files: [] } }));
      const wrapper = render();
      await flushPromises();
      expect(wrapper.find('[data-preview-stale]').exists()).toBe(true);
    });

    it('插件目标读取草稿 Schema，不显示说明', async () => {
      mocks.module.mockResolvedValue({ module: pluginModule, fields: [], form: { id: 3, schema_hash: 'saved-2', published_schema_hash: 'old-1', publish_config: {} } });
      const wrapper = render(pluginModule);
      await flushPromises();
      expect(wrapper.find('[data-preview-stale]').exists()).toBe(false);
    });
  });

  describe('conflict-no-base 采纳已解决', () => {
    const HASH = 'a'.repeat(64);
    function noBase(localHash: string) {
      const file = { path: VIEW, status: 'conflict-no-base' as const, localHash, remoteHash: HASH, localContent: '<template />', remoteContent: '<template />' };
      return preview({ sensitive: undefined, schemaHash: undefined, plan: { blocked: true, files: [file] }, conflicts: [file] });
    }

    it('本地内容已与生成内容一致：「与本地对比」中可采纳，确认后提交 hash 并重新预览', async () => {
      mocks.previewFormalGeneration.mockResolvedValueOnce(noBase(HASH));
      mocks.adoptResolvedBaseline.mockResolvedValue({});
      const wrapper = render();
      await flushPromises();
      expect(button(wrapper, 'adopt-resolved').exists()).toBe(false);
      await wrapper.find('[data-view="diff"]').trigger('click');
      expect(button(wrapper, 'adopt-resolved').exists()).toBe(true);
      await button(wrapper, 'adopt-resolved').trigger('click');
      await flushPromises();
      expect(mocks.confirm).toHaveBeenCalledTimes(1);
      expect(mocks.adoptResolvedBaseline).toHaveBeenCalledWith(7, { generationId: 42, path: VIEW, localHash: HASH, remoteHash: HASH });
      expect(mocks.previewFormalGeneration).toHaveBeenCalledTimes(2);
    });

    it('取消确认时不提交', async () => {
      mocks.previewFormalGeneration.mockResolvedValueOnce(noBase(HASH));
      mocks.confirm.mockRejectedValueOnce('cancel');
      const wrapper = render();
      await flushPromises();
      await wrapper.find('[data-view="diff"]').trigger('click');
      await button(wrapper, 'adopt-resolved').trigger('click');
      await flushPromises();
      expect(mocks.adoptResolvedBaseline).not.toHaveBeenCalled();
    });

    it('本地内容与生成内容仍不同：不提供采纳，提示先在本地改成生成内容', async () => {
      mocks.previewFormalGeneration.mockResolvedValueOnce(noBase('b'.repeat(64)));
      const wrapper = render();
      await flushPromises();
      await wrapper.find('[data-view="diff"]').trigger('click');
      expect(button(wrapper, 'adopt-resolved').exists()).toBe(false);
      expect(wrapper.find('[data-adopt-hint]').text()).toContain('改成与生成内容一致后点击「重新预览」');
    });

    it('没有采纳权限时不显示采纳按钮', async () => {
      mocks.permissions = ['development:business:preview-formal-generation', 'development:business:generate'];
      mocks.previewFormalGeneration.mockResolvedValueOnce(noBase(HASH));
      const wrapper = render();
      await flushPromises();
      await wrapper.find('[data-view="diff"]').trigger('click');
      expect(button(wrapper, 'adopt-resolved').exists()).toBe(false);
    });
  });

  it('全屏按钮切换 el-dialog 的 fullscreen', async () => {
    const wrapper = render();
    await flushPromises();
    expect(wrapper.find('[role="dialog"]').attributes('data-fullscreen')).toBe('false');
    await button(wrapper, 'fullscreen').trigger('click');
    expect(wrapper.find('[role="dialog"]').attributes('data-fullscreen')).toBe('true');
  });
});
