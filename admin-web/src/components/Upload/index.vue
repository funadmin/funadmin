<template>
  <div class="app-upload" :class="[`is-${type}`, { 'is-disabled': disabled }]">
    <!-- 单图：圆形/方形头像样式 -->
    <template v-if="type === 'image'">
      <ElUpload
        :show-file-list="false"
        :before-upload="beforeUpload"
        :http-request="customRequest"
        :disabled="disabled"
        accept="image/*"
        class="app-upload__image"
      >
        <div class="app-upload__image-box">
          <img v-if="previewUrl" :src="previewUrl" class="app-upload__image-img" alt="" />
          <div v-else class="app-upload__image-placeholder">
            <i class="i-ep-plus app-upload__image-icon" />
            <span class="app-upload__image-tip">点击上传</span>
          </div>
          <div v-if="loading" class="app-upload__image-mask">
            <i class="i-ep-loading app-upload__image-loading" />
          </div>
        </div>
      </ElUpload>
      <div v-if="hint" class="app-upload__hint">{{ hint }}</div>
    </template>

    <!-- 多图：网格 -->
    <template v-else-if="type === 'images'">
      <ElUpload
        :show-file-list="false"
        :before-upload="beforeUpload"
        :http-request="customRequest"
        :disabled="disabled || isMaxReached"
        accept="image/*"
        multiple
        class="app-upload__grid"
      >
        <template #default>
          <div class="app-upload__grid-list">
            <div
              v-for="(item, idx) in imageList"
              :key="item.url + idx"
              class="app-upload__grid-item"
              @click.stop
            >
              <img :src="item.url" class="app-upload__grid-img" alt="" />
              <div class="app-upload__grid-mask">
                <i class="i-ep-zoom-in" @click.stop="onPreview(item.url)" />
                <i class="i-ep-delete" @click.stop="onRemoveImage(idx)" />
              </div>
            </div>
            <div
              v-if="!isMaxReached"
              class="app-upload__grid-add"
              :class="{ 'is-loading': loading }"
            >
              <i v-if="loading" class="i-ep-loading app-upload__image-loading" />
              <template v-else>
                <i class="i-ep-plus app-upload__image-icon" />
                <span class="app-upload__image-tip">添加图片</span>
              </template>
            </div>
          </div>
        </template>
      </ElUpload>
      <div v-if="hint" class="app-upload__hint">{{ hint }}</div>
      <ElImageViewer
        v-if="viewerVisible"
        :url-list="[viewerUrl]"
        teleported
        @close="viewerVisible = false"
      />
    </template>

    <!-- 通用文件 -->
    <template v-else>
      <ElUpload
        :show-file-list="false"
        :before-upload="beforeUpload"
        :http-request="customRequest"
        :disabled="disabled || isMaxReached"
        :accept="accept"
        :multiple="multiple"
        drag
        class="app-upload__file"
      >
        <div class="app-upload__file-drop">
          <i v-if="loading" class="i-ep-loading app-upload__file-drop-icon is-loading" />
          <i v-else class="i-ep-upload-filled app-upload__file-drop-icon" />
          <div class="app-upload__file-drop-title">
            <span>点击或</span>
            <em>拖拽文件</em>
            <span>到此处上传</span>
          </div>
          <div v-if="hint" class="app-upload__file-drop-hint">{{ hint }}</div>
        </div>
      </ElUpload>

      <ul v-if="fileList.length" class="app-upload__file-list">
        <li v-for="(it, idx) in fileList" :key="(it.url || it.name) + idx" class="app-upload__file-row">
          <i :class="getFileIcon(it.name)" class="app-upload__file-row-icon" />
          <div class="app-upload__file-row-meta">
            <div class="app-upload__file-row-name" :title="it.name">{{ it.name }}</div>
            <div class="app-upload__file-row-info">
              <span>{{ formatBytes(it.size) }}</span>
              <span v-if="it.url" class="app-upload__file-row-status">
                <i class="i-ep-success-filled" /> 已上传
              </span>
            </div>
          </div>
          <div class="app-upload__file-row-actions">
            <ElButton
              v-if="it.url"
              link
              type="primary"
              size="small"
              @click="openFile(it.url!)"
            >
              <i class="i-ep-view" /> 预览
            </ElButton>
            <ElButton
              link
              type="danger"
              size="small"
              :disabled="disabled"
              @click="removeFile(idx)"
            >
              <i class="i-ep-delete" /> 删除
            </ElButton>
          </div>
        </li>
      </ul>
    </template>
  </div>
