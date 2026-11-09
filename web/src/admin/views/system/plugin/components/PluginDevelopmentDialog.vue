<template>
  <el-dialog v-model="visible" :title="t('plugin.devTitle', '插件开发工具')" width="820px" class="plugin-dev-dialog" destroy-on-close @open="loadOptions">
    <el-tabs v-model="mode">
      <el-tab-pane :label="t('plugin.create', '创建插件')" name="create">
        <el-form :model="createForm" label-position="top" class="create-form">
          <div class="field-row">
            <el-form-item :label="t('plugin.devFieldName', '插件标识')"><el-input v-model="createForm.name" :placeholder="t('plugin.devNamePlaceholder', '小写字母开头，仅字母和数字')" /></el-form-item>
            <el-form-item :label="t('plugin.devFieldTitle', '插件名称')"><el-input v-model="createForm.title" /></el-form-item>
          </div>
          <el-form-item :label="t('plugin.devModules', '生成内容')">
            <div class="module-grid">
              <el-checkbox v-for="item in modules" :key="item.key" v-model="createForm[item.key]" border class="module-card">
                <span class="module-card__title">{{ item.title }}</span>
                <span class="module-card__desc">{{ item.desc }}</span>
              </el-checkbox>
            </div>
          </el-form-item>
        </el-form>

        <el-alert v-if="preview?.conflicts.length" type="error" :title="t('plugin.devConflicts', { list: preview.conflicts.join(', ') }, { default: '冲突：{list}' })" :closable="false" class="mb-3" />
        <div class="plan" :class="{ 'is-empty': !groups.length }">
          <template v-if="groups.length">
            <div class="plan__summary">{{ t('plugin.devPlanSummary', { target: preview?.plan?.target || `plugins/${createForm.name}`, count: fileCount }, { default: '将在 {target} 生成 {count} 个文件' }) }}</div>
            <div class="plan__body">
              <section v-for="group in groups" :key="group.key" class="plan__group">
                <header class="plan__group-title">{{ group.title }}<span class="plan__count">{{ group.files.length }}</span></header>
                <div v-for="file in group.files" :key="file.path" class="plan__file">
                  <span class="plan__path" :title="file.path">{{ file.label }}</span>
                  <el-tag size="small" :type="statusType(file.status)" disable-transitions>{{ fileStatusLabel(file.status) }}</el-tag>
                </div>
              </section>
            </div>
          </template>
          <span v-else class="plan__empty">{{ stale ? t('plugin.devPlanStale', '表单已修改，请重新预览') : t('plugin.devPlanEmpty', '填写插件标识后点击“预览”查看将生成的文件') }}</span>
        </div>
        <div class="actions"><el-button v-perm="'plugin:dev-plugin:preview-create'" :loading="loading" @click="previewPlugin">{{ t('common.preview', '预览') }}</el-button><el-button v-perm="'plugin:dev-plugin:create'" type="primary" :loading="loading" :disabled="!preview || preview.conflicts.length > 0" @click="createPlugin">{{ t('plugin.devConfirmCreate', '确认创建') }}</el-button></div>
      </el-tab-pane>
      <el-tab-pane :label="t('plugin.devTabMaintain', '校验/打包')" name="maintain">
        <el-form label-width="110px"><el-form-item :label="t('plugin.devFieldPlugin', '插件')"><el-select v-model="selectedCode" class="w-full"><el-option v-for="item in plugins" :key="item.code" :label="`${item.name} (${item.code})`" :value="item.code" /></el-select></el-form-item></el-form>
        <el-descriptions v-if="result" :column="1" border><el-descriptions-item :label="t('plugin.devAuditId', '审计编号')">{{ result.auditId }}</el-descriptions-item><el-descriptions-item v-if="result.valid" :label="t('plugin.devValidateLabel', '校验')">{{ t('plugin.devManifestValid', '清单协议 v2 有效') }}</el-descriptions-item><el-descriptions-item v-if="result.downloadPath" :label="t('plugin.devDownloadPath', '下载路径')">{{ result.downloadPath }}</el-descriptions-item><el-descriptions-item v-if="result.sha256" :label="t('plugin.devSha256', 'SHA-256 摘要')">{{ result.sha256 }}</el-descriptions-item></el-descriptions>
        <div class="actions"><el-button v-perm="'plugin:dev-plugin:validate'" :loading="loading" @click="validatePlugin">{{ t('plugin.validate', '校验插件') }}</el-button><el-button v-perm="'plugin:dev-plugin:package'" type="primary" :loading="loading" @click="packagePlugin">{{ t('plugin.package', '打包插件') }}</el-button><el-button v-if="result?.downloadUrl" v-perm="'plugin:dev-plugin:package-download'" tag="a" :href="result.downloadUrl">{{ t('plugin.devDownloadPackage', '下载插件包') }}</el-button></div>
      </el-tab-pane>
    </el-tabs>
    <el-alert v-if="error" type="error" :title="error" :closable="false" class="mt-3" />
  </el-dialog>
