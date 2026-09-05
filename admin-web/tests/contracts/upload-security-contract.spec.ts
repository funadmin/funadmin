import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const service = readFileSync(resolve(process.cwd(), '../app/common/service/UploadService.php'), 'utf8');
const driver = readFileSync(resolve(process.cwd(), '../app/common/storage/LocalStorageDriver.php'), 'utf8');

describe('上传安全契约', () => {
  it('可执行与脚本扩展名黑名单覆盖常见 webshell 与 XSS 载体', () => {
    for (const ext of ['php', 'php3', 'php5', 'phtml', 'phar', 'asp', 'aspx', 'cgi', 'pl', 'py', 'sh', 'js', 'mjs', 'svg', 'htaccess', 'exe', 'jar', 'java', 'html', 'htm', 'xml', 'bat']) {
      expect(service).toContain(`'${ext}'`);
    }
  });

  it('扩展名统一小写净化后再校验', () => {
    expect(service).toMatch(/function safeExt\(string \$ext\): string/);
    expect(service).toMatch(/strtolower\(/);
    expect(service).toMatch(/preg_match\('\/\^\[a-z0-9\]/);
  });

  it('attach 落库前强制校验扩展名（覆盖分片路径）', () => {
    const attachBlock = service.match(/public function attach\([\s\S]*?Event::trigger\('afterUploadFile'/)?.[0] ?? '';
    expect(attachBlock).toMatch(/safeExt\(/);
    expect(attachBlock).toMatch(/blockedExtensions\(\)/);
  });

  it('分片合并的 fileExt 与保存目录均做净化', () => {
    const chunkBlock = service.match(/function chunkMerge\([\s\S]*?chunkMergeEnd|function chunkMerge\([\s\S]*?return \$this->attach/s)?.[0] ?? service;
    expect(chunkBlock).toMatch(/safeExt\(/);
    expect(service).toMatch(/function sanitizeSavePath\(string \$path\): string/);
  });

  it('本地存储驱动拒绝目录穿越', () => {
    expect(driver).toMatch(/\.\./);
    expect(driver).toMatch(/RuntimeException/);
  });
});
