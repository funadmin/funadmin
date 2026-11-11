import type { JSONContent } from '@tiptap/core';

export type DocumentScope = 'framework' | 'plugin_dev' | 'official_plugin' | 'third_party';
export type DocumentVisibility = 'public' | 'admin';
export type DocumentStatus = 'draft' | 'published' | 'archived';
export type DocumentType = 'guide' | 'reference' | 'api' | 'architecture' | 'deployment' | 'adr' | 'changelog';
export type EditorMode = 'edit' | 'split' | 'preview';

export const SCOPES: DocumentScope[] = ['framework', 'plugin_dev', 'official_plugin', 'third_party'];
export const TYPES: DocumentType[] = ['guide', 'reference', 'api', 'architecture', 'deployment', 'adr', 'changelog'];

export interface DocumentCategory {
  id: number;
  scope: DocumentScope;
  parentId: number;
  name: string;
  slug: string;
  sortOrder: number;
  /** 直接归属该目录的文档数（目录树接口返回） */
  documentCount?: number;
  children: DocumentCategory[];
}

export interface DocumentSummary {
  id: number;
  title: string;
  slug: string;
  summary: string;
  scope: DocumentScope;
  pluginCode: string;
  type: DocumentType;
  status: DocumentStatus;
  visibility?: DocumentVisibility;
  appliesTo: string;
  /** 同一目录内的阅读顺序，数值小的在前 */
  sortOrder: number;
  lockVersion: number;
  currentRevisionNo: number;
  /** 已发布文档的草稿与最新修订不同 */
  hasUnpublishedChanges: boolean;
  /** 元数据乐观锁，修改文档信息时回传 */
  metaVersion: number;
  categoryIds: number[];
  updatedAt: string;
  /** 读者反馈计数（仅列表接口返回） */
  feedback?: FeedbackCounts;
}

export interface DocumentDetail extends DocumentSummary {
  content: JSONContent;
  plainText: string;
}

export interface DocumentPage {
  list: DocumentSummary[];
  total: number;
  page: number;
  pageSize: number;
}

export interface DocumentQuery {
  scope?: DocumentScope | '';
  categoryId?: number;
  pluginCode?: string;
  type?: DocumentType | '';
  status?: DocumentStatus | '';
  keyword?: string;
  page?: number;
  pageSize?: number;
}

export interface DocumentMetaInput {
  visibility?: DocumentVisibility;
  title: string;
  slug: string;
  summary?: string;
  scope: DocumentScope;
  type: DocumentType;
  pluginCode?: string;
  appliesTo?: string;
  sortOrder?: number;
  categoryIds?: number[];
}

export interface FeedbackCounts {
  helpful: number;
  unhelpful: number;
}

export type FeedbackReason = 'incomplete' | 'unclear' | 'outdated' | 'broken' | 'missing' | 'other';

export interface FeedbackItem {
  id: number;
  documentId: number;
  title: string;
  slug: string;
  revisionNo: number;
  helpful: boolean;
  reasons: FeedbackReason[];
  comment: string;
  updatedAt: string;
}

export interface FeedbackQuery {
  documentId?: number;
  /** '1' 有帮助、'0' 无帮助、'' 全部 */
  helpful?: '' | '1' | '0';
  withComment?: boolean;
  page?: number;
  pageSize?: number;
}

export interface FeedbackPage {
  list: FeedbackItem[];
  total: number;
  page: number;
  pageSize: number;
  /** 当前文档范围内的汇总，不受 helpful / withComment 筛选影响 */
  summary: FeedbackCounts;
}

export interface RevisionSummary {
  revisionNo: number;
  title: string;
  changeSummary: string;
  createdBy: number;
  createdAt: string;
}

export interface RevisionDetail {
  revisionNo: number;
  title: string;
  summary: string;
  changeSummary: string;
  createdAt: string;
  content: JSONContent;
}

/** 回收站中的文档（已删除） */
export interface TrashedDocument {
  id: number;
  title: string;
  slug: string;
  scope: DocumentScope;
  status: DocumentStatus;
  deletedAt: string;
}

export interface TrashedDocumentPage {
  list: TrashedDocument[];
  total: number;
  page: number;
  pageSize: number;
}

/** 回收站中的目录（已删除） */
export interface TrashedCategory {
  id: number;
  scope: DocumentScope;
  parentId: number;
  name: string;
  slug: string;
  sortOrder: number;
  deletedAt: string;
}
