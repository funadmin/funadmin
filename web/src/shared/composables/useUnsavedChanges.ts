import { useCmsI18n } from '@/composables/useCmsI18n';
import { inject, onBeforeUnmount, onMounted, type Ref } from 'vue';
import { onBeforeRouteLeave, routerKey } from 'vue-router';
import { ElMessageBox } from 'element-plus';

export function useUnsavedChanges(dirty: Readonly<Ref<boolean>>) {
  const { t } = useCmsI18n();
  async function confirmDiscard(): Promise<boolean> {
    if (!dirty.value) return true;
    try {
      await ElMessageBox.confirm(t('cms.editor.youHaveUnsavedChangesContinuingWillDiscardThem'), t('cms.editor.unsavedChanges'), {
        confirmButtonText:t('cms.editor.discardChanges'), cancelButtonText:t('cms.editor.continueEditing'), type:'warning', distinguishCancelAndClose:true
      });
      return true;
    } catch { return false; }
  }
  function beforeUnload(event: BeforeUnloadEvent) {
    if (dirty.value) { event.preventDefault(); event.returnValue=''; }
  }
  if (inject(routerKey,null)) onBeforeRouteLeave(confirmDiscard);
  onMounted(()=>window.addEventListener('beforeunload',beforeUnload));
  onBeforeUnmount(()=>window.removeEventListener('beforeunload',beforeUnload));
  return { confirmDiscard };
}
