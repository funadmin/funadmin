<template>
  <PageWrapper title="附件库" subtitle="统一管理上传文件与附件分组；旧模板文件选择器继续兼容">
    <div class="grid gap-4 lg:grid-cols-[280px_minmax(0,1fr)]">
      <el-card shadow="never">
        <template #header>
          <div class="flex items-center justify-between">
            <span class="font-medium">附件分组</span>
            <el-button type="primary" link v-perm="'system:attachment-group:add'" @click="openGroupAdd(0)">新增</el-button>
          </div>
        </template>
        <div class="mb-3 grid grid-cols-2 gap-2">
          <el-button :type="query.groupId === undefined ? 'primary' : 'default'" plain @click="selectGroupFilter(undefined)">全部附件</el-button>
          <el-button :type="query.groupId === 0 ? 'primary' : 'default'" plain @click="selectGroupFilter(0)">未分组</el-button>
        </div>
        <el-tree
          :data="groupTree"
          node-key="id"
          default-expand-all
          :expand-on-click-node="false"
          :props="{ label: 'title', children: 'children' }"
          @node-click="selectGroup"
        >
          <template #default="{ data }">
            <div class="flex min-w-0 flex-1 items-center justify-between gap-2 pr-1">
              <span class="truncate">{{ data.title }}</span>
              <el-dropdown trigger="click" @command="(command: string) => onGroupCommand(command, data as AttachmentGroupModel)">
                <el-button link @click.stop><i class="i-ep-more-filled" /></el-button>
                <template #dropdown>
                  <el-dropdown-menu>
                    <el-dropdown-item command="add" v-perm="'system:attachment-group:add'">新增下级</el-dropdown-item>
                    <el-dropdown-item command="edit" v-perm="'system:attachment-group:edit'">编辑</el-dropdown-item>
                    <el-dropdown-item v-if="data.id !== 1" command="delete" divided v-perm="'system:attachment-group:delete'">删除</el-dropdown-item>
                  </el-dropdown-menu>
                </template>
              </el-dropdown>
            </div>
          </template>
        </el-tree>
      </el-card>

      <DataTableShell storage-key="system-attachment" :loading="loading" @refresh="loadData">
        <template #search>
          <SearchForm :model="query" :loading="loading" @search="onSearch" @reset="onReset">
            <el-form-item label="文件名称" prop="keyword">
              <el-input v-model="query.keyword" placeholder="原始名称/存储名称" clearable />
            </el-form-item>
            <el-form-item label="文件类型" prop="mimeType">
              <el-select v-model="query.mimeType" placeholder="全部" clearable class="!w-32">
                <el-option label="图片" value="image" />
                <el-option label="视频" value="video" />
                <el-option label="音频" value="audio" />
                <el-option label="文档" value="document" />
                <el-option label="压缩包" value="archive" />
              </el-select>
            </el-form-item>
          </SearchForm>
        </template>
        <template #toolbar-left>
          <el-button type="primary" plain v-perm="'system:attachment:upload'" @click="uploadInput?.click()">
            <i class="i-ep-upload" /> 上传到{{ selectedGroupTitle }}
          </el-button>
          <el-button plain :disabled="!selection.length" v-perm="'system:attachment:move'" @click="moveSelected">
            <i class="i-ep-folder-opened" /> 移动{{ selection.length ? `(${selection.length})` : '' }}
          </el-button>
          <el-button type="danger" plain :disabled="!selection.length" v-perm="'system:attachment:delete'" @click="removeSelected">
            <i class="i-ep-delete" /> 删除{{ selection.length ? `(${selection.length})` : '' }}
          </el-button>
          <input ref="uploadInput" class="hidden" type="file" multiple @change="uploadFiles" />
        </template>
        <template #default="{ size, stripe, border, headerCellStyle }">
          <el-table :data="list" v-loading="loading" :size="size" :stripe="stripe" :border="border" :header-cell-style="headerCellStyle" @selection-change="selection = $event">
            <el-table-column type="selection" width="48" align="center" />
            <el-table-column label="预览" width="82" align="center">
              <template #default="{ row }">
                <el-image v-if="row.mime.startsWith('image/')" :src="row.thumb || row.path" fit="cover" class="h-11 w-11 rounded" :preview-src-list="[row.path]" preview-teleported />
                <i v-else :class="fileIcon(row.ext)" class="text-3xl text-[var(--el-text-color-secondary)]" />
              </template>
            </el-table-column>
            <el-table-column prop="originalName" label="文件名称" min-width="200" show-overflow-tooltip />
            <el-table-column prop="ext" label="扩展名" width="90" align="center" />
            <el-table-column label="大小" width="110" align="right"><template #default="{ row }">{{ formatBytes(row.size) }}</template></el-table-column>
            <el-table-column prop="driver" label="存储" width="100" align="center" />
            <el-table-column prop="createdAt" label="上传时间" width="170" />
            <el-table-column label="操作" width="150" align="center" fixed="right">
              <template #default="{ row }">
                <el-button type="primary" link v-perm="'system:attachment:edit'" @click="renameOne(row as AttachmentModel)">重命名</el-button>
                <el-button type="primary" link @click="openFile(row.path)">查看</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="mt-4 flex justify-end">
            <el-pagination v-model:current-page="query.page" v-model:page-size="query.pageSize" :total="total" :page-sizes="[10, 20, 50, 100]" layout="total, sizes, prev, pager, next, jumper" @change="loadData" />
          </div>
        </template>
      </DataTableShell>
    </div>
    <AttachmentGroupDialog v-model="groupDialogVisible" :row="currentGroup" :parents="groupTree" @success="reloadGroups" />
  </PageWrapper>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { attachmentApi, attachmentGroupApi, type AttachmentGroupModel, type AttachmentModel, type AttachmentQuery } from '@/api/system/attachment';
