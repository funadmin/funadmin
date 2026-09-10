<template>
  <div class="generation-plan-view">
    <section data-section="summary" class="mb-4">
      <h4>{{ t('business.summary') }}</h4>
      <div class="flex flex-wrap gap-2">
        <el-tag v-for="item in summaryItems" :key="item.status" :type="tagType(item.meta.tone)" :title="t(item.meta.descriptionKey)">
          {{ t(item.meta.labelKey) }}: {{ item.count }}
        </el-tag>
      </div>
    </section>

    <section data-section="files" class="mb-4">
      <h4>{{ t('business.files') }}</h4>
      <p v-if="!plan.files.length">{{ t('business.noFiles') }}</p>
      <ul v-else class="space-y-2">
        <li v-for="file in plan.files" :key="`${file.status}:${file.path}`" class="flex items-center gap-2">
          <el-tag :type="tagType(fileMeta(file.status).tone)" :title="t(fileMeta(file.status).descriptionKey)">
            {{ t(fileMeta(file.status).labelKey) }}
          </el-tag>
          <code>{{ file.path }}</code>
        </li>
      </ul>
    </section>

    <section v-if="conflicts.length" data-section="conflicts" class="mb-4">
      <h4>{{ t('business.conflicts') }}</h4>
      <article v-for="file in conflicts" :key="file.path" :data-conflict-path="file.path" class="mb-3">
        <strong>{{ file.path }}</strong>
        <div class="grid gap-3 md:grid-cols-3">
          <div v-for="side in conflictSides" :key="side.key">
            <h5>{{ t(side.labelKey) }}</h5>
            <pre>{{ file[side.key] ?? '' }}</pre>
          </div>
        </div>
      </article>
    </section>

    <el-collapse>
      <el-collapse-item :title="t('business.advancedJson')">
        <pre>{{ rawJson }}</pre>
      </el-collapse-item>
    </el-collapse>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import type { TagProps } from 'element-plus';
import type { BusinessGenerationFile, BusinessGenerationPlan } from '@/api/development/business';
import { FILE_STATUS_META, type BusinessStatusTone } from '../constants';

const props = withDefaults(defineProps<{ plan: BusinessGenerationPlan; conflicts?: BusinessGenerationFile[] }>(), {
  conflicts: () => []
});
const { t } = useI18n();

const conflictSides = [
  { key: 'baseContent', labelKey: 'business.base' },
  { key: 'localContent', labelKey: 'business.local' },
  { key: 'remoteContent', labelKey: 'business.remote' }
] as const;
const conflicts = computed(() => props.conflicts.length
  ? props.conflicts
  : props.plan.files.filter((file) => file.status.includes('conflict')));
const summaryItems = computed(() => {
  const summary = props.plan.summary || props.plan.files.reduce<Partial<Record<BusinessGenerationFile['status'], number>>>((counts, file) => {
    counts[file.status] = (counts[file.status] || 0) + 1;
    return counts;
  }, {});
  return Object.entries(summary).map(([status, count]) => ({
    status,
    count,
    meta: fileMeta(status)
  }));
});
const rawJson = computed(() => JSON.stringify(props.plan, null, 2));

function fileMeta(status: string) {
  return FILE_STATUS_META[status as keyof typeof FILE_STATUS_META] || {
    labelKey: `business.status.file.${status}.label`, tone: 'info' as const, descriptionKey: `business.status.file.${status}.description`
  };
}

function tagType(tone: BusinessStatusTone): TagProps['type'] {
  return tone === 'primary' ? undefined : tone;
}
</script>
