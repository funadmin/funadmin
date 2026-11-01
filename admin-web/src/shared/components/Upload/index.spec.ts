import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { ElImage, ElUpload } from 'element-plus';
import { afterEach, describe, expect, it, vi } from 'vitest';
import Upload from './index.vue';
import { uploadApi, type UploadResult } from '@/api/common/upload';

vi.mock('@/api/common/upload', () => ({ uploadApi: { upload: vi.fn() } }));

const file = (overrides: Partial<UploadResult> = {}): UploadResult => ({
  url: '/uploads/photo.png', name: 'photo.png', size: 1024, ext: 'png',
  groupId: 0, driver: 'local', reused: false, uploadedAt: 0, ...overrides
});
const wrappers: ReturnType<typeof mount>[] = [];
function render(files: UploadResult[], disabled = false) {
  const wrapper = mount(Upload, { props: { type: 'file', modelValue: files, disabled }, attachTo: document.body });
  wrappers.push(wrapper);
  return wrapper;
}
afterEach(() => {
  wrappers.splice(0).forEach(wrapper => wrapper.unmount());
  vi.restoreAllMocks();
});

describe('公共上传文件列表', () => {
  it('图片显示缩略图，点击后大图预览传送到 body，关闭不修改文件', async () => {
    const item = file();
    const wrapper = render([item]);
    const image = wrapper.findComponent(ElImage);
    expect(image.exists()).toBe(true);
    expect(image.props()).toMatchObject({ src: item.url, previewSrcList: [item.url], previewTeleported: true, fit: 'cover' });
    await nextTick();
    await image.find('img').trigger('load');
    await image.find('img').trigger('click');
    expect(document.body.querySelector('.el-image-viewer__wrapper')).not.toBeNull();
    expect(wrapper.find('.el-image-viewer__wrapper').exists()).toBe(false);
    (document.body.querySelector('.el-image-viewer__close') as HTMLElement).click();
    await nextTick();
    expect(document.body.querySelector('.el-image-viewer__wrapper')).toBeNull();
    expect(wrapper.emitted('update:modelValue')).toBeUndefined();
  });

  it.each([
    { name: '无后缀', ext: 'JPG', url: 'https://cdn.example/photo?signature=abc' },
    { name: 'PHOTO.WEBP', ext: '', url: '/uploads/opaque?signature=abc#preview' },
    { name: '无后缀', ext: '', url: '/uploads/PHOTO.JPEG?signature=abc#preview' },
    { name: 'photo.png', ext: 'png', url: 'blob:http://localhost/mock-image' },
    { name: 'photo.png', ext: 'png', url: 'data:image/png;base64,iVBORw0KGgo=' }
  ])('识别图片元数据和带签名 URL 且原样使用地址：$url', (item) => {
    const image = render([file(item)]).findComponent(ElImage);
    expect(image.exists()).toBe(true);
    expect(image.props('src')).toBe(item.url);
  });

  it.each([
    { name: 'report.pdf', ext: 'pdf', url: '/report.pdf?name=photo.png' },
    { name: 'vector.svg', ext: 'svg', url: '/vector.svg' },
    { url: '' },
    { url: 'javascript:alert(1)' },
    { url: 'data:text/html;base64,PHNjcmlwdD4=' },
    { url: 'data:image/svg+xml;base64,PHN2Zz4=' },
    { url: 'file:///tmp/photo.png' },
    { url: 'http://[' }
  ])('非图片、空地址或不安全地址不创建缩略图：$url', (item) => {
    const wrapper = render([file(item)]);
    expect(wrapper.findComponent(ElImage).exists()).toBe(false);
    expect(wrapper.find('.app-upload__file-row-icon').exists()).toBe(true);
  });

  it('保留名称、大小、已有安全预览按钮和删除行为', async () => {
    const items = [file(), file({ name: 'report.pdf', ext: 'pdf', url: '/report.pdf' })];
    const wrapper = render(items);
    const open = vi.spyOn(window, 'open').mockReturnValue(null);
    const rows = wrapper.findAll('.app-upload__file-row');
    expect(rows[1].text()).toContain('report.pdf');
    expect(rows[1].text()).toContain('1.0 KB');
    for (const [index, row] of rows.entries()) {
      await row.findAll('button')[0].trigger('click');
      expect(open).toHaveBeenLastCalledWith(items[index].url, '_blank', 'noopener,noreferrer');
    }
    await rows[0].findAll('button')[1].trigger('click');
    expect(wrapper.emitted('update:modelValue')).toEqual([[[items[1]]]]);
  });

  it('禁用时保留删除按钮禁用状态', () => {
    const wrapper = render([file()], true);
    expect(wrapper.findAll('.app-upload__file-row button')[1].attributes('disabled')).toBeDefined();
  });

  it('上传过程中保留加载状态并在完成后追加文件', async () => {
    const wrapper = render([]);
    let resolveUpload!: (item: UploadResult) => void;
    vi.mocked(uploadApi.upload).mockReturnValueOnce(new Promise(resolve => { resolveUpload = resolve; }));
    const request = wrapper.findComponent(ElUpload).props('httpRequest')!;
    const pending = request({ file: new File(['image'], 'photo.png') } as Parameters<typeof request>[0]);
    await nextTick();
    expect(wrapper.find('.app-upload__file-drop-icon.is-loading').exists()).toBe(true);
    resolveUpload(file());
    await pending;
    await nextTick();
    expect(wrapper.find('.app-upload__file-drop-icon.is-loading').exists()).toBe(false);
    expect(wrapper.emitted('update:modelValue')).toEqual([[[file()]]]);
  });
});
