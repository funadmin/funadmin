<template>
  <el-dialog v-model="visible" title="离线安装插件" width="520px">
    <el-upload drag :auto-upload="false" :limit="1" accept=".zip" :on-change="selectFile">
      <div>拖放或选择 ZIP 插件包</div>
      <template #tip><div class="el-upload__tip">仅接受 100MB 以内的 ZIP 包，服务端将继续验证签名与包路径。</div></template>
    </el-upload>
    <template #footer>
      <el-button @click="visible = false">取消</el-button>
      <el-button type="primary" :loading="loading" :disabled="!file" v-perm="'system:plugin:install'" @click="install">安装</el-button>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import type { UploadFile } from 'element-plus';
import { pluginApi } from '@/api/system/plugin';

const visible = defineModel<boolean>({ default: false });
const emit = defineEmits<{ installed: [] }>();
const file = ref<File | null>(null);
const loading = ref(false);

function selectFile(upload: UploadFile) {
  file.value = upload.raw ?? null;
}

async function install() {
  if (!file.value) return;
  loading.value = true;
  try {
    await pluginApi.installLocal(file.value);
    file.value = null;
    visible.value = false;
    emit('installed');
  } finally {
    loading.value = false;
  }
}
</script>
