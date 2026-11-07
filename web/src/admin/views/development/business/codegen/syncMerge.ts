import type { FormSchemaDocument, FormSchemaNode } from '@/views/form/schema/types';
import { isFieldContainer, removeNodeAtPath, updateNodeAtPath, walkFieldNodes } from './fieldTable';

export interface DatabaseSyncDiff {
  inspectionHash: string;
  added: FormSchemaNode[];
  removed: string[];
  /** from / to 只包含 columnType、nullable、comment */
  changed: Array<{ field: string; from: Record<string, unknown>; to: Record<string, unknown> }>;
}

const PHYSICAL_KEYS = ['columnType', 'nullable', 'comment'] as const;

/** 把用户勾选的表结构差异合并进 Schema；changed 只更新 database 物理属性。 */
export const mergeDatabaseSync = (
  doc: FormSchemaDocument,
  diff: DatabaseSyncDiff,
  pick: { added: string[]; removed: string[]; changed: string[] }
): FormSchemaDocument => {
  const changes = diff.changed.filter((item) => pick.changed.includes(item.field));
  const removed = new Set(diff.removed.filter((name) => pick.removed.includes(name)));
  const added = diff.added.filter((node) => node.field && pick.added.includes(node.field));

  let next = doc;
  for (const change of changes) {
    const entry = walkFieldNodes(next.nodes).find(({ node }) => node.field === change.field && !isFieldContainer(node));
    if (!entry) continue;
    const physical = Object.fromEntries(PHYSICAL_KEYS.filter((key) => key in change.to).map((key) => [key, change.to[key]]));
    next = updateNodeAtPath(next, entry.path, (node) => ({ ...node, database: { ...node.database, ...physical } }));
  }
  // 逆序删除，避免前面的路径因删除而偏移
  const targets = walkFieldNodes(next.nodes).filter(({ node }) => removed.has(String(node.field ?? ''))).reverse();
  for (const { path } of targets) next = removeNodeAtPath(next, path);
  if (added.length) next = { ...next, nodes: [...next.nodes, ...structuredClone(added)] };
  return next;
};
