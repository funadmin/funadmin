<template>
  <PageWrapper title="表单设计器" subtitle="拖拽控件到画布；右侧编辑字段参数；created 表保存前需应用守卫式迁移">
    <template #extra>
      <div class="flex flex-wrap items-center gap-2">
        <el-button :disabled="!store.canUndo.value" @click="store.undo()">撤销</el-button>
        <el-button :disabled="!store.canRedo.value" @click="store.redo()">重做</el-button>
        <el-button v-if="store.form.value.source_type === 'adopted'" @click="inferVisible = true">重新推断</el-button>
        <el-button v-if="store.form.value.source_type === 'created'" @click="onPreview">迁移预览</el-button>
        <el-button :loading="saving" @click="onSave">保存草稿</el-button>
        <el-button type="primary" @click="openPublish">发布</el-button>
      </div>
    </template>

    <el-card shadow="never" class="mb-3">
      <template #header>表单基本信息</template>
      <el-form label-width="90px" class="designer-meta-form">
        <el-form-item label="表单名称" required>
          <el-input v-model="store.form.value.name" maxlength="100" placeholder="如：活动报名" />
        </el-form-item>
        <el-form-item label="表单标识" required>
          <el-input
            v-model="store.form.value.form_key"
            maxlength="61"
            placeholder="如 activity_form"
            @blur="normalizeFormKey"
          />
          <div class="form-tip">用于接口和数据页地址，以小写字母开头，只能包含小写字母、数字和下划线。</div>
        </el-form-item>
        <el-form-item label="来源" required>
          <el-radio-group v-model="store.form.value.source_type">
            <el-radio-button value="created">创建新表</el-radio-button>
            <el-radio-button value="adopted">采纳已有表</el-radio-button>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="绑定表" required>
          <el-input v-model="store.form.value.table_name" placeholder="如 fun_activity" @blur="normalizeFormKey" />
        </el-form-item>
      </el-form>
    </el-card>

    <div class="designer-layout flex gap-3">
      <!-- 左：控件 palette -->
      <el-card shadow="never" class="w-[230px] shrink-0">
        <template #header>控件（{{ CONTROL_REGISTRY.length }}）</template>
        <div ref="paletteRef" class="palette-list flex max-h-[calc(100vh-250px)] flex-col gap-2 overflow-y-auto pr-1">
          <template v-for="group in controlGroups" :key="group">
            <div class="sticky top-0 z-10 bg-[var(--el-bg-color-overlay)] py-1 text-xs font-semibold text-[var(--el-text-color-secondary)]">
              {{ group }}
            </div>
            <div
              v-for="control in controlsOf(group)"
              :key="control.type"
              class="palette-item cursor-grab rounded border border-[var(--el-border-color)] px-2 py-1.5 text-sm"
              :data-type="control.type"
            >
              {{ control.label }}
            </div>
          </template>
        </div>
      </el-card>

      <!-- 中：画布 -->
      <el-card shadow="never" class="min-w-0 flex-1">
        <template #header>
          <div class="flex items-center justify-between">
            <span>画布（{{ store.fields.value.length }} 字段）</span>
            <span class="text-xs text-[var(--el-text-color-secondary)]">{{ store.form.value.name || '未命名' }} → {{ store.form.value.table_name }}</span>
          </div>
        </template>
        <div ref="canvasRef" class="designer-canvas flex flex-col gap-2">
          <div
            v-for="(field, index) in store.fields.value"
            :key="field.field_name"
            class="canvas-item cursor-pointer rounded border px-3 py-2"
            :class="store.selectedKey.value === field.field_name ? 'border-[var(--el-color-primary)] bg-[var(--el-color-primary-light-9)]' : 'border-[var(--el-border-color)]'"
            @click="store.selectedKey.value = field.field_name"
          >
            <div class="mb-1 flex items-center justify-between text-xs text-[var(--el-text-color-secondary)]">
              <span>{{ field.field_name }} · {{ controlMeta(field.type).label }} · span {{ field.form_span }}</span>
              <span class="flex gap-1">
                <el-button link size="small" @click.stop="store.duplicateField(field.field_name)">复制</el-button>
                <el-button link size="small" type="danger" @click.stop="store.removeField(field.field_name)">删除</el-button>
              </span>
            </div>
            <div class="flex items-center gap-2">
              <span v-if="controlMeta(field.type).kind !== 'layout' && field.type !== 'hidden'" class="w-[110px] shrink-0 text-right text-sm">{{ field.label }}</span>
              <div class="min-w-0 flex-1" @click.stop>
                <FormControlRenderer
                  :field="field"
                  :model-value="field.default_value"
                  :options="previewOptions(field)"
                  disabled
                  preview
                />
              </div>
            </div>
            <div class="mt-1 text-right text-xs text-[var(--el-text-color-placeholder)]">#{{ index + 1 }}</div>
          </div>
          <el-empty v-if="!store.fields.value.length" description="从左侧拖入控件开始设计" />
        </div>
      </el-card>

      <!-- 右：属性面板 -->
      <el-card shadow="never" class="w-[360px] shrink-0">
        <template #header>字段属性</template>
        <PropsPanel v-if="store.selected.value" :field="store.selected.value" :source-type="store.form.value.source_type ?? 'created'" @update="store.updateField" />
        <el-empty v-else description="点选画布字段编辑参数" />
      </el-card>
    </div>

    <el-dialog v-model="publishVisible" title="发布表单" width="900px" :close-on-click-modal="false">
      <el-steps :active="publishStep" finish-status="success" align-center class="mb-5">
        <el-step title="发布设置" />
        <el-step title="变更预览" />
        <el-step title="冲突确认" />
        <el-step title="发布结果" />
      </el-steps>

      <el-form v-if="publishStep === 0" :model="publishConfig" label-width="110px" class="publish-config-grid">
        <el-form-item label="模块名"><el-input v-model="publishConfig.module" /></el-form-item>
        <el-form-item label="API 前缀"><el-input v-model="publishConfig.apiPrefix" /></el-form-item>
        <el-form-item label="页面路由"><el-input v-model="publishConfig.routePath" /></el-form-item>
        <el-form-item label="菜单名称"><el-input v-model="publishConfig.menuName" /></el-form-item>
        <el-form-item label="父级菜单">
          <el-tree-select v-model="publishConfig.parentSourceName" :data="parentMenus" node-key="sourceName" :props="menuTreeProps" check-strictly clearable class="w-full" />
        </el-form-item>
        <el-form-item label="菜单图标"><el-select v-model="publishConfig.icon" filterable class="w-full"><el-option v-for="icon in icons" :key="icon" :label="icon" :value="icon" /></el-select></el-form-item>
        <el-form-item label="表单容器"><el-radio-group v-model="publishConfig.formMode"><el-radio-button value="dialog">弹窗</el-radio-button><el-radio-button value="drawer">抽屉</el-radio-button></el-radio-group></el-form-item>
        <el-form-item label="完整功能"><el-checkbox v-model="publishConfig.batchDelete">批量删除</el-checkbox><el-checkbox v-model="publishConfig.import">导入</el-checkbox><el-checkbox v-model="publishConfig.export">导出</el-checkbox><el-checkbox v-model="publishConfig.softDeletes">软删除</el-checkbox></el-form-item>
      </el-form>

      <template v-else-if="publishStep === 1">
        <el-alert :title="publishPreview?.ddl.message || '正在等待预览'" type="info" :closable="false" class="mb-3" />
        <el-collapse>
          <el-collapse-item title="数据库迁移" name="ddl"><el-input :model-value="publishPreview?.ddl.sql || '无结构变更'" type="textarea" :rows="8" readonly /></el-collapse-item>
          <el-collapse-item title="生成文件" name="files">
            <el-table :data="publishPreview?.plan.files || []" size="small" border><el-table-column prop="path" label="路径" /><el-table-column prop="status" label="状态" width="110" /></el-table>
          </el-collapse-item>
        </el-collapse>
      </template>

      <template v-else-if="publishStep === 2">
        <el-alert v-if="!conflictFiles.length" title="没有人工修改冲突，可直接发布" type="success" :closable="false" class="mb-3" />
        <el-checkbox-group v-else v-model="allowOverwrite" class="flex flex-col gap-3">
          <el-card v-for="file in conflictFiles" :key="file.path" shadow="never">
            <el-checkbox :value="file.path">允许覆盖 {{ file.path }}</el-checkbox>
            <el-input :model-value="file.diff || ''" type="textarea" :rows="7" readonly class="mt-2" />
          </el-card>
        </el-checkbox-group>
      </template>

      <el-result v-else :icon="publishResult?.publishStatus === 'published' ? 'success' : 'warning'" :title="publishResult?.publishStatus === 'published' ? '发布成功' : '发布未完全完成'" :sub-title="publishResult?.routePath || ''">
        <template #extra><el-button v-if="publishResult?.routePath" type="primary" @click="openGeneratedRoute">打开独立页面</el-button></template>
      </el-result>

      <template #footer>
        <el-button @click="publishVisible = false">关闭</el-button>
        <el-button v-if="publishStep > 0 && publishStep < 3" @click="publishStep--">上一步</el-button>
        <el-button v-if="publishStep === 0" type="primary" :loading="previewingPublish" @click="onPreviewPublish">预览发布</el-button>
        <el-button v-else-if="publishStep === 1" type="primary" @click="publishStep = 2">下一步</el-button>
        <el-button v-else-if="publishStep === 2" type="primary" :loading="publishing" @click="onPublish">确认发布</el-button>
      </template>
    </el-dialog>

    <!-- 迁移预览 -->
    <el-dialog v-model="previewVisible" title="迁移预览" width="720px">
      <el-alert :title="preview?.message ?? ''" type="info" :closable="false" class="mb-2" />
      <el-input :model-value="preview?.sql ?? ''" type="textarea" :rows="14" readonly />
      <template #footer>
        <el-button @click="previewVisible = false">关闭</el-button>
        <el-button type="primary" :loading="applying" :disabled="!preview?.sql" @click="onApply">应用迁移</el-button>
      </template>
    </el-dialog>

    <!-- 采纳推断 -->
    <el-dialog v-model="inferVisible" title="从已有表推断字段" width="480px">
      <el-form label-width="90px">
        <el-form-item label="连接">
          <el-input model-value="mysql" disabled />
        </el-form-item>
        <el-form-item label="数据表">
          <el-input v-model="inferTable" placeholder="如 fun_activity" />
        </el-form-item>
      </el-form>
      <el-alert title="推断将替换当前画布字段（可撤销）" type="warning" :closable="false" />
      <template #footer>
        <el-button @click="inferVisible = false">取消</el-button>
        <el-button type="primary" :loading="inferring" @click="onInfer">推断</el-button>
      </template>
    </el-dialog>
  </PageWrapper>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ElMessage } from 'element-plus';
