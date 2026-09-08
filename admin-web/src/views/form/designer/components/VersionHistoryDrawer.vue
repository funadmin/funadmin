<template>
  <el-drawer v-model="visible" title="版本历史" size="760px" @open="loadVersions">
    <div class="mb-3 flex items-center gap-2">
      <el-select v-model="fromVersion" placeholder="起始版本" class="w-[150px]">
        <el-option v-for="item in versions" :key="item.id" :label="`v${item.version}`" :value="item.version" />
      </el-select>
      <el-select v-model="toVersion" placeholder="目标版本" class="w-[150px]">
        <el-option v-for="item in versions" :key="item.id" :label="`v${item.version}`" :value="item.version" />
      </el-select>
      <el-button :disabled="!canCompare" :loading="comparing" @click="compareVersions">比较版本</el-button>
    </div>

    <el-table :data="versions" border size="small" v-loading="loading">
      <el-table-column prop="version" label="版本" width="80">
        <template #default="{ row }">v{{ row.version }}</template>
      </el-table-column>
      <el-table-column prop="origin" label="来源" width="100" />
      <el-table-column prop="change_summary" label="变更摘要" min-width="180" show-overflow-tooltip />
      <el-table-column prop="created_by" label="操作人" width="110" />
      <el-table-column prop="created_at" label="创建时间" width="170" />
      <el-table-column label="操作" width="150" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="viewVersion(row.version)">查看</el-button>
          <el-button link type="danger" :loading="rollingBack === row.version" @click="rollbackVersion(row.version)">回滚</el-button>
        </template>
      </el-table-column>
    </el-table>

    <el-divider content-position="left">版本内容 / Diff</el-divider>
    <el-input :model-value="detailText" type="textarea" :rows="16" readonly />
  </el-drawer>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { formDesignerApi, type FormSchemaDiff, type FormSchemaVersion } from '@/api/form';

const props = defineProps<{ modelValue: boolean; formId?: number }>();
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; rollback: [version: FormSchemaVersion] }>();
const visible = computed({ get: () => props.modelValue, set: (value) => emit('update:modelValue', value) });
const versions = ref<FormSchemaVersion[]>([]);
const fromVersion = ref<number>();
const toVersion = ref<number>();
const selectedVersion = ref<FormSchemaVersion>();
const diff = ref<FormSchemaDiff>();
const loading = ref(false);
const comparing = ref(false);
const rollingBack = ref<number>();
const canCompare = computed(() => Boolean(props.formId && fromVersion.value && toVersion.value && fromVersion.value !== toVersion.value));
const detailText = computed(() => JSON.stringify(diff.value ?? selectedVersion.value?.schema_document ?? {}, null, 2));

async function loadVersions() {
  if (!props.formId) return;
  loading.value = true;
  try {
    versions.value = (await formDesignerApi.versions(props.formId)).list;
    fromVersion.value = versions.value[1]?.version;
    toVersion.value = versions.value[0]?.version;
  } finally {
    loading.value = false;
  }
}

async function viewVersion(version: number) {
  if (!props.formId) return;
  diff.value = undefined;
  selectedVersion.value = await formDesignerApi.version(props.formId, version);
}

async function compareVersions() {
  if (!props.formId || !fromVersion.value || !toVersion.value) return;
  comparing.value = true;
  try {
    selectedVersion.value = undefined;
    diff.value = await formDesignerApi.diff(props.formId, fromVersion.value, toVersion.value);
  } finally {
    comparing.value = false;
  }
}

async function rollbackVersion(version: number) {
  if (!props.formId) return;
  await ElMessageBox.confirm(`确认回滚到 v${version}？系统会创建一个新的不可变版本。`, '回滚确认', { type: 'warning' });
  rollingBack.value = version;
  try {
    const result = await formDesignerApi.rollback(props.formId, version);
    emit('rollback', result);
    await loadVersions();
    ElMessage.success('回滚版本已创建');
  } finally {
    rollingBack.value = undefined;
  }
}
</script>
