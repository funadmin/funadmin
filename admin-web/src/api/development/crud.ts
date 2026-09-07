import http from '@/utils/http';
import type { CrudConnection, CrudDefinition, CrudGeneration, CrudPreview, CrudResourceOptions, CrudTable } from '@/types/development/crud';

const PREFIX = '/development/crud';

export const crudDevelopmentApi = {
  connections: () => http.get<CrudConnection[]>(`${PREFIX}/connections`),
  tables: (connection: string, signal?: AbortSignal) => signal
    ? http.get<CrudTable[]>(`${PREFIX}/tables`, { connection }, { signal })
    : http.get<CrudTable[]>(`${PREFIX}/tables`, { connection }),
  tableSchema: (connection: string, table: string) => http.get<Record<string, unknown>>(`${PREFIX}/tables/${table}/schema`, { connection }),
  options: () => http.get<CrudResourceOptions>(`${PREFIX}/options`),
  infer: (connection: string, table: string) => http.post<{ schema: Record<string, unknown>; fields: CrudDefinition['fields'] }>(`${PREFIX}/infer`, { connection, table }),
  validate: (definition: CrudDefinition, signal?: AbortSignal) => signal
    ? http.post<{ valid: boolean; definitionHash: string }>(`${PREFIX}/definitions/validate`, { definition }, { signal })
    : http.post<{ valid: boolean; definitionHash: string }>(`${PREFIX}/definitions/validate`, { definition }),
  preview: (definition: CrudDefinition, signal?: AbortSignal) => signal
    ? http.post<CrudPreview>(`${PREFIX}/preview`, { definition }, { signal })
    : http.post<CrudPreview>(`${PREFIX}/preview`, { definition }),
  generate: (definition: CrudDefinition, confirmToken: string, allowOverwrite: string[], applyResources = false) =>
    http.post<CrudGeneration>(`${PREFIX}/generate`, { definition, confirmToken, allowOverwrite, applyResources }),
  applyResources: (id: number) => http.post<CrudGeneration>(`${PREFIX}/generations/${id}/apply-resources`),
  generation: (id: number) => http.get<Record<string, unknown>>(`${PREFIX}/generations/${id}`)
};
