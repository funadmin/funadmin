import { computed, ref, shallowRef } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import type { FormDefinition, FormPublishConfig, FormSchemaDocument as ApiSchemaDocument } from '@/api/form';
import { businessDevelopmentApi, isBusinessApiError, type BusinessModule } from '@/api/development/business';
import { menuApi } from '@/api/system/menu';
import { hasPermission } from '@/utils/permission';
import type { FormSchemaDocument } from '@/views/form/schema/types';
import { FORM_WIDTH_MAX, FORM_WIDTH_MIN, changedPublishConfig, publishConfigOf, pruneParentMenus } from './generationConfig';
import { integerFieldNodes, schemaFieldNodes } from './schemaFields';

export const PERM_SAVE_SCHEMA = 'development:business:save-schema';
export const PERM_SAVE_PUBLISH = 'development:business:save-publish-config';

const PATH_PATTERN = /^\/[a-z][a-z0-9-]*(?:\/[a-z][a-z0-9-]*)*$/;

export interface ConflictReview {
  local: FormSchemaDocument;
  serverDocument: FormSchemaDocument;
  serverHash: string;
}

// 接口层与字段表格模型各有一份同构的 Schema 文档类型（选项来源、栅格跨度的声明宽窄不同），在接口边界转换。
const fromApi = (document: ApiSchemaDocument): FormSchemaDocument => document as unknown as FormSchemaDocument;
const toApi = (document: FormSchemaDocument): ApiSchemaDocument => document as unknown as ApiSchemaDocument;

const isConflict = (error: unknown): boolean => isBusinessApiError(error) && error.data.error.code === 'FORM_SCHEMA_CONFLICT';

/**
 * 编辑生成信息的数据与保存流程：Schema 走 schema/save（带 expectedSchemaHash），发布设置走 publish-config。
 * 两次写入不是原子的：先 Schema 后发布设置，冲突或失败时保留未保存的改动。
 */
export interface GenerationEditorOptions {
  /** 每次服务端写入成功（Schema、发布设置、冲突时本地覆盖）后调用；即使整体保存未完成也会调用。 */
  onWrite?: (kind: 'schema' | 'publish') => void;
}

