import http from '@/utils/http';
import type { CrudConnection, CrudDefinition, CrudGeneration, CrudPreview, CrudTable } from '@/types/development/crud';

const PREFIX = '/development/crud';

export const crudDevelopmentApi = {
  connections: () => http.get<CrudConnection[]>(`${PREFIX}/connections`),
  tables: (connection: string) => http.get<CrudTable[]>(`${PREFIX}/tables`, { params: { connection } }),
  tableSchema: (connection: string, table: string) => http.get<Record<string, unknown>>(`${PREFIX}/table/${table}`, { params: { connection } }),
  infer: (connection: string, table: string) => http.post<{ schema: Record<string, unknown>; fields: CrudDefinition['fields'] }>(`${PREFIX}/infer`, { connection, table }),
  validate: (definition: CrudDefinition) => http.post<{ valid: boolean; definitionHash: string }>(`${PREFIX}/validate`, { definition }),
  preview: (definition: CrudDefinition) => http.post<CrudPreview>(`${PREFIX}/preview`, { definition }),
  generate: (definition: CrudDefinition, confirmToken: string, allowOverwrite: string[]) =>
    http.post<CrudGeneration>(`${PREFIX}/generate`, { definition, confirmToken, allowOverwrite }),
  generation: (id: number) => http.get<Record<string, unknown>>(`${PREFIX}/generations/${id}`)
};
