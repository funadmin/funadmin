import { computed, defineComponent, inject, provide, type ComputedRef, type PropType } from 'vue';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import RoleWorkspace from './index.vue';
import RoleAuthorizationPanel from './components/RoleAuthorizationPanel.vue';
import type { RoleAuthorization, RoleModel } from '@/api/system/role';

const mocks = vi.hoisted(() => ({
  all: vi.fn(),
  authorization: vi.fn(),
  saveAuthorization: vi.fn(),
  copyAuthorization: vi.fn(),
  permissions: ['system:role:perm', 'system:role:perm-copy', 'system:dept:list']
}));

vi.mock('@/api/system/role', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/api/system/role')>();
  return {
    ...actual,
    roleApi: {
      ...actual.roleApi,
      all: mocks.all,
      authorization: mocks.authorization,
      saveAuthorization: mocks.saveAuthorization,
      copyAuthorization: mocks.copyAuthorization
    }
  };
});

vi.mock('@/api/system/dept', () => ({ deptApi: { tree: vi.fn().mockResolvedValue([]) } }));
vi.mock('@/store/modules/user', () => ({
  useUserStore: () => ({ permissions: mocks.permissions })
}));
vi.mock('element-plus', () => ({ ElMessageBox: { confirm: vi.fn().mockResolvedValue(undefined) } }));

interface TabsContext {
  active: ComputedRef<string>;
  select: (name: string) => void;
}
interface RadioContext {
  value: ComputedRef<string | number | undefined>;
  select: (value: string) => void;
}
const tabsKey = Symbol('tabs');
const tableKey = Symbol('table');
const radioKey = Symbol('radio');

const ElTabsStub = defineComponent({
  props: { modelValue: { type: String, required: true } },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    provide<TabsContext>(tabsKey, {
      active: computed(() => props.modelValue),
      select: (name) => emit('update:modelValue', name)
    });
  },
  template: '<div class="tabs"><slot /></div>'
});
const ElTabPaneStub = defineComponent({
  props: { label: { type: String, required: true }, name: { type: String, required: true } },
  setup() { return { tabs: inject<TabsContext>(tabsKey)! }; },
  template: '<section><button type="button" :data-tab="name" @click="tabs.select(name)">{{ label }}</button><div v-if="tabs.active.value === name" :data-pane="name"><slot /></div></section>'
});
const ElTableStub = defineComponent({
  props: { data: { type: Array as PropType<Record<string, unknown>[]>, default: () => [] } },
  setup(props) { provide(tableKey, computed(() => props.data)); },
  template: '<div class="table"><slot /></div>'
});
const ElTableColumnStub = defineComponent({
  props: { prop: String, label: String },
  setup() { return { rows: inject<ComputedRef<Record<string, unknown>[]>>(tableKey)! }; },
  template: '<div class="column"><span v-if="label">{{ label }}</span><div v-for="(row, index) in rows" :key="index"><slot :row="row">{{ prop ? row[prop] : "" }}</slot></div></div>'
});
const ElCheckboxStub = defineComponent({
  props: { modelValue: Boolean, disabled: Boolean },
  emits: ['change'],
  template: '<button type="button" class="checkbox" :aria-pressed="modelValue" :disabled="disabled" @click="$emit(\'change\', !modelValue)"><slot /></button>'
});
const ElRadioGroupStub = defineComponent({
  props: { modelValue: [String, Number] },
  emits: ['update:modelValue'],
  setup(props, { emit }) {
    provide(radioKey, {
      value: computed(() => props.modelValue),
      select: (value: string) => emit('update:modelValue', value)
    });
  },
  template: '<div class="radio-group"><slot /></div>'
});
const ElRadioStub = defineComponent({
  props: { value: { type: String, required: true } },
  setup() { return { group: inject<RadioContext>(radioKey)! }; },
  template: '<button type="button" :data-radio="value" :aria-pressed="group.value === value" @click="group.select(value)"><slot /></button>'
});
const ElTreeStub = defineComponent({
  props: { data: Array },
  emits: ['check', 'node-click', 'node-contextmenu'],
  methods: { setCurrentKey() {}, setCheckedKeys() {}, getCheckedKeys() { return []; } },
  template: '<div class="tree"><button v-for="item in data" :key="item.id" type="button" class="tree-node" :data-role-id="item.id" @click="$emit(\'node-click\', item)" @contextmenu.prevent="$emit(\'node-contextmenu\', $event, item)"><slot :data="item" /></button></div>'
});
const passthrough = defineComponent({ template: '<div><slot name="extra" /><slot /></div>' });
const collapseItem = defineComponent({ template: '<section><slot name="title" /><slot /></section>' });
const commonStubs = {
  PageWrapper: passthrough,
  ElTabs: ElTabsStub,
  ElTabPane: ElTabPaneStub,
  ElTable: ElTableStub,
  ElTableColumn: ElTableColumnStub,
  ElCheckbox: ElCheckboxStub,
  ElRadioGroup: ElRadioGroupStub,
  ElRadio: ElRadioStub,
  ElTree: ElTreeStub,
  ElCollapse: passthrough,
  ElCollapseItem: collapseItem,
  ElTooltip: passthrough,
  ElTimeline: passthrough,
  ElTimelineItem: passthrough,
  ElTag: passthrough,
  ElButton: defineComponent({ emits: ['click'], template: '<button type="button" @click="$emit(\'click\')"><slot /></button>' }),
  ElForm: passthrough,
  ElFormItem: passthrough,
  ElRow: passthrough,
  ElCol: passthrough,
  ElSelect: passthrough,
  ElOption: passthrough,
  ElInput: passthrough,
  ElInputNumber: passthrough,
  ElRadioButton: passthrough,
  ElTreeSelect: passthrough,
  ElEmpty: passthrough,
  ElAlert: passthrough
};

