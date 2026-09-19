<template>
  <PageWrapper :title="t('business.visual.title', '创建业务')" :subtitle="t('business.visual.subtitle', '创建业务模块草稿后进入统一 FormSchema v2 设计器')">
    <el-card shadow="never">
      <el-form ref="formRef" :model="form" :rules="rules" label-position="left" label-width="100px" class="business-form max-w-4xl">
        <el-form-item :label="t('business.visual.mode', '创建方式')">
          <el-radio-group v-model="mode" :disabled="submitting" :aria-label="t('business.visual.mode', '创建方式')" class="creation-mode">
            <el-radio value="created">{{ t('business.visual.modeCreate', '创建新表') }}</el-radio>
            <el-radio value="adopted">{{ t('business.visual.modeAdopt', '使用已有表') }}</el-radio>
          </el-radio-group>
          <span class="field-help">{{ t('business.visual.modeHelp', '创建新表由插件拥有并经迁移建表；使用已有表仅引用表结构，不取得所有权。') }}</span>
        </el-form-item>
        <p v-if="permissionNotice" class="form-span-full" role="alert">{{ permissionNotice }}</p>
        <el-form-item :label="t('business.visual.target', '业务目标')">
          <el-select v-model="selected" :loading="targetsLoading" :disabled="submitting" :aria-label="t('business.visual.target', '业务目标')">
            <el-option v-for="item in candidates" :key="item.pluginCode || 'core'" :label="candidateLabel(item)" :disabled="item.available === false" :value="item.pluginCode || ''" />
          </el-select>
          <span v-if="targetNotice" role="alert">{{ targetNotice }}</span>
          <a v-if="canLoadTargets && !targetsLoading && targetNotice" href="#" @click.prevent="loadTargets">{{ t('business.visual.reloadTargets', '重新加载目标') }}</a>
          <span class="field-help">{{ mode === 'adopted' ? (selected ? t('business.visual.helpAdoptedSelected', '已有表仅作为外部依赖，不生成 CREATE／ALTER，不取得表所有权，卸载或清除插件不会删除该表。核心敏感表及其他插件所属表不可采纳。') : t('business.visual.helpAdoptedEmpty', '先只读检查已有表结构，采纳后保存不可变 Schema 基线。')) : (selected ? t('business.visual.helpCreatedSelected', '插件拥有新表；生成仅写源码和迁移，不建表、不安装启用，需安装／更新发布。') : t('business.visual.helpCreatedEmpty', '核心后台保持原有动态发布流程。')) }}</span>
        </el-form-item>
        <el-form-item :label="t('business.visual.name', '业务名称')" prop="name">
          <el-input v-model="form.name" maxlength="100" aria-describedby="business-name-help" />
          <span id="business-name-help" class="field-help">{{ t('business.visual.nameHelp', '用于展示业务模块，最多 100 个字符。') }}</span>
        </el-form-item>
        <el-form-item :label="t('business.visual.code', '业务标识')" prop="code">
          <el-input v-model="form.code" maxlength="61" :placeholder="t('business.visual.codePlaceholder', '例如 customer_order')" aria-describedby="business-code-help" @blur="normalize" />
          <span id="business-code-help" class="field-help">{{ t('business.visual.codeHelp', '以小写字母开头，只能包含小写字母、数字和下划线。') }}</span>
        </el-form-item>
        <el-form-item v-if="mode === 'created'" key="new-table" :label="t('business.visual.newTable', '新表名称')" prop="table">
          <el-input v-model="form.table" :placeholder="t('business.visual.newTablePlaceholder', '默认业务标识，自动补齐连接前缀')" aria-describedby="business-table-help" />
          <span id="business-table-help" class="field-help">{{ t('business.visual.newTableHelp', '留空时根据业务标识自动生成。') }}</span>
        </el-form-item>
        <el-form-item v-else key="existing-table" :label="t('business.visual.existingTable', '已有数据表')" prop="existingTable">
          <div class="existing-table-controls">
            <el-select v-if="canListTables" v-model="form.existingTable" filterable allow-create default-first-option :loading="tablesLoading" :disabled="submitting" :placeholder="t('business.visual.existingTableSelectPlaceholder', '搜索并选择已有数据表')" :aria-label="t('business.visual.existingTable', '已有数据表')" @visible-change="visible => visible && loadTables()">
              <el-option v-for="table in tables" :key="table.name" :label="table.comment ? `${table.name} — ${table.comment}` : table.name" :value="table.name" />
            </el-select>
            <el-input v-else v-model="form.existingTable" :disabled="submitting || !canInspect" :placeholder="t('business.visual.existingTableInputPlaceholder', '输入已有数据表名称')" :aria-label="t('business.visual.existingTable', '已有数据表')" />
            <el-button v-if="canInspect" data-action="inspect" :loading="inspecting || inspectPending" :disabled="submitting || !form.existingTable" @click="inspect">{{ t('business.visual.inspect', '检查结构') }}</el-button>
          </div>
          <span v-if="tableError" class="field-help" role="alert">{{ tableError }} <a href="#" @click.prevent="loadTables">{{ t('business.visual.reload', '重新加载') }}</a></span>
        </el-form-item>
        <el-form-item :label="t('business.visual.connection', '数据库连接')" prop="connection">
          <el-input v-model="form.connection" :disabled="Boolean(selected)" aria-describedby="business-connection-help" />
          <span id="business-connection-help" class="field-help">{{ t('business.visual.connectionHelp', '填写已配置的数据库连接标识。') }}</span>
        </el-form-item>
        <el-form-item :label="t('business.visual.remark', '备注')" class="form-span-full">
          <el-input v-model="form.remark" type="textarea" :rows="4" maxlength="1000" show-word-limit aria-describedby="business-remark-help" />
          <span id="business-remark-help" class="field-help">{{ t('business.visual.remarkHelp', '可选，最多 1000 个字符。') }}</span>
        </el-form-item>
        <el-form-item :label="t('business.visual.treeEnabled', '树形表格')">
          <el-switch v-model="form.treeEnabled" :disabled="submitting || mode === 'created'" aria-describedby="business-tree-help" />
          <span id="business-tree-help" class="field-help">{{ t('business.visual.treeHelp', '采纳已有表可直接绑定父级字段；创建新表请创建后在设计器“树形与分类”开启。') }}</span>
        </el-form-item>
        <el-form-item v-if="form.treeEnabled && mode === 'adopted'" :label="t('business.visual.treeParentField', '父级字段')">
          <el-select v-model="form.treeParentField" filterable :disabled="submitting" :placeholder="t('business.visual.treeParentFieldPlaceholder', '选择存储父记录主键的标量字段')">
            <el-option v-for="field in scalarInspectionFields" :key="String(field.name)" :label="String(field.label || field.name)" :value="String(field.name)" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="form.treeEnabled && mode === 'adopted'" :label="t('business.visual.treeSelectionMode', '新增页父级选择')">
          <el-radio-group v-model="form.treeSelectionMode" :disabled="submitting">
            <el-radio value="single">{{ t('business.visual.treeSelectionSingle', '单选') }}</el-radio>
            <el-radio value="multiple">{{ t('business.visual.treeSelectionMultiple', '多选') }}</el-radio>
          </el-radio-group>
          <span class="field-help">{{ t('business.visual.treeSelectionHelp', '新增/编辑页父级字段以当前记录树呈现：单选为树选择，多选为可勾选树。') }}</span>
        </el-form-item>
        <div class="sr-only" aria-live="polite" aria-atomic="true">{{ announcement }}</div>
        <BusinessPageState v-if="mode === 'adopted' && canInspect" class="form-span-full" :loading="inspecting" :error="inspectionError" :empty="!inspection" :empty-text="inspectionNotice || t('business.visual.inspectFirst', '选择或输入已有数据表后，请先检查结构')" :on-retry="form.existingTable && canInspect ? inspect : undefined">
          <template v-if="inspection">
            <el-alert :title="inspectionSummary" :type="inspectionBlockReason ? 'warning' : 'success'" :closable="false" class="mb-3" />
            <dl class="inspection-meta mb-3">
              <div><dt>{{ t('business.visual.metaConnection', '连接') }}</dt><dd>{{ inspection.connection }}</dd></div>
              <div><dt>{{ t('business.visual.metaTable', '数据表') }}</dt><dd>{{ inspection.table }}</dd></div>
              <div><dt>{{ t('business.visual.metaPrimaryKey', '主键') }}</dt><dd>{{ inspection.primaryKey.join(', ') || t('business.visual.noPrimaryKey', '缺少主键') }}</dd></div>
              <div><dt>{{ t('business.visual.snapshotHash', '快照哈希') }}</dt><dd class="snapshot-hash">{{ inspection.snapshotHash }}</dd></div>
            </dl>
            <div class="field-table-scroll" tabindex="0" role="region" :aria-label="t('business.visual.fieldListAria', '检查字段列表')">
              <el-table class="field-table" :data="inspection.fields" border max-height="360">
                <el-table-column prop="name" :label="t('business.visual.fieldField', '字段')" min-width="140" />
                <el-table-column prop="label" :label="t('business.visual.fieldName', '名称')" min-width="140" />
                <el-table-column prop="dbType" :label="t('business.visual.fieldType', '列类型')" min-width="140" />
                <el-table-column prop="component" :label="t('business.visual.fieldComponent', '控件')" min-width="120" />
              </el-table>
            </div>
            <div class="field-cards" :aria-label="t('business.visual.fieldCardsAria', '检查字段卡片')">
              <article v-for="(field, index) in inspection.fields" :key="String(field.name || index)" class="field-card">
                <strong>{{ field.name || field.field_name || t('business.visual.fieldFallback', { n: index + 1 }, '字段 {n}') }}</strong>
                <span>{{ field.label || '—' }}</span>
                <span>{{ field.dbType || field.columnType || field.column_type || '—' }}</span>
                <span>{{ field.component || field.type || '—' }}</span>
              </article>
            </div>
          </template>
        </BusinessPageState>
        <el-form-item class="form-span-full">
          <div class="form-actions">
            <el-button v-if="canSubmitMode" data-action="submit" type="primary" :loading="submitting" :disabled="submitting || !targetAvailable || (mode === 'adopted' && !canAdopt)" @click="submit">{{ mode === 'created' ? t('business.visual.submitCreate', '创建并开始设计') : t('business.visual.submitAdopt', '采纳并进入设计器') }}</el-button>
            <el-button :disabled="submitting" @click="cancel">{{ t('common.cancel', '取消') }}</el-button>
          </div>
        </el-form-item>
      </el-form>
    </el-card>
  </PageWrapper>
