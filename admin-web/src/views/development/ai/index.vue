<template>
  <PageWrapper class="ai-page">
    <nav class="mobile-actions" :aria-label="t('aiDevelopment.workspace')">
      <el-button data-testid="mobile-workspace" :aria-pressed="mobileTab === 'workspace'" @click="mobileTab = 'workspace'"><i class="i-ep-monitor" />{{ t('aiDevelopment.workspace') }}</el-button>
      <el-button data-testid="mobile-conversations" :aria-pressed="mobileTab === 'conversations'" @click="mobileTab = 'conversations'"><i class="i-ep-chat-line-round" />{{ t('aiDevelopment.conversations') }}</el-button>
      <el-button data-testid="mobile-context" :aria-pressed="mobileTab === 'context'" @click="mobileTab = 'context'"><i class="i-ep-document" />{{ t('aiDevelopment.task') }}</el-button>
    </nav>

    <div class="ai-layout" :class="{ 'ai-layout--inspector': !isMobile && inspectorOpen }">
      <section v-if="regionVisible('conversations')" class="ai-conversations-pane" data-ai-region="conversations">
        <ConversationList :conversations="store.conversations" :groups="store.conversationGroups" :selected-id="store.selectedConversationId" :archived="showArchived" @toggle-archived="showArchived = !showArchived" @action="conversationAction" @create="createConversation" @select="selectConversation" @create-group="createConversationGroup" @rename-group="renameConversationGroup" @delete-group="deleteConversationGroup">
          <template #actions>
            <el-button size="small" @click="openProviderSettings"><i class="i-ep-setting" />{{ t('aiDevelopment.provider') }}</el-button>
          </template>
        </ConversationList>
      </section>

      <main v-show="regionVisible('workspace')" class="ai-workspace-pane" data-ai-region="workspace">
        <header class="workspace-header">
          <div><strong>{{ selectedConversation?.title || t('aiDevelopment.selectConversation') }}</strong><small v-if="store.activeTask">{{ store.activeTask.stage }} · {{ statusLabel(store.activeTask.status) }}</small></div>
          <div class="workspace-actions">
            <el-button data-testid="toggle-inspector" @click="toggleInspector"><i class="i-ep-document" />{{ inspectorOpen ? t('aiDevelopment.changeSet.close') : t('aiDevelopment.task') }}</el-button>
            <el-button v-if="store.activeTask && running" type="danger" plain @click="store.cancelActiveTask()"><i class="i-ep-video-pause" />{{ t('aiDevelopment.stop') }}</el-button>
          </div>
        </header>
        <div class="workspace-scroll" data-scroll-container="primary">
          <el-alert v-if="store.syncError" type="error" :title="t('aiDevelopment.management.syncFailed')" :closable="false" />
          <MessageTimeline :messages="store.messages" />
          <ToolCallTimeline :tool-calls="store.toolCalls" @open-log="openToolLog" />
          <ApprovalCard v-for="approval in pendingApprovals" :key="approval.id" :approval="approval" @decision="(action, scope, feedback) => store.decideApproval(approval, action, scope, feedback)" />
        </div>
        <AiComposer :conversation-id="store.selectedConversationId" :model="selectedConversation?.model || ''" :profile="selectedProfile" :running="running" :saving="modelSaving" @sent="messageSent" @stop="store.cancelActiveTask()">
          <template #models>
          <form class="model-form" data-testid="model-form" @submit.prevent="saveModel">
            <small data-testid="current-model">{{ t('aiDevelopment.modelSelection.current') }}: {{ selectedConversation?.model || t('aiDevelopment.modelSelection.unset') }}</small>
            <div class="model-controls">
              <el-select data-testid="conversation-profile" :model-value="selectedConversation?.profile_id" :disabled="modelSaving || !selectedConversation" :aria-label="t('aiDevelopment.profiles.title')" @visible-change="(visible: boolean) => { if (visible) void loadProfiles(); }" @change="selectProfile">
                <el-option v-for="profile in profiles" :key="profile.id" :value="profile.id" :label="`${profile.name}${profile.is_default ? '（默认）' : ''} · ${profile.model}`" :disabled="!profile.enabled" />
              </el-select>
              <el-button :disabled="modelSaving || !selectedConversation" @click="inheritProfile">{{ t('aiDevelopment.profiles.inherit') }}</el-button>
              <label for="ai-model-id">{{ t('aiDevelopment.modelSelection.id') }}</label>
              <el-input id="ai-model-id" v-model="modelDraft" data-testid="model-id" :aria-label="t('aiDevelopment.modelSelection.id')" aria-describedby="ai-model-hint" :disabled="modelSaving || !selectedConversation" />
              <el-button data-testid="save-model" native-type="submit" :loading="modelSaving" :disabled="modelSaving || !selectedConversation || !modelDraft.trim()">{{ t('aiDevelopment.modelSelection.save') }}</el-button>
            </div>
            <small data-testid="profile-model-summary">档案默认模型：{{ selectedProfile?.model || '未选择档案' }}；Token 输入 {{ selectedProfile?.max_input_tokens ?? '未指定' }} / 输出 {{ selectedProfile?.max_output_tokens ?? '未指定' }} / 上下文 {{ selectedProfile?.context_window ?? '未指定' }}</small>
            <div v-if="selectedProfile" data-testid="favorite-models">常用模型：<button v-for="model in selectedProfile.favorite_models" :key="model" type="button" :disabled="modelSaving" @click="modelDraft = model">{{ model }}</button></div>
            <label for="ai-conversation-reasoning">会话思考档位</label>
            <select id="ai-conversation-reasoning" data-testid="conversation-reasoning" :value="selectedConversation?.reasoning_effort ?? ''" :disabled="modelSaving || !selectedProfile || !selectedProfile.enabled" @change="selectReasoning">
              <option value="">继承档案（default）</option>
              <option v-for="effort in legalReasoningEfforts" :key="effort" :value="effort">{{ effort }}</option>
            </select>
            <small data-testid="inherited-reasoning">思考模式：{{ selectedProfile ? (effectiveReasoning || '默认（不发送参数）') : '未选择档案，使用服务端配置' }}。{{ selectedConversation?.reasoning_effort == null ? '继承档案' : '会话覆盖' }}；仅影响新建任务，运行任务保留快照。</small>
            <small id="ai-model-hint">{{ t('aiDevelopment.modelSelection.hint') }} {{ t('aiDevelopment.profiles.selectionHint') }}</small>
            <p v-if="modelError !== null" data-testid="model-error" class="model-error" role="alert">{{ t('aiDevelopment.modelSelection.failed') }}{{ modelError ? `: ${modelError}` : '' }}</p>
          </form>
          </template>
          <template #approval><ApprovalModeSelector :model-value="selectedConversation?.approval_mode || 'request_approval'" :can-agent-approve="hasCapability('development:ai:approve')" :can-full-access="hasCapability('development:ai:full-access')" @update:model-value="updateApprovalMode" /></template>
        </AiComposer>
      </main>

      <aside v-if="regionVisible('context')" class="ai-context-pane" data-ai-region="context"><ContextPanel /></aside>
    </div>

    <ChangeSetDrawer v-model="changeSetOpen" :files="preview?.files || []" :preview="preview" :test-status="store.changeSet?.test_status || 'unknown'" :security-status="store.changeSet?.security_status || 'unknown'" @preview="previewChangeSet" @apply="applyChangeSet" />
    <ProviderSettingsDrawer v-model="providerOpen" :profiles="profiles" :busy="profileBusy" :models="profileModels" :saved-profile="savedProfile" :error="profileError" :notice="profileNotice" @select="resetProfileFeedback" @save="saveProfile" @copy="copyProfile" @remove="removeProfile" @default="defaultProfile" @models="fetchProfileModels" @test="testProvider" />
    <el-dialog v-model="moveOpen" :title="t('aiDevelopment.management.move')" width="min(440px, 94vw)">
      <el-select v-model="moveGroupId" :aria-label="t('aiDevelopment.management.groupName')">
        <el-option :value="0" :label="t('aiDevelopment.management.ungrouped')" />
        <el-option v-for="group in store.conversationGroups" :key="group.id" :value="group.id" :label="group.name" />
      </el-select>
      <template #footer><el-button @click="moveOpen = false">{{ t('aiDevelopment.management.cancel') }}</el-button><el-button type="primary" :loading="moving" @click="confirmMove">{{ t('aiDevelopment.management.confirm') }}</el-button></template>
    </el-dialog>
    <el-dialog v-model="logOpen" :title="t('aiDevelopment.toolLog')" width="min(760px, 94vw)"><pre class="tool-log">{{ toolLog }}</pre></el-dialog>
  </PageWrapper>
