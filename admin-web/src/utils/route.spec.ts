import { describe, expect, it } from 'vitest';
import type { RouteRecordRaw } from 'vue-router';
import { getFirstLeafRouteFullPath, joinRoutePath, resolveMenuPath } from './route';

const route = (path: string, children: RouteRecordRaw[] = []): RouteRecordRaw => ({
  path,
  ...(children.length ? { children } : {})
} as RouteRecordRaw);

describe('resolveMenuPath', () => {
  it('与动态路由和菜单组件一致处理绝对、相对路径', () => {
    expect(resolveMenuPath('/system', '/system/user')).toBe('/system/user');
    expect(resolveMenuPath('/system', 'user')).toBe('/system/user');
  });

  it('绝对路径只归一 pathname 并原样保留 query 和 hash', () => {
    expect(resolveMenuPath('/system', '/form/list?redirect=https://example.com/a#x//y'))
      .toBe('/form/list?redirect=https://example.com/a#x//y');
  });

  it('相对路径拼接父路径并原样保留 query', () => {
    expect(resolveMenuPath('/development/', 'form/list?redirect=https://example.com/a'))
      .toBe('/development/form/list?redirect=https://example.com/a');
  });

  it('pathname 重复斜杠仍归一且 hash 内双斜杠保持不变', () => {
    expect(resolveMenuPath('/system', '/form///list#x//y')).toBe('/form/list#x//y');
  });

  it('安全处理空 path 及只有 query 或 hash 的 path', () => {
    expect(resolveMenuPath('/system/', '')).toBe('/system');
    expect(resolveMenuPath('/system/', '?redirect=https://example.com/a')).toBe('/system?redirect=https://example.com/a');
    expect(resolveMenuPath('/system/', '#x//y')).toBe('/system#x//y');
  });

  it('joinRoutePath 复用相对路径语义且不破坏 suffix', () => {
    expect(joinRoutePath('/system/', '/user//list?redirect=https://example.com/a#x//y'))
      .toBe('/system/user/list?redirect=https://example.com/a#x//y');
  });
});

describe('getFirstLeafRouteFullPath', () => {
  it.each(['/system/user', '/form/list'])(
    '顶级 /system 下的绝对子菜单 %s 不重复拼接父路径',
    (absolutePath) => {
      expect(getFirstLeafRouteFullPath(route(absolutePath), '/system')).toBe(absolutePath);
    }
  );

  it('相对子路径仍拼接顶级父路径', () => {
    expect(getFirstLeafRouteFullPath(route('user'), '/system')).toBe('/system/user');
  });

  it('三级混合路径从绝对节点重置后继续拼接相对叶子', () => {
    const mixed = route('group', [route('/form', [route('list')])]);

    expect(getFirstLeafRouteFullPath(mixed, '/system')).toBe('/form/list');
  });

  it('叶子路径只归一 pathname 并保留 query 和 hash suffix', () => {
    expect(getFirstLeafRouteFullPath(
      route('/form//list?redirect=https://example.com/a#x//y'),
      '/system'
    )).toBe('/form/list?redirect=https://example.com/a#x//y');
  });
});