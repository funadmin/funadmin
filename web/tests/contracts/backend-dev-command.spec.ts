import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const packageJson = JSON.parse(readFileSync(resolve(process.cwd(), 'package.json'), 'utf8'));

describe('本地后端启动命令', () => {
  it('在 admin 目录以前台模式启动 webman（项目已无 ThinkPHP think 入口）', () => {
    expect(packageJson.scripts['dev:backend']).toBe('cd ../admin && php start.php start');
  });
});
