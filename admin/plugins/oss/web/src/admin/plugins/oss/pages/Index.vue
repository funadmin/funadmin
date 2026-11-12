<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElMessage, ElMessageBox } from 'element-plus';
import { hasPermission } from '@/utils/permission';
import PageWrapper from '@/components/PageWrapper/index.vue';
import DataTableShell from '@/components/DataTable/DataTableShell.vue';
import ConnectionResult from '../components/ConnectionResult.vue';
import { ossStorageApi } from '../api';
import { registerOssLocales } from '../locales';
import type { ConnectionTestResult, StorageOverview, StorageProfile, StorageProfilePayload, StorageProvider } from '../types';

/**
 * OSS 存储配置页。每个操作按自己的权限码显示（oss:storage:<方法>）：index 查看、save 保存、activate 设为当前、test 连接测试、
 * delete 删除（进入回收站）、recycle 查看回收站、restore 恢复、destroy 彻底删除（二次确认，不可撤销）。
 */
defineOptions({ name: 'OssStorage' });
registerOssLocales();
const { t } = useI18n({ useScope: 'global' });
const overview = ref<StorageOverview>({ profiles: [], current: null, managed: false });
const loading = ref(false), busy = ref(false), dialog = ref(false);
const error = ref(''), formError = ref('');
const testingId = ref(''), formTesting = ref(false);
const rowResult = ref<{ id: string; name: string; result: ConnectionTestResult } | null>(null);
const formResult = ref<ConnectionTestResult | null>(null);
const recycleOpen = ref(false), recycleLoading = ref(false), recycleError = ref('');
const trashed = ref<StorageProfile[]>([]);
// 放在脚本里：模板静态属性含 // 会让 vue-tsc 生成的代码出错。
const placeholders = { endpoint: 'https://oss-cn-hangzhou.aliyuncs.com', s3Endpoint: 'https://minio.example.com:9000', domain: 'https://cdn.example.com' };
const providers: StorageProvider[] = ['aliyun', 'tencent', 'qiniu', 's3'];
const providerLabel = (provider: StorageProvider) => t(`pluginOss.providers.${provider}`);
const current = computed(() => overview.value.profiles.find(profile => profile.id === overview.value.current));
const editing = computed(() => !!form.id);
const form = reactive<StorageProfilePayload>(emptyForm());
const accessLabel = computed(() => form.provider === 'tencent' ? 'SecretId' : form.provider === 'aliyun' ? 'AccessKeyId' : form.provider === 's3' ? 'Access Key ID' : 'AccessKey');
const secretLabel = computed(() => form.provider === 'tencent' ? 'SecretKey' : form.provider === 'aliyun' ? 'AccessKeySecret' : form.provider === 's3' ? 'Secret Access Key' : 'SecretKey');
const regionPlaceholder = computed(() => form.provider === 'qiniu' ? 'z0 / z1 / z2 / na0 / as0' : form.provider === 's3' ? 'us-east-1 / auto' : 'ap-guangzhou');
function emptyForm(): StorageProfilePayload {
  return { name: '', provider: 'aliyun', bucket: '', region: '', endpoint: '', domain: '', prefix: '', path_style: false, access_key: '', secret_key: '' };
}
function message(reason: unknown) { return reason instanceof Error ? reason.message : t('pluginOss.page.failed'); }
const confirm = (body: string, title: string, button: string) => ElMessageBox.confirm(body, title, { type: 'warning', confirmButtonText: button, cancelButtonText: t('pluginOss.recycle.cancel') }).then(() => true, () => false);
async function load() {
  if (!hasPermission('oss:storage:index')) return;
  loading.value = true; error.value = '';
  try { overview.value = await ossStorageApi.list(); }
  catch (reason) { error.value = message(reason); }
  finally { loading.value = false; }
}
function open(profile?: StorageProfile) {
  if (!hasPermission('oss:storage:save') || busy.value) return;
  delete form.id;
  Object.assign(form, emptyForm(), profile ? {
    id: profile.id, name: profile.name, provider: profile.provider, bucket: profile.bucket, region: profile.region,
    endpoint: profile.endpoint, domain: profile.domain, prefix: profile.prefix, path_style: !!profile.path_style
  } : {});
  formError.value = ''; formResult.value = null; dialog.value = true;
}
function close() {
  dialog.value = false;
  form.access_key = ''; form.secret_key = ''; formResult.value = null;
}
function payload(): StorageProfilePayload {
  return { ...form, name: form.name.trim(), path_style: form.provider === 's3' && form.path_style };
}
function missingRequired(): boolean {
  const required = [form.name, form.bucket, form.domain];
  if (form.provider === 'aliyun') required.push(form.endpoint);
  else required.push(form.region);
  if (!editing.value) required.push(form.access_key, form.secret_key);
  if (required.some(value => !value.trim())) { formError.value = t('pluginOss.form.required'); return true; }
  return false;
}
async function save() {
  if (!hasPermission('oss:storage:save') || busy.value || missingRequired()) return;
  busy.value = true; formError.value = '';
  try {
    await ossStorageApi.save(payload());
    close(); await load();
  } catch (reason) { formError.value = message(reason); }
  finally { busy.value = false; }
}
async function testForm() {
  if (!hasPermission('oss:storage:test') || busy.value || missingRequired()) return;
  busy.value = true; formTesting.value = true; formError.value = ''; formResult.value = null;
  try { formResult.value = await ossStorageApi.test(payload()); }
  catch (reason) { formError.value = message(reason); }
  finally { busy.value = false; formTesting.value = false; }
}
async function activate(profile: StorageProfile) {
  if (!hasPermission('oss:storage:activate') || busy.value) return;
  busy.value = true; error.value = '';
  try {
    await ossStorageApi.activate(profile.id);
    await load();
  } catch (reason) { error.value = message(reason); }
  finally { busy.value = false; }
}
async function test(profile: StorageProfile) {
  if (!hasPermission('oss:storage:test') || busy.value) return;
  busy.value = true; testingId.value = profile.id; error.value = ''; rowResult.value = null;
  try { rowResult.value = { id: profile.id, name: profile.name, result: await ossStorageApi.test({ id: profile.id }) }; }
  catch (reason) { error.value = message(reason); }
  finally { busy.value = false; testingId.value = ''; }
}
async function remove(profile: StorageProfile) {
  if (!hasPermission('oss:storage:delete') || busy.value) return;
  if (!(await confirm(t('pluginOss.list.deleteBody', { name: profile.name }), t('pluginOss.list.deleteTitle'), t('pluginOss.list.delete')))) return;
  busy.value = true; error.value = '';
  try {
    await ossStorageApi.remove(profile.id);
    ElMessage.success(t('pluginOss.list.deleted'));
    if (rowResult.value?.id === profile.id) rowResult.value = null;
    await load();
  } catch (reason) { error.value = message(reason); }
  finally { busy.value = false; }
}
async function loadRecycle() {
  recycleLoading.value = true; recycleError.value = '';
  try { trashed.value = (await ossStorageApi.recycle()).list; }
  catch (reason) { recycleError.value = message(reason); }
  finally { recycleLoading.value = false; }
}
function openRecycle() {
  if (!hasPermission('oss:storage:recycle')) return;
  trashed.value = []; recycleOpen.value = true; void loadRecycle();
}
async function restore(profile: StorageProfile) {
  if (!hasPermission('oss:storage:restore') || busy.value) return;
  busy.value = true; recycleError.value = '';
  try {
    await ossStorageApi.restore(profile.id);
    ElMessage.success(t('pluginOss.recycle.restored'));
    await Promise.all([loadRecycle(), load()]);
  } catch (reason) { recycleError.value = message(reason); }
  finally { busy.value = false; }
}
async function destroy(profile: StorageProfile) {
  if (!hasPermission('oss:storage:destroy') || busy.value) return;
  if (!(await confirm(t('pluginOss.recycle.destroyBody', { name: profile.name }), t('pluginOss.recycle.destroyTitle'), t('pluginOss.recycle.destroy')))) return;
  busy.value = true; recycleError.value = '';
  try {
    await ossStorageApi.destroy(profile.id);
    ElMessage.success(t('pluginOss.recycle.destroyed'));
    await loadRecycle();
  } catch (reason) { recycleError.value = message(reason); }
  finally { busy.value = false; }
}
onMounted(load);
</script>