import Sortable from 'sortablejs';
import {
  formDesignerApi,
  type FormPublishConfig,
  type FormPublishPreview,
  type FormPublishResult,
  type MigrationPreview
} from '@/api/form';
import { crudDevelopmentApi } from '@/api/development/crud';
import { usePermissionStore } from '@/store/modules/permission';
import { CONTROL_REGISTRY, controlMeta } from '../registry';
import { useDesigner } from '../composables/useDesigner';
import FormControlRenderer from '../components/FormControlRenderer.vue';
import PropsPanel from './components/PropsPanel.vue';

const route = useRoute();
const router = useRouter();
const permissionStore = usePermissionStore();
const store = useDesigner();
const saving = ref(false);
const applying = ref(false);
const inferring = ref(false);
const previewVisible = ref(false);
const inferVisible = ref(false);
const inferTable = ref('');
const preview = ref<MigrationPreview | null>(null);
const publishVisible = ref(false);
const publishStep = ref(0);
const previewingPublish = ref(false);
const publishing = ref(false);
const publishPreview = ref<FormPublishPreview | null>(null);
const publishResult = ref<FormPublishResult | null>(null);
const allowOverwrite = ref<string[]>([]);
const parentMenus = ref<Array<Record<string, unknown>>>([]);
const icons = ref<string[]>([]);
const menuTreeProps = { label: 'name', children: 'children', value: 'sourceName' };
const publishConfig = ref<FormPublishConfig>({
  module: 'generated', apiPrefix: '', routePath: '', menuEnabled: true, parentId: null,
  parentSourceName: '', menuName: '', icon: 'i-ep-document', sortOrder: 999,
  softDeletes: true, batchDelete: true, import: true, export: true, formMode: 'dialog'
});
const paletteRef = ref<HTMLElement>();
const canvasRef = ref<HTMLElement>();
let paletteSortable: Sortable | null = null;
let canvasSortable: Sortable | null = null;

