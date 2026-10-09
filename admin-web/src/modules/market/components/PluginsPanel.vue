<template>
  <div class="panel">
    <div class="panel-toolbar">
      <el-input v-model="keyword" clearable placeholder="搜索插件标识或名称" class="panel-search" @keyup.enter="reload" @clear="reload">
        <template #prefix><i class="i-ep-search" /></template>
      </el-input>
      <el-button @click="reload"><i class="i-ep-refresh" />刷新</el-button>
      <el-button v-if="canManage" type="primary" @click="uploadOpen = true"><i class="i-ep-upload" />上传插件包</el-button>
    </div>

    <el-table v-loading="loading" :data="rows" row-key="id" empty-text="还没有插件，上传第一个插件包后会自动创建">
      <el-table-column label="插件" min-width="220">
        <template #default="{ row }">
          <div class="plugin-cell">
            <strong>{{ row.name }}</strong>
            <span>{{ row.code }}<template v-if="row.author"> · {{ row.author }}</template></span>
          </div>
        </template>
      </el-table-column>
      <el-table-column label="分类" width="120"><template #default="{ row }">{{ row.categoryName || '—' }}</template></el-table-column>
      <el-table-column label="价格" width="150">
        <template #default="{ row }">
          <el-tag v-if="row.licenseType === 'free'" type="success" effect="light" round>免费</el-tag>
          <div v-else class="price-cell">
            <span v-if="row.pricePerpetual">买断 ¥{{ yuan(row.pricePerpetual) }}</span>
            <span v-if="row.priceYearly">¥{{ yuan(row.priceYearly) }}/年</span>
            <el-tag v-if="!row.pricePerpetual && !row.priceYearly" type="warning" effect="light" round>仅人工授权</el-tag>
          </div>
        </template>
      </el-table-column>
      <el-table-column label="最新版本" width="120">
        <template #default="{ row }"><span v-if="row.latestVersion" class="mono">v{{ row.latestVersion }}</span><span v-else class="muted">未发布</span></template>
      </el-table-column>
      <el-table-column label="版本" width="110">
        <template #default="{ row }">{{ row.versionCount }}<el-tag v-if="row.draftCount" size="small" type="info" effect="plain" class="ml-2">草稿 {{ row.draftCount }}</el-tag></template>
      </el-table-column>
      <el-table-column label="下载" prop="downloads" width="80" />
      <el-table-column label="状态" width="90">
        <template #default="{ row }"><span class="status-dot" :class="{ on: row.status === 1 }" />{{ row.status === 1 ? '上架' : '下架' }}</template>
      </el-table-column>
      <el-table-column label="操作" width="220" fixed="right">
        <template #default="{ row }">
          <el-button link type="primary" @click="openVersions(row as MarketPlugin)">版本管理</el-button>
          <el-button v-if="canManage" link type="primary" @click="openEdit(row as MarketPlugin)">编辑</el-button>
          <el-button v-if="canManage" link type="danger" @click="remove(row as MarketPlugin)">删除</el-button>
        </template>
      </el-table-column>
    </el-table>
    <div class="panel-pager">
      <el-pagination v-model:current-page="page" v-model:page-size="pageSize" :total="total" :page-sizes="[10, 20, 50]" layout="total, sizes, prev, pager, next" background @change="load" />
    </div>

    <el-dialog v-model="uploadOpen" title="上传插件包" width="min(560px, 94vw)" :close-on-click-modal="false" @closed="resetUpload">
      <el-upload drag :auto-upload="false" :limit="1" accept=".zip" :on-change="pickFile" :on-remove="() => (uploadFile = null)" :file-list="uploadList">
        <i class="i-ep-upload-filled upload-icon" />
        <div class="el-upload__text">拖拽插件 ZIP 到此处，或<em>点击选择</em></div>
        <template #tip><div class="upload-tip">服务端会按客户端安装规则校验 plugin.json，计算 SHA-256 与树哈希后用 Ed25519 签名，生成<strong>草稿</strong>版本；发布后客户端才能看到。同一版本号的草稿可重复上传覆盖。</div></template>
      </el-upload>
      <el-input v-model="changelog" type="textarea" :rows="4" maxlength="20000" show-word-limit placeholder="更新日志（可选，会展示在客户端插件中心）" class="mt-3" />
      <template #footer>
        <el-button @click="uploadOpen = false">取消</el-button>
        <el-button type="primary" :loading="uploading" :disabled="!uploadFile" @click="submitUpload">上传并签名</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="editOpen" title="编辑插件" width="min(560px, 94vw)">
      <el-form :model="form" label-position="top">
        <el-form-item label="名称" required><el-input v-model="form.name" maxlength="100" /></el-form-item>
        <el-form-item label="简介"><el-input v-model="form.description" type="textarea" :rows="3" maxlength="1000" show-word-limit /></el-form-item>
        <div class="form-grid">
          <el-form-item label="作者"><el-input v-model="form.author" maxlength="100" /></el-form-item>
          <el-form-item label="分类">
            <el-select v-model="form.categoryId" placeholder="未分类">
              <el-option :value="0" label="未分类" />
              <el-option v-for="item in categories" :key="item.id" :value="item.id" :label="item.name" />
            </el-select>
          </el-form-item>
          <el-form-item label="授权方式">
            <el-radio-group v-model="form.licenseType"><el-radio-button value="free">免费</el-radio-button><el-radio-button value="grant">付费 / 需授权</el-radio-button></el-radio-group>
          </el-form-item>
          <el-form-item label="排序"><el-input-number v-model="form.sort" :min="-9999" :max="9999" controls-position="right" /></el-form-item>
          <template v-if="form.licenseType === 'grant'">
            <el-form-item label="买断价（元，留空不提供）"><el-input v-model="form.pricePerpetual" placeholder="例如 199" inputmode="decimal"><template #prefix>¥</template></el-input></el-form-item>
            <el-form-item label="年费（元，留空不提供）"><el-input v-model="form.priceYearly" placeholder="例如 59" inputmode="decimal"><template #prefix>¥</template></el-input></el-form-item>
          </template>
        </div>
        <el-alert v-if="form.licenseType === 'grant' && !form.pricePerpetual && !form.priceYearly" type="info" :closable="false" show-icon title="未设置价格时前台不提供在线购买，只能由管理员在「授权」中手动开通。" class="mb-3" />
        <el-form-item label="封面图地址"><el-input v-model="form.cover" maxlength="500" placeholder="https:// 或 /storage/... 站内路径，建议 16:8" /></el-form-item>
        <el-form-item label="项目主页"><el-input v-model="form.homepage" maxlength="500" placeholder="https://" /></el-form-item>
        <el-form-item label="上架"><el-switch v-model="form.status" :active-value="1" :inactive-value="0" /></el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editOpen = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="saveEdit">保存</el-button>
      </template>
    </el-dialog>

    <VersionsDrawer v-model="versionsOpen" :plugin="current" :can-manage="canManage" @changed="afterVersionChange" />
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { ElMessageBox, type UploadFile, type UploadUserFile } from 'element-plus';
import { marketApi, yuan, type MarketCategory, type MarketPlugin, type PluginPayload } from '../api';
import VersionsDrawer from './VersionsDrawer.vue';