</template>

<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import { useUserStore } from '@/store/modules/user';
import { ElMessageBox, type FormInstance, type FormRules } from 'element-plus';
import { businessDevelopmentApi, isBusinessApiError, type BusinessDatabaseTable, type BusinessFieldInspection } from '@/api/development/business';
import BusinessPageState from './components/BusinessPageState.vue';
import { useLatestRequest } from './composables/useLatestRequest';
import { useDirtyGuard } from './composables/useDirtyGuard';
import { useBusinessTarget } from './composables/useBusinessTarget';

defineOptions({ name: 'BusinessVisual' });
const props = defineProps<{ initialMode?: 'created' | 'adopted' }>();
const router = useRouter();
const route = useRoute();
const user = useUserStore();
const { t } = useI18n();
// 合并的 save/inspect 别名不能推导后台独立动作授权。
const hasAction = (action: string) => user.permissions.some(permission => permission === '*' || permission === '*:*:*' || permission === `development.business:${action}`);
const canInspect = computed(() => hasAction('inspectdatabase'));
const canListTables = computed(() => hasAction('databasetables'));
const canCreate = computed(() => hasAction('createvisual'));
const canCreateFromDatabase = computed(() => hasAction('createfromdatabase'));
const canLoadTargets = computed(() => hasAction('modules') && (canCreate.value || canCreateFromDatabase.value));
const { selected, candidates, candidateLabel, defaultConnection, loading: targetsLoading, notice: targetNotice, available: targetAvailable, target, loadTargets } = useBusinessTarget(() => canLoadTargets.value);
const submitting = ref(false);
const dirty = ref(false);
const formRef = ref<FormInstance>();
const mode = ref<'created' | 'adopted'>(props.initialMode ?? (route.query.mode === 'adopted' || (!canCreate.value && canInspect.value) ? 'adopted' : 'created'));
const canSubmitMode = computed(() => mode.value === 'created' ? canCreate.value : canCreateFromDatabase.value);
const permissionNotice = computed(() => mode.value === 'created'
  ? (!canCreate.value ? t('business.visual.noCreatePermission', '无创建新表权限。') : '')
  : !canInspect.value ? t('business.visual.noInspectPermission', '无结构检查权限，不能检查或采纳已有表。')
    : !canCreateFromDatabase.value ? t('business.visual.noAdoptPermission', '当前为只读检查，无采纳权限，不能提交。') : '');
