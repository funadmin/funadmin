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
  lockVersion: number;
  currentRevisionNo: number;
  /** 已发布文档的草稿与最新修订不同 */
  hasUnpublishedChanges: boolean;
  /** 元数据乐观锁，修改文档信息时回传 */
  metaVersion: number;
  categoryIds: number[];
  updatedAt: string;
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
  categoryIds?: number[];
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