defineProps<{ canManage: boolean }>();
const emit = defineEmits<{ changed: [] }>();

const loading = ref(false);
const rows = ref<MarketPlugin[]>([]);
const total = ref(0);
const page = ref(1);
const pageSize = ref(10);
const keyword = ref('');
const categories = ref<MarketCategory[]>([]);

async function load() {
  loading.value = true;
  try {
    const result = await marketApi.plugins({ keyword: keyword.value, page: page.value, pageSize: pageSize.value });
    rows.value = result.list;
    total.value = result.total;
  } finally { loading.value = false; }
}
function reload() { page.value = 1; void load(); }

const uploadOpen = ref(false);
const uploading = ref(false);
const uploadFile = ref<File | null>(null);
const uploadList = ref<UploadUserFile[]>([]);
const changelog = ref('');
function pickFile(file: UploadFile) { uploadFile.value = file.raw ?? null; uploadList.value = [file]; }
function resetUpload() { uploadFile.value = null; uploadList.value = []; changelog.value = ''; }
async function submitUpload() {
  if (!uploadFile.value) return;
  uploading.value = true;
  try {
    await marketApi.upload(uploadFile.value, changelog.value);
    uploadOpen.value = false;
    await load();
    emit('changed');
  } finally { uploading.value = false; }
}

