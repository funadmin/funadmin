import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const adminRoot = resolve(import.meta.dirname, '../..');
const projectRoot = resolve(adminRoot, '..');
const generator = readFileSync(resolve(adminRoot, 'scripts/crud-gen.mjs'), 'utf8');
const composer = JSON.parse(readFileSync(resolve(projectRoot, 'composer.json'), 'utf8'));
const annotationConfig = readFileSync(resolve(projectRoot, 'config/annotation.php'), 'utf8');

describe('新生成 CRUD 使用官方 Attribute 路由', () => {
  it('安装官方 ThinkPHP 8 注解扩展', () => {
    expect(composer.require['topthink/think-annotation']).toBe('^3.0');
  });

  it('生成控制器导入并使用官方路由 Attribute', () => {
    expect(generator).toContain('use think\\annotation\\route\\Delete;');
    expect(generator).toContain('use think\\annotation\\route\\Get;');
    expect(generator).toContain('use think\\annotation\\route\\Post;');
    expect(generator).toContain('use think\\annotation\\route\\Put;');
    expect(generator).toContain("#[Get('${prefix}')]");
    expect(generator).toContain("#[Get('${prefix}/:id')]");
    expect(generator).toContain("#[Post('${prefix}')]");
    expect(generator).toContain("#[Put('${prefix}/:id')]");
    expect(generator).toContain("#[Delete('${deleteRule}')]");
  });

  it('不再输出显式路由片段，避免重复注册', () => {
    expect(generator).not.toContain('function backendRouteSnippet');
    expect(generator).not.toContain('待审阅路由片段');
  });

  it('启用路由 Attribute 扫描', () => {
    expect(annotationConfig).toContain("'route'  => [");
    expect(annotationConfig).toContain("'enable'      => true");
  });
});
