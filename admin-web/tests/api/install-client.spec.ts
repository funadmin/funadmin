import { describe, expect, it } from 'vitest';
import type { AxiosResponse } from 'axios';
import { installApi, installHttp } from '@/api/install';

/* 用自定义 adapter 模拟后端响应，不发起真实请求 */
function stub(status: number, body: unknown) {
  installHttp.defaults.adapter = async (config) =>
    ({ data: body, status, statusText: String(status), headers: {}, config }) as AxiosResponse;
}

describe('安装接口客户端', () => {
  it('2xx 且 code=200 时解包 data', async () => {
    stub(200, { code: 200, msg: 'ok', data: { installed: false } });
    await expect(installApi.environment()).resolves.toEqual({ installed: false });
  });

  it('非 2xx 时透出后端 msg 而不是 axios 通用错误', async () => {
    stub(409, { code: 409, msg: '当前版本已经安装了，如果需要重新安装请先删除install.lock', data: null });
    await expect(installApi.environment()).rejects.toThrow('当前版本已经安装了，如果需要重新安装请先删除install.lock');
  });

  it('2xx 但 code 非 200 时同样透出 msg', async () => {
    stub(200, { code: 422, msg: '两次输入密码不一致！', data: null });
    await expect(installApi.environment()).rejects.toThrow('两次输入密码不一致！');
  });
});
