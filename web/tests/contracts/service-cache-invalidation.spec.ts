import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { backendJson } from './backend-runtime';

const readProject = (path: string) => readFileSync(resolve(process.cwd(), '..', path), 'utf8');

describe('Service 公共缓存失效能力', () => {
  it('AbstractService 提供受保护的全局缓存清理方法', () => {
    const source = readProject('admin/app/common/service/AbstractService.php');
    expect(source).toContain('use support\\Cache;');
    expect(source).toMatch(/protected function clearApplicationCache\(\): void[\s\S]*Cache::clear\(\);/);
    const result = backendJson(String.raw`
      // Replace only this subprocess's default store; never clear the developer's file cache.
      \support\Cache::$instances[config('cache.default')] = new \Symfony\Component\Cache\Psr16Cache(new \Symfony\Component\Cache\Adapter\ArrayAdapter());
      $service = new class extends \app\common\service\AbstractService {
          public function invalidate(): void { $this->clearApplicationCache(); }
      };
      \support\Cache::set('contract-first', 1);
      \support\Cache::set('contract-second', 2);
      $before = [\support\Cache::get('contract-first'), \support\Cache::get('contract-second')];
      $service->invalidate();
      echo json_encode(['before' => $before, 'after' => [\support\Cache::has('contract-first'), \support\Cache::has('contract-second')]]);
    `);
    expect(result).toEqual({ before: [1, 2], after: [false, false] });
  });

  it('ResourceRegistryService 不再定义私有 clearCache', () => {
    const source = readProject('admin/app/admin/authorization/service/ResourceRegistryService.php');
    expect(source).not.toContain('private function clearCache');
    expect(source).not.toContain('use think\\facade\\Cache;');
    expect(source).not.toContain('ClearsApplicationCache');
    expect(source).toContain('$this->clearApplicationCache();');
  });

  it('PluginService 复用公共缓存清理能力', () => {
    const source = readProject('admin/app/admin/plugin/service/PluginService.php');
    expect(source).not.toContain('Cache::clear();');
    expect(source).not.toContain('use think\\facade\\Cache;');
    expect(source).not.toContain('ClearsApplicationCache');
    expect(source).toContain('$this->clearApplicationCache();');
  });
});
