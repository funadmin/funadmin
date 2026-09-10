<template>
  <PageWrapper title="生成记录" subtitle="查看 managed 生成、冲突与恢复状态">
    <DataTableShell storage-key="development-business-records" :loading="loading" @refresh="loadData">
      <template #search>
        <SearchForm :model="query" :loading="loading" @search="search" @reset="reset">
          <el-form-item label="模块 ID"><el-input-number v-model="query.moduleId" :min="0" controls-position="right" /></el-form-item>
          <el-form-item label="状态"><el-select v-model="query.status" clearable placeholder="全部" class="!w-36"><el-option v-for="status in statuses" :key="status" :label="status" :value="status" /></el-select></el-form-item>
        </SearchForm>
      </template>
      <template #default="{ size, stripe, border, headerCellStyle }">
        <el-table :data="list" :size="size" :stripe="stripe" :border="border" :header-cell-style="headerCellStyle">
          <el-table-column prop="id" label="ID" width="90" /><el-table-column prop="business_module_id" label="模块 ID" width="100" />
          <el-table-column prop="generation_mode" label="模式" width="120" /><el-table-column prop="status" label="状态" width="120"><template #default="{ row }"><el-tag :type="statusType(row.status)">{{ row.status }}</el-tag></template></el-table-column>
          <el-table-column prop="recovery_status" label="恢复状态" width="130" /><el-table-column prop="plan_digest" label="计划摘要" min-width="240" show-overflow-tooltip /><el-table-column prop="created_at" label="创建时间" width="170" />
          <el-table-column label="操作" width="100" fixed="right"><template #default="{ row }"><el-button link type="primary" @click="showDetail(row.id)">详情</el-button></template></el-table-column>
        </el-table>
        <div class="mt-4 flex justify-end"><el-pagination v-model:current-page="query.page" v-model:page-size="query.pageSize" :total="total" :page-sizes="[10, 20, 50, 100]" layout="total, sizes, prev, pager, next, jumper" @change="loadData" /></div>
      </template>
    </DataTableShell>
    <el-drawer v-model="detailVisible" title="生成记录详情" size="60%"><pre class="detail-json">{{ JSON.stringify(detail, null, 2) }}</pre></el-drawer>
  </PageWrapper>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { businessDevelopmentApi, type BusinessGeneration } from '@/api/development/business';

defineOptions({ name: 'BusinessRecords' });
const route = useRoute();
const loading = ref(false);
const list = ref<BusinessGeneration[]>([]);
const total = ref(0);
const statuses = ['planned', 'completed', 'failed', 'conflict'];
const query = reactive({ page: 1, pageSize: 20, moduleId: Number(route.query.moduleId || 0), status: '' });
const detailVisible = ref(false);
const detail = ref<BusinessGeneration | null>(null);
async function loadData() { loading.value = true; try { const data = await businessDevelopmentApi.generations(query); list.value = data.list; total.value = data.total; } finally { loading.value = false; } }
function search() { query.page = 1; loadData(); }
function reset() { Object.assign(query, { page: 1, pageSize: 20, moduleId: 0, status: '' }); loadData(); }
async function showDetail(id: number) { detail.value = await businessDevelopmentApi.generation(id); detailVisible.value = true; }
function statusType(status: string) { return status === 'completed' ? 'success' : status === 'failed' || status === 'conflict' ? 'danger' : 'warning'; }
onMounted(loadData);
</script>

<style scoped>.detail-json { white-space: pre-wrap; word-break: break-all; }</style>
