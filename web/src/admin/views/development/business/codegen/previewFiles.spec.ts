import { describe, expect, it } from 'vitest';
import type { BusinessGenerationFile } from '@/api/development/business';

import { countFileStatuses, fileLabel, fileLanguage, groupPreviewFiles, previewFileGroup } from './previewFiles';

const file = (path: string, status: BusinessGenerationFile['status'] = 'create'): BusinessGenerationFile => ({ path, status });

describe('previewFileGroup', () => {
  it.each([
    ['app/admin/generated/shop_goods/controller/ShopGoodsController.php', 'backend'],
    ['app/admin/generated/shop_goods/service/ShopGoodsService.php', 'backend'],
    ['app/admin/generated/shop_goods/model/ShopGoods.php', 'backend'],
    ['app/admin/generated/shop_goods/model/relation/Category.php', 'backend'],
    ['app/admin/generated/shop_goods/validate/ShopGoodsValidate.php', 'backend'],
    ['app/api/controller/generated/ShopGoodsController.php', 'backend'],
    ['database/generated/shop-goods.sql', 'database'],
    ['database/generated/shop-goods_permissions.sql', 'database'],
    ['database/generated/shop-goods_lang.sql', 'database'],
    ['web/src/admin/views/generated/shop-goods/index.vue', 'frontend'],
    ['web/src/admin/views/generated/shop-goods/components/ShopGoodsForm.vue', 'frontend'],
    ['web/src/admin/api/generated/shop-goods.ts', 'frontend'],
    ['app/admin/lang/zh-cn/shop-goods.php', 'lang'],
    ['app/admin/lang/en-us/shop-goods.php', 'lang'],
    ['tests/generated/ShopGoodsGeneratedTest.php', 'test'],
    ['web/tests/generated/shop-goods.spec.ts', 'test'],
    ['plugins/demo/app/admin/plugins/demo/controller/GoodsController.php', 'backend'],
    ['plugins/demo/web/src/admin/modules/demo/goods/index.vue', 'frontend'],
    ['plugins/demo/database/migrations/001_goods.sql', 'database'],
    ['plugins/demo/plugin.json', 'other'],
    ['README.md', 'other']
  ] as const)('%s → %s', (path, group) => {
    expect(previewFileGroup(path)).toBe(group);
  });
});

describe('groupPreviewFiles', () => {
  it('按固定类别顺序分组，组内保持服务端顺序，省略空分组', () => {
    const files = [
      file('web/tests/generated/a.spec.ts'),
      file('web/src/admin/views/generated/a/index.vue'),
      file('app/admin/generated/a/service/AService.php'),
      file('database/generated/a.sql'),
      file('app/admin/generated/a/controller/AController.php'),
      file('app/admin/lang/zh-cn/a.php')
    ];
    const groups = groupPreviewFiles(files);
    expect(groups.map((g) => g.group)).toEqual(['backend', 'database', 'frontend', 'lang', 'test']);
    expect(groups[0].files.map((f) => f.path)).toEqual([
      'app/admin/generated/a/service/AService.php',
      'app/admin/generated/a/controller/AController.php'
    ]);
  });

  it('空列表返回空数组', () => {
    expect(groupPreviewFiles([])).toEqual([]);
  });
});

describe('fileLanguage', () => {
  it.each([
    ['a/B.php', 'php'],
    ['a/b.sql', 'sql'],
    ['a/b.ts', 'typescript'],
    ['a/b.spec.ts', 'typescript'],
    ['a/b.vue', 'vue'],
    ['plugin.json', 'json'],
    ['README.md', 'text'],
    ['Makefile', 'text'],
    ['A.PHP', 'php']
  ] as const)('%s → %s', (path, language) => {
    expect(fileLanguage(path)).toBe(language);
  });
});

describe('fileLabel', () => {
  it('默认显示文件名，同组重名时带上父目录', () => {
    const siblings = [file('app/admin/lang/zh-cn/a.php'), file('app/admin/lang/en-us/a.php'), file('app/admin/lang/x/b.php')];
    expect(fileLabel('app/admin/lang/zh-cn/a.php', siblings)).toBe('zh-cn/a.php');
    expect(fileLabel('app/admin/lang/x/b.php', siblings)).toBe('b.php');
  });
});

describe('countFileStatuses', () => {
  it('按 FILE_STATUS_META 顺序统计出现过的状态', () => {
    expect(countFileStatuses([file('a', 'conflict'), file('b', 'create'), file('c', 'create'), file('d', 'update')])).toEqual([
      { status: 'create', count: 2 },
      { status: 'update', count: 1 },
      { status: 'conflict', count: 1 }
    ]);
  });
});
