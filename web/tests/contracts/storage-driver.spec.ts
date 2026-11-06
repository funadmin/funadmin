import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const root = resolve(process.cwd(), '..');
const readProjectFile = (path: string) => readFileSync(resolve(root, path), 'utf8');

describe('附件存储驱动契约', () => {
  it('上传请求由浏览器生成 multipart boundary', () => {
    const source = readProjectFile('web/src/shared/utils/http/index.ts');
    const uploadBlock = source.slice(source.indexOf('upload:'), source.indexOf('download:'));
    expect(uploadBlock).not.toContain("'Content-Type': 'multipart/form-data'");
  });

  it('附件页展示并可选择后台存储驱动', () => {
    const source = readProjectFile('web/src/admin/views/system/attachment/index.vue');
    expect(source).toContain('storageApi');
    expect(source).toContain('存储驱动');
    expect(source).toContain('system:storage:update');
  });

  it('上传结果返回实际存储驱动', () => {
    const api = readProjectFile('web/src/admin/api/common/upload.ts');
    expect(api).toContain('driver: string');
  });
});
