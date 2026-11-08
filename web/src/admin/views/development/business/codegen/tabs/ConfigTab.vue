<template>
  <el-form class="codegen-config" label-width="auto" label-position="right" @submit.prevent>
    <h4 class="codegen-config__section">{{ t('business.codegen.edit.sectionBasic', '基础信息') }}</h4>
    <el-form-item data-config="tableName" :label="t('business.codegen.edit.tableName', '表名称')">
      <span class="codegen-config__ro" :title="t('business.codegen.edit.tableNameHint', '即绑定的数据表')">{{ tableName }}</span>
    </el-form-item>
    <el-form-item data-config="title" :label="t('business.codegen.edit.tableComment', '表描述')" required>
      <el-input :model-value="doc.title" maxlength="100" :placeholder="t('business.codegen.edit.tableCommentPlaceholder', '如：商品')" @update:model-value="updateTitle" />
    </el-form-item>
    <el-form-item data-config="formKey" :label="t('business.codegen.edit.formKey', '业务标识')">
      <span class="codegen-config__ro">{{ formKey }}</span>
    </el-form-item>
    <el-form-item data-config="className" :label="t('business.codegen.edit.className', '类名')">
      <span class="codegen-config__ro">{{ className }}</span>
    </el-form-item>
    <el-form-item data-config="connection" :label="t('business.codegen.edit.connection', '数据源')">
      <span class="codegen-config__ro">{{ connection }}</span>
    </el-form-item>
    <el-form-item data-config="source" :label="t('business.codegen.edit.source', '来源')">
      <span class="codegen-config__ro">{{ adopted ? t('business.codegen.edit.sourceAdopted', '采纳已有表') : t('business.codegen.edit.sourceCreated', '创建新表') }}</span>
      <div class="codegen-config__tip">{{ t('business.codegen.edit.sourceTip', '来源与绑定表在装载数据表或新建表时确定，不能在此修改。') }}</div>
    </el-form-item>
    <el-form-item data-config="remark" :label="t('business.codegen.edit.remark', '备注')">
      <span class="codegen-config__ro">{{ form.remark || '—' }}</span>
    </el-form-item>

    <h4 class="codegen-config__section">{{ t('business.codegen.edit.sectionGenerate', '生成信息') }}</h4>
    <el-form-item data-config="target" :label="t('business.codegen.edit.target', '生成目标')">
      <span class="codegen-config__ro">{{ targetLabel }}</span>
      <el-tag v-if="target?.locked" size="small" effect="plain" type="info">{{ t('business.codegen.edit.targetLocked', '已锁定') }}</el-tag>
      <div class="codegen-config__tip">{{ t('business.codegen.edit.targetTip', '生成目标在装载或新建时确定，此处只读。') }}</div>
    </el-form-item>
    <el-form-item data-config="parentMenu" :label="t('business.codegen.edit.parentMenu', '所属菜单')">
      <el-tree-select
        :model-value="publish.parentSourceName || undefined"
        :data="menus"
        node-key="sourceName"
        :props="MENU_TREE_PROPS"
        check-strictly
        clearable
        class="w-full"
        :placeholder="t('business.codegen.edit.parentMenuPlaceholder', '不选择则创建一级菜单')"
        @update:model-value="updateParentMenu"
      />
    </el-form-item>
    <el-form-item data-config="generationType" :label="t('business.codegen.edit.generationType', '生成类型')">
      <el-radio-group :model-value="tree.enabled ? 'tree' : 'single'" @update:model-value="(value: unknown) => updateTree({ enabled: value === 'tree' })">
        <el-radio-button value="single">{{ t('business.codegen.edit.typeSingle', '单表 CRUD') }}</el-radio-button>
        <el-radio-button value="tree">{{ t('business.codegen.edit.typeTree', '树表 CRUD') }}</el-radio-button>
      </el-radio-group>
    </el-form-item>
    <template v-if="tree.enabled">
      <el-form-item data-config="treeParentField" :label="t('business.codegen.edit.treeParentField', '父级字段')" required>
        <el-select :model-value="tree.parentField" filterable class="w-full" :placeholder="t('business.codegen.edit.treeParentPlaceholder', '选择存储父记录主键的字段')" @update:model-value="(value: unknown) => updateTree({ parentField: String(value) })">
          <el-option v-for="node in treeParentFields" :key="String(node.field)" :label="fieldLabel(node)" :value="String(node.field)" />
        </el-select>
        <div class="codegen-config__tip">{{ t('business.codegen.edit.treeTip', '主键自动读取实际表主键；树列表不分页，最多 1000 条授权记录。') }}</div>
      </el-form-item>
      <el-form-item data-config="treeSelection" :label="t('business.codegen.edit.treeSelection', '新增页父级选择')">
        <el-radio-group :model-value="tree.selectionMode ?? 'single'" @update:model-value="(value: unknown) => updateTree({ selectionMode: value as 'single' | 'multiple' })">
          <el-radio value="single">{{ t('business.codegen.edit.selectionSingle', '单选') }}</el-radio>
          <el-radio value="multiple">{{ t('business.codegen.edit.selectionMultiple', '多选') }}</el-radio>
        </el-radio-group>
      </el-form-item>
    </template>
    <el-form-item data-config="module" :label="t('business.codegen.edit.moduleName', '模块名')" :required="!pluginTarget">
      <template v-if="pluginTarget">
        <span class="codegen-config__ro">{{ target?.pluginCode }}</span>
        <div class="codegen-config__tip">{{ t('business.codegen.edit.moduleNamePluginTip', '插件目标的模块名固定为插件标识。') }}</div>
      </template>
      <el-input v-else :model-value="publish.module" maxlength="64" @update:model-value="(module: string) => emitPublish({ module })" />
    </el-form-item>
    <el-form-item data-config="apiPrefix" :label="t('business.codegen.edit.apiPrefix', '接口前缀')" required>
      <el-input :model-value="publish.apiPrefix" @update:model-value="(apiPrefix: string) => emitPublish({ apiPrefix })" />
    </el-form-item>
    <el-form-item data-config="routePath" :label="t('business.codegen.edit.routePath', '前端路由')" required>
      <el-input :model-value="publish.routePath" @update:model-value="(routePath: string) => emitPublish({ routePath })" />
    </el-form-item>

    <h4 class="codegen-config__section">{{ t('business.codegen.edit.sectionPage', '页面效果') }}</h4>
    <el-form-item data-config="formMode" :label="t('business.codegen.edit.formMode', '表单效果')">
      <el-radio-group :model-value="publish.formMode" @update:model-value="(formMode: unknown) => emitPublish({ formMode: formMode as 'dialog' | 'drawer' })">
        <el-radio-button value="dialog">{{ t('business.codegen.edit.modeDialog', '弹出框') }}</el-radio-button>
        <el-radio-button value="drawer">{{ t('business.codegen.edit.modeDrawer', '抽屉') }}</el-radio-button>
      </el-radio-group>
    </el-form-item>
    <el-form-item data-config="formWidth" :label="t('business.codegen.edit.formWidth', '表单宽度')">
      <el-input-number
        :model-value="publish.formWidth"
        :min="FORM_WIDTH_MIN"
        :max="FORM_WIDTH_MAX"
        :step="10"
        controls-position="right"
        :value-on-clear="null"
        :placeholder="t('business.codegen.edit.formWidthDefault', '默认')"
        @update:model-value="(value: number | null | undefined) => emitPublish({ formWidth: value ?? undefined })"
      />
      <span class="codegen-config__unit">px</span>
      <div class="codegen-config__tip">{{ t('business.codegen.edit.formWidthTip', { min: FORM_WIDTH_MIN, max: FORM_WIDTH_MAX }, '{min}–{max}，留空沿用生成模板默认宽度') }}</div>
    </el-form-item>
    <el-form-item data-config="formFullscreen" :label="t('business.codegen.edit.formFullscreen', '表单全屏')">
      <el-switch :model-value="publish.formFullscreen === true" @update:model-value="(value: unknown) => emitPublish({ formFullscreen: Boolean(value) })" />
    </el-form-item>
    <el-form-item data-config="leftTree" :label="t('business.codegen.edit.leftTree', '左侧树筛选')">
      <span class="codegen-config__ro">{{ doc.list?.leftTree?.enabled ? t('business.codegen.edit.enabled', '已开启') : t('business.codegen.edit.disabled', '未开启') }}</span>
      <div class="codegen-config__tip">{{ t('business.codegen.edit.leftTreeTip', '数据来源、字段对应等复杂配置请在表单设计器「分类管理」中调整。') }}</div>
    </el-form-item>

    <h4 class="codegen-config__section">{{ t('business.codegen.edit.sectionFeatures', '功能开关') }}</h4>
    <div class="codegen-config__switches">
      <el-form-item v-for="item in featureSwitches" :key="item.key" :data-config="item.key" :label="item.label">
        <el-switch :model-value="publish[item.key] === true" @update:model-value="(value: unknown) => emitPublish({ [item.key]: Boolean(value) })" />
      </el-form-item>
    </div>
    <el-form-item data-config="dataScopeEnabled" :label="t('business.codegen.edit.dataScope', '数据权限')">
      <el-switch :model-value="publish.dataScopeEnabled" @update:model-value="(value: unknown) => emitPublish({ dataScopeEnabled: Boolean(value) })" />
    </el-form-item>
    <el-form-item v-if="publish.dataScopeEnabled" data-config="dataScopeField" :label="t('business.codegen.edit.dataScopeField', '部门字段')" required>
      <el-select :model-value="publish.dataScopeField" filterable class="w-full" @update:model-value="(value: unknown) => emitPublish({ dataScopeField: String(value) })">
        <el-option v-for="node in fields" :key="String(node.field)" :label="fieldLabel(node)" :value="String(node.field)" />
      </el-select>
    </el-form-item>
    <el-form-item data-config="memberApiEnabled" :label="t('business.codegen.edit.memberApi', '会员端 API')">
      <el-switch :model-value="publish.memberApiEnabled === true" @update:model-value="(value: unknown) => emitPublish({ memberApiEnabled: Boolean(value) })" />
      <span class="codegen-config__hint">{{ t('business.codegen.edit.memberApiHint', { path: memberApiPath }, '会员登录后调用 {path}，只能增删改查自己的记录') }}</span>
    </el-form-item>
    <el-form-item v-if="publish.memberApiEnabled" data-config="memberApiOwnerField" :label="t('business.codegen.edit.memberApiOwnerField', '会员归属字段')" required>
      <el-select :model-value="publish.memberApiOwnerField" filterable class="w-full" :placeholder="t('business.codegen.edit.memberApiOwnerPlaceholder', '选择整数类型字段，新增时自动写入当前会员')" @update:model-value="(value: unknown) => emitPublish({ memberApiOwnerField: String(value) })">
        <el-option v-for="node in ownerFields" :key="String(node.field)" :label="fieldLabel(node)" :value="String(node.field)" />
      </el-select>
    </el-form-item>
  </el-form>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { FormDefinition, FormPublishConfig } from '@/api/form';
