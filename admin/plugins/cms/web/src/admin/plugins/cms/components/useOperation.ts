import { cmsTranslate } from '@/composables/useCmsI18n';
import { ref } from 'vue';
export function useOperation() {
  const busy = ref(false), error = ref('');
  async function run<T>(action: () => Promise<T>): Promise<T | undefined> {
    busy.value = true; error.value = '';
    try { return await action(); }
    catch (cause: any) { error.value = typeof cause?.msg === 'string' ? cause.msg : cmsTranslate('cms.labels.operationFailedCheckYourInputAndRetry'); return undefined; }
    finally { busy.value = false; }
  }
  return { busy, error, run };
}
