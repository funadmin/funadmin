import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const readProject = (path: string) => readFileSync(resolve(process.cwd(), '..', path), 'utf8');

describe('Service 公共缓存失效能力', () => {
  it('AbstractService 提供受保护的全局缓存清理方法', () => {
    const source = readProject('app/common/service/AbstractService.php');
    expect(source).toContain('use think\\facade\\Cache;');
    expect(source).toMatch(/protected function clearApplicationCache\(\): void[\s\S]*Cache::clear\(\);/);
  });

  it('ResourceRegistryService 不再定义私有 clearCache', () => {
    const source = readProject('app/backend/service/ResourceRegistryService.php');
    expect(source).not.toContain('private function clearCache');
    expect(source).not.toContain('use think\\facade\\Cache;');
    expect(source).not.toContain('ClearsApplicationCache');
    expect(source).toContain('$this->clearApplicationCache();');
  });

  it('PluginService 复用公共缓存清理能力', () => {
    const source = readProject('app/backend/service/PluginService.php');
    expect(source).not.toContain('Cache::clear();');
    expect(source).not.toContain('use think\\facade\\Cache;');
    expect(source).not.toContain('ClearsApplicationCache');
    expect(source).toContain('$this->clearApplicationCache();');
  });
});