const definition = () => ({ ...store.form.value, publish_config: publishConfig.value, fields: store.fields.value });
const conflictFiles = computed(() => publishPreview.value?.conflicts ?? []);
const controlGroups = [...new Set(CONTROL_REGISTRY.map((control) => control.group))];
const controlsOf = (group: string) => CONTROL_REGISTRY.filter((control) => control.group === group);
const previewOptions = (field: { options_source?: Record<string, unknown> | null }) => {
  const options = field.options_source?.options;
  return Array.isArray(options) ? options as Array<{ label: string; value: string | number }> : [];
};
const normalizeIdentifier = (value: string) => value
  .trim()
  .toLowerCase()
  .replace(/[\s-]+/g, '_')
  .replace(/[^a-z0-9_]/g, '')
  .replace(/^_+|_+$/g, '')
  .slice(0, 61);
const normalizeFormKey = () => {
  const current = normalizeIdentifier(String(store.form.value.form_key ?? ''));
  const fromTable = normalizeIdentifier(String(store.form.value.table_name ?? '')).replace(/^fun_/, '');
  store.form.value.form_key = current || fromTable;
};
const validateDefinitionBasics = () => {
  normalizeFormKey();
  if (!String(store.form.value.name ?? '').trim()) {
    ElMessage.warning('请填写表单名称');
    return false;
  }
  if (!/^[a-z][a-z0-9_]{0,60}$/.test(String(store.form.value.form_key ?? ''))) {
    ElMessage.warning('请填写正确的表单标识');
    return false;
  }
  if (!/^[a-z][a-z0-9_]*$/.test(String(store.form.value.table_name ?? ''))) {
    ElMessage.warning('请填写正确的绑定表名');
    return false;
  }
  return true;
};

