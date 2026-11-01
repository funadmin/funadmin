import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { businessDevelopmentApi, type BusinessTargetCandidates, type BusinessTargetSelection } from '@/api/development/business';
import { i18n } from '@/locales';

const t = (key: string, fallback: string): string => i18n.global.t(key, fallback);
const tNamed = (key: string, named: Record<string, unknown>, fallback: string): string => i18n.global.t(key, named, fallback);

/** 候选以服务端为准；query 指定的不可用插件不得静默回退核心。 */
export function useBusinessTarget(canLoad: () => boolean = () => true) {
  const route = useRoute();
  const selected = ref(typeof route.query.plugin === 'string' ? route.query.plugin : '');
  const candidates = ref<BusinessTargetCandidates['list']>([]);
  const defaultConnection = ref('mysql');
  const loading = ref(true);
  const error = ref('');
  const candidate = computed(() => candidates.value.find((item) => (item.pluginCode ?? '') === selected.value));
  const available = computed(() => !loading.value && !error.value && Boolean(candidate.value) && candidate.value?.available !== false);
  const notice = computed(() => error.value || (loading.value ? t('business.visual.targetLoading', '正在加载业务目标') : !available.value
    ? candidate.value?.reason?.message ? tNamed('business.visual.pluginUnavailableReason', { msg: candidate.value.reason.message }, `所选插件不可用：${candidate.value.reason.message}`) : t('business.visual.pluginUnavailable', '所选插件不可用或无权限，请重新选择目标。')
    : ''));
  function candidateLabel(item: BusinessTargetCandidates['list'][number]): string {
    const name = item.type === 'plugin' ? `${item.name} (${item.pluginCode})` : item.name;
    return item.available === false ? `${name} — ${item.reason?.message || t('business.visual.targetUnavailable', '目标不可用')}` : name;
  }
  const target = computed<BusinessTargetSelection>(() => selected.value ? { type: 'plugin', pluginCode: selected.value } : { type: 'core' });
  watch(() => route.query.plugin, (plugin) => { selected.value = typeof plugin === 'string' ? plugin : ''; });
  async function loadTargets() {
    if (!canLoad()) {
      loading.value = false;
      candidates.value = [];
      error.value = t('business.visual.noTargetPermission', '无业务目标查看权限，不能创建或采纳；已授权的结构检查仍可使用。');
      return;
    }
    loading.value = true;
    error.value = '';
    try {
      const result = await businessDevelopmentApi.targets();
      candidates.value = result.list;
      defaultConnection.value = result.defaultConnection;
    } catch (reason) {
      error.value = reason instanceof Error ? reason.message : (reason as { msg?: string })?.msg || t('business.visual.targetLoadFailed', '业务目标加载失败，请重试');
      candidates.value = [];
    } finally { loading.value = false; }
  }
  onMounted(loadTargets);
  return { selected, candidates, candidateLabel, defaultConnection, loading, notice, available, target, loadTargets };
}
