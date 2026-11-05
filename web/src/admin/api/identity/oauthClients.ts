import http from '@/utils/http';

const CLIENTS = '/identity/oauth-clients';
const KEYS = '/identity/signing-keys';
const success = { requestOptions: { showSuccessMsg: true } };

export type OAuthClientType = 'public' | 'confidential' | 'machine';
export type OAuthGrant = 'authorization_code' | 'refresh_token' | 'client_credentials';
export type RedirectUriType = 'authorization_callback' | 'post_logout';

export interface OAuthRedirectUri { id?: number; uriType: RedirectUriType; redirectUri: string }
export interface OAuthClient {
  id: number; applicationId: number; clientId: string; name: string; clientType: OAuthClientType;
  status: 'active' | 'disabled'; requirePkce: boolean; grants: OAuthGrant[]; scopes: string[]; redirectUris: OAuthRedirectUri[];
}
export interface OAuthClientInput { applicationId: number; name: string; clientType: OAuthClientType; grants: OAuthGrant[]; scopes: string[] }
export interface ClientSecretMetadata { id: number; prefix: string; expiresAt?: string | null; revokedAt?: string | null; createdAt?: string }
export interface RevealedClientSecret extends ClientSecretMetadata { secret: string }
export interface SigningKey { id: number; kid: string; algorithm: 'RS256'; status: 'pending'|'active'|'retiring'|'retired'; activatedAt?: string; publishUntil?: string }

type ClientDto = Record<string, any>;
const mapRedirect = (row: ClientDto): OAuthRedirectUri => ({ id: row.id, uriType: row.uri_type, redirectUri: row.redirect_uri });
export const mapOAuthClient = (row: ClientDto): OAuthClient => ({
  id: row.id, applicationId: row.application_id, clientId: row.client_id, name: row.name, clientType: row.client_type,
  status: row.status, requirePkce: Boolean(row.require_pkce), grants: row.grants || [], scopes: row.scopes || [],
  redirectUris: (row.redirect_uris || []).map(mapRedirect)
});
const mapSecret = (row: ClientDto): ClientSecretMetadata => ({ id: row.id, prefix: row.prefix || row.secret_prefix, expiresAt: row.expiresAt || row.expires_at, revokedAt: row.revokedAt || row.revoked_at, createdAt: row.created_at });
const mapKey = (row: ClientDto): SigningKey => ({ id: row.id, kid: row.kid, algorithm: row.algorithm, status: row.status, activatedAt: row.activated_at, publishUntil: row.publish_until });

export const oauthClientApi = {
  list: async (params: API.PageQuery & { applicationId?: number }) => { const result = await http.get<API.PageResult<ClientDto>>(CLIENTS, params); return { ...result, list: result.list.map(mapOAuthClient) }; },
  detail: async (id: number) => mapOAuthClient(await http.get<ClientDto>(`${CLIENTS}/${id}`)),
  create: async (input: OAuthClientInput) => mapOAuthClient(await http.post<ClientDto>(CLIENTS, input, success)),
  update: async (id: number, input: OAuthClientInput) => mapOAuthClient(await http.put<ClientDto>(`${CLIENTS}/${id}`, input, success)),
  remove: (id: number) => http.delete(`${CLIENTS}/${id}`, undefined, success),
  disable: async (id: number) => mapOAuthClient(await http.post<ClientDto>(`${CLIENTS}/${id}/disable`, undefined, success)),
  secrets: async (id: number) => (await http.get<ClientDto[]>(`${CLIENTS}/${id}/secrets`)).map(mapSecret),
  createSecret: async (id: number, expiresAt?: string) => { const row = await http.post<ClientDto>(`${CLIENTS}/${id}/secrets`, { expiresAt }, success); return { ...mapSecret(row), secret: String(row.secret) }; },
  revokeSecret: (id: number, secretId: number) => http.delete(`${CLIENTS}/${id}/secrets/${secretId}`, undefined, success),
  replaceRedirectUris: (id: number, redirectUris: OAuthRedirectUri[]) => http.put(`${CLIENTS}/${id}/redirect-uris`, { redirectUris: redirectUris.map((row) => ({ type: row.uriType, uri: row.redirectUri })) }, success),
  replaceScopes: async (id: number, scopes: string[]) => mapOAuthClient(await http.put<ClientDto>(`${CLIENTS}/${id}/scopes`, { scopes }, success)),
  replaceGrants: async (id: number, grants: OAuthGrant[]) => mapOAuthClient(await http.put<ClientDto>(`${CLIENTS}/${id}/grants`, { grants }, success))
};

export const signingKeyApi = {
  list: async () => (await http.get<ClientDto[]>(KEYS)).map(mapKey),
  rotate: async (publishWindowSeconds = 86400) => mapKey(await http.post<ClientDto>(`${KEYS}/rotate`, { publishWindowSeconds }, success))
};