</template>

<script setup lang="ts">
import { hasPermission } from '@/utils/permission';
import { computed, reactive, ref, watch } from 'vue';
import { ElMessage } from 'element-plus';
import { useI18n } from 'vue-i18n';
import { pluginDevelopmentApi, type DevelopmentPluginOption, type PluginCreateInput, type PluginDevelopmentPlan, type PluginDevelopmentResult } from '@/api/development/plugin';
import { formatErrorMessage } from '@/utils/errorMessage';

type PlanFile = { path: string; status?: string };
type ModuleKey = 'application' | 'admin' | 'adminWeb';

const { t } = useI18n();
const visible = defineModel<boolean>({ default: false });
const props = withDefaults(defineProps<{ initialMode?: 'create' | 'maintain' }>(), { initialMode: 'create' });
const emit = defineEmits<{ changed: [pluginCode?: string] }>();
const mode = ref<'create' | 'maintain'>('create');
const loading = ref(false);
const error = ref('');
const plugins = ref<DevelopmentPluginOption[]>([]);
const selectedCode = ref('');
const preview = ref<PluginDevelopmentResult | null>(null);
const stale = ref(false);
const result = ref<PluginDevelopmentResult | null>(null);
const createForm = reactive<PluginCreateInput>({ name: '', title: '', application: true, admin: true, adminWeb: true });
const message = (value: unknown) => formatErrorMessage(value, t('http.requestFailed', '请求失败'));
const planFiles = (files: PluginDevelopmentPlan['files']): PlanFile[] => files.map((file) => typeof file === 'string' ? { path: file } : file);
const fileStatusLabel = (status?: string) => ({ create: t('plugin.devFileCreate', '新建'), unchanged: t('plugin.devFileUnchanged', '未变化'), overwrite: t('plugin.devFileOverwrite', '覆盖'), conflict: t('plugin.devFileConflict', '冲突') }[status || ''] || t('plugin.devFilePending', '待处理'));
const statusType = (status?: string) => ({ create: 'success', unchanged: 'info', overwrite: 'warning', conflict: 'danger' } as const)[status as 'create'] ?? 'info';

const modules = computed<Array<{ key: ModuleKey; title: string; desc: string }>>(() => [
  { key: 'application', title: t('plugin.application.app', '独立应用'), desc: t('plugin.devModuleAppDesc', '前台路由、控制器与语言包') },
  { key: 'admin', title: t('plugin.application.admin', '管理后台'), desc: t('plugin.devModuleAdminDesc', '后台接口控制器（按方法登记权限）') },
  { key: 'adminWeb', title: t('plugin.application.adminWeb', '管理前端'), desc: t('plugin.devModuleAdminWebDesc', '后台管理页面与接口封装') }
]);

/** 预览文件按插件内的位置分组，组内路径去掉公共前缀。 */
const groups = computed(() => {
  const plan = preview.value?.plan;
  if (!plan) return [];
  const target = (plan.target || `plugins/${createForm.name}`).replace(/\/+$/, '');
  const code = target.split('/').pop() || createForm.name;
  const sections = [
    { key: 'application', title: t('plugin.application.app', '独立应用'), prefix: `app/${code}/` },
    { key: 'admin', title: t('plugin.application.admin', '管理后台'), prefix: `app/admin/plugins/${code}/` },
    { key: 'adminWeb', title: t('plugin.application.adminWeb', '管理前端'), prefix: `web/src/admin/plugins/${code}/` }
  ];
  const buckets = new Map<string, Array<PlanFile & { label: string }>>();
  for (const file of planFiles(plan.files)) {
    const relative = file.path.startsWith(`${target}/`) ? file.path.slice(target.length + 1) : file.path;
    const section = sections.find((item) => relative.startsWith(item.prefix));
    const key = section?.key ?? 'root';
    buckets.set(key, [...(buckets.get(key) ?? []), { ...file, label: section ? relative.slice(section.prefix.length) : relative }]);
  }
  return [{ key: 'root', title: t('plugin.devGroupRoot', '插件根目录') }, ...sections]
    .filter((section) => buckets.has(section.key))
    .map((section) => ({ key: section.key, title: section.title, files: buckets.get(section.key)! }));
});
const fileCount = computed(() => groups.value.reduce((total, group) => total + group.files.length, 0));

