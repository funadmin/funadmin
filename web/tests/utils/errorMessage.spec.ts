import { describe, expect, it } from 'vitest';
import { formatErrorMessage } from '@/utils/errorMessage';

describe('formatErrorMessage', () => {
  it('uses the message from a structured API rejection instead of [object Object]', () => {
    expect(formatErrorMessage({ code: 422, msg: '插件 Manifest 校验失败', data: { errors: [] } })).toBe('插件 Manifest 校验失败');
  });

  it('uses nested data messages and serializes unknown objects safely', () => {
    expect(formatErrorMessage({ code: 422, data: { message: '目录不完整' } })).toBe('目录不完整');
    expect(formatErrorMessage({ code: 422, details: { path: 'plugin.json' } })).toBe('{"code":422,"details":{"path":"plugin.json"}}');
    expect(formatErrorMessage(null)).toBe('操作失败，请重试');
  });
});