</template>

<script setup lang="ts">
/**
 * 通用上传组件 —— 三模式
 * - image  : 单图（v-model: string）
 * - images : 多图网格（v-model: string[]）
 * - file   : 通用文件上传（v-model: UploadResult[]，含名称与大小元信息）
 *
 * 设计原则：
 * - 不直接耦合 element-plus 的 file-list 类型，自己抽象 list
 * - 校验与提示统一在 beforeUpload 走，错误用 ElMessage
 * - 自定义 customRequest，调 uploadApi.upload，避免 el-upload 自带 action 校验
 */
import { computed, ref, watch } from 'vue';
import {
  ElButton,
  ElImageViewer,
  ElMessage,
  ElUpload,
  type UploadRequestOptions
} from 'element-plus';
import { uploadApi, type UploadResult } from '@/api/common/upload';

type UploadType = 'image' | 'images' | 'file';

interface Props {
  /** v-model 值：单图 string，多图 string[]，文件 UploadResult[] */
  modelValue?: string | string[] | UploadResult[];
  /** 模式 */
  type?: UploadType;
  /** 接受的 mime/扩展（仅 file 模式）；image* 模式强制 image/* */
  accept?: string;
  /** 单文件最大体积（MB），默认 5 */
  maxSize?: number;
  /** images / file 模式最多选几个，0 = 不限 */
  maxCount?: number;
  /** 仅 file 模式生效 */
  multiple?: boolean;
  /** 自定义提示文本 */
  hint?: string;
  /** 业务分类标记，用于后端归档 */
  bizType?: 'image' | 'avatar' | 'file';
  /** 禁用 */
  disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: () => '',
  type: 'image',
  accept: '',
  maxSize: 5,
  maxCount: 0,
  multiple: true,
  hint: '',
  bizType: 'file',
  disabled: false
});

const emit = defineEmits<{
  'update:modelValue': [val: string | string[] | UploadResult[]];
  success: [item: UploadResult];
  error: [err: Error];
}>();

const loading = ref(false);

/* ---------- 单图 ---------- */
const previewUrl = computed(() =>
  props.type === 'image' ? (props.modelValue as string) || '' : ''
);

/* ---------- 多图 ---------- */
const imageList = ref<{ url: string }[]>([]);

watch(
  () => props.modelValue,
  (v) => {
    if (props.type === 'images') {
      imageList.value = ((v as string[]) || []).map((url) => ({ url }));
    }
  },
  { immediate: true }
);

const isMaxReached = computed(() => {
  if (!props.maxCount) return false;
  if (props.type === 'images') return imageList.value.length >= props.maxCount;
  if (props.type === 'file') return fileList.value.length >= props.maxCount;
  return false;
});

/* ---------- 文件：直接镜像 v-model，避免和 ElUpload 自带 file-list 类型耦合 ---------- */
const fileList = computed<UploadResult[]>(() =>
  props.type === 'file' ? ((props.modelValue as UploadResult[]) || []) : []
);

function removeFile(idx: number) {
  const next = ((props.modelValue as UploadResult[]) || []).filter((_, i) => i !== idx);
  emit('update:modelValue', next);
}

function openFile(url: string) {
  window.open(url, '_blank', 'noopener,noreferrer');
}