const role: RoleModel = {
  id: 30,
  name: '运营角色',
  code: 'operator',
  level: 3,
  dataScope: 'self',
  status: 1,
  parentId: 10,
  parentRoleIds: [20],
  departmentIds: [],
  permissionIds: []
};
const createAuthorization = (): RoleAuthorization => ({
  roleId: 30,
  roles: [{ id: 10, parentId: 0, name: '主父角色' }, { id: 20, parentId: 0, name: '额外父角色' }],
  permissionGroups: [{
    id: 1,
    name: '系统管理',
    resources: [{
      key: 'member',
      name: '成员资源',
      actions: [
        { id: 101, name: '查看成员', code: 'member:list', direct: false, inherited: false, inheritedFrom: [] },
        { id: 102, name: '删除成员', code: 'member:delete', direct: false, inherited: true, inheritedFrom: [{ roleId: 10, roleName: '主父角色' }] }
      ]
    }]
  }],
  fields: [{
    id: 201,
    permissionId: 101,
    resource: '成员资源',
    field: 'mobile',
    name: '手机号字段',
    view: false,
    edit: false,
    inheritedView: false,
    inheritedEdit: false,
    inheritedFrom: []
  }],
  dataScope: 'self',
  departmentIds: [],
  departmentTree: [{ id: 9, parentId: 0, name: '研发部门' }],
  effectivePermissionIds: [102],
  inheritance: {
    directParents: [
      { roleId: 10, roleName: '主父角色', relation: 'primary' },
      { roleId: 20, roleName: '额外父角色', relation: 'additional' }
    ],
    ancestors: [{ roleId: 1, roleName: '祖先角色', sourceRoleIds: [10, 20], sourceRoleNames: ['主父角色', '额外父角色'] }]
  }
});

async function mountWorkspace(): Promise<VueWrapper> {
  const wrapper = mount(RoleWorkspace, {
    attachTo: document.body,
    global: { stubs: commonStubs, directives: { perm: {}, loading: {} } }
  });
  await flushPromises();
  return wrapper;
}
async function selectTab(wrapper: VueWrapper, name: string) {
  await wrapper.get(`[data-tab="${name}"]`).trigger('click');
  await flushPromises();
}
function deferred<T>() {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>((done) => { resolve = done; });
  return { promise, resolve };
}
function authorizationWithResource(roleId: number, name: string): RoleAuthorization {
  const detail = createAuthorization();
  detail.roleId = roleId;
  detail.permissionGroups[0].resources[0].name = name;
  return detail;
}