const editOpen = ref(false);
const saving = ref(false);
const editingId = ref(0);
const form = reactive<PluginPayload>({ name: '', description: '', author: '', categoryId: 0, licenseType: 'free', status: 1, sort: 0, pricePerpetual: '', priceYearly: '', cover: '', homepage: '' });
async function openEdit(row: MarketPlugin) {
  editingId.value = row.id;
  Object.assign(form, {
    name: row.name, description: row.description, author: row.author, categoryId: row.categoryId, licenseType: row.licenseType, status: row.status, sort: row.sort,
    pricePerpetual: row.pricePerpetual ? yuan(row.pricePerpetual) : '', priceYearly: row.priceYearly ? yuan(row.priceYearly) : '', cover: row.cover, homepage: row.homepage
  });
  editOpen.value = true;
  categories.value = await marketApi.categories();
}
async function saveEdit() {
  saving.value = true;
  try {
    await marketApi.updatePlugin(editingId.value, { ...form });
    editOpen.value = false;
    await load();
  } finally { saving.value = false; }
}

async function remove(row: MarketPlugin) {
  try {
    await ElMessageBox.confirm(`删除插件「${row.name}」及其全部版本与授权？已发布版本需要先撤回。`, '删除插件', { type: 'warning', confirmButtonText: '删除' });
  } catch { return; }
  await marketApi.deletePlugin(row.id);
  await load();
  emit('changed');
}

const versionsOpen = ref(false);
const current = ref<MarketPlugin | null>(null);
function openVersions(row: MarketPlugin) { current.value = row; versionsOpen.value = true; }
async function afterVersionChange() { await load(); emit('changed'); }

onMounted(load);
</script>

<style scoped>
.panel { display: grid; gap: 12px; }
.panel-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.panel-toolbar :deep(.el-button + .el-button) { margin-left: 0; }
.panel-toolbar :deep(.el-button i) { margin-right: 4px; }
.panel-search { width: 260px; }
.panel-pager { display: flex; justify-content: flex-end; }
.plugin-cell { display: grid; gap: 2px; min-width: 0; }
.plugin-cell strong { color: var(--el-text-color-primary); font-weight: 600; }
.plugin-cell span { color: var(--el-text-color-secondary); font-family: var(--el-font-family-mono, ui-monospace, SFMono-Regular, Menlo, monospace); font-size: 12px; }
.mono { font-family: var(--el-font-family-mono, ui-monospace, SFMono-Regular, Menlo, monospace); }
.muted { color: var(--el-text-color-placeholder); }
.price-cell { display: grid; font-size: 12px; line-height: 1.6; }
.status-dot { display: inline-block; width: 6px; height: 6px; margin-right: 6px; border-radius: 50%; background: var(--el-text-color-placeholder); vertical-align: middle; }
.status-dot.on { background: var(--el-color-success); }
.upload-icon { color: var(--el-text-color-placeholder); font-size: 40px; }
.upload-tip { margin-top: 6px; color: var(--el-text-color-secondary); font-size: 12px; line-height: 1.6; }
.form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); column-gap: 16px; }
.form-grid :deep(.el-select), .form-grid :deep(.el-input-number) { width: 100%; }
@media (max-width: 640px) { .form-grid { grid-template-columns: 1fr; } .panel-search { width: 100%; } }
</style>
