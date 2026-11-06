import { describe, expect, it } from 'vitest';
import { backendJson } from './backend-runtime';

describe('CMS 公共页面与会员 API 路由隔离', () => {
  it('真实路由可同时注册公共通配页面和会员静态端点，未知会员 API 不落入页面渲染', () => {
    const routes = backendJson<Record<string, { status: number; controller: string | null; action: string | null }>>(String.raw`
      require 'tests/Cms/bootstrap.php';
      \Webman\Route::load([config_path()]);
      $result = [];
      foreach (['/cms/news/example', '/cms/member/context', '/cms/member/identity', '/cms/member/unknown'] as $path) {
        $match = \Webman\Route::dispatch('GET', $path);
        $callback = $match[0] === \FastRoute\Dispatcher::FOUND ? $match[1]['callback'] : null;
        $result[$path] = ['status' => $match[0], 'controller' => is_array($callback) ? $callback[0] : null, 'action' => is_array($callback) ? $callback[1] : null];
      }
      echo json_encode($result, JSON_THROW_ON_ERROR);
    `);
    expect(routes['/cms/news/example']).toEqual({ status: 1, controller: 'app\\cms\\controller\\Index', action: 'index' });
    expect(routes['/cms/member/context']).toEqual({ status: 1, controller: 'app\\cms\\controller\\MemberContent', action: 'context' });
    expect(routes['/cms/member/identity']).toEqual({ status: 1, controller: 'app\\cms\\controller\\MemberContent', action: 'identity' });
    expect(routes['/cms/member/unknown'].status).toBe(0);
  });
});