async function load() {
  const id = Number(route.query.id ?? 0);
  if (!id) return;
  const data = await formDesignerApi.detail(id);
  store.load({ ...data.form, fields: data.fields });
  inferTable.value = data.form.table_name;
}

async function onSave() {
  if (!validateDefinitionBasics()) return;
  saving.value = true;
  try {
    const saved = await formDesignerApi.save(definition());
    store.markSaved({ ...saved.form, fields: saved.fields });
    ElMessage.success('保存成功');
  } finally {
    saving.value = false;
  }
}

async function onPreview() {
  if (!validateDefinitionBasics()) return;
  preview.value = await formDesignerApi.preview(definition());
  previewVisible.value = true;
}

async function onApply() {
  if (!validateDefinitionBasics()) return;
  applying.value = true;
  try {
    preview.value = await formDesignerApi.apply(definition());
    ElMessage.success('迁移应用成功');
  } finally {
    applying.value = false;
  }
}

const openPublish = async () => {
  if (!validateDefinitionBasics() || !store.fields.value.some((field) => controlMeta(field.type).kind !== 'layout')) return;
  const key = String(store.form.value.form_key ?? '').replace(/_/g, '-');
  publishConfig.value = {
    ...publishConfig.value,
    ...store.form.value.publish_config,
    apiPrefix: store.form.value.publish_config?.apiPrefix || `/generated/${key}`,
    routePath: store.form.value.publish_config?.routePath || `/generated/${key}`,
    menuName: store.form.value.publish_config?.menuName || String(store.form.value.name ?? key)
  };
  if (!parentMenus.value.length) {
    const options = await crudDevelopmentApi.options();
    parentMenus.value = options.parentMenus as unknown as Array<Record<string, unknown>>;
    icons.value = options.icons;
  }
  publishStep.value = 0;
  publishPreview.value = null;
  publishResult.value = null;
  allowOverwrite.value = [];
  publishVisible.value = true;
};
const validatePublishConfig = () => {
  if (!/^[a-z][a-z0-9-]*$/.test(publishConfig.value.module)) {
    ElMessage.warning('模块名须以小写字母开头，仅包含小写字母、数字和短横线');
    return false;
  }
  if (!/^\/[a-z][a-z0-9-]*(?:\/[a-z][a-z0-9-]*)*$/.test(publishConfig.value.apiPrefix)) {
    ElMessage.warning('请填写正确的 API 前缀');
    return false;
  }
  if (!/^\/[a-z][a-z0-9-]*(?:\/[a-z][a-z0-9-]*)*$/.test(publishConfig.value.routePath)) {
    ElMessage.warning('请填写正确的页面路由');
    return false;
  }
  if (!publishConfig.value.menuName.trim()) {
    ElMessage.warning('请填写菜单名称');
    return false;
  }
  return true;
};
const onPreviewPublish = async () => {
  if (!validatePublishConfig()) return;
  previewingPublish.value = true;
  try {
    publishPreview.value = await formDesignerApi.previewPublish(definition());
    publishStep.value = 1;
  } finally {
    previewingPublish.value = false;
  }
};
const onPublish = async () => {
  const token = publishPreview.value?.sensitive?.confirmToken || '';
  if (!token) {
    ElMessage.warning('发布预览已失效，请重新预览');
    publishStep.value = 0;
    return;
  }
  publishing.value = true;
  try {
    publishResult.value = await formDesignerApi.publish(definition(), token, allowOverwrite.value);
    store.markSaved({ ...publishResult.value.form, fields: store.fields.value });
    const dynamicRoutes = await permissionStore.fetchMenus();
    dynamicRoutes.forEach((dynamicRoute) => {
      if (!dynamicRoute.name || !router.hasRoute(dynamicRoute.name)) router.addRoute(dynamicRoute);
    });
    publishStep.value = 3;
    ElMessage.success(publishResult.value.publishStatus === 'published' ? '全栈发布成功' : '代码已生成，菜单权限需要重试');
  } finally {
    publishing.value = false;
  }
};
const openGeneratedRoute = () => {
  if (publishResult.value?.routePath) router.push(publishResult.value.routePath);
};

