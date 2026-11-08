<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { hasPermission } from '@/utils/permission';
import PageWrapper from '@/components/PageWrapper/index.vue';
import DataTableShell from '@/components/DataTable/DataTableShell.vue';
import { ossStorageApi } from '../api';
import type { StorageOverview, StorageProfile, StorageProfilePayload, StorageProvider } from '../types';

defineOptions({ name: 'OssStorage' });
const overview = ref<StorageOverview>({ profiles: [], current: null, managed: false });
const loading = ref(false), busy = ref(false), dialog = ref(false);
const error = ref(''), formError = ref(''), resultMessage = ref('');
const testingId = ref('');
// 放在脚本里：模板静态属性含 // 会让 vue-tsc 生成的代码出错。
const placeholders = { endpoint: 'https://oss-cn-hangzhou.aliyuncs.com', domain: 'https://cdn.example.com' };
const providerLabels: Record<StorageProvider, string> = { aliyun: '阿里云 OSS', tencent: '腾讯云 COS', qiniu: '七牛云 Kodo' };
const current = computed(() => overview.value.profiles.find(profile => profile.id === overview.value.current));
const editing = computed(() => !!form.id);
const form = reactive<StorageProfilePayload>(emptyForm());
const accessLabel = computed(() => form.provider === 'tencent' ? 'SecretId' : form.provider === 'aliyun' ? 'AccessKeyId' : 'AccessKey');
const secretLabel = computed(() => form.provider === 'tencent' ? 'SecretKey' : form.provider === 'aliyun' ? 'AccessKeySecret' : 'SecretKey');
function emptyForm(): StorageProfilePayload {
  return { name: '', provider: 'aliyun', bucket: '', region: '', endpoint: '', domain: '', prefix: '', access_key: '', secret_key: '' };
}
function message(reason: unknown) { return reason instanceof Error ? reason.message : '操作失败，请重试'; }
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
    id: profile.id, name: profile.name, provider: profile.provider, bucket: profile.bucket,
    region: profile.region, endpoint: profile.endpoint, domain: profile.domain, prefix: profile.prefix
  } : {});
  formError.value = ''; dialog.value = true;
}
function close() {
  dialog.value = false;
  form.access_key = ''; form.secret_key = '';
}
async function save() {
  if (!hasPermission('oss:storage:save') || busy.value) return;
  const required = [form.name, form.bucket, form.domain, form.provider === 'aliyun' ? form.endpoint : form.region];
  if (!editing.value) required.push(form.access_key, form.secret_key);
  if (required.some(value => !value.trim())) { formError.value = '请填写所有必填项'; return; }
  busy.value = true; formError.value = '';
  try {
    await ossStorageApi.save({ ...form, name: form.name.trim() });
    close(); await load();
  } catch (reason) { formError.value = message(reason); }
  finally { busy.value = false; }
}
async function activate(profile: StorageProfile) {
  if (!hasPermission('oss:storage:activate') || busy.value) return;
  busy.value = true; error.value = ''; resultMessage.value = '';
  try {
    await ossStorageApi.activate(profile.id);
    await load();
  } catch (reason) { error.value = message(reason); }
  finally { busy.value = false; }
}
async function test(profile: StorageProfile) {
  if (!hasPermission('oss:storage:test') || busy.value) return;
  busy.value = true; testingId.value = profile.id; error.value = ''; resultMessage.value = '';
  try { resultMessage.value = (await ossStorageApi.test(profile.id)).message; }
  catch (reason) { error.value = message(reason); }
  finally { busy.value = false; testingId.value = ''; }
}
onMounted(load);
</script>

