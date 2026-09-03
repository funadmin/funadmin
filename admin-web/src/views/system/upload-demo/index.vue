<template>
  <PageWrapper title="通用上传演示" subtitle="单图 / 多图 / 文件 三模式 + 校验 + 业务分类 + 实时回显">
    <ElRow :gutter="16">
      <ElCol :xs="24" :md="24" :lg="8" class="upload-demo__col">
        <section class="upload-demo__card">
          <header class="upload-demo__header">
            <div class="upload-demo__header-icon upload-demo__header-icon--primary">
              <i class="i-ep-picture-filled" />
            </div>
            <div class="upload-demo__header-meta">
              <h3 class="upload-demo__header-title">单图上传</h3>
              <p class="upload-demo__header-sub">头像 / 封面 / 单张图片场景</p>
            </div>
            <ElTag size="small" effect="plain" round>image</ElTag>
          </header>

          <div class="upload-demo__body">
            <Upload
              v-model="avatar"
              type="image"
              biz-type="avatar"
              hint="支持 JPG / PNG / GIF / WebP，单张 ≤ 2MB"
              :max-size="2"
            />
          </div>

          <footer class="upload-demo__footer">
            <div class="upload-demo__footer-label">
              <i class="i-ep-link" />
              <span>v-model 值</span>
              <ElTag size="small" type="info" effect="plain">string</ElTag>
            </div>
            <div v-if="avatar" class="upload-demo__value">
              <code>{{ avatar }}</code>
              <ElButton link type="primary" size="small" @click="copy(avatar)">
                <i class="i-ep-document-copy" />
                复制
              </ElButton>
            </div>
            <div v-else class="upload-demo__empty">
              <i class="i-ep-document" />
              <span>暂无值，上传后自动回填</span>
            </div>
          </footer>
        </section>
      </ElCol>

      <ElCol :xs="24" :md="24" :lg="8" class="upload-demo__col">
        <section class="upload-demo__card">
          <header class="upload-demo__header">
            <div class="upload-demo__header-icon upload-demo__header-icon--success">
              <i class="i-ep-picture" />
            </div>
            <div class="upload-demo__header-meta">
              <h3 class="upload-demo__header-title">多图上传</h3>
              <p class="upload-demo__header-sub">相册 / 商品图集场景</p>
            </div>
            <ElTag size="small" effect="plain" round type="success">images</ElTag>
          </header>

          <div class="upload-demo__body">
            <Upload
              v-model="gallery"
              type="images"
              biz-type="image"
              :max-count="5"
              :max-size="3"
              hint="最多 5 张，单张 ≤ 3MB"
            />
          </div>

          <footer class="upload-demo__footer">
            <div class="upload-demo__footer-label">
              <i class="i-ep-link" />
              <span>v-model 值</span>
              <ElTag size="small" type="info" effect="plain">string[]</ElTag>
              <ElTag size="small" type="warning" effect="plain">
                {{ gallery.length }} / 5
              </ElTag>
            </div>
            <ul v-if="gallery.length" class="upload-demo__list">
              <li v-for="(url, i) in gallery" :key="url + i">
                <span class="upload-demo__list-idx">{{ i + 1 }}</span>
                <code>{{ url }}</code>
              </li>
            </ul>
            <div v-else class="upload-demo__empty">
              <i class="i-ep-picture" />
              <span>暂无图片</span>
            </div>
          </footer>
        </section>
      </ElCol>

      <ElCol :xs="24" :md="24" :lg="8" class="upload-demo__col">
        <section class="upload-demo__card">
          <header class="upload-demo__header">
            <div class="upload-demo__header-icon upload-demo__header-icon--warning">
              <i class="i-ep-folder-opened" />
            </div>
            <div class="upload-demo__header-meta">
              <h3 class="upload-demo__header-title">通用文件</h3>
              <p class="upload-demo__header-sub">附件 / 合同 / 表格场景</p>
            </div>
            <ElTag size="small" effect="plain" round type="warning">file</ElTag>
          </header>

          <div class="upload-demo__body">
            <Upload
              v-model="files"
              type="file"
              biz-type="file"
              :max-count="3"
              :max-size="10"
              multiple
              accept=".pdf,.doc,.docx,.xls,.xlsx,.zip"
              hint="支持 PDF / Word / Excel / Zip，单文件 ≤ 10MB，最多 3 个"
            />
          </div>

          <footer class="upload-demo__footer">
            <div class="upload-demo__footer-label">
              <i class="i-ep-link" />
              <span>v-model 值</span>
              <ElTag size="small" type="info" effect="plain">UploadResult[]</ElTag>
              <ElTag size="small" type="warning" effect="plain">
                {{ files.length }} / 3
              </ElTag>
            </div>
            <div v-if="files.length" class="upload-demo__summary">
              <i class="i-ep-success-filled" />
              <span>共 {{ files.length }} 个文件，合计 {{ formatSize(totalSize) }}</span>
            </div>
            <div v-else class="upload-demo__empty">
              <i class="i-ep-folder" />
              <span>暂无文件</span>
            </div>
          </footer>
        </section>
      </ElCol>
    </ElRow>

    <ElAlert
      class="upload-demo__alert"
      type="info"
      effect="light"
      show-icon
      :closable="false"
    >
      <template #title>
        <span class="upload-demo__alert-title">实现说明</span>
      </template>
      <ul class="upload-demo__alert-list">
        <li>统一封装在 <code>src/components/Upload/index.vue</code>，<code>v-model</code> 三种返回值。</li>
        <li>
          通过 <code>biz-type</code> 标记业务分类，方便后端按目录归档（如
          <code>avatar/</code>、<code>image/</code>、<code>file/</code>）。
        </li>
        <li>
          自定义 <code>customRequest</code> 调 <code>uploadApi.upload</code>，绕开 ElUpload
          默认 action 校验，便于换底层（OSS / COS / 本地）。
        </li>
        <li>校验在 <code>beforeUpload</code> 走 <code>maxSize</code>，错误提示统一 ElMessage。</li>
      </ul>
    </ElAlert>
  </PageWrapper>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { ElAlert, ElButton, ElCol, ElMessage, ElRow, ElTag } from 'element-plus';
