/** 回收站页签的定义：由各面板按资源提供读取、恢复与彻底删除的方法以及权限判断。 */
export interface RecycleRow { id: string; deleted_at: string }
export interface RecyclePage<T extends RecycleRow = RecycleRow> { rows: T[]; total: number; pageSize: number }
export interface RecycleSource<T extends RecycleRow = RecycleRow> {
  key: string;
  label: string;
  canView: boolean;
  canRestore: boolean;
  canDestroy: boolean;
  load: (page: number) => Promise<RecyclePage<T>>;
  title: (row: T) => string;
  meta: (row: T) => string;
  /** 行不能操作时（例如成员不在可管理范围）返回 false。 */
  actionable?: (row: T) => boolean;
  restore: (ids: string[]) => Promise<{ count: number; skipped?: number }>;
  destroy: (ids: string[]) => Promise<{ count: number }>;
  destroyBody: (row: T) => string;
}
