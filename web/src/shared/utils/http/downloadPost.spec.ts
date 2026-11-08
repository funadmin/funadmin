import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { AxiosAdapter, InternalAxiosRequestConfig } from 'axios';

const mocks = vi.hoisted(() => ({ error: vi.fn(), csrf: 'csrf-1' }));
vi.mock('element-plus', () => ({ ElMessage: { error: mocks.error, success: vi.fn() }, ElMessageBox: { alert: vi.fn(), confirm: vi.fn() } }));
vi.mock('@/utils/auth', () => ({ clearAuth: vi.fn(), getCsrfToken: () => mocks.csrf, setCsrfToken: vi.fn() }));

import { http, service } from './index';

let adapter: ReturnType<typeof vi.fn>;
let original: typeof service.defaults.adapter;

function respond(data: Blob, status = 200) {
  adapter.mockImplementation(async (config: InternalAxiosRequestConfig) => {
    const response = { data, status, statusText: '', headers: {}, config };
    const validate = config.validateStatus;
    if (validate && !validate(status)) throw Object.assign(new Error('status ' + status), { response, config });
    return response;
  });
}

beforeEach(() => {
  vi.clearAllMocks();
  original = service.defaults.adapter;
  adapter = vi.fn();
  service.defaults.adapter = adapter as unknown as AxiosAdapter;
});

afterEach(() => {
  service.defaults.adapter = original;
});

describe('http.downloadPost', () => {
  it('以 POST + CSRF 头请求 Blob 并原样返回二进制内容', async () => {
    const zip = new Blob(['PK'], { type: 'application/zip' });
    respond(zip);
    const result = await http.downloadPost('/x/archive', { nonce: 'n1' });
    expect(result).toBe(zip);
    const config = adapter.mock.calls[0][0] as InternalAxiosRequestConfig;
    expect(config.method).toBe('post');
    expect(config.responseType).toBe('blob');
    expect(config.headers['X-CSRF-TOKEN']).toBe('csrf-1');
    expect(JSON.parse(String(config.data))).toEqual({ nonce: 'n1' });
  });

  it('业务错误以 JSON 返回时解析并拒绝，不把 JSON 当作文件返回', async () => {
    respond(new Blob([JSON.stringify({ code: 422, msg: '没有可打包的生成文件', data: { error: { code: 'VALIDATION_ERROR', requestId: 'req-9' } } })], { type: 'application/json; charset=utf-8' }));
    await expect(http.downloadPost('/x/archive', {}, { requestOptions: { showErrorMsg: false } })).rejects.toMatchObject({ code: 422, msg: '没有可打包的生成文件' });
    expect(mocks.error).not.toHaveBeenCalled();
  });

  it('HTTP 4xx 的 JSON 错误同样解析业务消息并默认提示', async () => {
    respond(new Blob([JSON.stringify({ code: 403, msg: '缺少正式生成权限', data: null })], { type: 'application/json' }), 403);
    await expect(http.downloadPost('/x/archive')).rejects.toMatchObject({ code: 403, msg: '缺少正式生成权限' });
    expect(mocks.error).toHaveBeenCalledWith('缺少正式生成权限');
  });
});
