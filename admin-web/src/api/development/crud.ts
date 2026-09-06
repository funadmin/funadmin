import http from '@/utils/http';
import type { CrudConnection, CrudDefinition, CrudGeneration, CrudPreview, CrudTable } from '@/types/development/crud';

const PREFIX = '/development/crud';

export const crudDevelopmentApi = {
  connections: () => http.get<CrudConnection[]>(`${PREFIX}/connections`),
  tables: (connection: string, signal?: AbortSignal) => signal
    ? http.get<CrudTable[]>(`${PREFIX}/tables`, { connection }, { signal })
    : http.get<CrudTable[]>(`${PREFIX}/tables`, { connection }),
  tableSchema: (connection: string, table: string) => http.get<Record<string, unknown>>(`${PREFIX}/tables/${table}/schema`, { connection }),
  infer: (connection: string, table: string) => http.post<{ schema: Record<string, unknown>; fields: CrudDefinition['fields'] }>(`${PREFIX}/infer`, { connection, table }),
  validate: (definition: CrudDefinition, signal?: AbortSignal) => signal
    ? http.post<{ valid: boolean; definitionHash: string }>(`${PREFIX}/definitions/validate`, { definition }, { signal })
    : http.post<{ valid: boolean; definitionHash: string }>(`${PREFIX}/definitions/validate`, { definition }),
  preview: (definition: CrudDefinition, signal?: AbortSignal) => signal
    ? http.post<CrudPreview>(`${PREFIX}/preview`, { definition }, { signal })
    : http.post<CrudPreview>(`${PREFIX}/preview`, { definition }),
  generate: (definition: CrudDefinition, confirmToken: string, allowOverwrite: string[]) =>
    http.post<CrudGeneration>(`${PREFIX}/generate`, { definition, confirmToken, allowOverwrite }),
  generation: (id: number) => http.get<Record<string, unknown>>(`${PREFIX}/generations/${id}`)
};
