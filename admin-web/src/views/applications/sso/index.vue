<template>
  <PageWrapper title="单点登录" subtitle="统一配置身份提供方、Issuer 与协议安全能力">
    <div class="grid gap-4 md:grid-cols-3" role="radiogroup" aria-label="SSO 模式">
      <el-card v-for="mode in modes" :key="mode.value" shadow="hover" :class="{ selected: selectedMode === mode.value, disabled: mode.disabled }" tabindex="0" @click="!mode.disabled && select(mode.value)" @keyup.enter="!mode.disabled && select(mode.value)">
        <div class="flex items-center justify-between"><strong>{{ mode.title }}</strong><el-tag v-if="mode.disabled">首期不可用</el-tag><el-radio v-else :model-value="selectedMode" :value="mode.value" :aria-label="mode.title" /></div><p class="text-sm text-gray-500">{{ mode.description }}</p>
      </el-card>
    </div>
    <el-card class="mt-4"><template #header><div class="flex justify-between"><strong>应用与协议设置</strong><el-button @click="runCheck">配置自检</el-button></div></template>
      <el-form label-width="150px"><el-form-item label="Issuer"><el-input v-model="form.issuer" placeholder="https://identity.example.com" /></el-form-item><el-form-item label="Back-channel Logout"><el-switch v-model="form.backchannelLogoutEnabled" /></el-form-item></el-form>
      <el-table :data="applications"><el-table-column prop="name" label="应用"/><el-table-column prop="status" label="状态"/><el-table-column prop="runtimeType" label="运行类型"/><el-table-column label="设置"><template #default="scope"><el-button @click="openSettings(scope.row as EnterpriseApplication)">设置</el-button></template></el-table-column></el-table>
    </el-card>
    <el-drawer v-model="drawerVisible" title="应用 SSO 设置" size="520px"><el-descriptions :column="1" border><el-descriptions-item label="应用">{{ activeApplication?.name }}</el-descriptions-item><el-descriptions-item label="Redirect">{{ activeApplication?.launchUrl }}</el-descriptions-item><el-descriptions-item label="PKCE">强制 S256</el-descriptions-item><el-descriptions-item label="Scope">openid profile</el-descriptions-item></el-descriptions></el-drawer>
    <el-drawer v-model="checkVisible" title="SSO 配置自检" size="520px"><el-alert :title="checkResult?.passed ? '自检通过' : '存在待处理项'" :type="checkResult?.passed ? 'success' : 'warning'" :closable="false"/><el-table class="mt-4" :data="checkResult?.checks || []"><el-table-column prop="key" label="检查项"/><el-table-column label="结果"><template #default="scope"><el-tag :type="scope.row.passed ? 'success' : 'danger'">{{ scope.row.passed ? '通过' : '失败' }}</el-tag></template></el-table-column><el-table-column prop="message" label="说明" min-width="220"/></el-table><p class="sr-only">issuer、HTTPS、domain、redirect、PKCE、scope、key、backchannel</p></el-drawer>
    <div class="sticky-save"><el-button type="primary" :loading="saving" @click="save">保存配置</el-button></div>
  </PageWrapper>
</template>
<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { applicationApi, type EnterpriseApplication } from '@/api/identity/applications';
import { ssoApi, type SelfCheck, type SsoConfig } from '@/api/identity/management';
defineOptions({ name: 'SsoConfiguration' });
const modes: Array<{ value: 'off'|'identity_provider'|'external'; title: string; description: string; disabled: boolean }> = [{ value: 'off', title: '关闭 SSO', description: '保留本地登录，不提供统一身份入口', disabled: false }, { value: 'identity_provider', title: '身份提供方', description: 'FunAdmin 作为 OIDC Provider', disabled: false }, { value: 'external', title: '外部身份接入', description: '连接企业外部 IdP', disabled: true }];
const form = reactive<SsoConfig>({ enabled: false, providerMode: 'identity_provider', issuer: '', externalIdentityEnabled: false, backchannelLogoutEnabled: true });
const selectedMode = computed(() => form.enabled ? form.providerMode : 'off');
const applications = ref<EnterpriseApplication[]>([]), saving = ref(false), drawerVisible = ref(false), checkVisible = ref(false), activeApplication = ref<EnterpriseApplication>(), checkResult = ref<SelfCheck>();
const select = (mode: 'off'|'identity_provider'|'external') => { form.enabled = mode !== 'off'; if (mode !== 'off') form.providerMode = mode; };
const openSettings = (application: EnterpriseApplication) => { activeApplication.value = application; drawerVisible.value = true; };
const runCheck = async () => { checkResult.value = await ssoApi.check(); checkVisible.value = true; };
const save = async () => { saving.value = true; try { Object.assign(form, await ssoApi.save(form)); await runCheck(); } finally { saving.value = false; } };
onMounted(async () => { Object.assign(form, await ssoApi.config()); applications.value = (await applicationApi.list({ page: 1, pageSize: 100 })).list; });
</script>
<style scoped>.selected{border-color:var(--el-color-primary)}.disabled{cursor:not-allowed;opacity:.6}.sticky-save{position:sticky;bottom:0;z-index:5;display:flex;justify-content:flex-end;margin-top:16px;padding:12px;background:var(--el-bg-color);border-top:1px solid var(--el-border-color)}</style>
