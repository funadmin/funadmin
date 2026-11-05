import { describe, expect, it, vi } from 'vitest';
const http = vi.hoisted(() => ({ get: vi.fn().mockResolvedValue({}), post: vi.fn().mockResolvedValue({}) }));
vi.mock('@/utils/http', () => ({ default: http }));
describe('CMS plugin API', () => {
  it('keeps site/locale and draft publication as explicit distinct requests', async () => {
    const { cmsApi } = await import('../../../webman-admin/plugins/cms/admin-web/src/admin/modules/cms/api');
    await cmsApi.content({ site_id: '9007199254740993', locale: 'en-us' });
    expect(http.get).toHaveBeenCalledWith('/plugin/cms/content', { site_id: '9007199254740993', locale: 'en-us' });
    await cmsApi.saveContent({ site_id: '7', locale: 'zh-cn', title: 'Draft' });
    expect(http.post).toHaveBeenLastCalledWith('/plugin/cms/content/save', { site_id: '7', locale: 'zh-cn', title: 'Draft' });
    await cmsApi.publishContent({ site_id: '7', id: '8', revision_id: '9', lock_version: 2 });
    expect(http.post).toHaveBeenLastCalledWith('/plugin/cms/content/publish', { site_id: '7', id: '8', revision_id: '9', lock_version: 2 });
  });
});
