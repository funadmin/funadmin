<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import PageWrapper from '@/components/PageWrapper/index.vue';
import { useCmsI18n } from '@/composables/useCmsI18n';
import { cmsApi } from '@/plugins/cms/api';
import type { SiteContext } from '@/plugins/cms/types';
import { checkPermission } from '@/utils/permission';
import QuestionsPanel from '../components/QuestionsPanel.vue';
import { registerQaLocales } from '../locales';

/**
 * 问题管理页（菜单与路由按 qa:questions:index 显示）：选择站点与语言后承载问题列表、详情与问答回收站。
 * 只列出本站具有投稿查看权限（cms:submission:list）的站点；各按钮再按自己的权限码与站点权限显示。
 */
registerQaLocales();
const { t } = useCmsI18n();
const sites = ref<SiteContext[]>([]), siteId = ref(''), locale = ref(''), isSuper = ref(false), error = ref(''), loading = ref(false);
const current = computed(() => sites.value.find((site) => site.id === siteId.value));
const scope = computed(() => ({ site_id: siteId.value, locale: locale.value }));
const site = (code: string) => checkPermission(isSuper.value ? ['*'] : current.value?.permissions, code);
let generation = 0;
async function load() {
  const token = ++generation;
  loading.value = true;
  error.value = '';
  try {
    const result = await cmsApi.context('cms:submission:list');
    if (token !== generation) return;
    sites.value = result.items;
    isSuper.value = result.is_super;
    siteId.value = sites.value.find((item) => item.id === siteId.value)?.id || sites.value[0]?.id || '';
  } catch (e) {
    if (token === generation) error.value = (e as { msg?: string })?.msg || t('pluginQa.page.loadFailed');
  } finally {
    if (token === generation) loading.value = false;
  }
}
watch(siteId, () => { locale.value = current.value?.default_locale || ''; });
onMounted(() => void load());
onBeforeUnmount(() => { generation++; });
</script>

<template>
  <PageWrapper v-loading="loading">
    <template #header>
      <div class="qa-context">
        <strong>{{ t('pluginQa.page.title') }}</strong>
        <el-select v-if="sites.length" v-model="siteId" :aria-label="t('pluginQa.page.site')"><el-option v-for="item in sites" :key="item.id" :label="item.name" :value="item.id" /></el-select>
        <el-select v-if="current" v-model="locale" :aria-label="t('pluginQa.page.language')"><el-option v-for="language in current.locales" :key="language.locale" :label="language.name" :value="language.locale" /></el-select>
      </div>
    </template>
    <template #extra><el-button data-testid="qa-reload-sites" @click="load">{{ t('pluginQa.page.refreshSites') }}</el-button></template>
    <el-alert v-if="error" :title="error" type="error" :closable="false" />
    <QuestionsPanel v-if="siteId && locale && !error" :key="`${siteId}:${locale}`" :scope="scope" :site="site" />
    <el-empty v-else-if="!sites.length && !error && !loading" :description="t('pluginQa.page.noSites')" />
  </PageWrapper>
</template>

<style scoped>.qa-context{display:flex;align-items:center;flex-wrap:wrap;gap:12px}.qa-context .el-select{width:180px}</style>