import type { BusinessModule } from '@/api/development/business';
import type { FormSchemaDocument } from '@/views/form/schema/types';
import { FORM_WIDTH_MAX, FORM_WIDTH_MIN, classNameOf, entityOf } from '../generationConfig';
import { fieldLabel, integerFieldNodes, schemaFieldNodes, treeParentFieldNodes } from '../schemaFields';

type TreeConfig = NonNullable<NonNullable<FormSchemaDocument['list']>['tree']>;
type BooleanPublishKey = 'softDeletes' | 'batchDelete' | 'import' | 'export';

const props = defineProps<{
  doc: FormSchemaDocument;
  publish: FormPublishConfig;
  form: FormDefinition;
  module: BusinessModule | null;
  menus: API.MenuItem[];
  adopted: boolean;
}>();
const emit = defineEmits<{ 'update:doc': [doc: FormSchemaDocument]; 'update:publish': [patch: Partial<FormPublishConfig>] }>();

const { t } = useI18n();
const MENU_TREE_PROPS = { label: 'name', children: 'children', value: 'sourceName' };

// Schema 文档 database.* 优先于表单记录，与后端 FormCrudDefinitionFactory 一致。
const database = computed(() => ((props.doc as FormSchemaDocument & { database?: Record<string, unknown> }).database ?? {}));
const tableName = computed(() => String(database.value.table || props.form.table_name || props.module?.table_name || ''));
const connection = computed(() => String(database.value.connection || props.form.connection || props.module?.connection_name || 'mysql'));
const formKey = computed(() => props.doc.key || props.form.form_key);
const className = computed(() => classNameOf(formKey.value));
const target = computed(() => props.module?.metadata?.target);
// 插件目标在 FormCrudDefinitionFactory::forBusinessTarget 中以插件标识覆盖 module。
const pluginTarget = computed(() => target.value?.type === 'plugin');
const targetLabel = computed(() => target.value?.type === 'plugin'
  ? t('business.codegen.edit.targetPlugin', { code: target.value.pluginCode ?? '' }, '插件 {code}')
  : t('business.codegen.edit.targetCore', '核心（app/admin/business）'));
