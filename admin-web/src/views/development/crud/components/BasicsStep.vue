<template>
  <div class="basics-grid">
    <el-form label-width="125px">
      <el-form-item label="数据源"><el-select :model-value="connection" class="w-full" @update:model-value="$emit('update:connection', $event)"><el-option v-for="item in connections" :key="item.name" :label="item.name" :value="item.name" /></el-select></el-form-item>
      <el-form-item label="数据表"><el-select :model-value="table" filterable class="w-full" @update:model-value="$emit('update:table', $event)"><el-option v-for="item in tables" :key="item.name" :label="item.comment ? `${item.name} — ${item.comment}` : item.name" :value="item.name" /></el-select></el-form-item>
    </el-form>
    <el-skeleton v-if="inferring" :rows="6" animated />
    <template v-else-if="model">
      <el-divider content-position="left">模块定义</el-divider>
      <el-form :model="model" label-width="125px" class="definition-form">
        <el-form-item label="目标类型"><el-radio-group :model-value="model.target.type" @update:model-value="changeTarget"><el-radio-button value="core">核心</el-radio-button><el-radio-button value="plugin">插件</el-radio-button></el-radio-group></el-form-item>
        <template v-if="model.target.type === 'plugin'">
          <el-form-item label="插件">
            <div class="plugin-target-row">
              <el-select :model-value="model.target.plugin" class="w-full" @update:model-value="$emit('change-plugin', $event)"><el-option v-for="item in plugins" :key="item.code" :label="`${item.name} (${item.code})`" :value="item.code" /></el-select>
              <el-button type="primary" plain v-perm="'development:plugin:create'" @click="$emit('create-plugin')">新建插件</el-button>
            </div>
          </el-form-item>
          <el-form-item label="生成范围"><el-select :model-value="model.target.scope" class="w-full" @update:model-value="$emit('change-scope', $event)"><el-option v-for="scope in activeScopes" :key="scope" :label="scopeLabel(scope)" :value="scope" /></el-select></el-form-item>
          <el-form-item label="命名空间"><el-input :model-value="pluginNamespaces.join('；')" disabled /></el-form-item>
          <el-form-item label="接口 URL"><el-input :model-value="model.apiPrefix" disabled /></el-form-item>
        </template>
        <el-form-item label="连接"><el-input v-model="model.connection" disabled /></el-form-item>
        <el-form-item label="表名"><el-input v-model="model.table" disabled /></el-form-item>
        <el-form-item label="模块"><el-input v-model="model.module" :disabled="model.target.type === 'plugin'" /></el-form-item>
        <el-form-item label="实体"><el-input v-model="model.entity" /></el-form-item>
        <el-form-item label="标题"><el-input v-model="model.title" /></el-form-item>
        <el-form-item label="API 前缀"><el-input v-model="model.apiPrefix" :disabled="model.target.type === 'plugin'" /></el-form-item>
        <el-form-item label="路由路径"><el-input v-model="model.routePath" :disabled="model.target.type === 'plugin'" /></el-form-item>
        <el-form-item label="权限前缀"><el-input v-model="model.permissionPrefix" :disabled="model.target.type === 'plugin'" /></el-form-item>
        <el-form-item label="主键"><el-select v-model="model.primaryKey" class="w-full"><el-option v-for="field in model.fields" :key="field.name" :label="field.name" :value="field.name" /></el-select></el-form-item>
        <el-form-item label="时间戳"><el-switch v-model="model.timestamps" /></el-form-item>
        <el-form-item label="软删除"><el-switch v-model="model.softDeletes" /></el-form-item>
      </el-form>
      <el-collapse class="resource-config"><el-collapse-item title="菜单与权限" name="resources">
        <el-form :model="model" label-width="125px" class="definition-form">
          <el-form-item label="生成菜单"><el-switch v-model="model.menu.enabled" /></el-form-item>
          <el-form-item label="生成权限"><el-switch v-model="model.permission.enabled" /></el-form-item>
          <template v-if="model.menu.enabled">
            <el-form-item label="父级菜单"><el-tree-select v-model="model.menu.parentSourceName" :data="parentMenus" node-key="sourceName" value-key="sourceName" :props="{ label: 'name', children: 'children' }" clearable check-strictly class="w-full" /></el-form-item>
            <el-form-item label="菜单名称"><el-input v-model="model.menu.name" /></el-form-item>
            <el-form-item label="菜单图标"><IconSelect v-model="model.menu.icon" /></el-form-item>
            <el-form-item label="排序"><el-input-number v-model="model.menu.sortOrder" :min="0" :max="9999" /></el-form-item>
            <el-form-item label="隐藏"><el-switch v-model="model.menu.hidden" /></el-form-item>
            <el-form-item label="缓存"><el-switch v-model="model.menu.keepAlive" /></el-form-item>
            <el-form-item label="固定 Tab"><el-switch v-model="model.menu.affix" /></el-form-item>
            <el-form-item label="打开方式"><el-select v-model="model.menu.target" class="w-full"><el-option label="当前窗口" value="_self" /><el-option label="新窗口" value="_blank" /></el-select></el-form-item>
          </template>
          <el-form-item v-if="model.permission.enabled" label="权限组名"><el-input v-model="model.permission.groupName" /></el-form-item>
        </el-form>
        <el-alert :title="menuPathHint" type="info" :closable="false" class="mb-3" />
        <el-table v-if="model.permission.enabled" :data="model.permission.actions" border><el-table-column prop="action" label="动作" /><el-table-column label="权限码"><template #default="{ row }">{{ model.permissionPrefix }}:{{ row.codeSuffix }}</template></el-table-column><el-table-column label="中文名称"><template #default="{ row }"><el-input v-model="row.label" /></template></el-table-column></el-table>
      </el-collapse-item></el-collapse>
      <el-divider content-position="left">表结构摘要</el-divider>
      <div class="summary-cards">
        <el-card shadow="never"><template #header>字段（{{ model.fields.length }}）</template><div class="tag-list"><el-tag v-for="field in model.fields" :key="field.name" size="small" :type="field.primary ? 'success' : 'info'">{{ field.name }} · {{ field.dbType }}</el-tag></div></el-card>
        <el-card shadow="never"><template #header>索引（{{ indexes.length }}）</template><div v-if="indexes.length" class="tag-list"><el-tag v-for="(index, key) in indexes" :key="String(key)" size="small">{{ indexName(index) }}</el-tag></div><el-empty v-else description="未返回索引信息" :image-size="40" /></el-card>
      </div>
      <el-alert :title="laravelHint" :type="laravelReady ? 'success' : 'warning'" show-icon :closable="false" class="mt-3" />
      <el-divider content-position="left">生成目标摘要</el-divider>
      <el-descriptions v-if="model.target.type === 'core' && model.generationTargets" title="目标文件" :column="1" border><el-descriptions-item v-for="(path, key) in model.generationTargets" :key="key" :label="String(key)">{{ path }}</el-descriptions-item></el-descriptions>
      <template v-else><el-alert title="插件目标路径由服务安全派生" type="info" :closable="false" /><el-descriptions title="目标文件" :column="1" border class="mt-3"><el-descriptions-item v-for="path in pluginTargets" :key="path" label="路径">{{ path }}</el-descriptions-item></el-descriptions></template>
    </template>
    <el-empty v-else description="选择数据表后将自动推断模块与字段" />
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import IconSelect from '@/components/IconSelect/index.vue';
import type { DevelopmentPluginOption } from '@/api/development/plugin';
import type { CrudConnection, CrudDefinition, CrudParentMenu, CrudTable } from '@/types/development/crud';
import { scopeLabel } from '@/views/system/plugin/pluginDisplay';

