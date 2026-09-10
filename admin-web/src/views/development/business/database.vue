<template>
  <PageWrapper title="数据库采纳" subtitle="先只读检查表结构，再创建绑定已有数据表的业务模块">
    <el-card shadow="never">
      <el-form ref="formRef" :model="form" :rules="rules" label-width="110px" class="max-w-3xl">
        <el-form-item label="数据库连接" prop="connection">
          <el-input v-model="form.connection" name="connection" autocomplete="off" />
        </el-form-item>
        <el-form-item label="数据表" prop="table">
          <el-input v-model="form.table" name="table" autocomplete="off">
            <template #append>
              <el-button data-action="inspect" :loading="inspecting" @click="inspect">检查结构</el-button>
            </template>
          </el-input>
        </el-form-item>
        <el-form-item label="业务名称" prop="name">
          <el-input v-model="form.name" name="name" maxlength="100" />
        </el-form-item>
        <el-form-item label="业务标识" prop="code">
          <el-input v-model="form.code" name="code" maxlength="61" autocomplete="off" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="form.remark" name="remark" type="textarea" :rows="3" maxlength="1000" />
        </el-form-item>
      </el-form>

      <div class="sr-only" aria-live="polite" aria-atomic="true">{{ announcement }}</div>
      <BusinessPageState
        :loading="inspecting"
        :error="inspectionErrorMessage"
        :empty="!inspection"
        :empty-text="inspectionNotice || '请输入数据库连接和数据表，然后检查结构'"
        :on-retry="canRetryInspection ? inspect : undefined"
      >
        <template v-if="inspection">
          <el-alert :title="inspectionSummary" :type="inspectionBlockReason ? 'warning' : 'success'" :closable="false" class="mb-3" />
          <dl class="inspection-meta mb-3">
            <div><dt>连接</dt><dd>{{ inspection.connection }}</dd></div>
            <div><dt>数据表</dt><dd>{{ inspection.table }}</dd></div>
            <div><dt>主键</dt><dd>{{ inspection.primaryKey.join(', ') || '缺少主键' }}</dd></div>
            <div><dt>快照哈希</dt><dd class="snapshot-hash">{{ inspection.snapshotHash }}</dd></div>
          </dl>

          <div class="field-table-scroll" tabindex="0" role="region" aria-label="检查字段列表">
            <el-table class="field-table" :data="inspection.fields" border max-height="360">
              <el-table-column prop="name" label="字段" min-width="140" />
              <el-table-column prop="label" label="名称" min-width="140" />
              <el-table-column prop="columnType" label="列类型" min-width="140" />
              <el-table-column prop="type" label="控件" min-width="120" />
            </el-table>
          </div>
          <div class="field-cards" aria-label="检查字段卡片">
            <article v-for="(field, index) in inspection.fields" :key="String(field.name || index)" class="field-card">
              <strong>{{ field.name || field.field_name || `字段 ${index + 1}` }}</strong>
              <span>{{ field.label || '—' }}</span>
              <span>{{ field.columnType || field.column_type || '—' }}</span>
              <span>{{ field.type || '—' }}</span>
            </article>
          </div>
        </template>
      </BusinessPageState>

      <div class="mt-4">
        <el-button data-action="create" type="primary" :loading="submitting" :disabled="!canCreate || inspecting" @click="create">采纳并进入设计器</el-button>
        <el-button data-action="cancel" :disabled="submitting" @click="cancel">取消</el-button>
      </div>
    </el-card>
  </PageWrapper>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { ElMessage, ElMessageBox, type FormInstance, type FormRules } from 'element-plus';
import { useRouter } from 'vue-router';
import {
  businessDevelopmentApi,
  isBusinessApiError,
  type BusinessFieldInspection
} from '@/api/development/business';
import BusinessPageState from './components/BusinessPageState.vue';
import { useDirtyGuard } from './composables/useDirtyGuard';
import { useLatestRequest } from './composables/useLatestRequest';

defineOptions({ name: 'BusinessDatabase' });

interface DatabaseAdoptionForm {
  connection: string;
  table: string;
  name: string;
  code: string;
  remark: string;
}

const router = useRouter();
const formRef = ref<FormInstance>();
const form = reactive<DatabaseAdoptionForm>({ connection: 'mysql', table: '', name: '', code: '', remark: '' });
const initialForm = JSON.stringify(form);
const rules: FormRules<DatabaseAdoptionForm> = {
  connection: [{ required: true, whitespace: true, message: '请输入数据库连接', trigger: 'blur' }],
  table: [{ required: true, whitespace: true, message: '请输入数据表', trigger: 'blur' }],
  name: [{ required: true, whitespace: true, message: '请输入业务名称', trigger: 'blur' }],
  code: [
    { required: true, whitespace: true, message: '请输入业务标识', trigger: 'blur' },
    { pattern: /^[a-z][a-z0-9_]*$/, message: '业务标识须以小写字母开头，仅含小写字母、数字和下划线', trigger: 'blur' }
  ]
};

const submitting = ref(false);
const allowLeave = ref(false);
const inspectionNotice = ref('');
let inspectLocked = false;
let submitLocked = false;
let active = true;

const inspectionRequest = useLatestRequest<BusinessFieldInspection, [string, string]>(
  (connection, table) => businessDevelopmentApi.inspectDatabase(connection, table)
);
const inspection = computed(() => inspectionRequest.data.value || null);
const inspecting = inspectionRequest.loading;
const dirty = computed(() => !allowLeave.value && JSON.stringify(form) !== initialForm);
const { routeGuard } = useDirtyGuard(dirty);

