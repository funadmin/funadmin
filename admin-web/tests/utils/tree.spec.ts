import { describe, it, expect } from 'vitest';
import { listToTree, treeToList, findTreeNode, filterTree } from '@/utils/tree';

const flat = [
  { id: 1, parentId: 0, name: '系统' },
  { id: 2, parentId: 1, name: '用户' },
  { id: 3, parentId: 1, name: '角色' },
  { id: 4, parentId: 2, name: '新增' },
  { id: 5, parentId: 0, name: '日志' }
];

describe('utils/tree', () => {
  it('listToTree 默认 parentId=0 为根，正确构造层级', () => {
    const tree = listToTree(flat);
    expect(tree).toHaveLength(2);
    expect(tree[0].name).toBe('系统');
    expect(tree[0].children).toHaveLength(2);
    expect(tree[0].children![0].children![0].name).toBe('新增');
  });

  it('treeToList 还原全部节点（深度优先）', () => {
    const tree = listToTree(flat);
    const list = treeToList(tree);
    expect(list.map((n: any) => n.id)).toEqual([1, 2, 4, 3, 5]);
  });

  it('findTreeNode 命中嵌套节点', () => {
    const tree = listToTree(flat);
    const node = findTreeNode(tree, (n: any) => n.name === '新增');
    expect(node?.id).toBe(4);
  });

  it('findTreeNode 未命中返回 null', () => {
    const tree = listToTree(flat);
    expect(findTreeNode(tree, (n: any) => n.name === 'XXX')).toBeNull();
  });

  it('filterTree 保留命中节点的祖先链', () => {
    const tree = listToTree(flat);
    const result = filterTree(tree, (n: any) => n.name === '新增');
    expect(result).toHaveLength(1);
    expect(result[0].name).toBe('系统');
    expect(result[0].children).toHaveLength(1);
    expect(result[0].children![0].name).toBe('用户');
  });

  it('listToTree 支持自定义 key', () => {
    const data = [
      { code: 'a', pcode: '', label: 'A' },
      { code: 'b', pcode: 'a', label: 'B' }
    ];
    const tree = listToTree(data as any, {
      idKey: 'code',
      parentKey: 'pcode',
      rootValue: ''
    });
    expect(tree).toHaveLength(1);
    expect((tree[0] as any).children[0].code).toBe('b');
  });
});
