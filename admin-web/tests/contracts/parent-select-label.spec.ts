import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const files = [
  'src/views/system/menu/components/MenuFormDialog.vue',
  'src/views/system/permission/components/PermissionFormDialog.vue',
  'src/views/system/dept/components/DeptFormDialog.vue',
  'src/views/system/attachment/components/AttachmentGroupDialog.vue'
];

describe('父级选择器顶级文案', () => {
  it.each(files)('%s 使用文案表示值 0', (file) => {
    const source = readFileSync(resolve(process.cwd(), file), 'utf8');
    expect(source).toContain("id: 0");
    expect(source).toMatch(/(title|name): '无上级'/);
  });
});
