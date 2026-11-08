<template>
  <el-dialog
    :model-value="modelValue"
    :title="t('business.codegen.create.title', '新建表')"
    width="min(640px, 96vw)"
    top="8vh"
    class="codegen-create-dialog"
    :close-on-click-modal="false"
    :close-on-press-escape="!submitting"
    :show-close="!submitting"
    @update:model-value="setVisible"
  >
    <el-alert v-if="!canCreate" class="codegen-create__alert" type="warning" show-icon :closable="false" :title="t('business.codegen.create.noPermission', '没有创建新表权限：需要创建新表与查看业务目标权限。')" />
    <el-form ref="formRef" :model="form" :rules="rules" label-position="top" class="codegen-create" :aria-busy="submitting" @submit.prevent>
      <div class="codegen-create__two">
        <el-form-item :label="t('business.codegen.create.name', '业务名称')" prop="name">
          <el-input v-model="form.name" data-field="name" maxlength="100" :disabled="submitting" :placeholder="t('business.codegen.create.namePlaceholder', '例如 客户订单')" />
          <span class="codegen-create__hint">{{ t('business.codegen.create.nameHelp', '列表与菜单中显示的名称') }}</span>
        </el-form-item>
        <el-form-item :label="t('business.codegen.create.code', '业务标识')" prop="code">
          <el-input v-model="form.code" data-field="code" maxlength="61" :disabled="submitting" :placeholder="t('business.codegen.create.codePlaceholder', '例如 customer_order')" @update:model-value="onCodeInput" @blur="normalizeCode" />
          <span class="codegen-create__hint">{{ t('business.codegen.create.codeHelp', '英文名称会自动建议标识，可改；小写字母开头，只含小写字母、数字和下划线') }}</span>
        </el-form-item>
      </div>

      <el-form-item :label="t('business.codegen.create.target', '生成目标')">
        <div class="codegen-create__cards" role="radiogroup" :aria-label="t('business.codegen.create.target', '生成目标')">
          <button
            v-for="card in targetCards"
            :key="card.kind"
            type="button"
            role="radio"
            class="codegen-create__card"
            :class="{ 'is-active': kind === card.kind }"
            :data-target-card="card.kind"
            :aria-checked="kind === card.kind"
            :disabled="submitting"
            @click="chooseKind(card.kind)"
          >
            <strong>{{ card.title }}</strong>
            <small>{{ card.description }}</small>
          </button>
        </div>
        <el-select
          v-if="kind === 'plugin'"
          v-model="selected"
          data-field="plugin"
          class="codegen-create__plugin"
          filterable
          :loading="targetsLoading"
          :disabled="submitting || targetsLoading"
          :placeholder="t('business.codegen.create.pluginPlaceholder', '选择插件')"
          :aria-label="t('business.codegen.create.plugin', '插件')"
        >
          <el-option v-for="item in pluginCandidates" :key="item.pluginCode ?? ''" :label="candidateLabel(item)" :value="item.pluginCode ?? ''" :disabled="item.available === false" />
        </el-select>
        <span v-if="targetMessage" class="codegen-create__hint is-warning" role="alert">{{ targetMessage }}</span>
      </el-form-item>

      <div class="codegen-create__two">
        <el-form-item :label="t('business.codegen.create.connection', '数据库连接')" prop="connection">
          <!-- 后台没有列出数据库连接的接口：选项为服务端默认连接，允许输入其他已配置的连接标识。 -->
          <el-select
            v-model="form.connection"
            data-field="connection"
            filterable
            allow-create
            default-first-option
            :reserve-keyword="false"
            :disabled="submitting || Boolean(selected)"
            :aria-label="t('business.codegen.create.connection', '数据库连接')"
            @update:model-value="connectionTouched = true"
          >
            <el-option v-for="name in connectionOptions" :key="name" :label="name === defaultConnection ? t('business.codegen.create.connectionDefault', { name }, '{name}（默认）') : name" :value="name" />
          </el-select>
          <span class="codegen-create__hint">{{ selected ? t('business.codegen.create.connectionPluginHelp', '插件目标固定使用默认连接') : t('business.codegen.create.connectionHelp', '默认连接来自服务端配置，也可输入其他已配置的连接标识') }}</span>
        </el-form-item>
        <el-form-item :label="t('business.codegen.create.table', '数据表名')" prop="table">
          <el-input v-if="tableEditing" ref="tableInput" v-model="form.table" data-field="table" :disabled="submitting" @blur="finishTableEdit" />
          <button
            v-else
            type="button"
            class="codegen-create__readonly"
            data-table-display
            :disabled="submitting"
            :title="t('business.codegen.create.tableEdit', '点击修改数据表名')"
            :aria-label="t('business.codegen.create.tableEditAria', { name: autoTable || '-' }, '数据表名 {name}，点击修改')"
            @click="editTable"
          >
            <span :class="{ 'is-placeholder': !autoTable }">{{ autoTable || t('business.codegen.create.tablePlaceholder', '填写业务标识后自动生成') }}</span>
            <i class="i-ep-edit" aria-hidden="true" />
          </button>
          <span class="codegen-create__hint">{{ t('business.codegen.create.tableHelp', '自动补齐前缀（插件前缀；连接表前缀如 fun_ 由服务端补齐），点击可改') }}</span>
        </el-form-item>
      </div>

      <el-form-item :label="t('business.codegen.create.tableType', '表格类型')">
        <el-radio-group v-model="tableType" class="codegen-create__segment" :disabled="submitting" :aria-label="t('business.codegen.create.tableType', '表格类型')">
          <el-radio-button value="normal" data-table-type="normal">{{ t('business.codegen.create.typeNormal', '普通表格') }}</el-radio-button>
          <el-radio-button value="tree" data-table-type="tree">{{ t('business.codegen.create.typeTree', '树形表格') }}</el-radio-button>
        </el-radio-group>
        <span class="codegen-create__hint">{{ tableType === 'tree' ? t('business.codegen.create.typeTreeHelp', '树形表格需要父级字段：创建后在设计器「树形与分类」里选择父级字段并开启') : t('business.codegen.create.typeNormalHelp', '单表增删改查；需要树形时可在设计器里再开启') }}</span>
      </el-form-item>

      <p v-if="form.code" class="codegen-create__preview" data-create-preview>
        {{ t('business.codegen.create.preview', { code: form.code, table: form.table || autoTable }, '将创建：业务模块 {code} · 数据表 {table}；创建后进入表单设计器添加字段') }}
      </p>
    </el-form>

    <template #footer>
      <div class="codegen-create__footer">
        <el-button data-action="create-cancel" :disabled="submitting" @click="setVisible(false)">{{ t('business.codegen.create.cancel', '取消') }}</el-button>
        <el-button data-action="create-submit" type="primary" :loading="submitting" :disabled="!canSubmit" @click="submit">{{ t('business.codegen.create.submit', '创建并开始设计') }}</el-button>
      </div>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import type { FormInstance, FormRules, InputInstance } from 'element-plus';