export function useGenerationEditor(moduleId: () => number, options: GenerationEditorOptions = {}) {
  const { t } = useI18n();
  const loading = ref(false);
  const loadError = ref('');
  const module = ref<BusinessModule | null>(null);
  const form = ref<FormDefinition | null>(null);
  // Schema 文档以不可变方式替换，保留结构共享，未触碰的节点保持原引用。
  const baseDoc = shallowRef<FormSchemaDocument | null>(null);
  const doc = shallowRef<FormSchemaDocument | null>(null);
  const schemaHash = ref('');
  const basePublish = ref<FormPublishConfig | null>(null);
  const publish = ref<FormPublishConfig | null>(null);
  const menus = ref<API.MenuItem[]>([]);
  const saving = ref(false);
  const conflict = ref(false);
  const conflictReview = ref<ConflictReview | null>(null);
  const conflictLoading = ref(false);
  const conflictError = ref('');
  const publishSaveFailed = ref('');
  const pendingRelations = ref(false);
  // 已加载数据所属的模块；保存与冲突处理只针对它，避免把 A 模块的数据写进 B 模块
  const loadedId = ref(0);
  // 每次从服务端替换 Schema 时递增，供关联页签等本地草稿重新初始化
  const schemaRevision = ref(0);
  let loadToken = 0;

  const schemaDirty = computed(() => Boolean(doc.value) && JSON.stringify(doc.value) !== JSON.stringify(baseDoc.value));
  const publishChanges = computed(() => basePublish.value && publish.value ? changedPublishConfig(basePublish.value, publish.value) : {});
  const publishDirty = computed(() => Object.keys(publishChanges.value).length > 0);
  const dirty = computed(() => schemaDirty.value || publishDirty.value || pendingRelations.value);
  const loaded = computed(() => Boolean(doc.value && form.value && publish.value) && loadedId.value === moduleId() && !loading.value && !loadError.value);

  const errorText = (error: unknown, fallback: string): string => {
    if (isBusinessApiError(error)) return t('business.codegen.edit.requestError', { msg: error.msg, id: error.data.error.requestId }, '{msg}（请求 ID：{id}）');
    return error instanceof Error && error.message ? error.message : fallback;
  };

  const applyServerSchema = (document: FormSchemaDocument, hash: string) => {
    baseDoc.value = document;
    doc.value = document;
    schemaHash.value = hash;
    if (form.value) form.value = { ...form.value, schema_document: toApi(document), schema_hash: hash };
    schemaRevision.value++;
    pendingRelations.value = false;
  };

  async function loadMenus(formKey: string, token: number) {
    try {
      const tree = await menuApi.tree();
      if (token === loadToken) menus.value = pruneParentMenus(tree, formKey);
    } catch {
      if (token === loadToken) menus.value = [];
    }
  }

  function reset() {
    module.value = null;
    form.value = null;
    baseDoc.value = null;
    doc.value = null;
    schemaHash.value = '';
    basePublish.value = null;
    publish.value = null;
    menus.value = [];
    loadedId.value = 0;
    conflict.value = false;
    conflictReview.value = null;
    conflictError.value = '';
    publishSaveFailed.value = '';
    pendingRelations.value = false;
  }

  async function load() {
    const id = moduleId();
    const token = ++loadToken;
    // 先清空：加载失败或切换模块时不能继续显示、保存上一个模块的数据
    reset();
    loading.value = true;
    loadError.value = '';
    try {
      const data = await businessDevelopmentApi.module(id);
      if (token !== loadToken) return;
      const definition = data.form;
      if (!definition?.schema_document || definition.schema_document.schemaVersion !== 2 || !definition.schema_hash) {
        throw new Error(t('business.codegen.edit.noSchema', '业务模块没有可编辑的 Schema 文档或版本信息'));
      }
      module.value = data.module;
      form.value = definition;
      applyServerSchema(fromApi(definition.schema_document), String(definition.schema_hash));
      const config = publishConfigOf({ ...definition, name: definition.schema_document.title || definition.name });
      basePublish.value = structuredClone(config);
      publish.value = config;
      loadedId.value = id;
      void loadMenus(definition.form_key, token);
    } catch (error) {
      if (token !== loadToken) return;
      reset();
      loadError.value = errorText(error, t('business.codegen.edit.loadFailed', '加载生成信息失败'));
    } finally {
      if (token === loadToken) loading.value = false;
    }
  }

  function validateSchema(): string {
    const current = doc.value as FormSchemaDocument;
    if (!current.title.trim()) return t('business.codegen.edit.titleRequired', '请填写表描述');
    const tree = current.list?.tree;
    if (tree?.enabled && !tree.parentField) return t('business.codegen.edit.treeParentRequired', '树表需要选择父级字段');
    return '';
  }

  function validatePublish(): string {
    const config = publish.value as FormPublishConfig;
    const fields = doc.value ? schemaFieldNodes(doc.value) : [];
    if (!PATH_PATTERN.test(config.apiPrefix)) return t('business.codegen.edit.apiPrefixInvalid', '请填写正确的接口前缀，如 /generated/shop-goods');
    if (!PATH_PATTERN.test(config.routePath)) return t('business.codegen.edit.routePathInvalid', '请填写正确的前端路由，如 /generated/shop-goods');
    if (!config.menuName.trim()) return t('business.codegen.edit.menuNameRequired', '请填写菜单名称');
    if (config.formWidth != null && (!Number.isInteger(config.formWidth) || config.formWidth < FORM_WIDTH_MIN || config.formWidth > FORM_WIDTH_MAX)) {
      return t('business.codegen.edit.formWidthInvalid', { min: FORM_WIDTH_MIN, max: FORM_WIDTH_MAX }, '表单宽度须为 {min}–{max} 之间的整数');
    }
    if (config.dataScopeEnabled && !fields.some((node) => node.field === config.dataScopeField)) {
      return t('business.codegen.edit.dataScopeFieldRequired', '启用数据权限后请选择部门字段');
    }
    if (config.memberApiEnabled && !integerFieldNodes(fields).some((node) => node.field === config.memberApiOwnerField)) {
      return t('business.codegen.edit.memberApiOwnerRequired', '启用会员端 API 后请选择整数类型的会员归属字段');
    }
    return '';
  }

  /** 返回 true 表示没有遗留未保存的改动（含原本就没有改动）。 */
  async function save(): Promise<boolean> {
    if (saving.value || !loaded.value || !doc.value || !publish.value) return false;
    if (pendingRelations.value) {
      ElMessage.warning(t('business.codegen.edit.relationsPending', '关联配置有未完成的行，请补全或删除后再保存'));
      return false;
    }
    if (conflict.value && schemaDirty.value) {
      ElMessage.warning(t('business.codegen.edit.conflictPending', 'Schema 版本已变化，请先点击「核对版本」选择处理方式'));
      return false;
    }
    const invalid = (schemaDirty.value ? validateSchema() : '') || (publishDirty.value ? validatePublish() : '');
    if (invalid) {
      ElMessage.warning(invalid);
      return false;
    }
    if ((schemaDirty.value && !hasPermission(PERM_SAVE_SCHEMA)) || (publishDirty.value && !hasPermission(PERM_SAVE_PUBLISH))) {
      ElMessage.warning(t('business.codegen.edit.noSavePermission', '没有保存权限'));
      return false;
    }
    const id = loadedId.value;
    saving.value = true;
    let schemaSaved = false;
    try {
      if (schemaDirty.value) {
        try {
          const saved = await businessDevelopmentApi.saveSchema(id, toApi(doc.value), schemaHash.value, t('business.codegen.edit.saveSummary', '代码生成：编辑生成信息'));
          applyServerSchema(fromApi(saved.document), saved.schemaHash);
          schemaSaved = true;
          options.onWrite?.('schema');
        } catch (error) {
          if (isConflict(error)) {
            conflict.value = true;
            ElMessage.warning(t('business.codegen.edit.schemaConflict', 'Schema 版本已变化，保存已暂停，发布设置也未提交；本地改动已保留，请点击「核对版本」选择处理方式'));
          } else {
            ElMessage.error(errorText(error, t('business.codegen.edit.saveFailed', '保存失败，请重试')));
          }
          return false;
        }
      }
      if (publishDirty.value) {
        const snapshot = JSON.parse(JSON.stringify(publish.value)) as FormPublishConfig;
        try {
          const saved = await businessDevelopmentApi.savePublishConfig(id, publishChanges.value as Partial<FormPublishConfig>);
          if (form.value) form.value = { ...form.value, publish_config: saved.publishConfig };
          basePublish.value = snapshot;
          publishSaveFailed.value = '';
          options.onWrite?.('publish');
        } catch (error) {
          const reason = errorText(error, t('business.codegen.edit.requestFailed', '请求失败'));
          publishSaveFailed.value = schemaSaved || publishSaveFailed.value
            ? t('business.codegen.edit.partialSaved', { msg: reason }, '字段已保存，发布设置未保存：{msg}。改动已保留，可修改后重试')
            : t('business.codegen.edit.publishSaveFailed', { msg: reason }, '发布设置未保存：{msg}。改动已保留，可修改后重试');
          ElMessage.error(publishSaveFailed.value);
          return false;
        }
      }
      return true;
    } finally {
      saving.value = false;
    }
  }

  async function reviewConflict() {
    if (!loaded.value || !doc.value || conflictLoading.value) return;
    const local = doc.value;
    const id = loadedId.value;
    conflictLoading.value = true;
    conflictError.value = '';
    conflictReview.value = null;
    try {
      const data = await businessDevelopmentApi.module(id);
      if (id !== loadedId.value) return;
      const server = data.form;
      if (!server?.schema_hash || server.schema_document?.schemaVersion !== 2) {
        throw new Error(t('business.codegen.edit.serverDocMissing', '服务端文档或版本 hash 缺失，无法安全处理，请重新核对'));
      }
      conflictReview.value = { local, serverDocument: fromApi(server.schema_document), serverHash: String(server.schema_hash) };
    } catch (error) {
      conflictError.value = errorText(error, t('business.codegen.edit.versionReadFailed', '版本读取失败，请重新核对'));
    } finally {
      conflictLoading.value = false;
    }
  }

  function cancelConflictReview() {
    conflictReview.value = null;
    conflictError.value = '';
  }

  /** 与设计器一致的两种明确处理方式；都需要二次确认，本地覆盖只针对已核对的服务端版本。 */
  async function resolveConflict(choice: 'local' | 'server'): Promise<boolean> {
    const review = conflictReview.value;
    if (!review || saving.value || !loaded.value) return false;
    if (choice === 'local' && !hasPermission(PERM_SAVE_SCHEMA)) return false;
    const prompt = choice === 'local'
      ? t('business.codegen.edit.overwriteConfirm', '确认以已核对的本地 Schema 覆盖此服务端版本？服务端再次变化时将拒绝保存。')
      : t('business.codegen.edit.useServerConfirm', '确认放弃本地未保存的字段与关联改动，采用服务端版本？发布设置改动会保留。');
    try {
      await ElMessageBox.confirm(prompt, t('business.codegen.edit.conflictTitle', '核对保存冲突'), { type: 'warning' });
    } catch {
      return false;
    }
    if (choice === 'server') {
      applyServerSchema(review.serverDocument, review.serverHash);
      conflict.value = false;
      conflictReview.value = null;
      return true;
    }
    saving.value = true;
    try {
      const saved = await businessDevelopmentApi.saveSchema(loadedId.value, toApi(review.local), review.serverHash, t('business.codegen.edit.reasonConflictLocal', '代码生成：核对冲突后采用本地'));
      applyServerSchema(fromApi(saved.document), saved.schemaHash);
      options.onWrite?.('schema');
      conflict.value = false;
      conflictReview.value = null;
      return true;
    } catch (error) {
      conflictError.value = isConflict(error)
        ? t('business.codegen.edit.serverChangedAgain', '服务端版本再次变化，请重新核对；本地改动已保留')
        : errorText(error, t('business.codegen.edit.saveFailed', '保存失败，请重试'));
      return false;
    } finally {
      saving.value = false;
    }
  }

  return {
    loading, loadError, module, form, doc, schemaHash, publish, menus, saving,
    conflict, conflictReview, conflictLoading, conflictError, publishSaveFailed, pendingRelations,
    schemaDirty, publishDirty, dirty, loaded, schemaRevision,
    load, save, reviewConflict, cancelConflictReview, resolveConflict
  };
}
