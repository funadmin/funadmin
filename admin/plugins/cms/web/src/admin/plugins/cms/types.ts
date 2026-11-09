export type Id = string;
export interface ContributionPolicy { enabled:boolean; model_codes:string[]; models:Array<{code:string;name:string}> }
export interface MemberSubmission { id:Id;kind:string;title:string;body_html:string;member_id:Id;parent_id?:Id|null;status:string;lock_version:number;data?:FormData;review_reason?:string;created_at:string }
export interface Scope { site_id: Id; locale: string }
export type FormData = Record<string, any>;
export interface Site { id: Id; code: string; name: string; default_locale: string; theme: string; status: number; primary_domain_id: Id | null; settings: FormData }
export interface Locale { id: Id; site_id: Id; locale: string; url_prefix: string; name: string; status: number; seo_title: string; seo_description: string }
export interface Domain { id: Id; host: string; status: number; verification_status: string }
export interface SiteEditorOptions { themes: Array<{value: string; label: string}> }
export interface Settings { site: Site; locales: Locale[]; domains: Domain[]; admin_ids: Id[]; models: Array<{model_id: Id; status: number; default_template: string}> }
export interface ModelField { name: string; label: string; type: string; required?: boolean; options?: string[]; default?: unknown; description?:string; placeholder?:string; min?:number|null; max?:number|null; max_length?:number|null }
export interface ContentModel { id: Id; code: string; name: string; status: number; current_version: number; schema: { id: Id; field_schema: ModelField[] } }
export interface Revision { id: Id; revision_no: number; model_version_id: Id; title: string; summary: string; body_html: string; desired_path: string; cover_attach_id: Id | null; author_name: string; template: string; seo_title: string; seo_description: string; robots: string; data: FormData; created_at: string }
export interface ContentRow { id: Id; content_id: Id; model_id: Id; category_id: Id | null; locale: string; status: string; lock_version: number; draft_revision_id: Id; published_revision_id: Id | null; published_at: string | null; revision: Revision }
export interface ContentList { items: ContentRow[]; total: number; page: number; limit: number }
export interface Category extends FormData { id: Id; locale_id: Id; name: string; url: string }
export interface Provider { name: string; parameters: Record<string, {type: string; default?: unknown; required?: boolean; min?: number; max?: number; enum?: unknown[]}> }
export interface TagEditorOptions { models: Array<{value: string; label: string}>; categories: Array<{value: string; label: string; path: string}> }
export interface TemplateTag { id: Id; builtin?: false; locale: string; name: string; remark?: string; current_version: number; draft_version: number; draft: { provider: string; parameters: FormData; template_fragment: string } }
export interface BuiltinTemplateTag extends Provider { id: string; builtin: true }
export type TemplateTagListItem = TemplateTag | BuiltinTemplateTag;

export interface SiteContext { id: Id; name: string; default_locale: string; locales: Locale[]; permissions: string[] }
export interface SiteRole { id: Id; code: string; name: string; status: number; permissions: string[] }
export interface SiteMember { id: Id; username: string; real_name?: string; role_ids: Id[]; manageable: boolean; effective: string[]; host_permissions: string[] }
export interface AccessData { catalog: Record<string, {name: string; menu: string; actions: Record<string, string>}>; ceilings: Record<string, string[]>; host_permissions: string[]; roles: SiteRole[]; members: SiteMember[] }

export type ConfigurationScalarType = 'text'|'textarea'|'number'|'boolean'|'url'|'email'|'image'|'color'|'select';
export type ConfigurationType = ConfigurationScalarType|'list';
export type ConfigurationScalar = string|number|boolean|null;
export type ConfigurationRow = Record<string, ConfigurationScalar> & { enabled: boolean };
export interface ConfigurationField { key: string; label: string; type: ConfigurationType; group: string; localized: boolean; default: ConfigurationScalar|ConfigurationRow[]; options?: string[]; fields?: ConfigurationField[] }
export interface SiteConfiguration { fields: ConfigurationField[]; values: Record<string, any>; overrides: Record<string, any>; effective: Record<string, any>; locale: string }

export type FormSubmissionStatus = 'new'|'processing'|'done'|'invalid';
export interface CmsFormOption { value: string; label: string }
export interface CmsFormField { name: string; type: string; label: string; width: 'full'|'half'; placeholder?: string; help?: string; required?: boolean; default?: unknown; min?: number|string|null; max?: number|string|null; integer?: boolean; options?: CmsFormOption[]; multiple?: boolean; html?: string; accept?: string[]; max_size?: number; max_files?: number; value?: string; from_query?: boolean }
export interface CmsFormFieldText { label?: string; placeholder?: string; help?: string; options?: Record<string, string> }
export interface CmsFormLocaleText { name?: string; success_message?: string; submit_text?: string; fields?: Record<string, CmsFormFieldText> }
export interface CmsFormSettings { success_message: string; submit_text: string; captcha: boolean; rate_limit: number }
export interface CmsForm { id: Id; site_id: Id; code: string; name: string; enabled: boolean; fields: CmsFormField[]; texts: Record<string, CmsFormLocaleText>; settings: CmsFormSettings; lock_version: number; created_at: string; updated_at: string; submission_count?: number; new_count?: number }
export interface FormSubmissionRow { id: Id; form_id: Id; locale: string; status: FormSubmissionStatus; source_url: string; ip: string; user_agent: string; member_id: Id|null; lock_version: number; handled_at: string|null; created_at: string; form_code: string; form_name: string; summary: Array<{label: string; value: string}> }
export interface FormSubmissionList { list: FormSubmissionRow[]; total: number; page: number; pageSize: number }
export interface FormSubmissionFile { id: Id; field: string; name: string; mime: string; size: number; created_at: string }
export interface FormSubmissionEvent { id: Id; type: string; admin_id: Id|null; body: string; from_status: FormSubmissionStatus|null; to_status: FormSubmissionStatus|null; created_at: string }
export interface FormSubmissionDetail extends Omit<FormSubmissionRow, 'summary'> { fields: Array<{name: string; type: string; label: string; value: unknown; text: string; files?: FormSubmissionFile[]}>; files: FormSubmissionFile[]; events: FormSubmissionEvent[] }