const props = defineProps<{ connection: string; table: string; connections: CrudConnection[]; tables: CrudTable[]; parentMenus: CrudParentMenu[]; plugins: DevelopmentPluginOption[]; model: CrudDefinition | null; schema: Record<string, unknown> | null; inferring: boolean }>();
const emit = defineEmits<{ 'update:connection': [value: string]; 'update:table': [value: string]; 'change-target': [value: 'core' | 'plugin']; 'change-plugin': [value: string]; 'change-scope': [value: 'application' | 'console' | 'both']; 'create-plugin': [] }>();
const changeTarget = (value: string | number | boolean | undefined) => {
  if (value === 'core' || value === 'plugin') emit('change-target', value);
};
const activePlugin = computed(() => {
  const target = props.model?.target;
  return target?.type === 'plugin' ? props.plugins.find((item) => item.code === target.plugin) : undefined;
});
const activeScopes = computed(() => activePlugin.value?.scopes || []);
const pluginNamespaces = computed(() => {
  if (!props.model || props.model.target.type !== 'plugin') return [];
  const code = props.model.target.plugin;
  const namespaces: string[] = [];
  if (props.model.target.scope !== 'console') {
    for (const layer of ['controller', 'model', 'service', 'validate']) namespaces.push(`app\\${code}\\${layer}`);
  }
  if (props.model.target.scope !== 'application') {
    for (const layer of ['controller', 'model', 'service', 'validate']) namespaces.push(`app\\console\\${layer}\\plugin\\${code}`);
  }
  return namespaces;
});
const pluginTargets = computed(() => {
  if (!props.model || props.model.target.type !== 'plugin') return [];
  const root = `plugins/${props.model.target.plugin}`;
  const entity = props.model.entity;
  const targets = [`${root}/database/migrations/NNN_create_${entity.replace(/-/g, '_')}.sql`, `${root}/plugin.json`];
  if (props.model.target.scope !== 'console') {
    targets.push(`${root}/app/${props.model.target.plugin}/controller`, `${root}/app/${props.model.target.plugin}/model`, `${root}/app/${props.model.target.plugin}/service`, `${root}/app/${props.model.target.plugin}/validate`);
  }
  if (props.model.target.scope !== 'application') {
    targets.push(`${root}/app/console/controller`, `${root}/app/console/model`, `${root}/app/console/service`, `${root}/app/console/validate`, `${root}/admin-web/${entity}/api.ts`, `${root}/admin-web/${entity}/index.vue`, `${root}/admin-web/${entity}/components/${entity.split('-').map((part) => part[0]?.toUpperCase() + part.slice(1)).join('')}Form.vue`, `${root}/admin-web/${entity}/components/${entity.split('-').map((part) => part[0]?.toUpperCase() + part.slice(1)).join('')}Detail.vue`);
  }
  return targets;
});
const indexes = computed<unknown[]>(() => {
  const value = props.schema?.indexes ?? props.schema?.indices;
  return Array.isArray(value) ? value : [];
});
const indexName = (value: unknown) => {
  if (typeof value === 'string') return value;
  if (value && typeof value === 'object') {
    const row = value as Record<string, unknown>;
    return String(row.name ?? row.Key_name ?? row.key_name ?? '未命名索引');
  }
  return '未命名索引';
};
const fieldNames = computed(() => new Set(props.model?.fields.map((field) => field.name) || []));
const laravelReady = computed(() => fieldNames.value.has('id') && fieldNames.value.has('created_at') && fieldNames.value.has('updated_at'));
const menuPathHint = computed(() => props.model?.menu.parentSourceName || props.model?.menu.parentId
  ? `子菜单 href 预览：${props.model?.routePath.split('/').filter(Boolean).at(-1) || ''}`
  : `未选择父级，将生成一级绝对路径：${props.model?.routePath || ''}`);
const laravelHint = computed(() => laravelReady.value
  ? '符合 Laravel 常用约定：id 主键及 created_at、updated_at 时间戳已就绪'
  : 'Laravel 规范提示：建议使用 id 主键，并提供 created_at、updated_at；软删除表应提供 deleted_at');
</script>

<style scoped>
.definition-form { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 18px; }
.plugin-target-row { display: flex; gap: 8px; width: 100%; }
.summary-cards { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.tag-list { display: flex; flex-wrap: wrap; gap: 8px; }
@media (max-width: 768px) { .definition-form, .summary-cards { grid-template-columns: 1fr; } }
</style>
