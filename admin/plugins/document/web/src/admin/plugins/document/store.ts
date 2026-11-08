import { defineStore } from 'pinia';
import type { JSONContent } from '@tiptap/core';
import { documentApi } from './api';
import type { DocumentCategory, DocumentDetail, DocumentQuery, DocumentScope, DocumentSummary, EditorMode } from './types';

interface State {
  tree: DocumentCategory[];
  documents: DocumentSummary[];
  total: number;
  query: DocumentQuery & { page: number; pageSize: number };
  selectedScope: DocumentScope | '';
  selectedCategoryId: number | null;
  current: DocumentDetail | null;
  draft: JSONContent | null;
  /** 只有切换文档、恢复版本、重新载入、导入时递增；编辑器据此决定是否替换内容。 */
  contentGeneration: number;
  dirty: boolean;
  editorMode: EditorMode;
  loadingList: boolean;
  loadingDocument: boolean;
  saving: boolean;
  lastSavedAt: number | null;
  conflict: boolean;
  error: string;
}

const errorMessage = (e: unknown, fallback: string) => {
  const m = (e as { msg?: unknown; message?: unknown } | null)?.msg ?? (e as { message?: unknown } | null)?.message;
  return typeof m === 'string' && m ? m : fallback;
};
const errorCode = (e: unknown) => Number((e as { code?: unknown } | null)?.code);

/** 递增的请求令牌：只有最新一次请求的结果会写回状态，避免迟到响应覆盖。 */
let listToken = 0;
let documentToken = 0;
let autosaveTimer: ReturnType<typeof setTimeout> | null = null;
/** 每次本地编辑递增，用于判断保存请求期间是否又有新输入。 */
let editRevision = 0;
let inflightSave: Promise<boolean> | null = null;