const inspectPending = ref(false);
const form = reactive({ name: '', code: '', table: '', existingTable: '', connection: 'mysql', remark: '', treeEnabled: false, treeParentField: '', treeSelectionMode: 'single' as 'single' | 'multiple' });
const scalarInspectionFields = computed(() => (inspection.value?.fields ?? []).filter((field: Record<string, unknown>) => !field.primary && !/(text|json|blob|binary|date|time)/i.test(String(field.dbType ?? ''))));
const identifier = /^[a-z_][a-z0-9_]*$/;
const rules: FormRules = {
  name: [{ required: true, message: t('business.visual.nameRequired', '请输入业务名称'), trigger: 'blur' }],
  code: [{ required: true, pattern: /^[a-z][a-z0-9_]{0,60}$/, message: t('business.visual.codeInvalid', '以小写字母开头，只能包含小写字母、数字和下划线'), trigger: 'blur' }],
  table: [{ validator: (_rule, value, callback) => !value || identifier.test(value) ? callback() : callback(new Error(t('business.visual.tableInvalid', '数据表标识不合法'))), trigger: 'blur' }],
  connection: [{ required: true, pattern: identifier, message: t('business.visual.connectionInvalid', '连接标识不合法'), trigger: 'blur' }],
  existingTable: [{ required: true, message: t('business.visual.existingTableRequired', '请选择已有数据表'), trigger: 'change' }]
};
const tableRequest = useLatestRequest<BusinessDatabaseTable[], [string]>(connection => businessDevelopmentApi.databaseTables(connection));
const tables = computed(() => tableRequest.data.value || []);
const tablesLoading = tableRequest.loading;
const tableError = computed(() => requestError(tableRequest.error.value));
const inspectionRequest = useLatestRequest<BusinessFieldInspection, [string, string]>((connection, table) => businessDevelopmentApi.inspectDatabase(connection, table));
const inspection = inspectionRequest.data;
const inspecting = inspectionRequest.loading;
const inspectionNotice = ref('');
const inspectionError = computed(() => requestError(inspectionRequest.error.value));
const inspectionBlockReason = computed(() => !inspection.value ? '' : !inspection.value.fields.length ? t('business.visual.noFields', '未识别到字段，无法采纳') : !inspection.value.primaryKey.length ? t('business.visual.noPrimaryKeyBlock', '缺少主键，无法采纳') : '');
const inspectionSummary = computed(() => inspectionBlockReason.value || t('business.visual.inspectionSummary', { n: inspection.value?.fields.length || 0 }, '已识别 {n} 个字段，确认后将保存不可变 Schema 基线。'));
const announcement = computed(() => inspecting.value ? t('business.visual.announcing', '正在检查数据库结构') : submitting.value ? t('business.visual.creating', '正在创建业务') : inspectionError.value || inspectionNotice.value || (inspection.value ? inspectionSummary.value : ''));
const canAdopt = computed(() => Boolean(canInspect.value && canCreateFromDatabase.value && inspection.value && !inspecting.value && !inspectionBlockReason.value && inspection.value.connection === form.connection.trim() && inspection.value.table === form.existingTable));
let inspectionEpoch = 0;

