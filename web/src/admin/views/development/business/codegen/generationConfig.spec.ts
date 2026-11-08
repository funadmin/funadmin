import { describe, expect, it } from 'vitest';
import { changedPublishConfig, classNameOf, derivedPermissionCodes, publishConfigOf, pruneParentMenus } from './generationConfig';

describe('generationConfig', () => {
  it('类名与权限码按 PermissionCode::derive 规则推导', () => {
    expect(classNameOf('shop_goods')).toBe('ShopGoods');
    const base = publishConfigOf({ form_key: 'shop_goods', name: '商品', publish_config: null });
    expect(derivedPermissionCodes('shop_goods', base, null)).toEqual([
      'business:shop-goods:index', 'business:shop-goods:detail', 'business:shop-goods:create', 'business:shop-goods:update',
      'business:shop-goods:remove', 'business:shop-goods:restore', 'business:shop-goods:destroy', 'business:shop-goods:recycle',
      'business:shop-goods:restore-many', 'business:shop-goods:destroy-many', 'business:shop-goods:import', 'business:shop-goods:export'
    ]);
    const minimal = { ...base, softDeletes: false, batchDelete: false, import: false, export: false };
    expect(derivedPermissionCodes('shop_goods', minimal, 'demo')).toEqual([
      'demo:shop-goods:index', 'demo:shop-goods:detail', 'demo:shop-goods:create', 'demo:shop-goods:update', 'demo:shop-goods:remove'
    ]);
  });

  it('发布设置补齐与后端一致的派生默认值', () => {
    const config = publishConfigOf({ form_key: 'shop_goods', name: '商品', publish_config: { apiPrefix: '', sortOrder: 10 } });
    expect(config.apiPrefix).toBe('/business/shop-goods');
    expect(config.routePath).toBe('/business/shop-goods');
    expect(config.menuName).toBe('商品');
    expect(config.sortOrder).toBe(10);
    expect(config.softDeletes).toBe(true);
    expect(config.formWidth).toBeUndefined();
  });

  it('只返回改动过的发布设置键；清空的值以 null 提交', () => {
    const base = publishConfigOf({ form_key: 'a', name: 'A', publish_config: { formWidth: 900 } });
    expect(changedPublishConfig(base, { ...base })).toEqual({});
    expect(changedPublishConfig(base, { ...base, apiPrefix: '/x/y', formFullscreen: true, formWidth: undefined }))
      .toEqual({ apiPrefix: '/x/y', formFullscreen: true, formWidth: null });
  });

  it('上级菜单只保留有来源的菜单，并排除当前模块自身', () => {
    const menu = (id: number, sourceName: string, children?: API.MenuItem[]) => ({ id, name: sourceName, sourceType: 'admin_web', sourceName, children }) as unknown as API.MenuItem;
    const tree = [menu(1, 'system', [menu(2, 'shop-goods'), menu(3, 'user')]), { id: 4, name: 'x' } as API.MenuItem];
    expect(pruneParentMenus(tree, 'shop_goods').map((item) => [item.sourceName, item.children?.map((child) => child.sourceName)]))
      .toEqual([['system', ['user']]]);
  });
});