async function onInfer() {
  inferring.value = true;
  try {
    const data = await formDesignerApi.infer('mysql', inferTable.value.trim());
    store.replaceFields(data.fields);
    inferVisible.value = false;
    ElMessage.success('推断完成');
  } finally {
    inferring.value = false;
  }
}

onMounted(async () => {
  await load();
  if (paletteRef.value && canvasRef.value) {
    paletteSortable = Sortable.create(paletteRef.value, {
      group: { name: 'form-designer', pull: 'clone', put: false },
      draggable: '.palette-item',
      sort: false,
      animation: 150
    });
    canvasSortable = Sortable.create(canvasRef.value, {
      group: { name: 'form-designer', pull: false, put: true },
      animation: 150,
      onAdd: (event) => {
        const type = (event.item as HTMLElement).dataset.type ?? 'input';
        event.item.remove();
        store.addField(type);
      },
      onUpdate: (event) => store.moveField(event.oldIndex ?? 0, event.newIndex ?? 0)
    });
  }
});
onBeforeUnmount(() => {
  paletteSortable?.destroy();
  canvasSortable?.destroy();
});
</script>

<style scoped>
.publish-config-grid,
.designer-meta-form {
  display: grid;
  grid-template-columns: repeat(2, minmax(280px, 1fr));
  column-gap: 24px;
}
.form-tip {
  color: var(--el-text-color-secondary);
  font-size: 12px;
  line-height: 18px;
}
.designer-layout {
  align-items: flex-start;
}
.designer-canvas {
  min-height: max(520px, calc(100vh - 260px));
}
.palette-item:hover {
  border-color: var(--el-color-primary);
  color: var(--el-color-primary);
}
.palette-list {
  scrollbar-width: thin;
}
</style>
