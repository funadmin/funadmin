import http from '@/utils/http';

const PREFIX = '/identity/applications';
export type ApplicationStatus = 'draft' | 'published' | 'disabled';
export type RuntimeType = 'internal' | 'plugin' | 'standalone';
export type DatabaseMode = 'shared' | 'dedicated' | 'external';

export type Visibility = 'private' | 'tenant' | 'public';
type DomainType = 'web' | 'admin' | 'api' | 'identity_callback' | 'logout_callback';

export interface EnterpriseApplication {
  id: number; code: string; name: string; description: string; runtimeType: RuntimeType;
  launchUrl: string; status: ApplicationStatus; logoUrl?: string; brandConfig: Record<string, unknown>;
  icon?: string; sortOrder?: number; visibility?: Visibility; databaseMode?: DatabaseMode; baseUrl?: string; owner?: string; ownerIdentityUserId?: number;
}
export interface ApplicationInput { code: string; name: string; description: string; runtimeType: RuntimeType; launchUrl: string; logoUrl?: string; brandConfig?: Record<string, unknown>; icon?: string; sortOrder?: number; visibility?: Visibility; databaseMode?: DatabaseMode; baseUrl?: string; owner?: string; ownerIdentityUserId?: number }
export interface AssignmentInput { id?: number; subjectType: 'all' | 'user' | 'department' | 'role'; subjectId?: number; effect: 'allow' | 'deny' }
export interface DomainInput { id?: number; identityCallback: string; logoutCallback: string; domainType?: DomainType }
export interface DatabaseInput { mode: DatabaseMode; credentialRef?: string; healthPath?: string; credentialConfigured?: boolean }

interface ApplicationDto extends Omit<EnterpriseApplication, 'runtimeType' | 'launchUrl' | 'logoUrl' | 'brandConfig' | 'sortOrder' | 'databaseMode' | 'baseUrl'> {
  runtime_type: RuntimeType; launch_url: string; logo_url?: string; brand_config?: Record<string, unknown> | string | null;
  sort_order?: number; database_mode?: DatabaseMode; base_url?: string; owner_identity_user_id?: number;
}
interface AssignmentDto { id?: number; subject_type: AssignmentInput['subjectType']; subject_id?: number | null; effect: AssignmentInput['effect'] }
interface DomainDto { id?: number; domain_type?: DomainType; scheme: string; host: string; port: number; identity_callback_path?: string | null; logout_callback_path?: string | null }
interface DatabaseDto { mode: DatabaseMode; credential_ref?: string; health_path?: string; credential_configured?: boolean; credentialConfigured?: boolean }

const parseBrandConfig = (value: ApplicationDto['brand_config']): Record<string, unknown> => {
  if (!value) return {};
  if (typeof value !== 'string') return value;
  try { return JSON.parse(value) as Record<string, unknown>; } catch { return {}; }
};
const callbackUrl = (dto: DomainDto, path?: string | null) => {
  if (!path) return '';
  const port = dto.port && !((dto.scheme === 'https' && dto.port === 443) || (dto.scheme === 'http' && dto.port === 80)) ? `:${dto.port}` : '';
  return `${dto.scheme}://${dto.host}${port}${path}`;
};

export const mapApplication = (dto: ApplicationDto): EnterpriseApplication => ({
  id: dto.id, code: dto.code, name: dto.name, description: dto.description, status: dto.status,
  runtimeType: dto.runtime_type, launchUrl: dto.launch_url, logoUrl: dto.logo_url,
  brandConfig: parseBrandConfig(dto.brand_config), icon: dto.icon, sortOrder: dto.sort_order,
  visibility: dto.visibility, databaseMode: dto.database_mode, baseUrl: dto.base_url, owner: dto.owner,
  ownerIdentityUserId: dto.owner_identity_user_id
});
export const mapAssignment = (dto: AssignmentDto): AssignmentInput => ({ id: dto.id, subjectType: dto.subject_type, subjectId: dto.subject_id ?? undefined, effect: dto.effect });
export const mapDomain = (dto: DomainDto): DomainInput => ({ id: dto.id, domainType: dto.domain_type, identityCallback: callbackUrl(dto, dto.identity_callback_path), logoutCallback: callbackUrl(dto, dto.logout_callback_path) });
export const mapDatabase = (dto: DatabaseDto): DatabaseInput => ({ mode: dto.mode, credentialRef: dto.credential_ref, healthPath: dto.health_path, credentialConfigured: dto.credential_configured ?? dto.credentialConfigured ?? false });
export const serializeDomainChange = (domain: DomainInput, expectedDomainIds: number[], touched: boolean) => {
  if (!touched) return null;
  const hasIdentity = domain.identityCallback.trim() !== '';
  const hasLogout = domain.logoutCallback.trim() !== '';
  const domains = hasIdentity || hasLogout ? [{ identityCallback: domain.identityCallback.trim(), logoutCallback: domain.logoutCallback.trim(), domainType: domain.domainType }] : [];
  return { domains, expectedDomainIds };
};
const success = { requestOptions: { showSuccessMsg: true } };

export const applicationApi = {
  list: async (params: API.PageQuery) => {
    const result = await http.get<API.PageResult<ApplicationDto>>(PREFIX, params);
    return { ...result, list: result.list.map(mapApplication) };
  },
  detail: async (id: number) => mapApplication(await http.get<ApplicationDto>(`${PREFIX}/${id}`)),
  create: async (input: ApplicationInput) => mapApplication(await http.post<ApplicationDto>(PREFIX, input, success)),
  update: async (id: number, input: ApplicationInput) => mapApplication(await http.put<ApplicationDto>(`${PREFIX}/${id}`, input, success)),
  remove: (id: number) => http.delete<void>(`${PREFIX}/${id}`, undefined, success),
  publish: async (id: number) => mapApplication(await http.post<ApplicationDto>(`${PREFIX}/${id}/publish`, undefined, success)),
  disable: async (id: number) => mapApplication(await http.post<ApplicationDto>(`${PREFIX}/${id}/disable`, undefined, success)),
  launch: (id: number) => http.get<{ launchUrl: string }>(`${PREFIX}/${id}/launch`),
  assignments: async (id: number) => (await http.get<AssignmentDto[]>(`${PREFIX}/${id}/assignments`)).map(mapAssignment),
  saveAssignments: (id: number, assignments: AssignmentInput[]) => http.put(`${PREFIX}/${id}/assignments`, { assignments }, success),
  domains: async (id: number) => (await http.get<DomainDto[]>(`${PREFIX}/${id}/domains`)).map(mapDomain),
  saveDomains: (id: number, change: { domains: DomainInput[]; expectedDomainIds: number[] }) => http.put(`${PREFIX}/${id}/domains`, change, success),
  database: async (id: number) => mapDatabase(await http.get<DatabaseDto>(`${PREFIX}/${id}/database`)),
  saveDatabase: (id: number, input: DatabaseInput) => http.put(`${PREFIX}/${id}/database`, input, success),
  health: (id: number) => http.post<{ status: string }>(`${PREFIX}/${id}/health`, undefined, success)
};