<template>
  <PageWrapper title="OSS 存储" subtitle="管理统一公开附件的对象存储">
    <template #header><h1 class="m-0 text-base font-semibold">OSS 存储</h1></template>
    <el-alert v-if="!hasPermission('oss:storage:index')" title="无权查看存储配置" type="warning" :closable="false" />
    <template v-else>
      <el-alert v-if="error" class="mb-4" :title="error" type="error" :closable="false" />
      <el-alert v-if="resultMessage" class="mb-4" :title="resultMessage" type="success" :closable="false" />
      <el-alert class="mb-4" :type="overview.managed ? 'success' : 'info'" :closable="false">
        <template #title>{{ overview.managed ? '正在接管新上传' : '未配置，尚未接管' }}</template>
        <template v-if="overview.managed && current">当前存储：{{ current.name }} · {{ providerLabels[current.provider] }} · {{ current.bucket }}。统一公开附件的新上传将使用此配置。</template>
        <template v-else>插件已启用。添加配置并设为当前存储后，自动接管统一公开附件的新上传。</template>
      </el-alert>
      <p class="mb-4 text-sm text-[var(--el-text-color-secondary)]">切换当前配置只影响新上传；历史附件仍使用原配置。测试连接会上传并删除随机测试对象。</p>
      <DataTableShell storage-key="oss-storage" :loading="loading" :show-column-setting="false" @refresh="load">
        <template #toolbar-left><el-button v-if="hasPermission('oss:storage:save')" type="primary" :disabled="busy" @click="open()">添加配置</el-button></template>
        <template #default="{ size, stripe, border, headerCellStyle }">
          <el-table :data="overview.profiles" :size="size" :stripe="stripe" :border="border" :header-cell-style="headerCellStyle" empty-text="暂无存储配置">
            <el-table-column label="配置" min-width="170"><template #default="{ row }"><div class="font-medium">{{ row.name }}</div><el-tag v-if="row.id === overview.current" size="small" type="success">当前存储</el-tag><span v-else class="text-xs text-[var(--el-text-color-secondary)]">历史存储 / 备用配置</span></template></el-table-column>
            <el-table-column label="厂商" min-width="130"><template #default="{ row }">{{ providerLabels[row.provider as StorageProvider] }}</template></el-table-column>
            <el-table-column label="存储位置" min-width="220"><template #default="{ row }"><div>{{ row.bucket }}</div><div class="text-xs text-[var(--el-text-color-secondary)]">{{ row.region || row.endpoint }}</div></template></el-table-column>
            <el-table-column label="访问域名 / 目录" min-width="220"><template #default="{ row }"><div>{{ row.domain }}</div><div class="text-xs text-[var(--el-text-color-secondary)]">{{ row.prefix || '根目录' }}</div></template></el-table-column>
            <el-table-column label="凭据" width="100"><template #default="{ row }"><el-tag :type="row.has_credentials ? 'info' : 'warning'" size="small">{{ row.has_credentials ? '已设置' : '未设置' }}</el-tag></template></el-table-column>
            <el-table-column label="操作" min-width="250" fixed="right"><template #default="{ row }">
              <el-button v-if="hasPermission('oss:storage:save')" type="primary" link :disabled="busy" @click="open(row as StorageProfile)">编辑</el-button>
              <el-button v-if="hasPermission('oss:storage:activate') && row.id !== overview.current" type="primary" link :disabled="busy || !row.has_credentials" @click="activate(row as StorageProfile)">设为当前存储</el-button>
              <el-button v-if="hasPermission('oss:storage:test')" type="primary" link :disabled="busy || !row.has_credentials" :loading="testingId === row.id" @click="test(row as StorageProfile)">测试连接</el-button>
            </template></el-table-column>
          </el-table>
        </template>
      </DataTableShell>
    </template>
    <el-dialog v-model="dialog" :title="editing ? '编辑存储配置' : '添加存储配置'" width="min(680px, 94vw)" :close-on-click-modal="false" :close-on-press-escape="!busy" :show-close="!busy" destroy-on-close @closed="close">
      <el-alert v-if="formError" class="mb-4" :title="formError" type="error" :closable="false" />
      <el-alert v-if="editing" class="mb-4" title="存储目标不可修改；如需更换桶、域名或目录，请添加新配置。密钥留空保留现有凭据。" type="info" :closable="false" />
      <el-form label-position="top" :disabled="busy" @submit.prevent="save">
        <el-form-item label="配置名称" required data-field="name"><el-input v-model="form.name" maxlength="100" autocomplete="off" /></el-form-item>
        <el-form-item label="云厂商" required data-field="provider"><el-select v-model="form.provider" :disabled="editing" class="w-full"><el-option v-for="(label, provider) in providerLabels" :key="provider" :value="provider" :label="label" /></el-select></el-form-item>
        <el-form-item label="Bucket" required data-field="bucket"><el-input v-model="form.bucket" :disabled="editing" :placeholder="form.provider === 'tencent' ? 'bucket-1250000000' : 'bucket-name'" /></el-form-item>
        <el-form-item v-if="form.provider === 'aliyun'" label="Endpoint" required data-field="endpoint"><el-input v-model="form.endpoint" :disabled="editing" :placeholder="placeholders.endpoint" /></el-form-item>
        <el-form-item v-else :label="form.provider === 'qiniu' ? '上传区域' : 'Region'" required data-field="region"><el-input v-model="form.region" :disabled="editing" :placeholder="form.provider === 'qiniu' ? 'z0 / z1 / z2 / na0 / as0' : 'ap-guangzhou'" /></el-form-item>
        <el-form-item label="公开访问域名" required data-field="domain"><el-input v-model="form.domain" :disabled="editing" :placeholder="placeholders.domain" /></el-form-item>
        <el-form-item label="目录前缀" data-field="prefix"><el-input v-model="form.prefix" :disabled="editing" placeholder="public；留空使用根目录" /></el-form-item>
        <el-form-item :label="accessLabel" :required="!editing" data-field="access_key"><el-input v-model="form.access_key" type="password" show-password autocomplete="new-password" :placeholder="editing ? '留空保留现有值' : '请输入访问密钥 ID'" /></el-form-item>
        <el-form-item :label="secretLabel" :required="!editing" data-field="secret_key"><el-input v-model="form.secret_key" type="password" show-password autocomplete="new-password" :placeholder="editing ? '留空保留现有值' : '请输入访问密钥'" /></el-form-item>
      </el-form>
      <template #footer><el-button :disabled="busy" @click="close">取消</el-button><el-button v-if="hasPermission('oss:storage:save')" type="primary" :loading="busy" @click="save">保存</el-button></template>
    </el-dialog>
  </PageWrapper>
</template>
