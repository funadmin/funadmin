<template>
  <PageWrapper class="ai-page">
    <template #header><div><h2>AI 开发助手</h2><small>会话、工具审批与工作区变更</small></div></template>
    <template #extra>
      <el-button @click="openProviderSettings"><i class="i-ep-setting" />Provider</el-button>
      <el-button class="mobile-only" @click="contextDrawerOpen = true"><i class="i-ep-document" />任务</el-button>
    </template>

    <div class="ai-layout">
      <section class="ai-conversations-pane">
        <ConversationList :conversations="store.conversations" :selected-id="store.selectedConversationId" @create="createConversation" @select="selectConversation" />
      </section>

      <main class="ai-workspace-pane">
        <header class="workspace-header">
          <div><strong>{{ selectedConversation?.title || '选择或新建会话' }}</strong><small v-if="store.activeTask">{{ store.activeTask.stage }} · {{ store.activeTask.status }}</small></div>
          <el-button v-if="store.activeTask && running" type="danger" plain @click="store.cancelActiveTask()"><i class="i-ep-video-pause" />停止</el-button>
        </header>
        <div class="workspace-scroll">
          <MessageTimeline :messages="store.messages" />
          <ToolCallTimeline :tool-calls="store.toolCalls" @open-log="openToolLog" />
          <ApprovalCard v-for="approval in pendingApprovals" :key="approval.id" :approval="approval" @decision="(action, scope, feedback) => store.decideApproval(approval, action, scope, feedback)" />
        </div>
        <form class="composer" @submit.prevent="sendMessage">
          <el-input v-model="prompt" type="textarea" :rows="3" resize="none" placeholder="描述你要分析或修改的内容。高风险操作会按审批模式暂停。" />
          <el-button type="primary" native-type="submit" :disabled="!selectedConversation || !prompt.trim() || running">发送</el-button>
        </form>
      </main>

      <aside class="ai-context-pane"><ContextPanel /></aside>
    </div>

    <ElDrawer v-model="contextDrawerOpen" title="任务上下文" size="min(440px, 94vw)"><ContextPanel /></ElDrawer>
    <ElTabs v-model="mobileTab" class="mobile-tabs"><el-tab-pane label="会话" name="conversations" /><el-tab-pane label="工作区" name="workspace" /><el-tab-pane label="任务" name="context" /></ElTabs>
    <ChangeSetDrawer v-model="changeSetOpen" :files="preview?.files || []" :preview="preview" :test-status="store.changeSet?.test_status || 'unknown'" :security-status="store.changeSet?.security_status || 'unknown'" @preview="previewChangeSet" @apply="applyChangeSet" />
    <ProviderSettingsDrawer v-if="providerSettings" v-model="providerOpen" :settings="providerSettings" @test="testProvider" />
    <el-dialog v-model="logOpen" title="工具日志" width="min(760px, 94vw)"><pre class="tool-log">{{ toolLog }}</pre></el-dialog>
  </PageWrapper>
</template>

<script setup lang="ts">
import { computed, defineComponent, h, onBeforeUnmount, onMounted, ref } from 'vue';
import { ElDescriptions, ElDescriptionsItem, ElMessage, ElMessageBox, ElTag } from 'element-plus';
import PageWrapper from '@/components/PageWrapper/index.vue';
import { aiDevelopmentApi, type AiApprovalMode, type AiChangeSetPreview, type AiProviderSettings } from '@/api/development/ai';
import { useAiDevelopmentStore } from '@/store/modules/aiDevelopment';
import { useUserStore } from '@/store/modules/user';
import ConversationList from './components/ConversationList.vue';
import MessageTimeline from './components/MessageTimeline.vue';
import ApprovalCard from './components/ApprovalCard.vue';
import ApprovalModeSelector from './components/ApprovalModeSelector.vue';
import ToolCallTimeline from './components/ToolCallTimeline.vue';
import ChangeSetDrawer from './components/ChangeSetDrawer.vue';
import ProviderSettingsDrawer from './components/ProviderSettingsDrawer.vue';

