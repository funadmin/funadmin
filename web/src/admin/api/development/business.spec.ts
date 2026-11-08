import { beforeEach, describe, expect, it, vi } from 'vitest';

const httpMocks = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), delete: vi.fn(), download: vi.fn(), downloadPost: vi.fn() }));
vi.mock('@/utils/http', () => ({ default: httpMocks }));

import { businessDevelopmentApi, isBusinessApiError } from './business';

describe('businessDevelopmentApi', () => {
  beforeEach(() => vi.clearAllMocks());

  it('modules 透传生成目标筛选 target', async () => {
    httpMocks.get.mockResolvedValue({ list: [], total: 0, page: 1, pageSize: 20 });
    await businessDevelopmentApi.modules({ page: 1, pageSize: 20, target: 'plugin' });
    expect(httpMocks.get).toHaveBeenCalledWith('/development/business/modules', { page: 1, pageSize: 20, target: 'plugin' });
  });

  it('removeModule 默认提示成功与失败，批量删除可关闭逐条提示', async () => {
    httpMocks.delete.mockResolvedValue({ removed: 1 });
    await businessDevelopmentApi.removeModule(5);
    expect(httpMocks.delete).toHaveBeenLastCalledWith('/development/business/modules/5', undefined, { requestOptions: { showSuccessMsg: true, showErrorMsg: true } });
    await businessDevelopmentApi.removeModule(6, { showSuccessMsg: false, showErrorMsg: false });
    expect(httpMocks.delete).toHaveBeenLastCalledWith('/development/business/modules/6', undefined, { requestOptions: { showSuccessMsg: false, showErrorMsg: false } });
  });

  it('读取服务端目标候选与默认连接，不使用插件中心列表替代', async () => {
    const response = { list: [{ type: 'plugin', pluginCode: 'demo', name: '演示', scope: 'console' }], defaultConnection: 'mysql', migrationPath: 'database/migrations' };
    httpMocks.get.mockResolvedValue(response);
    expect(await businessDevelopmentApi.targets()).toEqual(response);
    expect(httpMocks.get).toHaveBeenCalledWith('/development/business/targets');
  });

  it('草稿目录按模块 ID 读取，不请求运行态或发送执行参数', async () => {
    httpMocks.get.mockResolvedValue({ moduleId: 12, designOnly: true, executable: false, actions: {}, resources: {} });
    await businessDevelopmentApi.designActionCatalog(12);
    expect(httpMocks.get).toHaveBeenCalledWith('/development/business/modules/12/schema/design-action-catalog');
    expect(httpMocks.post).not.toHaveBeenCalled();
  });

  it('recoverGeneration 发送 generation id 与 CAS recovery 状态', async () => {
    httpMocks.post.mockResolvedValue({ state: 'rolled_back' });
    await businessDevelopmentApi.recoverGeneration(41, 'recovery_required');
    expect(httpMocks.post).toHaveBeenCalledWith('/development/business/generations/41/recover', {
      expectedRecoveryStatus: 'recovery_required'
    });
  });

  it('rollbackSchema 发送当前 Schema hash 作为 CAS 前置条件', async () => {
    httpMocks.post.mockResolvedValue({ version: 8, schema_hash: 'next-hash' });
    await businessDevelopmentApi.rollbackSchema(12, 7, 'current-hash', '恢复稳定版本');
    expect(httpMocks.post).toHaveBeenCalledWith('/development/business/modules/12/schema/versions/7/rollback', {
      expectedSchemaHash: 'current-hash',
      summary: '恢复稳定版本'
    });
  });

  it('databaseSync 按模块 ID POST 数据库同步差异', async () => {
    const diff = { inspectionHash: 'h1', added: [], removed: [], changed: [] };
    httpMocks.post.mockResolvedValue(diff);
    expect(await businessDevelopmentApi.databaseSync(12)).toBe(diff);
    expect(httpMocks.post).toHaveBeenCalledWith('/development/business/modules/12/schema/database-sync');
  });

  it('formalGenerationArchive 以带 CSRF 的 POST 下载 zip Blob，并透传预览 nonce', async () => {
    const blob = new Blob(['zip'], { type: 'application/zip' });
    httpMocks.downloadPost.mockResolvedValue(blob);
    expect(await businessDevelopmentApi.formalGenerationArchive(12, 'nonce-1')).toBe(blob);
    expect(httpMocks.downloadPost).toHaveBeenCalledWith(
      '/development/business/modules/12/formal-generation/archive',
      { nonce: 'nonce-1' },
      { requestOptions: { showErrorMsg: false } }
    );
    expect(httpMocks.download).not.toHaveBeenCalled();
  });

  it('adoptResolvedBaseline 按模块 POST 采纳已解决的 conflict-no-base 文件', async () => {
    httpMocks.post.mockResolvedValue({ adopted: true });
    const input = { generationId: 42, path: 'app/a.php', localHash: 'a'.repeat(64), remoteHash: 'a'.repeat(64) };
    expect(await businessDevelopmentApi.adoptResolvedBaseline(12, input)).toEqual({ adopted: true });
    expect(httpMocks.post).toHaveBeenCalledWith('/development/business/modules/12/baselines/adopt-resolved', input);
  });

  it('识别统一响应中的结构化安全错误', () => {
    expect(isBusinessApiError({
      code: 409,
      msg: '状态冲突',
      data: { error: { code: 'GENERATION_RECOVERY_STATUS_CONFLICT', requestId: 'req-1', retryable: true, details: {} } }
    })).toBe(true);
    expect(isBusinessApiError(new Error('network'))).toBe(false);
  });

  it('正式生成 API 不暴露旧覆盖参数', () => {
    expect(String(businessDevelopmentApi.formalGeneration)).not.toContain('allowOverwrite');
    expect('formFullPublishApi' in businessDevelopmentApi).toBe(false);
  });
});