import { businessDevelopmentApi, type BusinessModuleDetail } from '@/api/development/business';
import { hasPermission } from '@/utils/permission';
import { useBusinessTarget } from '../composables/useBusinessTarget';
import {
  BUSINESS_CODE_PATTERN, IDENTIFIER_PATTERN, applyFieldErrors, createVisualPayload, defaultCreatedTable,
  designerLocation, normalizeBusinessCode, parseCreationError, suggestBusinessCode
} from '../constants';

const props = defineProps<{ modelValue: boolean }>();
const emit = defineEmits<{
  'update:modelValue': [value: boolean];
  /** 创建成功（随后进入表单设计器）。 */
  created: [detail: BusinessModuleDetail];
}>();

const { t } = useI18n();
const route = useRoute();
const router = useRouter();

type TargetKind = 'core' | 'plugin';

// 与创建向导一致：创建新表需要 create-visual，读取业务目标沿用 modules 授权。
const canCreate = computed(() => hasPermission('development:business:create-visual') && hasPermission('development:business:modules'));
const {
  selected, candidates, candidateLabel, defaultConnection, loading: targetsLoading,
  notice: targetNotice, available: targetAvailable, target, loadTargets
} = useBusinessTarget(() => canCreate.value);

const formRef = ref<FormInstance>();
const tableInput = ref<InputInstance>();
const submitting = ref(false);
const form = reactive({ name: '', code: '', table: '', connection: defaultConnection.value });
const codeTouched = ref(false);
const connectionTouched = ref(false);
const tableEditing = ref(false);
const tableType = ref<'normal' | 'tree'>('normal');
const kind = ref<TargetKind>(selected.value ? 'plugin' : 'core');