export const useDocumentStore = defineStore('pluginDocument', {
  state: (): State => ({
    tree: [],
    documents: [],
    total: 0,
    query: { page: 1, pageSize: 20, keyword: '' },
    selectedScope: '',
    selectedCategoryId: null,
    current: null,
    draft: null,
    contentGeneration: 0,
    dirty: false,
    editorMode: 'split',
    loadingList: false,
    loadingDocument: false,
    saving: false,
    lastSavedAt: null,
    conflict: false,
    error: ''
  }),

  actions: {
    async loadTree() {
      this.tree = await documentApi.tree();
    },

    async loadList(patch: Partial<DocumentQuery> = {}) {
      Object.assign(this.query, patch);
      const token = ++listToken;
      this.loadingList = true;
      try {
        const query: DocumentQuery = { ...this.query, scope: this.selectedScope || undefined, categoryId: this.selectedCategoryId ?? undefined };
        const page = await documentApi.list(query);
        if (token !== listToken) return;
        this.documents = page.list;
        this.total = page.total;
      } finally {
        if (token === listToken) this.loadingList = false;
      }
    },

    async selectCategory(scope: DocumentScope | '', categoryId: number | null) {
      this.selectedScope = scope;
      this.selectedCategoryId = categoryId;
      await this.loadList({ page: 1 });
    },

    async openDocument(id: number) {
      this.cancelAutosave();
      const token = ++documentToken;
      this.loadingDocument = true;
      try {
        const detail = await documentApi.detail(id);
        if (token !== documentToken) return;
        this.replaceDocument(detail);
        this.error = '';
      } finally {
        if (token === documentToken) this.loadingDocument = false;
      }
    },

    /** 放弃本地修改，重新读取服务器上的最新草稿。 */
    async reload() {
      if (this.current) await this.openDocument(this.current.id);
    },

    setContent(content: JSONContent) {
      this.draft = content;
      this.dirty = true;
      editRevision++;
    },

    /** 用外部内容替换草稿（导入），编辑器会随之更新。 */
    replaceContent(content: JSONContent) {
      this.setContent(content);
      this.contentGeneration++;
    },

    scheduleAutosave(delay = 4000) {
      this.cancelAutosave();
      autosaveTimer = setTimeout(() => {
        autosaveTimer = null;
        if (!this.conflict) void this.saveDraft();
      }, delay);
    },

    cancelAutosave() {
      if (autosaveTimer) clearTimeout(autosaveTimer);
      autosaveTimer = null;
    },

    /**
     * 保存草稿。成功后只更新 lockVersion 等元数据，不回写正文：
     * 服务端正文与编辑器 JSON 的键顺序不同，回写会让光标跳动并吞掉途中输入。
     */
    async saveDraft(): Promise<boolean> {
      this.cancelAutosave();
      if (inflightSave) return inflightSave;
      if (!this.current || !this.draft) return false;
      if (!this.dirty) return true;
      const id = this.current.id;
      const revisionAtSend = editRevision;
      inflightSave = (async () => {
        this.saving = true;
        try {
          const saved = await documentApi.saveDraft(id, this.draft!, this.current!.lockVersion);
          if (this.current?.id !== id) return true;
          this.mergeServerState(saved);
          this.dirty = editRevision !== revisionAtSend;
          this.conflict = false;
          this.error = '';
          this.lastSavedAt = Date.now();
          this.syncListRow(saved);
          return true;
        } catch (e) {
          if (errorCode(e) === 409) this.conflict = true;
          this.error = errorMessage(e, '保存失败，请重试');
          return false;
        } finally {
          this.saving = false;
          inflightSave = null;
        }
      })();
      return inflightSave;
    },

    /** 先保存未保存的修改，再按当前 lockVersion 发布；返回是否真的发布了。 */
    async publish(changeSummary: string): Promise<boolean> {
      if (!this.current) return false;
      this.cancelAutosave();
      if (!(await this.saveDraft()) || this.dirty || !this.current) return false;
      try {
        const detail = await documentApi.publish(this.current.id, changeSummary, this.current.lockVersion);
        this.mergeServerState(detail);
        this.syncListRow(detail);
        return true;
      } catch (e) {
        if (errorCode(e) === 409) this.conflict = true;
        this.error = errorMessage(e, '发布失败，请重试');
        return false;
      }
    },

    async archive(): Promise<boolean> {
      if (!this.current) return false;
      this.cancelAutosave();
      if (!(await this.saveDraft()) || this.dirty || !this.current) return false;
      try {
        const detail = await documentApi.archive(this.current.id, this.current.lockVersion);
        this.mergeServerState(detail);
        this.syncListRow(detail);
        return true;
      } catch (e) {
        if (errorCode(e) === 409) this.conflict = true;
        this.error = errorMessage(e, '归档失败，请重试');
        return false;
      }
    },

    async restoreRevision(revisionNo: number) {
      if (!this.current) return;
      this.cancelAutosave();
      try {
        const detail = await documentApi.restore(this.current.id, revisionNo, this.current.lockVersion);
        this.replaceDocument(detail);
        this.syncListRow(detail);
      } catch (e) {
        if (errorCode(e) === 409) this.conflict = true;
        this.error = errorMessage(e, '恢复失败，请重试');
        throw e;
      }
    },

    async remove() {
      if (!this.current) return;
      this.cancelAutosave();
      const id = this.current.id;
      await documentApi.remove(id);
      this.current = null;
      this.draft = null;
      this.dirty = false;
      this.documents = this.documents.filter((row) => row.id !== id);
      this.total = Math.max(0, this.total - 1);
    },

    /** 整体替换当前文档与正文（打开、恢复、重新载入）。 */
    replaceDocument(detail: DocumentDetail) {
      this.current = detail;
      this.draft = detail.content;
      this.dirty = false;
      this.conflict = false;
      this.contentGeneration++;
    },

    /** 合并服务端返回的状态与 lockVersion，保留本地正文。 */
    mergeServerState(detail: DocumentDetail) {
      if (!this.current) return;
      this.current = { ...detail, content: this.current.content, plainText: this.current.plainText };
    },

    /** 只合并元数据：保留本地正文、lockVersion、冲突与未保存状态，否则下一次保存会绕过乐观锁。 */
    applyMeta(detail: DocumentDetail) {
      if (!this.current || this.current.id !== detail.id) return;
      const { content: _c, plainText: _p, lockVersion: _l, ...meta } = detail;
      this.current = { ...this.current, ...meta };
      this.syncListRow(this.current);
    },

    syncListRow(detail: DocumentDetail) {
      const index = this.documents.findIndex((row) => row.id === detail.id);
      if (index < 0) return;
      const { content: _content, plainText: _plain, ...summary } = detail;
      this.documents.splice(index, 1, summary);
    }
  }
});
