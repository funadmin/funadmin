/**
 * 树形结构工具
 */
export interface TreeNode {
  id: number | string;
  parentId: number | string;
  children?: TreeNode[];
  [key: string]: any;
}

interface TreeOptions {
  idKey?: string;
  parentKey?: string;
  childrenKey?: string;
  rootValue?: number | string | null;
}

/** 列表转树 */
export function listToTree<T extends Recordable>(list: T[], options: TreeOptions = {}): T[] {
  const { idKey = 'id', parentKey = 'parentId', childrenKey = 'children', rootValue = 0 } = options;
  const map = new Map<number | string, T & { [key: string]: any }>();
  const result: T[] = [];

  list.forEach((item) => {
    map.set(item[idKey], { ...item, [childrenKey]: [] });
  });

  list.forEach((item) => {
    const node = map.get(item[idKey])!;
    const parent = map.get(item[parentKey]);
    if (parent) {
      parent[childrenKey].push(node);
    } else if (item[parentKey] === rootValue || item[parentKey] === null || item[parentKey] === undefined) {
      result.push(node);
    }
  });

  return result;
}

/** 树转列表 */
export function treeToList<T extends Recordable>(
  tree: T[],
  childrenKey = 'children'
): T[] {
  const result: T[] = [];
  const stack = [...tree];
  while (stack.length) {
    const node = stack.shift()!;
    const children = node[childrenKey];
    result.push(node);
    if (Array.isArray(children) && children.length) {
      stack.unshift(...children);
    }
  }
  return result;
}

/** 在树中查找节点 */
export function findTreeNode<T extends Recordable>(
  tree: T[],
  predicate: (node: T) => boolean,
  childrenKey = 'children'
): T | null {
  for (const node of tree) {
    if (predicate(node)) return node;
    const children = node[childrenKey];
    if (Array.isArray(children) && children.length) {
      const found = findTreeNode(children, predicate, childrenKey);
      if (found) return found;
    }
  }
  return null;
}

/** 过滤树（保留命中节点的祖先链） */
export function filterTree<T extends Recordable>(
  tree: T[],
  predicate: (node: T) => boolean,
  childrenKey = 'children'
): T[] {
  return tree
    .map((node) => ({ ...node }))
    .filter((node: any) => {
      const children = node[childrenKey];
      if (Array.isArray(children) && children.length) {
        node[childrenKey] = filterTree(children, predicate, childrenKey);
      }
      return predicate(node) || (node[childrenKey] && node[childrenKey].length > 0);
    });
}