const inspectionErrorMessage = computed(() => {
  const error = inspectionRequest.error.value;
  if (!error) return '';
  if (isBusinessApiError(error)) return error.msg;
  return error instanceof Error ? error.message : '结构检查失败';
});
const inspectionBlockReason = computed(() => {
  if (!inspection.value) return '';
  if (inspection.value.fields.length === 0) return '未识别到字段，无法采纳';
  if (inspection.value.primaryKey.length === 0) return '缺少主键，无法采纳';
  return '';
});
const canCreate = computed(() => Boolean(inspection.value && !inspectionBlockReason.value && !submitting.value));
const canRetryInspection = computed(() => Boolean(form.connection.trim() && form.table.trim()));
const inspectionSummary = computed(() => inspectionBlockReason.value || `已识别 ${inspection.value?.fields.length || 0} 个字段，确认后将保存不可变 Schema 基线。`);
const announcement = computed(() => {
  if (inspecting.value) return '正在检查数据库结构';
  if (submitting.value) return '正在采纳数据库结构';
  if (inspectionErrorMessage.value) return inspectionErrorMessage.value;
  if (inspectionNotice.value) return inspectionNotice.value;
  if (inspection.value) return inspectionSummary.value;
  return '';
});

watch(
  () => [form.connection, form.table],
  ([connection, table], [previousConnection, previousTable]) => {
    if (connection === previousConnection && table === previousTable) return;
    const hadInspectionActivity = Boolean(inspection.value || inspecting.value || inspectionRequest.error.value);
    inspectionRequest.data.value = undefined;
    inspectionRequest.error.value = undefined;
    inspectionRequest.invalidate();
    inspectLocked = false;
    if (hadInspectionActivity) inspectionNotice.value = '连接或数据表已变化，请重新检查';
  }
);

async function inspect(): Promise<void> {
  if (inspectLocked || submitLocked) return;
  inspectLocked = true;
  try {
    await formRef.value?.validateField(['connection', 'table']);
    const connection = form.connection.trim();
    const table = form.table.trim();
    inspectionNotice.value = '';
    inspectionRequest.data.value = undefined;
    const result = await inspectionRequest.execute(connection, table);
    if (!active || !result || form.connection.trim() !== connection || form.table.trim() !== table) return;
    if (!form.code) form.code = table.replace(/^fun_/, '').toLowerCase();
    if (!form.name) form.name = form.code;
  } catch (error) {
    if (!inspectionRequest.error.value && error) return;
  } finally {
    inspectLocked = false;
  }
}

async function create(): Promise<void> {
  if (submitLocked || inspectLocked || !inspection.value || inspectionBlockReason.value) return;
  submitLocked = true;
  try {
    await formRef.value?.validate();
    const inspected = inspection.value;
    if (!inspected || inspected.connection !== form.connection.trim() || inspected.table !== form.table.trim()) {
      invalidateInspection('连接或数据表与检查结果不一致，请重新检查');
      return;
    }
    try {
      await ElMessageBox.confirm(
        `确认采纳连接 ${inspected.connection} 的表 ${inspected.table}？主键：${inspected.primaryKey.join(', ')}；字段数：${inspected.fields.length}。采纳后将保存不可变 Schema 基线。`,
        '确认数据库采纳',
        { type: 'warning', confirmButtonText: '确认采纳', cancelButtonText: '取消' }
      );
    } catch {
      return;
    }

    submitting.value = true;
    const result = await businessDevelopmentApi.createFromDatabase({
      connection: form.connection.trim(),
      table: form.table.trim(),
      name: form.name.trim(),
      code: form.code.trim(),
      remark: form.remark,
      expectedInspectionHash: inspected.snapshotHash
    });
    if (!active) return;
    await router.push({ path: '/development/business/designer', query: { id: String(result.module.form_id), moduleId: String(result.module.id) } });
  } catch (error) {
    if (isBusinessApiError(error) && error.data.error.code === 'DATABASE_INSPECTION_STALE') {
      invalidateInspection('数据库结构已变化，请重新检查');
      ElMessage.warning('数据库结构已变化，请重新检查');
    }
  } finally {
    submitting.value = false;
    submitLocked = false;
  }
}

function invalidateInspection(message: string): void {
  inspectionRequest.data.value = undefined;
  inspectionRequest.error.value = undefined;
  inspectionRequest.invalidate();
  inspectionNotice.value = message;
}

async function cancel(): Promise<void> {
  if (!routeGuard()) return;
  allowLeave.value = true;
  try {
    await router.push('/development/business/mine');
  } catch (error) {
    allowLeave.value = false;
    throw error;
  }
}

onBeforeUnmount(() => {
  active = false;
  inspectionRequest.invalidate();
});
</script>

<style scoped>
.inspection-meta {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px 20px;
}
.inspection-meta div { min-width: 0; }
.inspection-meta dt { color: var(--el-text-color-secondary); font-size: 12px; }
.inspection-meta dd { margin: 2px 0 0; overflow-wrap: anywhere; }
.snapshot-hash { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
.field-table-scroll { overflow-x: auto; }
.field-cards { display: none; }
.field-card {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 6px 12px;
  padding: 12px;
  border: 1px solid var(--el-border-color);
  border-radius: 6px;
}
@media (max-width: 640px) {
  .inspection-meta { grid-template-columns: 1fr; }
  .field-table-scroll { display: none; }
  .field-cards { display: grid; gap: 10px; }
}
</style>
