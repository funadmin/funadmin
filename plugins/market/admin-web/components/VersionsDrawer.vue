<template>
  <el-drawer :model-value="modelValue" size="min(1000px, 100vw)" :title="plugin ? `${plugin.name} · 版本管理` : '版本管理'" class="market-versions-drawer" @update:model-value="$emit('update:modelValue', $event)" @open="load">
    <el-table v-loading="loading" :data="versions" row-key="id" empty-text="暂无版本">
      <el-table-column type="expand">
        <template #default="{ row }">
          <div class="version-detail">
            <dl>
              <dt>依赖要求</dt><dd>{{ requiresText(row as MarketVersion) }}</dd>
              <dt>SHA-256</dt><dd class="mono">{{ row.sha256 }}</dd>
              <dt>树哈希</dt><dd class="mono">{{ row.treeHash }}</dd>
              <dt>数据库能力</dt><dd class="mono">{{ row.databaseCapability || '无迁移' }}</dd>
              <dt>签名</dt><dd>{{ row.signed ? 'Ed25519 已签名' : '未签名' }}</dd>
            </dl>
            <div class="changelog">
              <div class="changelog__head"><strong>更新日志</strong><el-button v-if="canManage" link type="primary" @click="editChangelog(row as MarketVersion)">编辑</el-button></div>
              <pre>{{ row.changelog || '（未填写）' }}</pre>
            </div>
          </div>
        </template>
      </el-table-column>
      <el-table-column label="版本" width="120"><template #default="{ row }"><strong class="mono">v{{ row.version }}</strong></template></el-table-column>
      <el-table-column label="状态" width="96">
        <template #default="{ row }"><el-tag :type="statusMeta[row.status].type" effect="light" round>{{ statusMeta[row.status].label }}</el-tag></template>
      </el-table-column>
      <el-table-column label="包含" width="120">
        <template #default="{ row }"><span class="apps"><el-tag v-if="row.applications.app" size="small" effect="plain">独立应用</el-tag><el-tag v-if="row.applications.admin" size="small" effect="plain">后台</el-tag></span></template>
      </el-table-column>
      <el-table-column label="大小" width="90"><template #default="{ row }">{{ formatSize(row.size) }}</template></el-table-column>
      <el-table-column label="下载" prop="downloadCount" width="70" />
      <el-table-column label="发布时间" min-width="170"><template #default="{ row }">{{ row.publishedAt || '—' }}</template></el-table-column>
      <el-table-column label="操作" width="170" fixed="right">
        <template #default="{ row }">
          <template v-if="canManage">
            <el-button v-if="row.status === 'draft'" link type="primary" @click="act('publish', row as MarketVersion)">发布</el-button>
            <el-button v-if="row.status === 'published'" link type="warning" @click="act('withdraw', row as MarketVersion)">撤回</el-button>
            <el-button v-if="row.status === 'withdrawn'" link type="primary" @click="act('republish', row as MarketVersion)">重新发布</el-button>
            <el-button v-if="row.status !== 'published' && row.downloadCount === 0" link type="danger" @click="act('delete', row as MarketVersion)">删除</el-button>
            <el-button link @click="download(row as MarketVersion)">下载包</el-button>
          </template>
        </template>
      </el-table-column>
    </el-table>

    <el-dialog v-model="changelogOpen" title="编辑更新日志" width="min(560px, 94vw)" append-to-body>
      <el-input v-model="changelogDraft" type="textarea" :rows="8" maxlength="20000" show-word-limit />
      <template #footer>
        <el-button @click="changelogOpen = false">取消</el-button>
        <el-button type="primary" :loading="savingChangelog" @click="saveChangelog">保存</el-button>
      </template>
    </el-dialog>
  </el-drawer>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { ElMessageBox } from 'element-plus';
import { formatSize, marketApi, type MarketPlugin, type MarketVersion, type VersionStatus } from '../api';

const props = defineProps<{ modelValue: boolean; plugin: MarketPlugin | null; canManage: boolean }>();
const emit = defineEmits<{ 'update:modelValue': [value: boolean]; changed: [] }>();

const statusMeta: Record<VersionStatus, { label: string; type: 'info' | 'success' | 'warning' }> = {
  draft: { label: '草稿', type: 'info' },
  published: { label: '已发布', type: 'success' },
  withdrawn: { label: '已撤回', type: 'warning' }
};
const loading = ref(false);
const versions = ref<MarketVersion[]>([]);

async function load() {
  if (!props.plugin) return;
  loading.value = true;
  try { versions.value = await marketApi.versions(props.plugin.id); } finally { loading.value = false; }
}

const confirmTexts = {
  publish: (version: string) => `发布 v${version} 后，已登录市场账号的客户端即可安装或升级到该版本。`,
  withdraw: (version: string) => `撤回 v${version} 后客户端将无法再安装该版本，已安装的站点不受影响。`,
  republish: (version: string) => `重新发布 v${version}？发布前会再次校验签名与包文件。`,
  delete: (version: string) => `删除 v${version} 及其包文件？此操作不可恢复。`
};
async function act(action: keyof typeof confirmTexts, row: MarketVersion) {
  try {
    await ElMessageBox.confirm(confirmTexts[action](row.version), '确认操作', { type: action === 'delete' || action === 'withdraw' ? 'warning' : 'info' });
  } catch { return; }
  const run = { publish: marketApi.publishVersion, withdraw: marketApi.withdrawVersion, republish: marketApi.republishVersion, delete: marketApi.deleteVersion }[action];
  await run(row.id);
  await load();
  emit('changed');
}

async function download(row: MarketVersion) {
  const blob = await marketApi.downloadVersion(row.id);
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `${props.plugin?.code ?? 'plugin'}-${row.version}.zip`;
  link.click();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

function requiresText(row: MarketVersion) {
  const parts = [row.requires.funadmin && `FunAdmin ${row.requires.funadmin}`, row.requires.php && `PHP ${row.requires.php}`];
  const plugins = Object.entries(row.requires.plugins ?? {}).map(([code, constraint]) => `${code} ${constraint}`);
  return [...parts.filter(Boolean), ...plugins].join('；') || '无';
}

const changelogOpen = ref(false);
const changelogDraft = ref('');
const savingChangelog = ref(false);
let editingVersion = 0;
function editChangelog(row: MarketVersion) { editingVersion = row.id; changelogDraft.value = row.changelog; changelogOpen.value = true; }
async function saveChangelog() {
  savingChangelog.value = true;
  try {
    await marketApi.updateVersion(editingVersion, changelogDraft.value);
    changelogOpen.value = false;
    await load();
  } finally { savingChangelog.value = false; }
}
</script>

<style scoped>
.mono { font-family: var(--el-font-family-mono, ui-monospace, SFMono-Regular, Menlo, monospace); }
.apps { display: inline-flex; flex-wrap: wrap; gap: 4px; }
.version-detail { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 16px; padding: 4px 16px 8px 48px; }
.version-detail dl { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 6px 12px; margin: 0; font-size: 12px; }
.version-detail dt { color: var(--el-text-color-secondary); }
.version-detail dd { margin: 0; overflow-wrap: anywhere; color: var(--el-text-color-regular); }
.changelog__head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; font-size: 13px; }
.changelog pre { max-height: 200px; margin: 0; overflow: auto; padding: 10px 12px; border-radius: 8px; background: var(--el-fill-color-light); color: var(--el-text-color-regular); font-family: inherit; font-size: 12px; white-space: pre-wrap; }
@media (max-width: 720px) { .version-detail { grid-template-columns: 1fr; padding-left: 16px; } }
</style>
