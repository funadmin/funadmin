import http from '@/utils/http';
import type { Scope, FormData, Site, Settings, SiteEditorOptions, ContentModel, ContentRow, ContentList, Revision, Category, Provider, TagEditorOptions, TemplateTag, TemplateTagListItem, SiteContext, AccessData, SiteMember, SiteConfiguration, ContributionPolicy } from './types';
const base = '/plugins/cms';
const get = <T>(path: string, params?: FormData) => http.get<T>(`${base}${path}`, params);
const post = <T>(path: string, data: FormData) => http.post<T>(`${base}${path}`, data);
export const cmsApi = {
  contributionSettings: (site_id:string) => get<ContributionPolicy>('/contributions/settings',{site_id}),
  saveContributionSettings: (data:FormData) => post<ContributionPolicy>('/contributions/settings/save',data),
  configuration: (site_id: string, locale?: string) => get<SiteConfiguration>('/configuration', locale ? {site_id, locale} : {site_id}),
  saveConfiguration: (data: FormData) => post<SiteConfiguration>('/configuration/save', data),
  context: (menu?: string) => get<{items: SiteContext[]; is_super: boolean}>('/context', menu ? {menu} : undefined),
  access: (site_id: string) => get<AccessData>('/access', {site_id}),
  candidates: (site_id: string) => get<SiteMember[]>('/access/candidates', {site_id}),
  saveRole: (data: FormData) => post('/access/roles/save', data),
  assignRoles: (data: FormData) => post('/access/members/save', data),
  removeMember: (data: FormData) => post('/access/members/remove', data),
  siteModels: (scope: Scope) => get<Settings['models']>('/models/bindings', scope),
  sites: () => get<{items: Site[]; is_super: boolean}>('/sites'),
  settings: (site_id: string) => get<Settings>('/settings', {site_id}),
  siteOptions: (site_id?: string) => get<SiteEditorOptions>('/sites/options', site_id ? {site_id} : undefined),
  saveSite: (data: FormData) => post<Site>('/sites/save', data),
  saveLocale: (data: FormData) => post('/locales/save', data),
  saveDomain: (data: FormData) => post('/domains/save', data),
  grant: (data: FormData) => post('/sites/grant', data),
  saveSiteModel: (data: FormData) => post('/site-models/save', data),
  content: (scope: Scope & FormData) => get<ContentList>('/content', scope),
  translation: (data: FormData) => get<ContentRow>('/content/translation', data),
  attachments: (data: FormData) => get<FormData[]>('/content/attachments', data),
  upload: (scope: Scope, file: File, images = false) => {
    const body = new globalThis.FormData();
    body.append('site_id', scope.site_id); body.append('locale', scope.locale);
    body.append('file', file); body.append('bizType', images ? 'image' : 'file');
    return http.upload<{id: string; url: string; name: string; ext: string}>(`${base}/content/upload`, body);
  },
  administrators: () => get<FormData[]>('/sites/admins'),
  menuTargets: (scope: Scope) => get<FormData[]>('/menus/targets', scope),
  lookups: (scope: Scope) => get<{models: ContentModel[]; categories: Category[]; templates: Array<{value: string; label: string}>}>('/content/lookups', scope),
  detail: (data: FormData) => get<ContentRow>('/content/detail', data),
  revisions: (data: FormData) => get<Revision[]>('/content/revisions', data),
  saveContent: (data: FormData) => post<ContentRow>('/content/save', data),
  publishContent: (data: FormData) => post<ContentRow>('/content/publish', data),
  offline: (data: FormData) => post('/content/offline', data),
  previewContent: (data: FormData) => get<{html: string}>('/content/preview', data),
  categories: (scope: Scope) => get<Category[]>('/categories', scope),
  categoryStatus: (data: FormData) => post('/categories/status', data),
  menuStatus: (data: FormData) => post('/menus/status', data),
  saveCategory: (data: FormData) => post<Category>('/categories/save', data),
  models: (scope: Scope) => get<ContentModel[]>('/models', scope),
  saveModel: (data: FormData) => post<ContentModel>('/models/save', data),
  menus: (scope: Scope) => get<FormData[]>('/menus', scope),
  saveMenu: (data: FormData) => post('/menus/save', data),
  removeMenu: (data: FormData) => post('/menus/remove', data),
  tags: (scope: Scope) => get<TemplateTagListItem[]>('/tags', scope),
  providers: (scope: Scope) => get<Provider[]>('/tags/providers', scope),
  tagOptions: (scope: Scope) => get<TagEditorOptions>('/tags/options', scope),
  saveTag: (data: FormData) => post<TemplateTag>('/tags/save', data),
  publishTag: (data: FormData) => post('/tags/publish', data),
  previewTag: (data: FormData) => get<{html: string}>('/tags/preview', data)
};