function requestError(error: unknown): string {
  if (!error) return '';
  return isBusinessApiError(error) ? error.msg : error instanceof Error ? error.message : t('business.visual.requestFailed', '请求失败，请重试');
}

function invalidateInspection(message = '') {
  inspectionEpoch += 1;
  inspectionRequest.invalidate();
  inspectionRequest.data.value = undefined;
  inspectionRequest.error.value = undefined;
  inspectionNotice.value = message;
}

async function loadTables() {
  if (!canListTables.value || mode.value !== 'adopted' || !form.connection.trim() || tablesLoading.value) return;
  try { await tableRequest.execute(form.connection.trim()); } catch { /* 错误由页面展示并允许重试。 */ }
}

async function inspect() {
  if (!canInspect.value || mode.value !== 'adopted' || submitting.value || inspecting.value || inspectPending.value) return;
  inspectPending.value = true;
  invalidateInspection();
  const epoch = inspectionEpoch;
  try {
    await formRef.value?.validateField(['connection', 'existingTable']);
    if (epoch !== inspectionEpoch || !canInspect.value) return;
    const result = await inspectionRequest.execute(form.connection.trim(), form.existingTable);
    if (epoch !== inspectionEpoch || !result || inspection.value !== result) return;
    if (!form.code.trim()) form.code = result.table.replace(/^fun_/, '').toLowerCase();
    if (!form.name.trim()) form.name = form.code;
  } catch { /* 校验和检查错误由对应控件展示。 */ }
  finally { inspectPending.value = false; }
}