const store = useAiDevelopmentStore();
const userStore = useUserStore();
const prompt = ref('');
const contextDrawerOpen = ref(false);
const changeSetOpen = ref(false);
const providerOpen = ref(false);
const logOpen = ref(false);
const toolLog = ref('');
const mobileTab = ref('workspace');
const preview = ref<AiChangeSetPreview | null>(null);
const providerSettings = ref<AiProviderSettings | null>(null);
const selectedConversation = computed(() => store.conversations.find((item) => item.id === store.selectedConversationId));
const pendingApprovals = computed(() => store.approvals.filter((item) => item.status === 'pending'));
const running = computed(() => store.activeTask?.status === 'running' || store.activeTask?.status === 'paused');
const hasCapability = (capability: string) => userStore.permissions.some((item) => item === '*' || item === '*:*:*' || item === capability);

const ContextPanel = defineComponent({
  name: 'AiContextPanel',
  setup() {
    const mode = computed({
      get: () => selectedConversation.value?.approval_mode || 'request_approval',
      set: (value: AiApprovalMode) => updateApprovalMode(value)
    });
    return () => h('div', { class: 'context-panel' }, [
      h('h3', '任务与权限'),
      h(ApprovalModeSelector, { modelValue: mode.value, canAgentApprove: hasCapability('development:ai:approve'), canFullAccess: hasCapability('development:ai:full-access'), 'onUpdate:modelValue': (value: AiApprovalMode) => { mode.value = value; } }),
      store.activeTask ? h(ElDescriptions, { column: 1, border: true, size: 'small' }, () => [
        h(ElDescriptionsItem, { label: '任务' }, () => `#${store.activeTask?.id} ${store.activeTask?.type}`),
        h(ElDescriptionsItem, { label: '阶段' }, () => store.activeTask?.stage || '-'),
        h(ElDescriptionsItem, { label: '状态' }, () => h(ElTag, {}, () => store.activeTask?.status)),
        h(ElDescriptionsItem, { label: '测试' }, () => store.activeTask?.test_result ? JSON.stringify(store.activeTask.test_result) : '-'),
        h(ElDescriptionsItem, { label: '风险' }, () => pendingApprovals.value.map((item) => item.risk_reason).join('；') || '暂无待审批风险')
      ]) : h('p', { class: 'empty-context' }, '暂无活动任务'),
      store.changeSet ? h('button', { class: 'changeset-link', onClick: () => { changeSetOpen.value = true; void ensurePreview(); } }, `ChangeSet #${store.changeSet.id} · ${store.changeSet.status}`) : null
    ]);
  }
});

async function createConversation() {
  const conversation = await aiDevelopmentApi.createConversation({ title: '新 AI 会话', approval_mode: 'request_approval' });
  store.conversations.unshift(conversation);
  await selectConversation(conversation.id);
}

async function selectConversation(id: number) {
  await store.selectConversation(id);
}

async function updateApprovalMode(mode: AiApprovalMode) {
  if (!selectedConversation.value) return;
  try {
    const updated = await aiDevelopmentApi.updateConversation(selectedConversation.value.id, { approval_mode: mode });
    Object.assign(selectedConversation.value, updated);
  } catch (error) {
    ElMessage.error(error instanceof Error ? error.message : '审批模式更新失败（403 capability）');
  }
}

async function sendMessage() {
  if (!store.selectedConversationId || !prompt.value.trim()) return;
  const message = await aiDevelopmentApi.createMessage(store.selectedConversationId, { role: 'user', content: [{ type: 'text', text: prompt.value.trim() }] });
  store.messages.push(message);
  const task = await aiDevelopmentApi.executeTask(store.selectedConversationId, { idempotency_key: crypto.randomUUID(), type: 'chat', message_id: message.id });
  store.activateTask(task);
  prompt.value = '';
  await store.refreshTaskContext();
  await store.connectEvents();
  store.saveRouteState();
}

