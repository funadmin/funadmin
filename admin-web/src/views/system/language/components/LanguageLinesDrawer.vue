<template>
  <el-drawer v-model="visible" :title="t('systemLanguage.linesTitle', { locale }, { default: '译文条目 · {locale}' })" size="min(760px, 100vw)" append-to-body destroy-on-close>
    <div class="mb-3 flex flex-wrap items-center gap-2">
      <el-input v-model="keyword" :placeholder="t('systemLanguage.keywordPlaceholder', '搜索 key / 译文')" clearable class="!w-64" @keyup.enter="reload(1)" @clear="reload(1)" />
      <el-button type="primary" plain @click="reload(1)"><i class="i-ep-search" /> {{ t('common.search', '查询') }}</el-button>
      <el-button plain @click="openAdd"><i class="i-ep-plus" /> {{ t('systemLanguage.addLine', '新增译文') }}</el-button>
      <span class="text-xs text-[var(--el-text-color-secondary)]">{{ t('systemLanguage.inlineTip', '行内修改失焦即保存；保存后当前语言译文包即时刷新。') }}</span>
    </div>
    <el-table v-loading="loading" :data="rows" border>
      <el-table-column prop="key" label="Key" min-width="220" show-overflow-tooltip />
      <el-table-column :label="t('systemLanguage.lineValue', '译文')" min-width="260">
        <template #default="{ row }">
          <el-input v-model="row.value" size="small" @change="save(row as LanguageLineModel)" />
        </template>
      </el-table-column>
      <el-table-column prop="updatedAt" :label="t('systemLanguage.updatedAt', '更新时间')" width="170" />
      <el-table-column :label="t('common.operation', '操作')" width="80" align="center">
        <template #default="{ row }">
          <el-button link type="danger" @click="remove(row as LanguageLineModel)">{{ t('common.remove', '删除') }}</el-button>
        </template>
      </el-table-column>
    </el-table>
    <div class="mt-4 flex justify-end">
      <el-pagination
        v-model:current-page="page"
        v-model:page-size="pageSize"
        :total="total"
        :page-sizes="[20, 50, 100]"
        layout="total, sizes, prev, pager, next"
        @change="reload()"
      />
    </div>

    <el-dialog v-model="addVisible" :title="t('systemLanguage.addLine', '新增译文')" width="min(560px, 90vw)" append-to-body>
      <el-form label-width="80px">
        <el-form-item label="Key">
          <el-select v-model="addKey" filterable allow-create default-first-option :placeholder="t('systemLanguage.keyPlaceholder', '选择或输入 i18n key')">
            <el-option v-for="candidate in keyCandidates" :key="candidate" :label="candidate" :value="candidate" />
          </el-select>
        </el-form-item>
        <el-form-item :label="t('systemLanguage.lineValue', '译文')">
          <el-input v-model="addValue" type="textarea" :rows="3" :placeholder="t('systemLanguage.valuePlaceholder', '该 key 在当前语言下的译文')" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="addVisible = false">{{ t('common.cancel', '取消') }}</el-button>
        <el-button type="primary" :disabled="!addKey" @click="submitAdd">{{ t('systemLanguage.save', '保存') }}</el-button>
      </template>
    </el-dialog>
  </el-drawer>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { ElMessageBox } from 'element-plus';
import { useI18n } from 'vue-i18n';
import { languageApi, type LanguageLineModel } from '@/api/system/language';
import { applyRemotePack, flattenMessages } from '@/locales/remotePack';
import zhCN from '@/locales/zh-CN';
import enUS from '@/locales/en-US';

const props = defineProps<{ modelValue: boolean; locale: string }>();
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>();

const { t } = useI18n();
const visible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
});

const rows = ref<LanguageLineModel[]>([]);
const loading = ref(false);
const keyword = ref('');
const page = ref(1);
const pageSize = ref(20);
const total = ref(0);
const addVisible = ref(false);
const addKey = ref('');
const addValue = ref('');

const staticPack = computed(() => {
  const locale = props.locale.toLowerCase();
  if (locale === 'zh-cn') return flattenMessages(zhCN as Record<string, unknown>);
  if (locale === 'en-us') return flattenMessages(enUS as Record<string, unknown>);
  return {};
});
const keyCandidates = computed(() => Object.keys(staticPack.value).sort());

async function reload(target?: number) {
  if (target) page.value = target;
  loading.value = true;
  try {
    const result = await languageApi.lines({ page: page.value, pageSize: pageSize.value, locale: props.locale, keyword: keyword.value || undefined });
    rows.value = result.list;
    total.value = result.total;
  } finally {
    loading.value = false;
  }
}

async function save(row: LanguageLineModel) {
  await languageApi.saveLine({ locale: props.locale, key: row.key, value: row.value });
  void applyRemotePack(props.locale);
  await reload();
}

async function remove(row: LanguageLineModel) {
  await ElMessageBox.confirm(t('systemLanguage.deleteLineConfirm', { key: row.key }, { default: '确认删除译文 {key}？删除后该 key 回落静态语言包。' }), t('systemLanguage.deleteConfirmTitle', '删除确认'), { type: 'warning' });
  await languageApi.removeLine(row.id);
  void applyRemotePack(props.locale);
  await reload();
}

function openAdd() {
  addKey.value = '';
  addValue.value = '';
  addVisible.value = true;
}

async function submitAdd() {
  await languageApi.saveLine({ locale: props.locale, key: addKey.value.trim(), value: addValue.value });
  addVisible.value = false;
  void applyRemotePack(props.locale);
  await reload(1);
}

watch(visible, (open) => {
  if (open) void reload(1);
});
</script>
