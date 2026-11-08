import { defineComponent } from 'vue';
import { mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import DesignerTopBar from './DesignerTopBar.vue';
import { saveIndicator, shortcutAction } from './designerTopBar';

const stubs = {
  ElButton: defineComponent({
    inheritAttrs: false,
    props: { disabled: Boolean, loading: Boolean, type: String, plain: Boolean, text: Boolean },
    emits: ['click'],
    template: '<button v-bind="$attrs" type="button" :data-type="type" :data-plain="plain ? \'true\' : \'false\'" :disabled="disabled || loading" @click="$emit(\'click\')"><slot /></button>'
  }),
  ElTooltip: defineComponent({ props: ['content', 'disabled'], template: '<span :data-tooltip="disabled ? \'\' : content"><slot /></span>' }),
  ElDropdown: defineComponent({ template: '<div data-dropdown><slot /><div data-dropdown-menu><slot name="dropdown" /></div></div>' }),
  ElDropdownMenu: defineComponent({ template: '<ul><slot /></ul>' }),
  ElDropdownItem: defineComponent({
    inheritAttrs: false,
    props: ['disabled', 'divided'],
    emits: ['click'],
    template: '<li><button v-bind="$attrs" type="button" :disabled="disabled" @click="$emit(\'click\')"><slot /></button></li>'
  })
};

const baseProps = {
  title: '订单',
  formKey: 'order',
  tableName: 'fun_order',
  targetLabel: '核心后台',
  canUndo: true,
  canRedo: true,
  mode: 'edit' as const,
  saveStatus: 'saved' as const,
  online: true,
  saveBlocked: false,
  actionsDisabled: false,
  canSettings: true,
  canPreview: true,
  canGenerate: true,
  showDynamicPublish: true,
  canVersions: true,
  canExport: true,
  canOpenRuntime: true
};

let wrapper: VueWrapper | undefined;
function render(props: Record<string, unknown> = {}) {
  wrapper = mount(DesignerTopBar, { props: { ...baseProps, ...props }, global: { stubs }, attachTo: document.body });
  return wrapper;
}
const action = (name: string) => wrapper!.find(`[data-action="${name}"]`);
const press = (init: KeyboardEventInit, target: EventTarget = document.body) => {
  const event = new KeyboardEvent('keydown', { bubbles: true, cancelable: true, ...init });
  target.dispatchEvent(event);
  return event;
};

afterEach(() => {
  wrapper?.unmount();
  wrapper = undefined;
  document.body.innerHTML = '';
});

describe('saveIndicator', () => {
  it('按保存状态、离线与冲突暂停映射状态点颜色', () => {
    expect(saveIndicator({ saveStatus: 'saved', online: true, saveBlocked: false }).tone).toBe('success');
    expect(saveIndicator({ saveStatus: 'unsaved', online: true, saveBlocked: false }).tone).toBe('warning');
    expect(saveIndicator({ saveStatus: 'saving', online: true, saveBlocked: false }).tone).toBe('warning');
    expect(saveIndicator({ saveStatus: 'failed', online: true, saveBlocked: false }).tone).toBe('danger');
    expect(saveIndicator({ saveStatus: 'unsaved', online: false, saveBlocked: false }).tone).toBe('info');
    // 冲突暂停优先于离线：必须提示用户去核对版本。
    expect(saveIndicator({ saveStatus: 'failed', online: false, saveBlocked: true })).toEqual({ tone: 'danger', key: 'paused' });
  });
});

describe('shortcutAction', () => {
  it('⌘Z / Ctrl+Z 撤销，⇧⌘Z / Ctrl+Shift+Z 重做，其余按键忽略', () => {
    expect(shortcutAction(new KeyboardEvent('keydown', { key: 'z', metaKey: true }))).toBe('undo');
    expect(shortcutAction(new KeyboardEvent('keydown', { key: 'z', ctrlKey: true }))).toBe('undo');
    expect(shortcutAction(new KeyboardEvent('keydown', { key: 'Z', metaKey: true, shiftKey: true }))).toBe('redo');
    expect(shortcutAction(new KeyboardEvent('keydown', { key: 'Z', ctrlKey: true, shiftKey: true }))).toBe('redo');
    expect(shortcutAction(new KeyboardEvent('keydown', { key: 'z' }))).toBeNull();
    expect(shortcutAction(new KeyboardEvent('keydown', { key: 'z', ctrlKey: true, altKey: true }))).toBeNull();
    expect(shortcutAction(new KeyboardEvent('keydown', { key: 's', metaKey: true }))).toBeNull();
  });
});

describe('DesignerTopBar', () => {
  it('展示返回、标题与 key → 表名 · 生成目标、撤销重做、四段模式切换和主次按钮', () => {
    render();
    expect(action('back-to-list').exists()).toBe(true);
    expect(wrapper!.find('[data-topbar-title]').text()).toBe('订单');
    expect(wrapper!.find('[data-topbar-meta]').text()).toBe('order → fun_order · 核心后台');
    expect(action('undo').exists()).toBe(true);
    expect(action('redo').exists()).toBe(true);
    expect(wrapper!.findAll('[data-mode]').map((item) => item.text())).toEqual(['编辑', '桌面', '平板', '手机']);
    expect(action('settings').text()).toContain('表单设置');
    expect(action('more').exists()).toBe(true);
    // 只有「生成」是实心主按钮，「预览代码」为次按钮。
    expect(action('preview-code').attributes('data-plain')).toBe('true');
    expect(action('generate').attributes('data-plain')).toBe('false');
    expect(action('generate').attributes('data-type')).toBe('primary');
    expect(wrapper!.findAll('button[data-type="primary"][data-plain="false"]')).toHaveLength(1);
  });

  it('状态点按保存状态着色并显示文字', async () => {
    render({ saveStatus: 'saved' });
    const status = () => wrapper!.find('[data-save-status]');
    expect(status().attributes('data-tone')).toBe('success');
    expect(status().text()).toBe('已保存');
    await wrapper!.setProps({ saveStatus: 'unsaved' });
    expect(status().attributes('data-tone')).toBe('warning');
    expect(status().text()).toBe('未保存');
    await wrapper!.setProps({ saveStatus: 'failed' });
    expect(status().attributes('data-tone')).toBe('danger');
    await wrapper!.setProps({ saveStatus: 'unsaved', online: false });
    expect(status().attributes('data-tone')).toBe('info');
    expect(status().text()).toBe('离线草稿');
    await wrapper!.setProps({ saveBlocked: true });
    expect(status().attributes('data-tone')).toBe('danger');
    expect(status().text()).toBe('保存已暂停');
  });

  it('保存失败时状态显示服务端错误信息（title 与提示气泡），其他状态不显示', async () => {
    render({ saveStatus: 'failed', saveError: '字段映射不能为空（请求 ID：r2）' });
    const status = () => wrapper!.find('[data-save-status]');
    expect(status().text()).toContain('保存失败');
    expect(status().attributes('title')).toBe('字段映射不能为空（请求 ID：r2）');
    const tooltip = () => status().element.closest('[data-tooltip]')?.getAttribute('data-tooltip');
    expect(tooltip()).toBe('字段映射不能为空（请求 ID：r2）');
    expect(status().find('[data-save-error-icon]').exists()).toBe(true);
    await wrapper!.setProps({ saveStatus: 'saving' });
    expect(status().attributes('title')).toBeUndefined();
    expect(tooltip()).toBe('');
    expect(status().find('[data-save-error-icon]').exists()).toBe(false);
    await wrapper!.setProps({ saveStatus: 'failed', saveError: '' });
    expect(status().attributes('title')).toBeUndefined();
  });

  it('点击按钮发出对应事件，模式切换发出 update:mode', async () => {
    render();
    for (const [name, event] of [['back-to-list', 'back'], ['undo', 'undo'], ['redo', 'redo'], ['settings', 'settings'], ['preview-code', 'preview'], ['generate', 'generate']] as const) {
      await action(name).trigger('click');
      expect(wrapper!.emitted(event), name).toHaveLength(1);
    }
    await wrapper!.find('[data-mode="mobile"]').trigger('click');
    expect(wrapper!.emitted('update:mode')?.[0]).toEqual(['mobile']);
    expect(wrapper!.find('[data-mode="edit"]').attributes('aria-checked')).toBe('true');
  });

  it('撤销/重做按可用性禁用', () => {
    render({ canUndo: false, canRedo: false });
    expect(action('undo').attributes('disabled')).toBeDefined();
    expect(action('redo').attributes('disabled')).toBeDefined();
  });

  it('dirty 或冲突时「预览代码」「生成」禁用', () => {
    render({ actionsDisabled: true });
    expect(action('preview-code').attributes('disabled')).toBeDefined();
    expect(action('generate').attributes('disabled')).toBeDefined();
  });

  it('无权限时不显示预览代码、生成与表单设置', () => {
    render({ canPreview: false, canGenerate: false, canSettings: false });
    expect(action('preview-code').exists()).toBe(false);
    expect(action('generate').exists()).toBe(false);
    expect(action('settings').exists()).toBe(false);
  });

  it('「⋯」菜单含动态发布、版本历史、导出 JSON、打开运行时，并发出对应事件', async () => {
    render();
    const menu = wrapper!.find('[data-dropdown-menu]');
    expect(menu.findAll('button').map((item) => item.text())).toEqual(['动态发布', '版本历史', '导出 JSON', '打开运行时']);
    for (const [name, event] of [['dynamic-publish', 'dynamic-publish'], ['versions', 'versions'], ['export-json', 'export'], ['open-runtime', 'runtime']] as const) {
      await action(name).trigger('click');
      expect(wrapper!.emitted(event), name).toHaveLength(1);
    }
  });

  it('插件目标隐藏「动态发布」；禁用的菜单项不发事件', async () => {
    render({ showDynamicPublish: false, versionsDisabled: true });
    expect(action('dynamic-publish').exists()).toBe(false);
    expect(action('versions').attributes('disabled')).toBeDefined();
  });

  it('生成目标提示放在「生成」按钮的提示气泡里', () => {
    render({ generateHint: '使用已保存草稿生成源码' });
    expect(action('generate').element.closest('[data-tooltip]')?.getAttribute('data-tooltip')).toBe('使用已保存草稿生成源码');
  });

  it('⌘Z 撤销、⇧⌘Z 重做；焦点在输入框或可编辑区域时忽略', () => {
    render();
    const undo = press({ key: 'z', metaKey: true });
    expect(wrapper!.emitted('undo')).toHaveLength(1);
    expect(undo.defaultPrevented).toBe(true);
    press({ key: 'z', ctrlKey: true, shiftKey: true });
    expect(wrapper!.emitted('redo')).toHaveLength(1);
    for (const tag of ['input', 'textarea']) {
      const field = document.createElement(tag);
      document.body.appendChild(field);
      const event = press({ key: 'z', metaKey: true }, field);
      expect(event.defaultPrevented, tag).toBe(false);
    }
    const editable = document.createElement('div');
    editable.setAttribute('contenteditable', 'true');
    document.body.appendChild(editable);
    press({ key: 'z', ctrlKey: true }, editable);
    expect(wrapper!.emitted('undo')).toHaveLength(1);
  });

  it('不可撤销/重做时快捷键不发事件，卸载后移除监听', () => {
    render({ canUndo: false, canRedo: false });
    press({ key: 'z', metaKey: true });
    press({ key: 'z', metaKey: true, shiftKey: true });
    expect(wrapper!.emitted('undo')).toBeUndefined();
    expect(wrapper!.emitted('redo')).toBeUndefined();
    const mounted = wrapper!;
    mounted.unmount();
    wrapper = undefined;
    const event = press({ key: 'z', metaKey: true });
    expect(event.defaultPrevented).toBe(false);
  });
});