async function ensurePreview() {
  if (!store.changeSet) return;
  preview.value = await aiDevelopmentApi.previewChangeSet(store.changeSet.id, store.changeSet.selection || []);
}
async function previewChangeSet(selection: string[]) { if (store.changeSet) preview.value = await aiDevelopmentApi.previewChangeSet(store.changeSet.id, selection); }
async function applyChangeSet(confirmToken: string, selection: string[]) {
  if (!store.changeSet || preview.value?.blocked) return;
  await ElMessageBox.confirm('将把选中的文件应用到工作区。请再次确认。', '最终应用二次确认', { type: 'warning', confirmButtonText: '确认应用' });
  try {
    await store.applyChangeSet(confirmToken, selection);
  } catch (error) {
    ElMessage.error(error instanceof Error ? error.message : '最终 apply 审批不存在或尚未批准');
    return;
  }
  changeSetOpen.value = false;
  ElMessage.success('ChangeSet 已应用');
}
async function openToolLog(id: number, stream: 'stdout' | 'stderr') { toolLog.value = (await aiDevelopmentApi.toolLog(id, stream)).content; logOpen.value = true; }
async function openProviderSettings() { providerSettings.value = await aiDevelopmentApi.settings(); providerOpen.value = true; }
async function testProvider(payload: Record<string, unknown>) { await aiDevelopmentApi.testSettings(payload); ElMessage.success('Provider 连接测试成功'); }

onMounted(async () => { await store.restoreRouteState(); if (store.activeTask) { await store.refreshTaskContext(); await store.connectEvents(); } });
onBeforeUnmount(() => store.closeEvents());
</script>

<style scoped>
.ai-page { height: calc(100vh - 132px); }
h2 { margin: 0; font-size: 18px; } header small { color: var(--el-text-color-secondary); }
.ai-layout { display: grid; grid-template-columns: minmax(220px, 260px) minmax(380px, 1fr) minmax(270px, 330px); height: 100%; min-height: 620px; border: 1px solid var(--el-border-color-lighter); border-radius: 12px; overflow: hidden; background: var(--el-bg-color); }
.ai-conversations-pane, .ai-context-pane { min-width: 0; overflow: auto; background: var(--el-fill-color-extra-light); }
.ai-conversations-pane { border-right: 1px solid var(--el-border-color-lighter); }
.ai-context-pane { border-left: 1px solid var(--el-border-color-lighter); }
.ai-workspace-pane { display: grid; min-width: 0; grid-template-rows: auto minmax(0, 1fr) auto; }
.workspace-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; border-bottom: 1px solid var(--el-border-color-lighter); }
.workspace-header div { display: grid; gap: 2px; }.workspace-scroll { overflow: auto; }.composer { display: grid; grid-template-columns: 1fr auto; align-items: end; gap: 10px; padding: 12px; border-top: 1px solid var(--el-border-color-lighter); }
.context-panel { display: grid; gap: 14px; padding: 16px; }.context-panel h3 { margin: 0; }.empty-context { color: var(--el-text-color-secondary); }.changeset-link { border: 1px solid var(--el-color-primary-light-5); border-radius: 8px; padding: 10px; background: var(--el-color-primary-light-9); color: var(--el-color-primary); cursor: pointer; }.tool-log { overflow: auto; max-height: 60vh; white-space: pre-wrap; }.mobile-only, .mobile-tabs { display: none; }
@media (max-width: 1024px) { .ai-page { height: auto; }.ai-layout { grid-template-columns: minmax(210px, 30%) 1fr; min-height: 70vh; }.ai-context-pane { display: none; }.mobile-only { display: inline-flex; }.mobile-tabs { display: block; } }
@media (max-width: 680px) { .ai-layout { display: block; }.ai-conversations-pane { max-height: 230px; border-right: 0; border-bottom: 1px solid var(--el-border-color-lighter); }.ai-workspace-pane { min-height: 65vh; }.composer { grid-template-columns: 1fr; } }
</style>