import PageWrapper from '@/components/PageWrapper/index.vue';
import Upload from '@/components/Upload/index.vue';
import type { UploadResult } from '@/api/common/upload';

defineOptions({ name: 'SystemUploadDemo' });

const avatar = ref<string>('');
const gallery = ref<string[]>([]);
const files = ref<UploadResult[]>([]);

/** 文件总大小（字节） */
const totalSize = computed(() => files.value.reduce((sum, f) => sum + (f.size || 0), 0));

/** 复制到剪贴板 */
async function copy(text: string) {
  try {
    await navigator.clipboard.writeText(text);
    ElMessage.success('已复制');
  } catch {
    ElMessage.error('复制失败，请手动复制');
  }
}

/** 字节数 → 可读字符串 */
function formatSize(size?: number): string {
  if (!size && size !== 0) return '-';
  if (size < 1024) return `${size} B`;
  if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
  return `${(size / 1024 / 1024).toFixed(2)} MB`;
}
</script>

<style scoped>
.upload-demo__col {
  margin-bottom: 16px;
}

/* ---------- 卡片 ---------- */
.upload-demo__card {
  height: 100%;
  display: flex;
  flex-direction: column;
  background: var(--el-bg-color);
  border: 1px solid var(--el-border-color-lighter);
  border-radius: 10px;
  overflow: hidden;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.upload-demo__card:hover {
  border-color: var(--el-color-primary-light-5);
  box-shadow: 0 4px 16px rgba(45, 140, 240, 0.06);
}

/* ---------- header ---------- */
.upload-demo__header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border-bottom: 1px solid var(--el-border-color-lighter);
  background: var(--el-fill-color-extra-light);
}
.upload-demo__header-icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  flex-shrink: 0;
}
.upload-demo__header-icon--primary {
  background: rgba(45, 140, 240, 0.1);
  color: var(--el-color-primary);
}
.upload-demo__header-icon--success {
  background: rgba(103, 194, 58, 0.1);
  color: var(--el-color-success);
}
.upload-demo__header-icon--warning {
  background: rgba(230, 162, 60, 0.1);
  color: var(--el-color-warning);
}
.upload-demo__header-meta {
  flex: 1;
  min-width: 0;
}
.upload-demo__header-title {
  margin: 0;
  font-size: 14px;
  font-weight: 600;
  color: var(--el-text-color-primary);
  line-height: 1.4;
}
.upload-demo__header-sub {
  margin: 2px 0 0;
  font-size: 12px;
  color: var(--el-text-color-secondary);
  line-height: 1.4;
}