import { uploadApi } from '@/api/common/upload';
import AttachmentGroupDialog from './components/AttachmentGroupDialog.vue';

defineOptions({ name: 'SystemAttachment' });
const loading = ref(false);
const list = ref<AttachmentModel[]>([]);
const total = ref(0);
const selection = ref<AttachmentModel[]>([]);
const groupTree = ref<AttachmentGroupModel[]>([]);
const groupDialogVisible = ref(false);
const currentGroup = ref<AttachmentGroupModel | null>(null);
const uploadInput = ref<HTMLInputElement>();
const query = reactive<AttachmentQuery>({ page: 1, pageSize: 20, keyword: '', groupId: 1, mimeType: '' });
const flatGroups = computed(() => {
  const result: AttachmentGroupModel[] = [];
  const walk = (nodes: AttachmentGroupModel[]) => nodes.forEach((node) => { result.push(node); if (node.children) walk(node.children); });
  walk(groupTree.value);
  return result;
});
const selectedGroupTitle = computed(() => flatGroups.value.find((item) => item.id === query.groupId)?.title || '默认分组');

async function loadGroups() { groupTree.value = await attachmentGroupApi.tree(); }
async function reloadGroups() { await loadGroups(); await loadData(); }
async function loadData() {
  loading.value = true;
  try { const result = await attachmentApi.list(query); list.value = result.list; total.value = result.total; selection.value = []; }
  finally { loading.value = false; }
}
function onSearch() { query.page = 1; loadData(); }
function onReset() { Object.assign(query, { page: 1, pageSize: 20, keyword: '', groupId: undefined, mimeType: '' }); loadData(); }
function selectGroupFilter(groupId: number | undefined) { query.groupId = groupId; query.page = 1; loadData(); }
function selectGroup(data: AttachmentGroupModel) { selectGroupFilter(data.id); }
function openGroupAdd(parentId: number) { currentGroup.value = null; groupDialogVisible.value = true; if (parentId) setTimeout(() => { currentGroup.value = { id: 0, parentId, title: '', thumb: '', status: 1, sort: 999, isDefault: 0, createdAt: '', updatedAt: '' }; }, 0); }
function openGroupEdit(group: AttachmentGroupModel) { currentGroup.value = group; groupDialogVisible.value = true; }
async function onGroupCommand(command: string, group: AttachmentGroupModel) {
  if (command === 'add') return openGroupAdd(group.id);
  if (command === 'edit') return openGroupEdit(group);
  await ElMessageBox.confirm(`确认删除附件分组“${group.title}”吗？组内附件将移至未分组。`, '删除确认', { type: 'warning' });
  await attachmentGroupApi.remove(group.id);
  if (query.groupId === group.id) query.groupId = 0;
  await reloadGroups();
}
async function uploadFiles(event: Event) {
  const input = event.target as HTMLInputElement;
  const files = Array.from(input.files || []); input.value = '';
  if (!files.length) return;
  const uploadGroupId = query.groupId && query.groupId > 0 ? query.groupId : 1;
  let reusedOutsideGroup = 0;
  for (const file of files) {
    const result = await uploadApi.upload(file, file.type.startsWith('image/') ? 'image' : 'file', uploadGroupId);
    if (result.reused && result.groupId !== uploadGroupId) reusedOutsideGroup++;
  }
  if (reusedOutsideGroup > 0) ElMessage.warning(`${reusedOutsideGroup} 个重复文件复用了其他分组中的已有记录`);
  else ElMessage.success(`成功上传 ${files.length} 个文件`);
  await loadData();
}
async function renameOne(row: AttachmentModel) {
  const result = await ElMessageBox.prompt('请输入新的文件名称', '重命名', { inputValue: row.originalName, inputPattern: /^.{1,255}$/, inputErrorMessage: '名称长度必须为 1 至 255 个字符' });
  await attachmentApi.rename(row.id, result.value.trim()); await loadData();
}
async function moveSelected() {
  const result = await ElMessageBox.prompt('请输入目标附件分组 ID，0 表示未分组', '移动附件', { inputPattern: /^\d+$/, inputErrorMessage: '请输入非负整数' });
  await attachmentApi.move(selection.value.map((item) => item.id), Number(result.value)); await loadData();
}
async function removeSelected() {
  await ElMessageBox.confirm(`确认永久删除选中的 ${selection.value.length} 个附件吗？本地文件将同步删除。`, '永久删除确认', { type: 'error', confirmButtonText: '永久删除' });
  await attachmentApi.remove(selection.value.map((item) => item.id)); await loadData();
}
function formatBytes(size: number) { if (size < 1024) return `${size} B`; if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`; return `${(size / 1024 / 1024).toFixed(2)} MB`; }
function fileIcon(ext: string) { return ['zip', 'rar', '7z', 'tar', 'gz'].includes(ext) ? 'i-ep-folder-opened' : ['mp4', 'mov', 'avi'].includes(ext) ? 'i-ep-video-camera' : ['mp3', 'wav'].includes(ext) ? 'i-ep-headset' : 'i-ep-document'; }
function openFile(url: string) { window.open(url, '_blank', 'noopener,noreferrer'); }
onMounted(async () => { await loadGroups(); await loadData(); });
</script>
