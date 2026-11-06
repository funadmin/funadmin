import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, shallowMount } from '@vue/test-utils';
import { reactive } from 'vue';
import { ElMessage } from 'element-plus';
import AttachmentPage from './index.vue';

const api = vi.hoisted(() => ({ list: vi.fn(), tree: vi.fn(), settings: vi.fn(), upload: vi.fn() }));
const user = reactive({ permissions: [] as string[] });
vi.mock('@/api/system/attachment', () => ({ attachmentApi: { list: api.list }, attachmentGroupApi: { tree: api.tree } }));
vi.mock('@/api/system/storage', () => ({ storageApi: { settings: api.settings } }));
vi.mock('@/api/common/upload', () => ({ uploadApi: { upload: api.upload } }));
vi.mock('@/store/modules/user', () => ({ useUserStore: () => user }));
vi.mock('./components/AttachmentGroupDialog.vue', () => ({ default: { render: () => null } }));
beforeEach(() => {
  vi.clearAllMocks();
  user.permissions = ['system:admin-upload:upload'];
  api.list.mockResolvedValue({ list: [], total: 0 });
  api.tree.mockResolvedValue([]);
  api.settings.mockResolvedValue({ driver: 'local', drivers: [], fallback: false });
  api.upload.mockResolvedValue({ reused: false, groupId: 1 });
});
afterEach(() => ElMessage.closeAll());
async function render() {
  const wrapper = shallowMount(AttachmentPage, { global: {
    stubs: Object.fromEntries(['PageWrapper', 'DataTableShell', 'SearchForm', 'ElCard', 'ElButton', 'ElTree', 'ElDropdown', 'ElDropdownMenu', 'ElDropdownItem', 'ElFormItem', 'ElInput', 'ElSelect', 'ElOption', 'ElTable', 'ElTableColumn', 'ElImage', 'ElPagination'].map(name => [name, true])),
    directives: { perm: {}, loading: {} }
  } });
  await flushPromises();
  return wrapper;
}
function uploadEvent(files: File[]) {
  return { target: { files, value: 'selected' } } as unknown as Event;
}
describe('附件批量上传权限', () => {
  it('首文件等待期间撤权，停止上传后续文件', async () => {
    let completeFirst!: (value: { reused: boolean; groupId: number }) => void;
    api.upload.mockImplementationOnce(() => new Promise(resolve => { completeFirst = resolve; }));
    const wrapper = await render();
    const first = new File(['first'], 'first.txt', { type: 'text/plain' });
    const second = new File(['second'], 'second.txt', { type: 'text/plain' });
    const actions = (wrapper.vm as any).$.setupState as { uploadFiles(event: Event): Promise<void> };
    const pending = actions.uploadFiles(uploadEvent([first, second]));
    expect(api.upload).toHaveBeenCalledExactlyOnceWith(first, 'file', 1);
    user.permissions = [];
    completeFirst({ reused: false, groupId: 1 });
    await pending;
    expect(api.upload).toHaveBeenCalledTimes(1);
    wrapper.unmount();
  });
  it('权限保留时继续上传所有文件', async () => {
    const wrapper = await render();
    const actions = (wrapper.vm as any).$.setupState as { uploadFiles(event: Event): Promise<void> };
    await actions.uploadFiles(uploadEvent([new File(['a'], 'a.txt'), new File(['b'], 'b.txt')]));
    expect(api.upload).toHaveBeenCalledTimes(2);
    wrapper.unmount();
  });
});
