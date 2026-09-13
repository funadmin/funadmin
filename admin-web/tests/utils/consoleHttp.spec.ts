import { beforeEach, describe, expect, it, vi } from 'vitest';
import { AxiosError } from 'axios';

const ui = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn(), alert: vi.fn(), confirm: vi.fn(() => Promise.reject()) }));
vi.mock('element-plus', () => ({ ElMessage: { success: ui.success, error: ui.error }, ElMessageBox: { alert: ui.alert, confirm: ui.confirm } }));
vi.mock('@/utils/auth', () => ({ clearAuth: vi.fn(), getCsrfToken: () => '', setCsrfToken: vi.fn() }));
import { request } from '@/utils/http';

function send(data: unknown, status = 200, options = {}) {
  return request({ url: '/identity/applications', requestOptions: { showSuccessMsg: true, ...options }, adapter: async (config) => {
    const response = { data, status, statusText: '', headers: {}, config };
    if (status >= 400) throw new AxiosError('Request failed', 'ERR_BAD_RESPONSE', config, undefined, response);
    return response;
  } });
}

describe('console HTTP 业务失败', () => {
  beforeEach(() => vi.clearAllMocks());
  it.each(['msg', 'message'])('200 失败兼容 %s，拒绝且不触发成功提示', async (key) => {
    const body = { code: 422, [key]: '域名无法解析', data: null };
    await expect(send(body)).rejects.toEqual(body);
    expect(ui.error).toHaveBeenCalledWith('域名无法解析');
    expect(ui.success).not.toHaveBeenCalled();
  });
  it('非字符串 msg 安全回退至 message', async () => {
    await expect(send({ code: 400, msg: { secret: true }, message: '名称无效' })).rejects.toBeDefined();
    expect(ui.error).toHaveBeenCalledWith('名称无效');
  });
  it.each([400, 403, 404, 409, 422, 429])('%s 保留安全业务 message 与错误详情', async (status) => {
    const body = { code: status, message: '操作条件不满足', data: { version: 2 } };
    await expect(send(body, status)).rejects.toEqual(body);
    expect(ui.error).toHaveBeenCalledWith('操作条件不满足');
  });
  it.each([500, 501, 502, 503, 504])('%s 不向 UI 或调用方泄露原始消息', async (status) => {
    let rejected: unknown;
    try { await send({ code: 0, msg: 'secret SQL', message: 'secret path', data: { token: 'secret' } }, status); }
    catch (error) { rejected = error; }
    expect(JSON.stringify(rejected)).not.toContain('secret');
    expect(ui.error).toHaveBeenCalled();
    expect(JSON.stringify(ui.error.mock.calls)).not.toContain('secret');
    expect(ui.success).not.toHaveBeenCalled();
  });
  it('静默模式仍然拒绝业务失败', async () => {
    await expect(send({ code: 422, message: '失败' }, 200, { showErrorMsg: false })).rejects.toBeDefined();
    expect(ui.error).not.toHaveBeenCalled();
  });
  it('成功继续返回 data', async () => {
    await expect(send({ code: 200, msg: '保存成功', data: { id: 1 } })).resolves.toEqual({ id: 1 });
    expect(ui.success).toHaveBeenCalledWith('保存成功');
  });
});
