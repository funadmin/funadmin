<template>
  <div class="search-form border-0 bg-transparent p-0">
    <el-form
      ref="formRef"
      :model="model"
      :label-width="labelWidth"
      class="search-form__grid"
      @submit.prevent="onSearch"
    >
      <slot :model="model" />
      <el-form-item class="search-form__actions !mb-0">
        <div class="inline-flex flex-nowrap items-center gap-2">
          <el-button type="primary" plain :loading="loading" @click="onSearch">
            <i class="i-ep-search" /> {{ t('table.search') }}
          </el-button>
          <el-button type="primary" plain @click="onReset">
            <i class="i-ep-refresh-right" /> {{ t('table.reset') }}
          </el-button>
          <slot name="extra" />
        </div>
      </el-form-item>
    </el-form>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { FormInstance } from 'element-plus';

const { t } = useI18n();

/**
 * 注意：使用 :model 单向绑定（而非 v-model），
 * 父级传入的 reactive 对象通过引用共享，子组件直接 mutate 即同步生效；
 * 不再 emit update:modelValue，避免父级 const reactive 触发 Vue 编译器
 * 「v-model cannot update a const reactive binding」警告。
 */
interface Props {
  model: Recordable;
  loading?: boolean;
  labelWidth?: string | number;
}

const props = withDefaults(defineProps<Props>(), {
  loading: false,
  labelWidth: '80px'
});

const emit = defineEmits<{
  (e: 'search', val: Recordable): void;
  (e: 'reset'): void;
}>();

const formRef = ref<FormInstance>();

function onSearch() {
  emit('search', { ...props.model });
}

function onReset() {
  formRef.value?.resetFields();
  const KEEP = ['page', 'pageSize'];
  const m = props.model as Recordable;
  Object.keys(m).forEach((k) => {
    if (KEEP.includes(k)) return;
    const v = m[k];
    if (typeof v === 'string') m[k] = '';
    else m[k] = undefined;
  });
  emit('reset');
}
</script>

<style scoped>
.search-form__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 12px 20px;
  align-items: center;
  width: 100%;
}

.search-form__grid :deep(.el-form-item) {
  min-width: 0;
  margin-right: 0;
  margin-bottom: 0;
}

.search-form__grid :deep(.el-form-item__content) {
  min-width: 0;
}

.search-form__grid :deep(.el-input),
.search-form__grid :deep(.el-select),
.search-form__grid :deep(.el-date-editor) {
  width: 100% !important;
}

.search-form__actions {
  justify-self: end;
}

.search-form__actions :deep(.el-form-item__content) {
  justify-content: flex-end;
  flex-wrap: nowrap;
}

@media (max-width: 767px) {
  .search-form__grid {
    grid-template-columns: minmax(0, 1fr);
  }

  .search-form__actions {
    justify-self: stretch;
  }

  .search-form__actions :deep(.el-form-item__content) {
    justify-content: flex-start;
  }
}
</style>