// 表单变化后旧预览不再可信：清空并要求重新预览，避免按过期计划创建。
watch(() => ({ ...createForm }), () => { if (preview.value) { preview.value = null; stale.value = true; } });

async function run(operation: () => Promise<void>) { error.value = ''; loading.value = true; try { await operation(); } catch (reason) { error.value = message(reason); } finally { loading.value = false; } }
async function loadOptions() { mode.value = props.initialMode; if (!hasPermission('plugin:dev-plugin:options')) return; await run(async () => { plugins.value = await pluginDevelopmentApi.options(); selectedCode.value ||= plugins.value[0]?.code || ''; }); }
async function previewPlugin() { if (!(hasPermission('plugin:dev-plugin:preview-create'))) return; await run(async () => { preview.value = await pluginDevelopmentApi.previewCreate({ ...createForm }); stale.value = false; }); }
async function createPlugin() { if (!(hasPermission('plugin:dev-plugin:create'))) return; await run(async () => { result.value = await pluginDevelopmentApi.create({ ...createForm }); ElMessage.success(t('plugin.devCreateSuccess', { id: result.value.auditId }, { default: '插件已创建，审计编号：{id}' })); const pluginCode = result.value.plugin?.code || createForm.name; preview.value = null; stale.value = false; await loadOptions(); emit('changed', pluginCode); }); }
async function validatePlugin() { if (!(hasPermission('plugin:dev-plugin:validate'))) return; if (!selectedCode.value) return; await run(async () => { result.value = await pluginDevelopmentApi.validate(selectedCode.value); ElMessage.success(t('plugin.devValidateSuccess', '插件校验通过')); }); }
async function packagePlugin() { if (!(hasPermission('plugin:dev-plugin:package'))) return; if (!selectedCode.value) return; await run(async () => { result.value = await pluginDevelopmentApi.package(selectedCode.value); ElMessage.success(t('plugin.devPackageSuccess', '插件打包完成')); }); }
</script>

<style scoped>
.create-form :deep(.el-form-item) { margin-bottom: 14px; }
.create-form :deep(.el-form-item__label) { padding-bottom: 4px; line-height: 20px; font-weight: 500; }
.field-row { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
.module-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; width: 100%; }
.module-grid .module-card.el-checkbox { display: flex; align-items: flex-start; height: auto; margin: 0; padding: 10px 12px; border-radius: 8px; white-space: normal; }
.module-grid .module-card.el-checkbox.is-checked { background: var(--el-color-primary-light-9); }
.module-card :deep(.el-checkbox__input) { margin-top: 3px; }
.module-card :deep(.el-checkbox__label) { display: flex; flex-direction: column; gap: 2px; min-width: 0; padding-left: 8px; }
.module-card__title { font-size: 14px; font-weight: 500; line-height: 20px; color: var(--el-text-color-primary); }
.module-card__desc { font-size: 12px; font-weight: 400; line-height: 18px; color: var(--el-text-color-secondary); }
.plan { border: 1px solid var(--el-border-color-lighter); border-radius: 8px; background: var(--el-fill-color-blank); }
.plan.is-empty { display: flex; align-items: center; justify-content: center; min-height: 96px; }
.plan__empty { font-size: 13px; color: var(--el-text-color-secondary); }
.plan__summary { padding: 10px 14px; border-bottom: 1px solid var(--el-border-color-lighter); font-size: 13px; color: var(--el-text-color-regular); }
.plan__body { max-height: 320px; overflow-y: auto; }
.plan__group-title { position: sticky; top: 0; z-index: 1; display: flex; align-items: center; gap: 6px; padding: 6px 14px; background: var(--el-fill-color-light); font-size: 12px; font-weight: 500; color: var(--el-text-color-regular); }
.plan__count { padding: 0 6px; border-radius: 9px; background: var(--el-fill-color-darker); font-size: 11px; line-height: 16px; color: var(--el-text-color-secondary); }
.plan__file { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 6px 14px 6px 24px; border-bottom: 1px solid var(--el-border-color-extra-light); }
.plan__file:last-child { border-bottom: 0; }
.plan__path { min-width: 0; overflow: hidden; font-family: var(--el-font-family-mono, ui-monospace, SFMono-Regular, Menlo, monospace); font-size: 12.5px; text-overflow: ellipsis; white-space: nowrap; color: var(--el-text-color-primary); }
.actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 16px; }
@media (max-width: 720px) {
  .field-row, .module-grid { grid-template-columns: minmax(0, 1fr); }
}
</style>
