<template>
  <PageWrapper title="表单设计器" subtitle="拖拽控件到画布；右侧编辑字段参数；created 表保存前需应用守卫式迁移">
    <template #extra>
      <div class="flex flex-wrap items-center gap-2">
        <el-button :disabled="!store.canUndo.value" @click="store.undo()">撤销</el-button>
        <el-button :disabled="!store.canRedo.value" @click="store.redo()">重做</el-button>
        <el-button v-if="store.form.value.source_type === 'adopted'" @click="inferVisible = true">重新推断</el-button>
        <el-button v-if="store.form.value.source_type === 'created'" @click="onPreview">迁移预览</el-button>
        <el-button type="primary" :loading="saving" @click="onSave">保存</el-button>
      </div>
    </template>

    <div class="designer-layout flex gap-3">
      <!-- 左：控件 palette -->
      <el-card shadow="never" class="w-[200px] shrink-0">
        <template #header>控件</template>
        <div ref="paletteRef" class="flex flex-col gap-2">
          <div
            v-for="control in CONTROL_REGISTRY"
            :key="control.type"
            class="palette-item cursor-grab rounded border border-[var(--el-border-color)] px-2 py-1.5 text-sm"
            :data-type="control.type"
          >
            {{ control.label }}
          </div>
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
        <div ref="canvasRef" class="flex min-h-[320px] flex-col gap-2">
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
              <span class="w-[110px] shrink-0 text-right text-sm">{{ field.label }}</span>
              <div class="min-w-0 flex-1" @click.stop>
                <el-switch v-if="field.type === 'switch'" disabled :model-value="false" />
                <el-input-number v-else-if="field.type === 'number'" disabled class="w-full" />
                <el-select v-else-if="field.type === 'select'" disabled placeholder="请选择" class="w-full" />
                <el-date-picker v-else-if="field.type === 'date'" disabled placeholder="选择日期" class="w-full" />
                <el-input v-else disabled :type="field.type === 'textarea' ? 'textarea' : 'text'" :rows="2" :placeholder="field.placeholder || field.label" />
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
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { ElMessage } from 'element-plus';
import Sortable from 'sortablejs';
import { formDesignerApi, type MigrationPreview } from '@/api/form';
import { CONTROL_REGISTRY, controlMeta } from '../registry';
import { useDesigner } from '../composables/useDesigner';
import PropsPanel from './components/PropsPanel.vue';

const route = useRoute();
const store = useDesigner();
const saving = ref(false);
const applying = ref(false);
const inferring = ref(false);
const previewVisible = ref(false);
const inferVisible = ref(false);
const inferTable = ref('');
const preview = ref<MigrationPreview | null>(null);
const paletteRef = ref<HTMLElement>();
const canvasRef = ref<HTMLElement>();
let paletteSortable: Sortable | null = null;
let canvasSortable: Sortable | null = null;

const definition = () => ({ ...store.form.value, fields: store.fields.value });

async function load() {
  const id = Number(route.query.id ?? 0);
  if (!id) return;
  const data = await formDesignerApi.detail(id);
  store.load({ ...data.form, fields: data.fields });
  inferTable.value = data.form.table_name;
}

async function onSave() {
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
  preview.value = await formDesignerApi.preview(definition());
  previewVisible.value = true;
}

async function onApply() {
  applying.value = true;
  try {
    preview.value = await formDesignerApi.apply(definition());
    ElMessage.success('迁移应用成功');
  } finally {
    applying.value = false;
  }
}

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
.designer-layout {
  align-items: flex-start;
}
.palette-item:hover {
  border-color: var(--el-color-primary);
  color: var(--el-color-primary);
}
</style>
