import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const controller = readFileSync(
  resolve(process.cwd(), '../app/install/controller/Index.php'),
  'utf8'
);

const requiredChecks = [
  'os', 'php', 'php_int', 'json', 'session', 'pdo', 'pdo_mysql', 'mysqli',
  'openssl', 'curl', 'fileinfo', 'image', 'freetype', 'zip', 'upload',
  'runtime', 'public', 'env', 'migrations'
];

describe('安装环境检测契约', () => {
  it('覆盖运行依赖、上传限制和安装目录权限', () => {
    requiredChecks.forEach((key) => {
      expect(controller).toContain(`environmentCheck('${key}'`);
    });
  });

  it('校验每一个迁移文件都可读', () => {
    expect(controller).toContain('is_readable($file)');
  });
});
