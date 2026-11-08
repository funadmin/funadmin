import type { JSONContent } from '@tiptap/core';
import http from '@/utils/http';
import type {
  DocumentCategory,
  DocumentDetail,
  DocumentMetaInput,
  DocumentPage,
  DocumentQuery,
  RevisionDetail,
  RevisionSummary
} from './types';

const docs = '/plugin/document/documents';
const categories = '/plugin/document/categories';

/** 业务错误（409 冲突、422 校验）由页面自行提示，不弹全局消息。 */
const quiet = { requestOptions: { showErrorMsg: false } };

export const documentApi = {
  tree: () => http.get<DocumentCategory[]>(categories),
  createCategory: (data: Partial<DocumentCategory>) => http.post<DocumentCategory>(categories, data, quiet),
  updateCategory: (id: number, data: Partial<DocumentCategory>) => http.put<DocumentCategory>(`${categories}/${id}`, data, quiet),
  deleteCategory: (id: number) => http.delete(`${categories}/${id}`, undefined, quiet),

  list: (query: DocumentQuery) => http.get<DocumentPage>(docs, query),
  detail: (id: number) => http.get<DocumentDetail>(`${docs}/${id}`),
  create: (data: DocumentMetaInput & { content?: JSONContent }) => http.post<DocumentDetail>(docs, data, quiet),
  updateMeta: (id: number, data: Partial<DocumentMetaInput> & { metaVersion: number }) => http.put<DocumentDetail>(`${docs}/${id}`, data, quiet),
  saveDraft: (id: number, content: JSONContent, lockVersion: number) =>
    http.put<DocumentDetail>(`${docs}/${id}/draft`, { content, lockVersion }, quiet),
  remove: (id: number) => http.delete(`${docs}/${id}`),
  publish: (id: number, changeSummary: string, lockVersion: number) =>
    http.post<DocumentDetail>(`${docs}/${id}/publish`, { changeSummary, lockVersion }, quiet),
  archive: (id: number, lockVersion: number) => http.post<DocumentDetail>(`${docs}/${id}/archive`, { lockVersion }, quiet),
  revisions: (id: number, page = 1) => http.get<{ list: RevisionSummary[]; total: number }>(`${docs}/${id}/revisions`, { page }),
  revision: (id: number, revisionNo: number) => http.get<RevisionDetail>(`${docs}/${id}/revisions/${revisionNo}`),
  restore: (id: number, revisionNo: number, lockVersion: number) =>
    http.post<DocumentDetail>(`${docs}/${id}/revisions/${revisionNo}/restore`, { lockVersion }, quiet)
};
