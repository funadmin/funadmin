import { beforeEach, describe, expect, it, vi } from 'vitest';

const httpMocks = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }));
vi.mock('@/utils/http', () => ({ default: httpMocks }));

import { businessDevelopmentApi, isBusinessApiError } from './business';

describe('businessDevelopmentApi', () => {
  beforeEach(() => vi.clearAllMocks());

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
