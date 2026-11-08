<template>
  <el-form class="codegen-menu" label-width="auto" @submit.prevent>
    <el-form-item data-menu="menuEnabled" :label="t('business.codegen.edit.menuEnabled', '生成菜单')">
      <el-switch :model-value="publish.menuEnabled" @update:model-value="(value: unknown) => emitPublish({ menuEnabled: Boolean(value) })" />
    </el-form-item>
    <el-form-item data-menu="menuParent" :label="t('business.codegen.edit.parentMenu', '所属菜单')">
      <el-tree-select
        :model-value="publish.parentSourceName || undefined"
        :data="menus"
        node-key="sourceName"
        :props="MENU_TREE_PROPS"
        check-strictly
        clearable
        class="w-full"
        :disabled="!publish.menuEnabled"
        :placeholder="t('business.codegen.edit.parentMenuPlaceholder', '不选择则创建一级菜单')"
        @update:model-value="(value: unknown) => emitPublish({ parentSourceName: String(value ?? ''), parentId: null })"
      />
    </el-form-item>
    <el-form-item data-menu="menuName" :label="t('business.codegen.edit.menuName', '菜单名称')" required>
      <el-input :model-value="publish.menuName" maxlength="50" :disabled="!publish.menuEnabled" @update:model-value="(menuName: string) => emitPublish({ menuName })" />
    </el-form-item>
    <el-form-item data-menu="icon" :label="t('business.codegen.edit.menuIcon', '图标')">
      <IconSelect :model-value="publish.icon" @update:model-value="(icon: string) => emitPublish({ icon })" />
    </el-form-item>
    <el-form-item data-menu="sortOrder" :label="t('business.codegen.edit.sortOrder', '排序')">
      <el-input-number :model-value="publish.sortOrder" :min="0" :max="99999" controls-position="right" @update:model-value="(value: number | null | undefined) => emitPublish({ sortOrder: Number(value ?? 0) })" />
    </el-form-item>
    <el-form-item data-menu="permissions" :label="t('business.codegen.edit.permissionCodes', '权限码')">
      <div class="codegen-menu__codes">
        <p class="codegen-menu__tip">{{ t('business.codegen.edit.permissionTip', '按方法自动推导（模块:资源:操作），只读；随字段出现的 status、options 等方法以生成结果为准。') }}</p>
        <ul class="codegen-menu__list">
          <li v-for="code in codes" :key="code" data-permission-code><code>{{ code }}</code></li>
        </ul>
      </div>
    </el-form-item>
  </el-form>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { FormPublishConfig } from '@/api/form';
import IconSelect from '@/components/IconSelect/index.vue';
import { derivedPermissionCodes } from '../generationConfig';

const props = defineProps<{ publish: FormPublishConfig; menus: API.MenuItem[]; formKey: string; pluginCode?: string | null }>();
const emit = defineEmits<{ 'update:publish': [patch: Partial<FormPublishConfig>] }>();

const { t } = useI18n();
const MENU_TREE_PROPS = { label: 'name', children: 'children', value: 'sourceName' };
const codes = computed(() => derivedPermissionCodes(props.formKey, props.publish, props.pluginCode));
const emitPublish = (patch: Partial<FormPublishConfig>) => emit('update:publish', patch);
</script>

<style scoped>
.codegen-menu {
  max-width: 760px;
  padding: 4px 4px 8px;
}

.codegen-menu :deep(.el-form-item__label) {
  white-space: nowrap;
}

.codegen-menu__codes {
  width: 100%;
}

.codegen-menu__tip {
  margin: 0 0 6px;
  color: var(--el-text-color-secondary);
  font-size: 12px;
  line-height: 1.5;
}

.codegen-menu__list {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.codegen-menu__list code {
  display: inline-block;
  padding: 2px 8px;
  border: 1px solid var(--el-border-color-lighter);
  border-radius: 4px;
  background: var(--el-fill-color-light);
  color: var(--el-text-color-regular);
  font-size: 12px;
}
</style>