const targetCards = computed(() => [
  { kind: 'core' as const, title: t('business.codegen.create.targetCore', '核心后台'), description: t('business.codegen.create.targetCoreHelp', '代码生成到 app/admin/business，可动态发布') },
  { kind: 'plugin' as const, title: t('business.codegen.create.targetPlugin', '插件'), description: t('business.codegen.create.targetPluginHelp', '生成到插件目录，安装／更新后生效') }
]);
const pluginCandidates = computed(() => candidates.value.filter((item) => item.type === 'plugin'));
const pluginMissing = computed(() => kind.value === 'plugin' && !selected.value);
const targetMessage = computed(() => {
  if (!canCreate.value) return '';
  if (pluginMissing.value) return targetsLoading.value || pluginCandidates.value.length ? '' : t('business.codegen.create.noPlugins', '没有可选的插件');
  return targetNotice.value;
});
const autoTable = computed(() => defaultCreatedTable(form.code, selected.value));
const connectionOptions = computed(() => [...new Set([defaultConnection.value, form.connection.trim()].filter(Boolean))]);
/** 目标与权限是否允许创建（不含提交中状态，供提交过程中复核）。 */
const targetReady = computed(() => canCreate.value && targetAvailable.value && !pluginMissing.value);
const canSubmit = computed(() => targetReady.value && !submitting.value);

const rules: FormRules = {
  name: [{ required: true, whitespace: true, message: t('business.codegen.create.nameRequired', '请输入业务名称'), trigger: 'blur' }],
  code: [{ required: true, pattern: BUSINESS_CODE_PATTERN, message: t('business.codegen.create.codeInvalid', '以小写字母开头，只能包含小写字母、数字和下划线'), trigger: 'blur' }],
  table: [{ validator: (_rule, value, callback) => !value || IDENTIFIER_PATTERN.test(value) ? callback() : callback(new Error(t('business.codegen.create.tableInvalid', '数据表名只能包含小写字母、数字和下划线'))), trigger: 'blur' }],
  connection: [{ required: true, pattern: IDENTIFIER_PATTERN, message: t('business.codegen.create.connectionInvalid', '连接标识不合法'), trigger: 'change' }]
};

// 业务标识跟随名称建议，直到用户手动改过（清空后恢复跟随）。
watch(() => form.name, (name) => { if (!codeTouched.value) form.code = suggestBusinessCode(name); });
// 数据表名默认只读并跟随标识与目标；进入编辑后由用户决定。
watch([autoTable, tableEditing], () => { if (!tableEditing.value) form.table = autoTable.value; }, { immediate: true });
// 插件目标服务端只接受默认连接；核心目标在用户未改动时跟随服务端默认连接。
watch([selected, defaultConnection], () => {
  if (selected.value || !connectionTouched.value) form.connection = defaultConnection.value;
});
// 换目标后插件前缀随之改变：恢复自动表名（与创建向导切换目标时清空表名一致）。
watch(selected, (value) => {
  tableEditing.value = false;
  if (value) kind.value = 'plugin';
});

function onCodeInput(value: string) {
  codeTouched.value = value !== '';
}

function normalizeCode() {
  form.code = normalizeBusinessCode(form.code);
}

function chooseKind(next: TargetKind) {
  if (submitting.value || kind.value === next) return;
  kind.value = next;
  selected.value = '';
}

async function editTable() {
  if (submitting.value) return;
  form.table = autoTable.value;
  tableEditing.value = true;
  await nextTick();
  tableInput.value?.focus?.();
}

function finishTableEdit() {
  form.table = form.table.trim();
  if (!form.table) tableEditing.value = false;
}

function setVisible(value: boolean) {
  if (!value && submitting.value) return;
  emit('update:modelValue', value);
}

async function reset() {
  Object.assign(form, { name: '', code: '', table: '', connection: defaultConnection.value });
  codeTouched.value = false;
  connectionTouched.value = false;
  tableEditing.value = false;
  tableType.value = 'normal';
  selected.value = typeof route.query.plugin === 'string' ? route.query.plugin : '';
  kind.value = selected.value ? 'plugin' : 'core';
  await nextTick();
  formRef.value?.clearValidate();
  await loadTargets();
}

// 首次打开时组件刚挂载，状态即为初始值（目标由 useBusinessTarget 挂载时加载）；之后每次重新打开都清空上一次的输入。
watch(() => props.modelValue, (visible) => { if (visible) void reset(); });

