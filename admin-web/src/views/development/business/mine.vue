<template>
  <PageWrapper title="我的业务" subtitle="统一查看、设计、发布和生成业务模块">
    <DataTableShell storage-key="development-business-mine" :loading="loading" @refresh="loadData">
      <template #search>
        <SearchForm :model="query" :loading="loading" @search="search" @reset="reset">
          <el-form-item label="关键词"><el-input v-model="query.keyword" placeholder="业务名称或标识" clearable /></el-form-item>
          <el-form-item label="来源">
            <el-select v-model="query.origin" placeholder="全部" clearable class="!w-36">
              <el-option label="可视化" value="visual" /><el-option label="数据库" value="database" /><el-option label="旧表单" value="legacy_form" />
            </el-select>
          </el-form-item>
          <el-form-item label="状态">
            <el-select v-model="query.status" placeholder="全部" clearable class="!w-36">
              <el-option label="草稿" value="draft" /><el-option label="动态发布" value="dynamic_published" /><el-option label="已发布" value="published" /><el-option label="已停用" value="disabled" />
            </el-select>
          </el-form-item>
        </SearchForm>
      </template>
      <template #toolbar-left>
        <el-button type="primary" v-perm="'development:business:save'" @click="router.push('/development/business/visual')"><i class="i-ep-plus" />可视化创建</el-button>
        <el-button v-perm="'development:business:inspect'" @click="router.push('/development/business/database')">采纳数据表</el-button>
      </template>
      <template #default="{ size, stripe, border, headerCellStyle }">
        <el-table :data="list" :size="size" :stripe="stripe" :border="border" :header-cell-style="headerCellStyle">
          <el-table-column prop="name" label="业务模块" min-width="180"><template #default="{ row }"><div>{{ row.name }}</div><small>{{ row.code }}</small></template></el-table-column>
          <el-table-column prop="origin" label="来源" width="110"><template #default="{ row }"><el-tag effect="plain">{{ originLabel(row.origin) }}</el-tag></template></el-table-column>
          <el-table-column prop="table_name" label="数据表" min-width="160" />
          <el-table-column prop="lifecycle_status" label="发布状态" width="120"><template #default="{ row }"><el-tag :type="statusType(row.lifecycle_status)">{{ row.lifecycle_status }}</el-tag></template></el-table-column>
          <el-table-column prop="generation_status" label="生成状态" width="120" />
          <el-table-column prop="updated_at" label="更新时间" width="170" />
          <el-table-column label="操作" width="250" fixed="right">
            <template #default="{ row }">
              <el-button link type="primary" v-perm="'development:business:save'" @click="design(row as BusinessModule)">设计</el-button>
              <el-button link v-if="row.runtime_route" @click="router.push(row.runtime_route)">运行时</el-button>
              <el-button link v-perm="'development:business:generate'" @click="previewGeneration(row as BusinessModule)">生成预览</el-button>
              <el-button link v-perm="'development:business:records'" @click="router.push({ path: '/development/business/records', query: { moduleId: row.id } })">记录</el-button>
            </template>
          </el-table-column>
        </el-table>
        <div class="mt-4 flex justify-end"><el-pagination v-model:current-page="query.page" v-model:page-size="query.pageSize" :total="total" :page-sizes="[10, 20, 50, 100]" layout="total, sizes, prev, pager, next, jumper" @change="loadData" /></div>
      </template>
    </DataTableShell>
    <el-dialog v-model="previewVisible" title="正式生成预览" width="900px">
      <el-alert v-if="previewError" :title="previewError" type="error" show-icon class="mb-3" />
      <pre class="preview-json">{{ JSON.stringify(preview, null, 2) }}</pre>
      <template #footer><el-button @click="previewVisible = false">关闭</el-button></template>
    </el-dialog>
  </PageWrapper>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { businessDevelopmentApi, type BusinessFormalGenerationPreview, type BusinessModule } from '@/api/development/business';

defineOptions({ name: 'BusinessMine' });
const router = useRouter();
const loading = ref(false);
const list = ref<BusinessModule[]>([]);
const total = ref(0);
const query = reactive({ page: 1, pageSize: 20, keyword: '', status: '', origin: '' });
const previewVisible = ref(false);
const preview = ref<BusinessFormalGenerationPreview | null>(null);
const previewError = ref('');
async function loadData() { loading.value = true; try { const data = await businessDevelopmentApi.modules(query); list.value = data.list; total.value = data.total; } finally { loading.value = false; } }
function search() { query.page = 1; loadData(); }
function reset() { Object.assign(query, { page: 1, pageSize: 20, keyword: '', status: '', origin: '' }); loadData(); }
function design(row: BusinessModule) { if (row.form_id) router.push({ path: '/development/business/designer', query: { id: String(row.form_id), moduleId: String(row.id) } }); }
async function previewGeneration(row: BusinessModule) { previewVisible.value = true; preview.value = null; previewError.value = ''; try { preview.value = await businessDevelopmentApi.previewFormalGeneration(row.id, crypto.randomUUID()); } catch (error) { previewError.value = error instanceof Error ? error.message : '生成预览失败'; } }
function originLabel(value: string) { return ({ visual: '可视化', database: '数据库', legacy_form: '旧表单' } as Record<string, string>)[value] || value; }
function statusType(value: string) { return value === 'published' || value === 'dynamic_published' ? 'success' : value === 'disabled' ? 'info' : 'warning'; }
onMounted(loadData);
</script>

<style scoped>
small { color: var(--el-text-color-secondary); }
.preview-json { max-height: 60vh; overflow: auto; white-space: pre-wrap; word-break: break-all; background: var(--el-fill-color-light); padding: 12px; border-radius: 4px; }
</style>