watch(mode, async () => {
  invalidateInspection();
  tableRequest.invalidate();
  tableRequest.data.value = undefined;
  tableRequest.error.value = undefined;
  await nextTick();
  formRef.value?.clearValidate();
}, { flush: 'sync' });
watch(() => [form.connection, selected.value], () => {
  form.existingTable = '';
  tableRequest.invalidate();
  tableRequest.data.value = undefined;
  tableRequest.error.value = undefined;
  invalidateInspection(t('business.visual.staleNotice', '连接或目标已变化，请重新检查'));
}, { flush: 'sync' });
watch(() => form.existingTable, () => invalidateInspection(), { flush: 'sync' });

watch([selected, defaultConnection], () => {
  if (selected.value) form.connection = defaultConnection.value;
  form.table = '';
});
useDirtyGuard(dirty);
watch(form, () => { dirty.value = true; }, { deep: true, flush: 'sync' });

function normalize() {
  form.code = form.code.trim().toLowerCase().replace(/[^a-z0-9_]/g, '_');
  if (mode.value === 'created' && !form.table && form.code) form.table = `${selected.value ? `${selected.value}_` : ''}${form.code}`;
}

function fieldErrorMessage(value: unknown): string | undefined {
  if (typeof value === 'string') return value;
  if (Array.isArray(value)) return value.find((item): item is string => typeof item === 'string');
  if (value && typeof value === 'object' && 'message' in value && typeof value.message === 'string') return value.message;
  return undefined;
}

function errorRecord(reason: unknown): Record<string, unknown> {
  return reason && typeof reason === 'object' ? reason as Record<string, unknown> : {};
}

function responseDetails(reason: unknown): Record<string, unknown> {
  const response = errorRecord(reason);
  const data = errorRecord(response.data);
  const error = errorRecord(data.error);
  return errorRecord(response.details ?? data.details ?? error.details);
}

function responseFieldErrors(reason: unknown): Record<string, string> {
  const response = errorRecord(reason);
  const data = errorRecord(response.data);
  const details = responseDetails(reason);
  const source = details.fieldErrors ?? data.fieldErrors ?? response.fieldErrors;
  if (Array.isArray(source)) {
    return Object.fromEntries(source.flatMap((item) => {
      const error = errorRecord(item);
      const field = typeof error.path === 'string' ? error.path : typeof error.field === 'string' ? error.field : '';
      const message = fieldErrorMessage(error.message);
      return field && message ? [[field, message]] : [];
    }));
  }
  return Object.fromEntries(
    Object.entries(errorRecord(source)).flatMap(([field, value]) => {
      const message = fieldErrorMessage(value);
      return message ? [[field, message]] : [];
    })
  );
}

function responseCode(reason: unknown): number | undefined {
  const response = errorRecord(reason);
  const data = errorRecord(response.data);
  return [response.status, response.code, data.status, data.code].find((value): value is number => typeof value === 'number');
}

function setFieldErrors(errors: Record<string, string>) {
  formRef.value?.clearValidate();
  for (const [field, message] of Object.entries(errors)) {
    const context = formRef.value?.fields.find((item) => item.prop === field);
    if (!context) continue;
    context.validateState = 'error';
    context.validateMessage = message;
  }
  const first = Object.keys(errors)[0];
  if (!first) return;
  formRef.value?.scrollToField(first);
  formRef.value?.fields.find((item) => item.prop === first)?.$el?.querySelector<HTMLElement>('input, textarea, select, [tabindex]')?.focus();
}

function cancel() {
  if (submitting.value) return;
  router.push('/development/business/mine');
}

