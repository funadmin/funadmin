export type ListTreeRow<T> = T & { __listChildren?: ListTreeRow<T>[] };

/** 使用独立副本构树；缺失父节点和环的断点作为根，原始行集合仍用于导出。 */
export function buildListTree<T extends object>(rows: T[], primaryKey: string, parentField: string): ListTreeRow<T>[] {
  const nodes = rows.map(row => ({ ...row }) as ListTreeRow<T>);
  const value = (row: T, key: string) => (row as Record<string, unknown>)[key];
  const byId = new Map(nodes.map(node => [String(value(node, primaryKey)), node]));
  const parents = new Map<ListTreeRow<T>, ListTreeRow<T>>();
  for (const node of nodes) {
    const parentValue = value(node, parentField);
    if (parentValue === null || parentValue === undefined || parentValue === '') continue;
    const parent = byId.get(String(parentValue));
    if (parent && parent !== node) parents.set(node, parent);
  }
  const finished = new Set<ListTreeRow<T>>();
  for (const node of nodes) {
    const path = new Set<ListTreeRow<T>>();
    let current: ListTreeRow<T> | undefined = node;
    while (current && !finished.has(current)) {
      if (path.has(current)) { parents.delete(current); break; }
      path.add(current);
      current = parents.get(current);
    }
    for (const visited of path) finished.add(visited);
  }
  const roots: ListTreeRow<T>[] = [];
  for (const node of nodes) {
    const parent = parents.get(node);
    if (parent) (parent.__listChildren ??= []).push(node);
    else roots.push(node);
  }
  return roots;
}