/** 字节数 → 可读字符串 */
function formatBytes(size?: number): string {
  if (size == null) return '-';
  if (size < 1024) return `${size} B`;
  if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
  return `${(size / 1024 / 1024).toFixed(2)} MB`;
}

/** 根据扩展名返回本地图标 class */
function getFileIcon(name: string): string {
  const ext = (name.split('.').pop() || '').toLowerCase();
  if (['pdf'].includes(ext)) return 'i-ep-document';
  if (['doc', 'docx'].includes(ext)) return 'i-ep-document';
  if (['xls', 'xlsx', 'csv'].includes(ext)) return 'i-ep-tickets';
  if (['ppt', 'pptx'].includes(ext)) return 'i-ep-collection';
  if (['zip', 'rar', '7z', 'tar', 'gz'].includes(ext)) return 'i-ep-folder-opened';
  if (['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'].includes(ext)) return 'i-ep-picture';
  if (['mp4', 'mov', 'avi', 'mkv'].includes(ext)) return 'i-ep-video-camera';
  if (['mp3', 'wav', 'flac'].includes(ext)) return 'i-ep-headset';
  return 'i-ep-document';
}

/* ---------- 上传校验 ---------- */
function beforeUpload(file: File): boolean {
  const sizeMB = file.size / 1024 / 1024;
  if (sizeMB > props.maxSize) {
    ElMessage.warning(`文件 ${file.name} 超过 ${props.maxSize}MB`);
    return false;
  }
  return true;
}

/* ---------- 自定义请求 ---------- */
async function customRequest(opts: UploadRequestOptions) {
  loading.value = true;
  try {
    const file = opts.file as unknown as File;
    const res = await uploadApi.upload(file, props.bizType);
    emit('success', res);

    if (props.type === 'image') {
      emit('update:modelValue', res.url);
    } else if (props.type === 'images') {
      const next = [...((props.modelValue as string[]) || []), res.url];
      emit('update:modelValue', next);
    } else {
      const next = [...((props.modelValue as UploadResult[]) || []), res];
      emit('update:modelValue', next);
    }
  } catch (e: any) {
    emit('error', e);
    ElMessage.error(e?.msg || e?.message || '上传失败');
  } finally {
    loading.value = false;
  }
}

/* ---------- 多图操作 ---------- */
const viewerVisible = ref(false);
const viewerUrl = ref('');

function onPreview(url: string) {
  viewerUrl.value = url;
  viewerVisible.value = true;
}

function onRemoveImage(idx: number) {
  const next = ((props.modelValue as string[]) || []).filter((_, i) => i !== idx);
  emit('update:modelValue', next);
}
</script>

<style scoped>
.app-upload__hint {
  margin-top: 6px;
  font-size: 12px;
  color: var(--el-text-color-secondary);
  line-height: 1.5;
}

/* ---------- 单图 ---------- */
.app-upload__image-box {
  position: relative;
  width: 104px;
  height: 104px;
  border: 1px dashed var(--el-border-color);
  border-radius: 8px;
  cursor: pointer;
  overflow: hidden;
  transition: border-color 0.2s;
  background: var(--el-fill-color-lighter);
}
.app-upload__image-box:hover {
  border-color: var(--el-color-primary);
}
.app-upload__image-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.app-upload__image-placeholder {
  width: 100%;
  height: 100%;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  color: var(--el-text-color-secondary);
  gap: 6px;
}
.app-upload__image-icon {
  font-size: 24px;
}
.app-upload__image-tip {
  font-size: 12px;
}
.app-upload__image-mask {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.45);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
}
.app-upload__image-loading {
  font-size: 22px;
  animation: app-upload-spin 1s linear infinite;
}
@keyframes app-upload-spin {
  to { transform: rotate(360deg); }
}

