import { describe, expect, it } from 'vitest';
import { defaultListButtons } from '../../runtime/listButtonHost';
import type { FormListButton } from '../../schema/types';
import { effectiveListButtons, listPreviewNodes } from './listPreviewSample';

describe('列表预览示例工具', () => {
  it('effectiveListButtons：已配置按钮原样使用，继承时取默认按钮并只保留可用内置动作', () => {
    const custom: FormListButton[] = [{ id: 'a', label: 'A', action: { type: 'refresh' } }];
    const result = effectiveListButtons([{ key: 'toolbar', builtinKeys: ['create'] }, { key: 'row', builtinKeys: [] }, { key: 'categoryNode', builtinKeys: ['edit'] }], { row: custom });
    expect(Object.keys(result)).toEqual(['toolbar', 'row', 'categoryNode']);
    expect(result.row).toBe(custom);
    // 非内置动作（宿主刷新）不受 builtinKeys 过滤。
    expect(result.toolbar).toEqual(defaultListButtons('toolbar').filter(button => button.action.type !== 'builtin' || button.action.key === 'create'));
    expect(result.toolbar?.map(button => button.id)).toEqual(['create', 'refresh']);
    expect(result.categoryNode?.map(button => button.id)).toEqual(['edit']);
    // 空数组（全部隐藏）保持为空，不回退默认。
    expect(effectiveListButtons([{ key: 'row', builtinKeys: ['edit'] }], { row: [] }).row).toEqual([]);
    expect(effectiveListButtons([{ key: 'row', builtinKeys: ['edit'] }], undefined).row?.map(button => button.id)).toEqual(['edit']);
  });
  it('listPreviewNodes：层级树含子节点，平铺列表全部为根节点', () => {
    const t = (_key: string, fallback: string) => fallback;
    expect(listPreviewNodes(t)).toEqual([{ label: '示例分类 1', depth: 0 }, { label: '子分类 1-1', depth: 1 }, { label: '示例分类 2', depth: 0 }]);
    expect(listPreviewNodes(t, false)).toEqual([{ label: '示例分类 1', depth: 0 }, { label: '示例分类 2', depth: 0 }, { label: '示例分类 3', depth: 0 }]);
  });
});