async function submit() {
  if (!canSubmit.value) return;
  submitting.value = true;
  try {
    normalizeCode();
    form.name = form.name.trim();
    form.connection = form.connection.trim();
    if (!await formRef.value?.validate().catch(() => false) || !targetReady.value) return;
    const result = await businessDevelopmentApi.createVisual(createVisualPayload({
      name: form.name,
      code: form.code,
      table: form.table.trim(),
      connection: form.connection,
      remark: '',
      treeEnabled: tableType.value === 'tree',
      treeParentField: '',
      treeSelectionMode: 'single'
    }, target.value));
    emit('created', result);
    emit('update:modelValue', false);
    await router.push(designerLocation(result.module));
  } catch (reason) {
    const { fieldErrors, status, message } = parseCreationError(reason);
    if (Object.keys(fieldErrors).length) applyFieldErrors(formRef.value, fieldErrors);
    else if (status === 409) applyFieldErrors(formRef.value, { code: message ?? t('business.codegen.create.codeExists', '业务标识已存在') });
  } finally {
    submitting.value = false;
  }
}
</script>

<style scoped>
.codegen-create__alert {
  margin-bottom: 12px;
}

.codegen-create__two {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0 16px;
}

.codegen-create :deep(.el-form-item__content) {
  flex-direction: column;
  align-items: stretch;
  gap: 4px;
}

.codegen-create__hint {
  color: var(--el-text-color-secondary);
  font-size: 12px;
  line-height: 1.5;
}

.codegen-create__hint.is-warning {
  color: var(--el-color-warning);
}

.codegen-create__cards {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.codegen-create__card {
  display: grid;
  gap: 4px;
  padding: 10px 12px;
  border: 1.5px solid var(--el-border-color);
  border-radius: 12px;
  background: var(--el-bg-color);
  color: var(--el-text-color-primary);
  text-align: left;
  cursor: pointer;
  font: inherit;
  transition: border-color 0.15s, box-shadow 0.15s;
}

.codegen-create__card:hover:not(:disabled) {
  border-color: var(--el-color-primary);
}

.codegen-create__card:focus-visible {
  outline: 2px solid var(--el-color-primary);
  outline-offset: 2px;
}

.codegen-create__card:disabled {
  cursor: not-allowed;
  opacity: 0.6;
}

.codegen-create__card strong {
  font-size: 13px;
  line-height: 1.4;
}

.codegen-create__card small {
  color: var(--el-text-color-secondary);
  font-size: 12px;
  line-height: 1.5;
}

.codegen-create__card.is-active {
  border-color: var(--el-color-primary);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--el-color-primary) 14%, transparent);
}

.codegen-create__plugin {
  width: 100%;
  margin-top: 8px;
}

.codegen-create__readonly {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  width: 100%;
  min-height: 32px;
  padding: 0 11px;
  border: 1px solid var(--el-border-color);
  border-radius: var(--el-border-radius-base);
  background: var(--el-fill-color-light);
  color: var(--el-text-color-regular);
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 13px;
  text-align: left;
  cursor: text;
}

.codegen-create__readonly span {
  min-width: 0;
  overflow-wrap: anywhere;
}

.codegen-create__readonly .is-placeholder {
  color: var(--el-text-color-placeholder);
  font-family: var(--el-font-family);
}

.codegen-create__readonly i {
  flex: none;
  color: var(--el-text-color-secondary);
}

.codegen-create__readonly:hover:not(:disabled) {
  border-color: var(--el-color-primary);
}

.codegen-create__readonly:focus-visible {
  outline: 2px solid var(--el-color-primary);
  outline-offset: 1px;
}

.codegen-create__segment {
  align-self: flex-start;
}

.codegen-create__preview {
  margin: 0;
  padding: 8px 12px;
  border-radius: 10px;
  background: color-mix(in srgb, var(--el-color-primary) 8%, var(--el-bg-color));
  color: var(--el-text-color-regular);
  font-size: 12px;
  overflow-wrap: anywhere;
}

/* 夜间模式：主色混合比例略高，避免选中描边与预览底色在深色背景上看不出（scoped 下编译为 html.dark .codegen-create__…[data-v-…]）。 */
html.dark .codegen-create__card.is-active {
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--el-color-primary) 28%, transparent);
}

html.dark .codegen-create__preview {
  background: color-mix(in srgb, var(--el-color-primary) 16%, var(--el-bg-color));
}

.codegen-create__footer {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 8px;
}

.codegen-create__footer .el-button + .el-button {
  margin-left: 0;
}

@media (max-width: 640px) {
  .codegen-create__two,
  .codegen-create__cards {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