/* ---------- body ---------- */
.upload-demo__body {
  padding: 20px 16px 16px;
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: flex-start;
}

/* ---------- footer ---------- */
.upload-demo__footer {
  padding: 12px 16px 16px;
  border-top: 1px dashed var(--el-border-color-lighter);
  background: var(--el-fill-color-extra-light);
}
.upload-demo__footer-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--el-text-color-secondary);
  margin-bottom: 8px;
}
.upload-demo__footer-label > span {
  margin-right: 4px;
}

/* ---------- 单图：value 行 ---------- */
.upload-demo__value {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  border-radius: 6px;
  background: var(--el-bg-color);
  border: 1px solid var(--el-border-color-lighter);
}
.upload-demo__value code {
  flex: 1;
  font-size: 12px;
  color: var(--el-text-color-regular);
  font-family: 'JetBrains Mono', Consolas, Menlo, monospace;
  word-break: break-all;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* ---------- list ---------- */
.upload-demo__list {
  margin: 0;
  padding: 0;
  list-style: none;
  display: flex;
  flex-direction: column;
  gap: 6px;
  max-height: 200px;
  overflow: auto;
}
.upload-demo__list li {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 10px;
  border-radius: 6px;
  background: var(--el-bg-color);
  border: 1px solid var(--el-border-color-lighter);
  font-size: 12px;
}
.upload-demo__list-idx {
  flex-shrink: 0;
  width: 18px;
  height: 18px;
  border-radius: 4px;
  background: var(--el-color-primary-light-9);
  color: var(--el-color-primary);
  font-size: 11px;
  font-weight: 600;
  display: flex;
  align-items: center;
  justify-content: center;
}
.upload-demo__list code {
  flex: 1;
  font-family: 'JetBrains Mono', Consolas, Menlo, monospace;
  color: var(--el-text-color-regular);
  word-break: break-all;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* ---------- 文件列表 ---------- */
.upload-demo__file-item {
  display: flex !important;
  align-items: center;
  gap: 10px !important;
}
.upload-demo__file-icon {
  font-size: 18px;
  color: var(--el-color-primary);
  flex-shrink: 0;
}
.upload-demo__file-meta {
  flex: 1;
  min-width: 0;
}
.upload-demo__file-name {
  font-size: 12px;
  color: var(--el-text-color-primary);
  font-weight: 500;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.upload-demo__file-size {
  font-size: 11px;
  color: var(--el-text-color-secondary);
  margin-top: 2px;
}

/* ---------- 空状态 ---------- */
.upload-demo__empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 16px 0;
  color: var(--el-text-color-placeholder);
  font-size: 12px;
}
.upload-demo__empty > i {
  font-size: 22px;
  opacity: 0.5;
}

/* ---------- 文件统计 ---------- */
.upload-demo__summary {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 6px 10px;
  border-radius: 6px;
  background: var(--el-color-success-light-9);
  color: var(--el-color-success);
  font-size: 12px;
}
.upload-demo__summary > i {
  font-size: 14px;
}

/* ---------- 底部说明 ---------- */
.upload-demo__alert {
  margin-top: 8px;
}
.upload-demo__alert-title {
  font-weight: 600;
}
.upload-demo__alert-list {
  margin: 8px 0 0;
  padding-left: 20px;
  font-size: 13px;
  line-height: 1.8;
  color: var(--el-text-color-regular);
}
.upload-demo__alert-list code {
  padding: 1px 6px;
  border-radius: 4px;
  background: var(--el-fill-color);
  color: var(--el-color-primary);
  font-family: 'JetBrains Mono', Consolas, Menlo, monospace;
  font-size: 12px;
}
</style>
