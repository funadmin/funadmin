import { reactive, ref } from 'vue';
import { businessDevelopmentApi, type BusinessDatabaseTable } from '@/api/development/business';

export interface RelationColumn {
  name: string;
  type: string;
  comment: string;
  primary: boolean;
}

export type CatalogState = 'idle' | 'loading' | 'ready' | 'error';

export interface RelationColumnsEntry {
  state: CatalogState;
  columns: RelationColumn[];
  error: string;
}

const errorMessage = (reason: unknown): string => {
  const msg = (reason as { msg?: unknown } | null)?.msg;
  if (typeof msg === 'string' && msg) return msg;
  return reason instanceof Error && reason.message ? reason.message : String(reason);
};

const toColumns = (schema: Record<string, unknown>): RelationColumn[] =>
  (Array.isArray(schema.columns) ? schema.columns as Array<Record<string, unknown>> : []).map((column) => ({
    name: String(column.name ?? ''),
    type: String(column.type ?? ''),
    comment: String(column.comment ?? ''),
    primary: column.primary === true
  })).filter((column) => column.name);

/**
 * 关联页签的数据表与字段目录：按连接缓存，一次弹窗打开内只请求一次数据表列表、每张表只请求一次字段。
 * reset() 后丢弃进行中的请求结果；连接变化时自动重置。
 */
export function createRelationCatalog(connection: () => string) {
  const tables = ref<BusinessDatabaseTable[]>([]);
  const tablesState = ref<CatalogState>('idle');
  const tablesError = ref('');
  const columns = reactive(new Map<string, RelationColumnsEntry>());
  let generation = 0;
  let loadedConnection = '';
  let tablesRequest: Promise<void> | null = null;
  const columnRequests = new Map<string, Promise<void>>();

  const reset = () => {
    generation++;
    tables.value = [];
    tablesState.value = 'idle';
    tablesError.value = '';
    columns.clear();
    tablesRequest = null;
    columnRequests.clear();
    loadedConnection = connection();
  };
  const syncConnection = () => {
    if (connection() !== loadedConnection) reset();
  };

  const loadTables = (force = false): Promise<void> => {
    syncConnection();
    if (tablesRequest) return tablesRequest;
    if (!force && tablesState.value === 'ready') return Promise.resolve();
    const current = generation;
    tablesState.value = 'loading';
    tablesError.value = '';
    const request = businessDevelopmentApi.databaseTables(loadedConnection).then((list) => {
      if (current !== generation) return;
      tables.value = Array.isArray(list) ? list : [];
      tablesState.value = 'ready';
    }, (reason: unknown) => {
      if (current !== generation) return;
      tablesState.value = 'error';
      tablesError.value = errorMessage(reason);
    }).finally(() => {
      if (tablesRequest === request) tablesRequest = null;
    });
    tablesRequest = request;
    return request;
  };

  const loadColumns = (table: string, force = false): Promise<void> => {
    syncConnection();
    if (!table) return Promise.resolve();
    const pending = columnRequests.get(table);
    if (pending) return pending;
    if (!force && columns.get(table)?.state === 'ready') return Promise.resolve();
    const current = generation;
    columns.set(table, { state: 'loading', columns: [], error: '' });
    const request = businessDevelopmentApi.databaseTableSchema(loadedConnection, table).then((schema) => {
      if (current !== generation) return;
      columns.set(table, { state: 'ready', columns: toColumns(schema ?? {}), error: '' });
    }, (reason: unknown) => {
      if (current !== generation) return;
      columns.set(table, { state: 'error', columns: [], error: errorMessage(reason) });
    }).finally(() => {
      if (columnRequests.get(table) === request) columnRequests.delete(table);
    });
    columnRequests.set(table, request);
    return request;
  };

  /** 数据表列表已加载时才能判断：true 存在、false 不存在；未加载或加载失败为 undefined。 */
  const tableExists = (table: string): boolean | undefined =>
    tablesState.value === 'ready' ? tables.value.some((item) => item.name === table) : undefined;

  return { tables, tablesState, tablesError, columns, loadTables, loadColumns, tableExists, reset };
}

export type RelationCatalog = ReturnType<typeof createRelationCatalog>;