/* ---------- 多图 ---------- */
.app-upload__grid-list {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}
.app-upload__grid-item {
  position: relative;
  width: 104px;
  height: 104px;
  border-radius: 8px;
  overflow: hidden;
  background: var(--el-fill-color-lighter);
  border: 1px solid var(--el-border-color-lighter);
}
.app-upload__grid-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}
.app-upload__grid-mask {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.5);
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 16px;
  opacity: 0;
  transition: opacity 0.2s;
  font-size: 18px;
  cursor: default;
}
.app-upload__grid-mask i {
  cursor: pointer;
}
.app-upload__grid-item:hover .app-upload__grid-mask {
  opacity: 1;
}
.app-upload__grid-add {
  width: 104px;
  height: 104px;
  border: 1px dashed var(--el-border-color);
  border-radius: 8px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  color: var(--el-text-color-secondary);
  background: var(--el-fill-color-lighter);
  gap: 6px;
  transition: border-color 0.2s, color 0.2s;
}
.app-upload__grid-add:hover {
  border-color: var(--el-color-primary);
  color: var(--el-color-primary);
}
.app-upload__grid-add.is-loading {
  cursor: wait;
}

/* ---------- 通用文件 ---------- */
.app-upload__file {
  width: 100%;
}
.app-upload__file :deep(.el-upload),
.app-upload__file :deep(.el-upload-dragger) {
  width: 100%;
  display: block;
}
.app-upload__file :deep(.el-upload-dragger) {
  padding: 20px 16px;
  border: 1px dashed var(--el-border-color);
  border-radius: 8px;
  background: var(--el-fill-color-lighter);
  transition: border-color 0.2s, background-color 0.2s;
}
.app-upload__file :deep(.el-upload-dragger:hover) {
  border-color: var(--el-color-primary);
  background: var(--el-color-primary-light-9);
}
.app-upload__file :deep(.el-upload.is-drag-over .el-upload-dragger) {
  border-color: var(--el-color-primary);
  background: var(--el-color-primary-light-9);
}

.app-upload__file-drop {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  color: var(--el-text-color-secondary);
}
.app-upload__file-drop-icon {
  font-size: 28px;
  color: var(--el-color-primary);
}
.app-upload__file-drop-icon.is-loading {
  animation: app-upload-spin 1s linear infinite;
}
.app-upload__file-drop-title {
  font-size: 14px;
  color: var(--el-text-color-regular);
  display: flex;
  align-items: baseline;
  gap: 4px;
}
.app-upload__file-drop-title em {
  font-style: normal;
  color: var(--el-color-primary);
  font-weight: 600;
}
.app-upload__file-drop-hint {
  font-size: 12px;
  color: var(--el-text-color-placeholder);
}

.app-upload__file-list {
  list-style: none;
  margin: 12px 0 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.app-upload__file-row {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 8px 12px;
  border: 1px solid var(--el-border-color-lighter);
  border-radius: 6px;
  background: var(--el-bg-color);
  transition: border-color 0.2s, background-color 0.2s;
}
.app-upload__file-row:hover {
  border-color: var(--el-color-primary-light-5);
  background: var(--el-color-primary-light-9);
}
.app-upload__file-row-icon {
  font-size: 22px;
  color: var(--el-color-primary);
  flex-shrink: 0;
}
.app-upload__file-row-meta {
  flex: 1;
  min-width: 0;
}
.app-upload__file-row-name {
  font-size: 13px;
  color: var(--el-text-color-primary);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.app-upload__file-row-info {
  margin-top: 2px;
  font-size: 12px;
  color: var(--el-text-color-secondary);
  display: flex;
  align-items: center;
  gap: 12px;
}
.app-upload__file-row-status {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  color: var(--el-color-success);
}
.app-upload__file-row-actions {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: 4px;
}

/* 强制隐藏原生 input，防止被全局 reset 复原（截图里漏出"选择文件 未选择任何文件" 即此原因） */
.app-upload :deep(.el-upload__input),
.app-upload :deep(input[type='file']) {
  display: none !important;
}

.app-upload.is-disabled {
  opacity: 0.6;
  pointer-events: none;
}
</style>