</template>

<script setup lang="ts">
import { computed, defineComponent, h, onBeforeUnmount, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { ElDescriptions, ElDescriptionsItem, ElMessage, ElMessageBox, ElTag } from 'element-plus';
import PageWrapper from '@/components/PageWrapper/index.vue';
import { aiDevelopmentApi, profileCapabilityError, type AiCatalogModel, type AiApprovalMode, type AiChangeSetPreview, type AiProfile, type AiProfileInput, type AiReasoningEffort } from '@/api/development/ai';
import { useAiDevelopmentStore } from '@/store/modules/aiDevelopment';
import { useUserStore } from '@/store/modules/user';
import ConversationList from './components/ConversationList.vue';
import AiComposer from './components/AiComposer.vue';
import type { AiTask, AiMessage } from '@/api/development/ai';
import MessageTimeline from './components/MessageTimeline.vue';
import ApprovalCard from './components/ApprovalCard.vue';
import ApprovalModeSelector from './components/ApprovalModeSelector.vue';
import ToolCallTimeline from './components/ToolCallTimeline.vue';
import ChangeSetDrawer from './components/ChangeSetDrawer.vue';
import ProviderSettingsDrawer from './components/ProviderSettingsDrawer.vue';
import { aiEnumLabel } from './i18n';

const { t } = useI18n();
const store = useAiDevelopmentStore();
const userStore = useUserStore();
const showArchived = ref(false);
const moveOpen = ref(false);
const moveConversationId = ref<number | null>(null);
const moveGroupId = ref(0);
const moving = ref(false);
const mobileQuery = typeof window === 'undefined' ? null : window.matchMedia('(max-width: 1024px)');
const isMobile = ref(mobileQuery?.matches ?? false);
const changeSetOpen = ref(false);
const inspectorOpen = ref(false);
const providerOpen = ref(false);
const logOpen = ref(false);
const toolLog = ref('');
const mobileTab = ref('workspace');
const preview = ref<AiChangeSetPreview | null>(null);
const profiles = ref<AiProfile[]>([]);
const profileBusy = ref(false);
const profileError = ref('');
const profileNotice = ref('');
const profileModels = ref<AiCatalogModel[]>([]);
const savedProfile = ref<AiProfile | null>(null);
let profileGeneration = 0;
function resetProfileFeedback() { profileGeneration++; profileModels.value = []; profileError.value = ''; profileNotice.value = ''; }
watch(providerOpen, () => resetProfileFeedback());
async function loadProfiles() { await manage(async () => { profiles.value = await aiDevelopmentApi.profiles(); }); }
async function selectProfile(id: number) {
  const profile = profiles.value.find((item) => item.id === id);
  const conversationId = store.selectedConversationId;
  const generation = store.selectionGeneration;
  if (!profile || !conversationId || modelSaving.value) return;
  const error = profileCapabilityError({ ...profile, reasoning_effort: selectedConversation.value?.reasoning_effort ?? profile.reasoning_effort });
  if (!profile.enabled || error) { modelError.value = error || '档案已停用'; return; }
  modelSaving.value = true;
  modelError.value = null;
  try { await store.updateConversationProfile(conversationId, id, profile.model); }
  catch (error) { if (store.selectedConversationId === conversationId && store.selectionGeneration === generation) modelError.value = error instanceof Error ? error.message : ''; }
  finally { modelSaving.value = false; }
}
async function inheritProfile() {
  const id = store.selectedConversationId;
  const generation = store.selectionGeneration;
  await manage(async () => {
    const profile = await aiDevelopmentApi.defaultProfile();
    if (id !== store.selectedConversationId || generation !== store.selectionGeneration) return;
    if (!profile) throw new Error('没有默认档案');
    profiles.value = [...profiles.value.filter((item) => item.id !== profile.id), profile];
    await selectProfile(profile.id);
  });
}
async function profileOperation(operation: () => Promise<void>) {
  if (profileBusy.value) return;
  profileBusy.value = true;
  profileError.value = ''; profileNotice.value = '';
  const generation = profileGeneration;
  try { await operation(); }
  catch (error) { if (generation === profileGeneration && error !== 'cancel' && error !== 'close') profileError.value = t('aiDevelopment.errors.generic'); }
  finally { profileBusy.value = false; }
}
async function saveProfile(payload: AiProfileInput, id: number | null) {
  const generation = profileGeneration;
  await profileOperation(async () => {
    const result = id === null ? await aiDevelopmentApi.createProfile(payload) : await aiDevelopmentApi.updateProfile(id, payload);
    profiles.value = [...profiles.value.filter((item) => item.id !== result.id), result];
    if (generation === profileGeneration && providerOpen.value) {
      savedProfile.value = result;
      profileNotice.value = t('aiDevelopment.profiles.saved');
    }
  });
}
async function copyProfile(id: number) {
  const generation = profileGeneration;
  await profileOperation(async () => {
    const name = await askName('title');
    const result = await aiDevelopmentApi.copyProfile(id, name);
    profiles.value.push(result);
    if (generation === profileGeneration && providerOpen.value) savedProfile.value = result;
  });
}
async function removeProfile(id: number) {
  await profileOperation(async () => {
    await confirmRemoval('deleteConfirm');
    await aiDevelopmentApi.deleteProfile(id);
    profiles.value = profiles.value.filter((item) => item.id !== id);
    providerOpen.value = false; savedProfile.value = null;
  });
}
async function defaultProfile(id: number) {
  await profileOperation(async () => {
    await aiDevelopmentApi.makeDefaultProfile(id);
    profiles.value = profiles.value.map((item) => ({ ...item, is_default: item.id === id }));
  });
}
async function fetchProfileModels(id: number) {
  const generation = profileGeneration;
  await profileOperation(async () => {
    const result = await aiDevelopmentApi.profileModels(id);
    if (generation !== profileGeneration) return;
    profileModels.value = result;
    if (!result.length) profileNotice.value = t('aiDevelopment.profiles.emptyModels');
  });
}
const selectedConversation = computed(() => store.conversations.find((item) => item.id === store.selectedConversationId));
const selectedProfile = computed(() => profiles.value.find(p => p.id === selectedConversation.value?.profile_id));
const effectiveReasoning = computed(() => selectedConversation.value?.reasoning_effort ?? selectedProfile.value?.reasoning_effort ?? null);
const legalReasoningEfforts = computed(() => (['low', 'medium', 'high'] as AiReasoningEffort[]).filter(reasoning_effort => selectedProfile.value && !profileCapabilityError({ ...selectedProfile.value, model: selectedConversation.value?.model || selectedProfile.value.model, reasoning_effort })));
async function selectReasoning(event: Event) {
  const control = event.target as HTMLSelectElement;
  const value = control.value;
  control.value = selectedConversation.value?.reasoning_effort ?? '';
  const id = selectedConversation.value?.id;
  const profile = selectedProfile.value;
  if (!id || !profile || !profile.enabled || modelSaving.value) return;
  const effort = value === '' ? null : value as AiReasoningEffort;
  const error = profileCapabilityError({ ...profile, model: selectedConversation.value!.model, reasoning_effort: effort ?? profile.reasoning_effort });
  if (error) { modelError.value = error; return; }
  const generation = store.selectionGeneration;
  modelSaving.value = true;
  modelError.value = null;
  try { await store.updateConversationReasoning(id, effort); }
  catch (error) { if (store.selectedConversationId === id && store.selectionGeneration === generation) modelError.value = error instanceof Error ? error.message : ''; }
  finally { modelSaving.value = false; }
}
const modelDraft = ref('');
const modelSaving = ref(false);
const modelError = ref<string | null>(null);
watch([() => store.selectedConversationId, () => store.selectionGeneration, () => selectedConversation.value?.model], () => {
  modelDraft.value = selectedConversation.value?.model || '';
  modelError.value = null;
}, { immediate: true });

async function saveModel() {
  const id = selectedConversation.value?.id;
  const model = modelDraft.value.trim();
  if (id === undefined || !model || modelSaving.value) return;
  if (selectedProfile.value) {
    const error = profileCapabilityError({ ...selectedProfile.value, model, reasoning_effort: effectiveReasoning.value });
    if (error) { modelError.value = error; return; }
  }
  const generation = store.selectionGeneration;
  modelSaving.value = true;
  modelError.value = null;
  try {
    await store.updateConversationModel(id, model);
  } catch (error) {
    if (store.selectedConversationId === id && store.selectionGeneration === generation) {
      modelError.value = error instanceof Error ? error.message : '';
    }
  } finally {
    modelSaving.value = false;
  }
}
const pendingApprovals = computed(() => store.approvals.filter((item) => item.status === 'pending'));
const running = computed(() => store.activeTask?.status === 'running' || store.activeTask?.status === 'paused');
const hasCapability = (capability: string) => userStore.permissions.some((item) => item === '*' || item === '*:*:*' || item === capability);
const regionVisible = (region: string) => isMobile.value ? mobileTab.value === region : region !== 'context' || inspectorOpen.value;
const statusLabel = (status: string) => aiEnumLabel(t, 'statuses', status);
const toggleInspector = () => { inspectorOpen.value = !inspectorOpen.value; };
const updateViewport = (event: MediaQueryListEvent | MediaQueryList) => { isMobile.value = event.matches; };

const ContextPanel = defineComponent({
  name: 'AiContextPanel',
  setup() {
    const mode = computed({
      get: () => selectedConversation.value?.approval_mode || 'request_approval',
      set: (value: AiApprovalMode) => updateApprovalMode(value)
    });
    return () => h('div', { class: 'context-panel' }, [
      h('h3', t('aiDevelopment.taskAndPermissions')),
      h(ApprovalModeSelector, { modelValue: mode.value, canAgentApprove: hasCapability('development:ai:approve'), canFullAccess: hasCapability('development:ai:full-access'), 'onUpdate:modelValue': (value: AiApprovalMode) => { mode.value = value; } }),
      store.activeTask ? h(ElDescriptions, { column: 1, border: true, size: 'small' }, () => [
        h(ElDescriptionsItem, { label: t('aiDevelopment.task') }, () => `#${store.activeTask?.id} ${aiEnumLabel(t, 'taskTypes', store.activeTask?.type || 'unknown')}`),
        h(ElDescriptionsItem, { label: t('aiDevelopment.stage') }, () => aiEnumLabel(t, 'taskStages', store.activeTask?.stage || 'unknown')),
        h(ElDescriptionsItem, { label: t('aiDevelopment.status') }, () => h(ElTag, {}, () => statusLabel(store.activeTask?.status || 'unknown'))),
        h(ElDescriptionsItem, { label: t('aiDevelopment.test') }, () => store.activeTask?.test_result ? JSON.stringify(store.activeTask.test_result) : '-'),
        h(ElDescriptionsItem, { label: t('aiDevelopment.risk') }, () => pendingApprovals.value.map((item) => item.risk_reason).join('；') || t('aiDevelopment.noPendingRisk'))
      ]) : h('p', { class: 'empty-context' }, t('aiDevelopment.noActiveTask')),
      store.changeSet ? h('button', { class: 'changeset-link', onClick: () => { changeSetOpen.value = true; void ensurePreview(); } }, `${t('aiDevelopment.changeSet.entity')} #${store.changeSet.id} · ${aiEnumLabel(t, 'changeSetStatuses', store.changeSet.status)}`) : null
    ]);
  }
});

async function manage(operation: () => Promise<void>) {
  try { await operation(); } catch (error) {
    if (error !== 'cancel' && error !== 'close') ElMessage.error(t('aiDevelopment.management.failed'));
  }
}

async function askName(key: 'groupName' | 'title', value = '') {
  const result = await ElMessageBox.prompt(t(`aiDevelopment.management.${key}`), t('aiDevelopment.management.rename'), {
    inputValue: value,
    inputValidator: (input) => Boolean(input?.trim()) || t('aiDevelopment.management.required'),
    confirmButtonText: t('aiDevelopment.management.confirm'), cancelButtonText: t('aiDevelopment.management.cancel')
  });
  return result.value.trim();
}

async function confirmRemoval(key: 'deleteGroupConfirm' | 'deleteConfirm') {
  await ElMessageBox.confirm(t(`aiDevelopment.management.${key}`), t('aiDevelopment.management.delete'), {
    type: 'warning', confirmButtonText: t('aiDevelopment.management.confirm'), cancelButtonText: t('aiDevelopment.management.cancel')
  });
}

async function createConversationGroup() {
  await manage(async () => {
    const name = await askName('groupName');
    const group = await aiDevelopmentApi.createConversationGroup(name);
    store.conversationGroups.push(group);
    showArchived.value = false;
  });
}

async function renameConversationGroup(id: number) {
  await manage(async () => {
    const current = store.conversationGroups.find((group) => group.id === id);
    if (!current) return;
    const name = await askName('groupName', current.name);
    const group = await aiDevelopmentApi.updateConversationGroup(id, name);
    Object.assign(current, group);
  });
}

async function deleteConversationGroup(id: number) {
  await manage(async () => {
    await confirmRemoval('deleteGroupConfirm');
    await store.deleteConversationGroup(id);
  });
}

async function conversationAction(command: string, id: number) {
  await manage(async () => {
    const current = store.conversations.find((item) => item.id === id);
    if (!current) return;
    if (command === 'rename') {
      const title = await askName('title', current.title);
      const updated = await aiDevelopmentApi.updateConversation(id, { title });
      current.title = updated.title;
    } else if (command === 'move') {
      moveConversationId.value = id;
      moveGroupId.value = current.group_id ?? 0;
      moveOpen.value = true;
    } else if (command === 'delete') {
      await confirmRemoval('deleteConfirm');
      await store.deleteConversation(id);
    } else if (command === 'archive' || command === 'restore') {
      await store.updateConversationState(id, { is_archived: command === 'archive' });
    } else if (command === 'read' || command === 'unread') {
      await store.updateConversationState(id, { is_unread: command === 'unread' });
    }
  });
}

async function confirmMove() {
  if (moveConversationId.value === null || moving.value) return;
  const id = moveConversationId.value;
  moving.value = true;
  await manage(async () => {
    await store.updateConversationState(id, { group_id: moveGroupId.value || null });
    moveOpen.value = false;
  });
  moving.value = false;
}

watch(() => store.selectedConversationId, () => {
  preview.value = null;
  changeSetOpen.value = false;
  logOpen.value = false;
  toolLog.value = '';
});

async function createConversation() {
  await manage(async () => {
    const profile = await aiDevelopmentApi.defaultProfile();
    const conversation = await aiDevelopmentApi.createConversation({ title: t('aiDevelopment.newConversationTitle'), approval_mode: 'request_approval', ...(profile ? { profile_id: profile.id } : {}) });
    store.conversations.unshift(conversation);
    showArchived.value = false;
    await selectConversation(conversation.id);
  });
}

async function selectConversation(id: number) {
  await manage(async () => {
    await store.selectConversation(id);
    if (isMobile.value) mobileTab.value = 'workspace';
  });
}

const approvalSaving = ref(false);
async function updateApprovalMode(mode: AiApprovalMode) {
  const conversation = selectedConversation.value;
  if (!conversation || approvalSaving.value || mode === conversation.approval_mode) return;
  if ((mode === 'agent_approval' && !hasCapability('development:ai:approve')) || (mode === 'full_access' && !hasCapability('development:ai:full-access'))) return;
  const generation = store.selectionGeneration;
  approvalSaving.value = true;
  try {
    const levels = { request_approval: 0, agent_approval: 1, full_access: 2 };
    if (levels[mode] > levels[conversation.approval_mode]) await ElMessageBox.confirm(t(mode === 'full_access' ? 'aiDevelopment.approvalModes.fullAccessBoundary' : 'aiDevelopment.approvalModes.agentBoundary'), t('aiComposer.approval'), { type: 'warning' });
    if (store.selectedConversationId !== conversation.id || store.selectionGeneration !== generation) return;
    const updated = await aiDevelopmentApi.updateConversation(conversation.id, { approval_mode: mode });
    if (store.selectedConversationId === conversation.id && store.selectionGeneration === generation) Object.assign(conversation, updated);
  } catch (error) {
    if (error !== 'cancel' && error !== 'close') ElMessage.error(t('aiDevelopment.errors.approvalModeUpdate'));
  } finally { approvalSaving.value = false; }
}

async function messageSent(task: AiTask, message: AiMessage, id: number) {
  if (store.selectedConversationId !== id) return;
  const generation = store.selectionGeneration;
  if (!store.messages.some(item => item.id === message.id)) store.messages.push(message);
  store.activateTask(task);
  await manage(async () => {
    await store.refreshTaskContext();
    if (store.selectedConversationId !== id || generation !== store.selectionGeneration) return;
    await store.connectEvents();
    store.saveRouteState();
  });
}

async function ensurePreview() {
  if (!store.changeSet) return;
  preview.value = await aiDevelopmentApi.previewChangeSet(store.changeSet.id, store.changeSet.selection || []);
}
async function previewChangeSet(selection: string[]) { if (store.changeSet) preview.value = await aiDevelopmentApi.previewChangeSet(store.changeSet.id, selection); }
async function applyChangeSet(confirmToken: string, selection: string[]) {
  if (!store.changeSet || preview.value?.blocked) return;
  await ElMessageBox.confirm(t('aiDevelopment.changeSet.applyConfirm'), t('aiDevelopment.changeSet.applyConfirmTitle'), { type: 'warning', confirmButtonText: t('aiDevelopment.changeSet.applyConfirmButton') });
  try {
    await store.applyChangeSet(confirmToken, selection);
  } catch {
    ElMessage.error(t('aiDevelopment.errors.applyUnavailable'));
    return;
  }
  changeSetOpen.value = false;
  ElMessage.success(t('aiDevelopment.changeSet.applied'));
}
async function openToolLog(id: number, stream: 'stdout' | 'stderr') { toolLog.value = (await aiDevelopmentApi.toolLog(id, stream)).content; logOpen.value = true; }
async function openProviderSettings() { await loadProfiles(); providerOpen.value = true; }
async function testProvider(payload: Record<string, unknown>) {
  const generation = profileGeneration;
  await profileOperation(async () => {
    const result = await aiDevelopmentApi.testSettings(payload);
    if (!result.reachable) throw new Error('连接失败');
    if (generation === profileGeneration) profileNotice.value = t('aiDevelopment.providerSettings.testSuccess');
  });
}

onMounted(async () => {
  mobileQuery?.addEventListener('change', updateViewport);
  void loadProfiles();
  await manage(async () => {
    await store.restoreRouteState();
    if (store.activeTask) { await store.refreshTaskContext(); await store.connectEvents(); }
  });
});
onUnmounted(() => mobileQuery?.removeEventListener('change', updateViewport));
onBeforeUnmount(() => { store.closeEvents(); store.selectionGeneration += 1; });
</script>

<style scoped>
.ai-page { height: 100%; min-height: 0; }
.ai-page :deep(> main > div:last-child) { overflow: hidden; }
header small { color: var(--el-text-color-secondary); }
.ai-layout { display: grid; grid-template-columns: minmax(220px, 260px) minmax(0, 1fr); height: 100%; min-height: 0; border: 1px solid var(--el-border-color-lighter); border-radius: 12px; overflow: hidden; background: var(--el-bg-color); }
.ai-layout--inspector { grid-template-columns: minmax(220px, 260px) minmax(380px, 1fr) minmax(270px, 330px); }
.ai-conversations-pane, .ai-context-pane { min-width: 0; min-height: 0; overflow: auto; background: var(--el-fill-color-extra-light); }
.ai-conversations-pane { border-right: 1px solid var(--el-border-color-lighter); }
.ai-context-pane { border-left: 1px solid var(--el-border-color-lighter); }
.ai-workspace-pane { display: grid; min-width: 0; min-height: 0; grid-template-rows: auto minmax(0, 1fr) auto; }
.workspace-header { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; border-bottom: 1px solid var(--el-border-color-lighter); }
.workspace-header > div:first-child { display: grid; gap: 2px; }.workspace-actions { display: flex; gap: 8px; }.workspace-scroll { min-height: 0; overflow: auto; }.composer { display: grid; grid-template-columns: 1fr auto; align-items: end; gap: 10px; padding: 12px; border-top: 1px solid var(--el-border-color-lighter); }
.model-form { flex: 1 0 100%; min-width: 0; display: grid; gap: 6px; }
.model-controls { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.model-controls .el-input { flex: 1 1 180px; min-width: 0; }
.model-error { margin: 0; color: var(--el-color-danger); overflow-wrap: anywhere; }
.context-panel { display: grid; gap: 14px; padding: 16px; }.context-panel h3 { margin: 0; }.empty-context { color: var(--el-text-color-secondary); }.changeset-link { border: 1px solid var(--el-color-primary-light-5); border-radius: 8px; padding: 10px; background: var(--el-color-primary-light-9); color: var(--el-color-primary); cursor: pointer; }.tool-log { overflow: auto; max-height: 60vh; white-space: pre-wrap; }.mobile-actions { display: none; }
@media (max-width: 1024px) { .mobile-actions { display: flex; gap: 8px; padding-bottom: 10px; }.ai-layout { display: block; min-height: 0; }.ai-conversations-pane { height: 100%; border-right: 0; }.ai-context-pane { height: 100%; border-left: 0; }.ai-workspace-pane { height: 100%; }.workspace-actions [data-testid="toggle-inspector"] { display: none; } }
@media (max-width: 680px) { .composer { grid-template-columns: 1fr; } }
</style>
