<template>
  <el-drawer v-model="visible" title="云市场账号" size="420px">
    <el-alert v-if="account" :title="`已登录：${account.nickname || account.username}`" type="success" :closable="false" />
    <el-form v-else label-position="top" class="mt-4" @submit.prevent="login">
      <el-form-item label="账号"><el-input v-model="form.account" autocomplete="username" /></el-form-item>
      <el-form-item label="密码"><el-input v-model="form.password" type="password" show-password autocomplete="current-password" /></el-form-item>
      <el-button type="primary" :loading="loading" native-type="submit">登录</el-button>
    </el-form>
    <div v-if="account" class="mt-4 flex gap-2">
      <el-button type="primary" plain :loading="loading" v-perm="'system:plugin:account-refresh'" @click="refreshToken">刷新令牌</el-button>
      <el-button type="danger" plain :loading="loading" @click="logout">退出市场账号</el-button>
    </div>
  </el-drawer>
</template>

<script setup lang="ts">
import { reactive, ref, watch } from 'vue';
import { pluginApi, type PluginAccount } from '@/api/plugin';

const visible = defineModel<boolean>({ default: false });
const emit = defineEmits<{ changed: [] }>();
const account = ref<PluginAccount | null>(null);
const loading = ref(false);
const form = reactive({ account: '', password: '' });

async function load() { account.value = await pluginApi.currentAccount(); }
async function login() { loading.value = true; try { account.value = await pluginApi.accountLogin(form.account, form.password); form.password = ''; emit('changed'); } finally { loading.value = false; } }
async function refreshToken() { loading.value = true; try { account.value = await pluginApi.accountRefresh(); emit('changed'); } finally { loading.value = false; } }
async function logout() { loading.value = true; try { await pluginApi.accountLogout(); account.value = null; emit('changed'); } finally { loading.value = false; } }
watch(visible, (open) => { if (open) load(); }, { immediate: true });
</script>