describe('角色授权工作区 mount 行为', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mocks.permissions.splice(0, mocks.permissions.length, 'system:role:perm', 'system:role:perm-copy', 'system:dept:list');
    mocks.all.mockResolvedValue([role]);
    mocks.authorization.mockImplementation(async () => createAuthorization());
    mocks.saveAuthorization.mockResolvedValue(undefined);
    mocks.copyAuthorization.mockResolvedValue(undefined);
  });

  it('按需加载一次授权，跨页签保留修改并保存完整授权 payload，同时展示继承来源', async () => {
    const wrapper = await mountWorkspace();
    expect(mocks.all).toHaveBeenCalledTimes(1);
    expect(mocks.authorization).not.toHaveBeenCalled();

    await selectTab(wrapper, 'permissions');
    expect(mocks.authorization).toHaveBeenCalledTimes(1);
    expect(wrapper.text()).toContain('成员资源');
    const permissionCheckbox = wrapper.findAll('.checkbox').find((item) => item.text().includes('查看成员'))!;
    await permissionCheckbox.trigger('click');
    expect(permissionCheckbox.attributes('aria-pressed')).toBe('true');

    await selectTab(wrapper, 'fields');
    expect(wrapper.text()).toContain('手机号字段');
    expect(mocks.authorization).toHaveBeenCalledTimes(1);
    const fieldEditCheckbox = wrapper.findAll('.checkbox').find((item) => item.text().includes('编辑'))!;
    await fieldEditCheckbox.trigger('click');

    await selectTab(wrapper, 'data');
    await wrapper.get('[data-radio="custom"]').trigger('click');
    await wrapper.findComponent(RoleAuthorizationPanel).getComponent(ElTreeStub).vm.$emit('check', { id: 9 }, { checkedKeys: [9] });
    await selectTab(wrapper, 'permissions');
    expect(wrapper.findAll('.checkbox').find((item) => item.text().includes('查看成员'))!.attributes('aria-pressed')).toBe('true');
    expect(mocks.authorization).toHaveBeenCalledTimes(1);

    await selectTab(wrapper, 'inheritance');
    for (const text of ['主父角色', '额外父角色', '祖先角色', '来源：主父角色、额外父角色']) expect(wrapper.text()).toContain(text);
    expect(mocks.authorization).toHaveBeenCalledTimes(1);

    await selectTab(wrapper, 'data');
    await wrapper.findComponent(RoleAuthorizationPanel).get('.role-authorization-panel__footer button').trigger('click');
    await flushPromises();
    expect(mocks.saveAuthorization).toHaveBeenCalledWith(30, {
      permissionIds: [101],
      fieldPermissions: [{ fieldId: 201, view: true, edit: true }],
      dataScope: 'custom',
      departmentIds: [9]
    });
  });

  it('右键切换授权角色时丢弃旧缓存并加载新角色授权', async () => {
    const secondRole = { ...role, id: 40, name: '访客角色', code: 'guest' };
    mocks.all.mockResolvedValue([role, secondRole]);
    mocks.authorization.mockImplementation(async (roleId: number) => ({
      ...createAuthorization(),
      roleId,
      permissionGroups: [{
        ...createAuthorization().permissionGroups[0],
        resources: [{
          ...createAuthorization().permissionGroups[0].resources[0],
          name: roleId === 30 ? '运营权限' : '访客权限'
        }]
      }]
    }));
    const wrapper = await mountWorkspace();
    await selectTab(wrapper, 'permissions');
    expect(wrapper.text()).toContain('运营权限');

    await wrapper.get('[data-role-id="40"]').trigger('contextmenu');
    const permissionAction = document.querySelector<HTMLButtonElement>('[data-context-action="permission"]');
    expect(permissionAction).not.toBeNull();
    permissionAction!.click();
    await flushPromises();

    expect(mocks.authorization).toHaveBeenNthCalledWith(2, 40);
    expect(wrapper.text()).toContain('访客权限');
    expect(wrapper.text()).not.toContain('运营权限');
  });

  it('授权详情请求失败后再次进入页签会重新请求', async () => {
    mocks.authorization.mockRejectedValueOnce(new Error('temporary')).mockResolvedValueOnce(createAuthorization());
    const wrapper = await mountWorkspace();

    await selectTab(wrapper, 'permissions');
    await selectTab(wrapper, 'basic');
    await selectTab(wrapper, 'permissions');

    expect(mocks.authorization).toHaveBeenCalledTimes(2);
    expect(wrapper.text()).toContain('成员资源');
  });

  it('A→B→A 快速切换时忽略第一次 A 的过期响应', async () => {
    const secondRole = { ...role, id: 40, name: '访客角色', code: 'guest' };
    const firstA = deferred<RoleAuthorization>();
    const requestB = deferred<RoleAuthorization>();
    const latestA = deferred<RoleAuthorization>();
    mocks.all.mockResolvedValue([role, secondRole]);
    mocks.authorization
      .mockReturnValueOnce(firstA.promise)
      .mockReturnValueOnce(requestB.promise)
      .mockReturnValueOnce(latestA.promise);
    const wrapper = await mountWorkspace();

    await selectTab(wrapper, 'permissions');
    await wrapper.get('[data-role-id="40"]').trigger('click');
    await selectTab(wrapper, 'permissions');
    await wrapper.get('[data-role-id="30"]').trigger('click');
    await selectTab(wrapper, 'permissions');

    latestA.resolve(authorizationWithResource(30, '最新 A 权限'));
    await flushPromises();
    expect(wrapper.text()).toContain('最新 A 权限');

    firstA.resolve(authorizationWithResource(30, '过期 A 权限'));
    requestB.resolve(authorizationWithResource(40, 'B 权限'));
    await flushPromises();
    expect(wrapper.text()).toContain('最新 A 权限');
    expect(wrapper.text()).not.toContain('过期 A 权限');
  });

  it('无授权管理权限时不展示继承页签且不请求授权详情', async () => {
    mocks.permissions.splice(0, mocks.permissions.length, 'system:role:list');
    const wrapper = await mountWorkspace();

    expect(wrapper.find('[data-tab="permissions"]').exists()).toBe(false);
    expect(wrapper.find('[data-tab="inheritance"]').exists()).toBe(false);
    expect(mocks.authorization).not.toHaveBeenCalled();
  });
});