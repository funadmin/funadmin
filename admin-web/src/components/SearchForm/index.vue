<template>
  <div class="search-form border-0 bg-transparent p-0">
    <el-form
      ref="formRef"
      :model="model"
      :inline="true"
      :label-width="labelWidth"
      class="flex flex-wrap items-center gap-2"
      @submit.prevent="onSearch"
    >
      <slot :model="model" />
      <el-form-item class="!mb-0 ml-auto">
        <div class="inline-flex flex-nowrap items-center gap-2">
          <el-button type="primary" plain @click="onReset">
            <i class="i-ep-refresh-right" /> {{ t('table.reset') }}
          </el-button>
          <el-button type="primary" plain :loading="loading" @click="onSearch">
            <i class="i-ep-search" /> {{ t('table.search') }}
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