<template>
  <PageWrapper :title="t('pluginOss.page.title')" :subtitle="t('pluginOss.page.subtitle')">
    <template #header><h1 class="m-0 text-base font-semibold">{{ t('pluginOss.page.title') }}</h1></template>
    <el-alert v-if="!hasPermission('oss:storage:index')" :title="t('pluginOss.page.noPermission')" type="warning" :closable="false" />
    <template v-else>
      <el-alert v-if="error" class="mb-4" :title="error" type="error" :closable="false" />
      <div v-if="rowResult" class="mb-4" data-testid="oss-row-result">
        <div class="mb-1 text-sm font-medium">{{ t('pluginOss.test.title') }} · {{ rowResult.name }}</div>
        <ConnectionResult :result="rowResult.result" />
      </div>
      <el-alert class="mb-4" :type="overview.managed ? 'success' : 'info'" :closable="false">
        <template #title>{{ overview.managed ? t('pluginOss.page.managed') : t('pluginOss.page.unmanaged') }}</template>
        <template v-if="overview.managed && current">{{ t('pluginOss.page.currentSummary', { name: current.name, provider: providerLabel(current.provider), bucket: current.bucket }) }}</template>
        <template v-else>{{ t('pluginOss.page.idleSummary') }}</template>
      </el-alert>
      <p class="mb-4 text-sm text-[var(--el-text-color-secondary)]">{{ t('pluginOss.page.hint') }}</p>
      <DataTableShell storage-key="oss-storage" :loading="loading" :show-column-setting="false" @refresh="load">
        <template #toolbar-left>
          <el-button v-if="hasPermission('oss:storage:save')" type="primary" :disabled="busy" @click="open()">{{ t('pluginOss.list.add') }}</el-button>
          <el-button v-if="hasPermission('oss:storage:recycle')" :disabled="busy" @click="openRecycle">{{ t('pluginOss.list.recycle') }}</el-button>
        </template>
        <template #default="{ size, stripe, border, headerCellStyle }">
          <el-table :data="overview.profiles" :size="size" :stripe="stripe" :border="border" :header-cell-style="headerCellStyle" :empty-text="t('pluginOss.list.empty')">
            <el-table-column :label="t('pluginOss.list.profile')" min-width="170"><template #default="{ row }"><div class="font-medium">{{ row.name }}</div><el-tag v-if="row.id === overview.current" size="small" type="success">{{ t('pluginOss.list.current') }}</el-tag><span v-else class="text-xs text-[var(--el-text-color-secondary)]">{{ t('pluginOss.list.standby') }}</span></template></el-table-column>
            <el-table-column :label="t('pluginOss.list.provider')" min-width="150"><template #default="{ row }">{{ providerLabel(row.provider as StorageProvider) }}</template></el-table-column>
            <el-table-column :label="t('pluginOss.list.location')" min-width="220"><template #default="{ row }"><div>{{ row.bucket }}</div><div class="text-xs text-[var(--el-text-color-secondary)]">{{ [row.region, row.endpoint].filter(Boolean).join(' · ') }}<template v-if="row.path_style"> · {{ t('pluginOss.list.pathStyle') }}</template></div></template></el-table-column>
            <el-table-column :label="t('pluginOss.list.domain')" min-width="220"><template #default="{ row }"><div>{{ row.domain }}</div><div class="text-xs text-[var(--el-text-color-secondary)]">{{ row.prefix || t('pluginOss.list.root') }}</div></template></el-table-column>
            <el-table-column :label="t('pluginOss.list.credentials')" width="100"><template #default="{ row }"><el-tag :type="row.has_credentials ? 'info' : 'warning'" size="small">{{ row.has_credentials ? t('pluginOss.list.set') : t('pluginOss.list.unset') }}</el-tag></template></el-table-column>
            <el-table-column :label="t('pluginOss.list.actions')" min-width="300" fixed="right"><template #default="{ row }">
              <el-button v-if="hasPermission('oss:storage:save')" type="primary" link :disabled="busy" @click="open(row as StorageProfile)">{{ t('pluginOss.list.edit') }}</el-button>
              <el-button v-if="hasPermission('oss:storage:activate') && row.id !== overview.current" type="primary" link :disabled="busy || !row.has_credentials" @click="activate(row as StorageProfile)">{{ t('pluginOss.list.activate') }}</el-button>
              <el-button v-if="hasPermission('oss:storage:test')" type="primary" link :disabled="busy || !row.has_credentials" :loading="testingId === row.id" @click="test(row as StorageProfile)">{{ t('pluginOss.list.test') }}</el-button>
              <el-button v-if="hasPermission('oss:storage:delete') && row.id !== overview.current" type="danger" link :disabled="busy" @click="remove(row as StorageProfile)">{{ t('pluginOss.list.delete') }}</el-button>
            </template></el-table-column>
          </el-table>
        </template>
      </DataTableShell>
    </template>
    <el-dialog v-model="dialog" :title="editing ? t('pluginOss.form.editTitle') : t('pluginOss.form.addTitle')" width="min(680px, 94vw)" :close-on-click-modal="false" :close-on-press-escape="!busy" :show-close="!busy" destroy-on-close @closed="close">
      <el-alert v-if="formError" class="mb-4" :title="formError" type="error" :closable="false" />
      <el-alert v-if="editing" class="mb-4" :title="t('pluginOss.form.locked')" type="info" :closable="false" />
      <el-form label-position="top" :disabled="busy" @submit.prevent="save">
        <el-form-item :label="t('pluginOss.form.name')" required data-field="name"><el-input v-model="form.name" maxlength="100" autocomplete="off" /></el-form-item>
        <el-form-item :label="t('pluginOss.form.provider')" required data-field="provider"><el-select v-model="form.provider" :disabled="editing" class="w-full"><el-option v-for="provider in providers" :key="provider" :value="provider" :label="providerLabel(provider)" /></el-select></el-form-item>
        <el-form-item :label="t('pluginOss.form.bucket')" required data-field="bucket"><el-input v-model="form.bucket" :disabled="editing" :placeholder="form.provider === 'tencent' ? 'bucket-1250000000' : 'bucket-name'" /></el-form-item>
        <el-form-item v-if="form.provider === 'aliyun'" :label="t('pluginOss.form.endpoint')" required data-field="endpoint"><el-input v-model="form.endpoint" :disabled="editing" :placeholder="placeholders.endpoint" /></el-form-item>
        <el-form-item v-if="form.provider === 's3'" :label="t('pluginOss.form.endpointOptional')" data-field="endpoint"><el-input v-model="form.endpoint" :disabled="editing" :placeholder="placeholders.s3Endpoint" /><div class="text-xs text-[var(--el-text-color-secondary)]">{{ t('pluginOss.form.s3Help') }}</div></el-form-item>
        <el-form-item v-if="form.provider !== 'aliyun'" :label="form.provider === 'qiniu' ? t('pluginOss.form.qiniuRegion') : t('pluginOss.form.region')" required data-field="region"><el-input v-model="form.region" :disabled="editing" :placeholder="regionPlaceholder" /></el-form-item>
        <el-form-item v-if="form.provider === 's3'" data-field="path_style"><el-checkbox v-model="form.path_style">{{ t('pluginOss.form.pathStyle') }}</el-checkbox><div class="w-full text-xs text-[var(--el-text-color-secondary)]">{{ t('pluginOss.form.pathStyleHelp') }}</div></el-form-item>
        <el-form-item :label="t('pluginOss.form.domain')" required data-field="domain"><el-input v-model="form.domain" :disabled="editing" :placeholder="placeholders.domain" /></el-form-item>
        <el-form-item :label="t('pluginOss.form.prefix')" data-field="prefix"><el-input v-model="form.prefix" :disabled="editing" :placeholder="t('pluginOss.form.prefixPlaceholder')" /></el-form-item>
        <el-form-item :label="accessLabel" :required="!editing" data-field="access_key"><el-input v-model="form.access_key" type="password" show-password autocomplete="new-password" :placeholder="editing ? t('pluginOss.form.keepSecret') : t('pluginOss.form.accessPlaceholder')" /></el-form-item>
        <el-form-item :label="secretLabel" :required="!editing" data-field="secret_key"><el-input v-model="form.secret_key" type="password" show-password autocomplete="new-password" :placeholder="editing ? t('pluginOss.form.keepSecret') : t('pluginOss.form.secretPlaceholder')" /></el-form-item>
      </el-form>
      <div v-if="hasPermission('oss:storage:test')" class="text-xs text-[var(--el-text-color-secondary)]">{{ t('pluginOss.form.testHint') }}</div>
      <ConnectionResult v-if="formResult" class="mt-2" :result="formResult" />
      <template #footer>
        <el-button :disabled="busy" @click="close">{{ t('pluginOss.form.cancel') }}</el-button>
        <el-button v-if="hasPermission('oss:storage:test')" :disabled="busy && !formTesting" :loading="formTesting" data-testid="oss-form-test" @click="testForm">{{ t('pluginOss.form.test') }}</el-button>
        <el-button v-if="hasPermission('oss:storage:save')" type="primary" :loading="busy && !formTesting" :disabled="formTesting" @click="save">{{ t('pluginOss.form.save') }}</el-button>
      </template>
    </el-dialog>
    <el-dialog v-model="recycleOpen" :title="t('pluginOss.recycle.title')" width="min(760px, 94vw)" data-testid="oss-recycle">
      <el-alert v-if="recycleError" class="mb-4" :title="recycleError" type="error" :closable="false" />
      <el-table v-loading="recycleLoading" :data="trashed" :empty-text="t('pluginOss.recycle.empty')">
        <el-table-column :label="t('pluginOss.list.profile')" min-width="160"><template #default="{ row }"><div class="font-medium">{{ row.name }}</div><div class="text-xs text-[var(--el-text-color-secondary)]">{{ providerLabel(row.provider as StorageProvider) }} · {{ row.bucket }}</div></template></el-table-column>
        <el-table-column :label="t('pluginOss.recycle.deletedAt')" min-width="160" prop="deleted_at" />
        <el-table-column :label="t('pluginOss.list.actions')" min-width="180"><template #default="{ row }">
          <el-button v-if="hasPermission('oss:storage:restore')" type="primary" link :disabled="busy" @click="restore(row as StorageProfile)">{{ t('pluginOss.recycle.restore') }}</el-button>
          <el-button v-if="hasPermission('oss:storage:destroy')" type="danger" link :disabled="busy" @click="destroy(row as StorageProfile)">{{ t('pluginOss.recycle.destroy') }}</el-button>
        </template></el-table-column>
      </el-table>
    </el-dialog>
  </PageWrapper>
</template>