const memberApiPath = computed(() => {
  const entity = entityOf(formKey.value);
  return target.value?.type === 'plugin' && target.value.pluginCode ? `/${target.value.pluginCode}/${entity}` : `/api/v2/${entity}`;
});

const fields = computed(() => schemaFieldNodes(props.doc));
const ownerFields = computed(() => integerFieldNodes(fields.value));
const treeParentFields = computed(() => treeParentFieldNodes(fields.value));
const tree = computed<TreeConfig>(() => props.doc.list?.tree ?? { enabled: false });

const featureSwitches = computed<Array<{ key: BooleanPublishKey; label: string }>>(() => [
  { key: 'softDeletes', label: t('business.codegen.edit.softDeletes', '回收站（软删除）') },
  { key: 'batchDelete', label: t('business.codegen.edit.batchDelete', '批量删除') },
  { key: 'import', label: t('business.codegen.edit.import', '导入') },
  { key: 'export', label: t('business.codegen.edit.export', '导出') }
]);

const emitPublish = (patch: Partial<FormPublishConfig>) => emit('update:publish', patch);
const updateTitle = (title: string) => emit('update:doc', { ...props.doc, title });
// 与设计器 updateTree 一致：在已有 list.tree 上合并
const updateTree = (patch: Partial<TreeConfig>) => emit('update:doc', {
  ...props.doc,
  list: { ...props.doc.list, tree: { enabled: false, ...props.doc.list?.tree, ...patch } }
});
// 与设计器 updateParentMenu 一致：按来源名挂载，清空数字 parentId
const updateParentMenu = (value: unknown) => emitPublish({ parentSourceName: String(value ?? ''), parentId: null });
</script>

<style scoped>
.codegen-config {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0 28px;
  padding: 4px 4px 8px;
}

/* 标签宽度由 el-form 取最长标签（label-width="auto"），标签本身不折行。 */
.codegen-config :deep(.el-form-item__label) {
  white-space: nowrap;
}

.codegen-config__section {
  grid-column: 1 / -1;
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 6px 0 14px;
  padding-top: 10px;
  border-top: 1px dashed var(--el-border-color);
  color: var(--el-text-color-primary);
  font-size: 13px;
  font-weight: 600;
}

.codegen-config__section:first-child {
  border-top: 0;
  padding-top: 0;
}

.codegen-config__switches {
  display: contents;
}

.codegen-config__ro {
  color: var(--el-text-color-regular);
  font-family: var(--el-font-family);
  word-break: break-all;
  margin-right: 8px;
}

.codegen-config__tip,
.codegen-config__hint {
  width: 100%;
  color: var(--el-text-color-secondary);
  font-size: 12px;
  line-height: 1.5;
}

.codegen-config__hint {
  width: auto;
  margin-left: 12px;
}

.codegen-config__unit {
  margin-left: 8px;
  color: var(--el-text-color-secondary);
}

@media (max-width: 860px) {
  .codegen-config {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
