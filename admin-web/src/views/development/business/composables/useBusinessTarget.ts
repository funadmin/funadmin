import { computed, onMounted, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { businessDevelopmentApi, type BusinessTargetCandidates, type BusinessTargetSelection } from '@/api/development/business';

/** 候选以服务端为准；query 指定的不可用插件不得静默回退核心。 */
export function useBusinessTarget() {
  const route = useRoute();
  const selected = ref(typeof route.query.plugin === 'string' ? route.query.plugin : '');
  const candidates = ref<BusinessTargetCandidates['list']>([]);
  const defaultConnection = ref('mysql');
  const loading = ref(true);
  const error = ref('');
  const available = computed(() => !loading.value && !error.value && candidates.value.some((item) => (item.pluginCode ?? '') === selected.value));
  const notice = computed(() => error.value || (loading.value ? '正在加载业务目标' : !available.value ? '所选插件不可用或无权限，请重新选择目标。服务端未提供具体排除原因。' : ''));
  const target = computed<BusinessTargetSelection>(() => selected.value ? { type: 'plugin', pluginCode: selected.value } : { type: 'core' });
  watch(() => route.query.plugin, (plugin) => { selected.value = typeof plugin === 'string' ? plugin : ''; });
  async function loadTargets() {
    loading.value = true;
    error.value = '';
    try {
      const result = await businessDevelopmentApi.targets();
      candidates.value = result.list;
      defaultConnection.value = result.defaultConnection;
    } catch (reason) {
      error.value = reason instanceof Error ? reason.message : (reason as { msg?: string })?.msg || '业务目标加载失败，请重试';
      candidates.value = [];
    } finally { loading.value = false; }
  }
  onMounted(loadTargets);
  return { selected, candidates, defaultConnection, loading, notice, available, target, loadTargets };
}
