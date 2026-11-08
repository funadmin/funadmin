<template>
  <div class="table-pagination">
    <ElPagination v-bind="{ ...props, ...$attrs }" class="table-pagination__control" />
  </div>
</template>

<script setup lang="ts">
import { paginationProps } from 'element-plus/es/components/pagination/index';
import type { PropType } from 'vue';

defineOptions({ inheritAttrs: false });

// 沿用 Element Plus 的分页协议，统一默认外观，事件和 v-model 原样交给内层控件。
const props = defineProps({
  ...paginationProps,
  background: { type: Boolean, default: true },
  pageSizes: { type: Array as PropType<number[]>, default: () => [10, 20, 50, 100] },
  layout: { type: String, default: 'total, sizes, prev, pager, next, jumper' }
});
</script>

<style scoped>
.table-pagination {
  display: flex;
  width: 100%;
  min-width: 0;
  box-sizing: border-box;
  margin-top: 16px;
  padding-bottom: 4px;
  overflow-x: auto;
}

.table-pagination__control {
  flex: 0 0 auto;
  margin-left: auto;
}
</style>