async function submit() {
  if (!canSubmitMode.value || !canLoadTargets.value || submitting.value || !targetAvailable.value || (mode.value === 'adopted' && !canAdopt.value)) return;
  submitting.value = true;
  try {
    if (mode.value === 'created') normalize();
    if (!await formRef.value?.validate() || !canSubmitMode.value || !canLoadTargets.value) return;
    const { existingTable, ...visualForm } = form;
    const listConfig = form.treeEnabled && mode.value === 'adopted' && form.treeParentField
      ? { tree: { enabled: true, parentField: form.treeParentField, selectionMode: form.treeSelectionMode } }
      : {};
    let result;
    if (mode.value === 'adopted') {
      const inspected = inspection.value;
      if (!inspected || !canAdopt.value) return;
      const selectedTarget = JSON.stringify(target.value);
      await ElMessageBox.confirm(
        t('business.visual.adoptConfirm', { connection: inspected.connection, table: inspected.table, keys: inspected.primaryKey.join(', '), count: inspected.fields.length }, '确认采纳连接 {connection} 的表 {table}？主键：{keys}；字段数：{count}。采纳后将保存不可变 Schema 基线。'),
        t('business.visual.adoptConfirmTitle', '确认数据库采纳'),
        { type: 'warning', confirmButtonText: t('business.visual.adoptConfirmButton', '确认采纳'), cancelButtonText: t('common.cancel', '取消') }
      );
      if (!canSubmitMode.value || !canLoadTargets.value || !targetAvailable.value || selectedTarget !== JSON.stringify(target.value) || inspection.value !== inspected || !canAdopt.value) return;
      result = await businessDevelopmentApi.createFromDatabase({ ...visualForm, listConfig, name: form.name.trim(), code: form.code.trim(), connection: form.connection.trim(), table: existingTable, target: target.value, expectedInspectionHash: inspected.snapshotHash });
    } else {
      result = await businessDevelopmentApi.createVisual({ ...visualForm, listConfig, target: target.value });
    }
    dirty.value = false;
    await router.push({ path: '/development/business/designer', query: { id: String(result.module.form_id), moduleId: String(result.module.id) } });
  } catch (reason) {
    if (isBusinessApiError(reason) && reason.data.error.code === 'DATABASE_INSPECTION_STALE') {
      invalidateInspection(t('business.visual.inspectionStale', '数据库结构已变化，请重新检查'));
      return;
    }
    const fieldErrors = responseFieldErrors(reason);
    if (mode.value === 'adopted' && fieldErrors.table) {
      fieldErrors.existingTable = fieldErrors.table;
      delete fieldErrors.table;
    }
    if (Object.keys(fieldErrors).length) setFieldErrors(fieldErrors);
    else if (responseCode(reason) === 409) {
      const response = errorRecord(reason);
      setFieldErrors({ code: typeof response.msg === 'string' ? response.msg : t('business.visual.codeExists', '业务标识已存在') });
    }
  } finally {
    submitting.value = false;
  }
}
</script>

<style scoped>
/* 双排自适应：宽屏两列、窄屏单列；备注/检查结果/操作行跨满两列。
   宽度收敛到 4xl，避免宽屏下输入框被拉伸得松散 */
.business-form {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  column-gap: 28px;
}
.business-form .form-span-full {
  grid-column: 1 / -1;
}
.inspection-meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 20px; }
.inspection-meta div { min-width: 0; }
.inspection-meta dt { color: var(--el-text-color-secondary); font-size: 12px; }
.inspection-meta dd { margin: 2px 0 0; overflow-wrap: anywhere; }
.snapshot-hash { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
.field-table-scroll { overflow-x: auto; }
.field-cards { display: none; }
.field-card { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px 12px; padding: 12px; border: 1px solid var(--el-border-color); border-radius: 6px; overflow-wrap: anywhere; }
.existing-table-controls {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  width: 100%;
}
.existing-table-controls .el-select { flex: 1; min-width: 200px; }
.field-help {
  display: block;
  width: 100%;
  color: var(--el-text-color-secondary);
  font-size: 12px;
  line-height: 1.5;
}

.form-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}

.form-actions :deep(.el-button) {
  margin-left: 0;
}

@media (max-width: 640px) {
  .business-form { grid-template-columns: 1fr; }
  .inspection-meta { grid-template-columns: 1fr; }
  .field-table-scroll { display: none; }
  .field-cards { display: grid; gap: 10px; }
  .business-form :deep(.el-form-item__label) {
    width: auto !important;
  }

  .form-actions {
    width: 100%;
  }
}
</style>
