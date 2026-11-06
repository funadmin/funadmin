import { h } from 'vue';
import { ElMessageBox } from 'element-plus';
import { i18n } from '@/locales';

interface ComposerConflict {
  package: string;
  source: string;
  pluginVersion: string;
  otherVersion: string;
  reason: string;
}

const tr = (key: string, fallback: string) => i18n.global.t(key, fallback) as string;

/** Reject cancellation so callers never clear files or show success for an aborted operation. */
export async function withComposerConflictConfirm<T>(run: (accept: boolean) => Promise<T>): Promise<T> {
  try {
    return await run(false);
  } catch (error) {
    const payload = error as { code?: number; data?: { reason?: string; conflicts?: ComposerConflict[] } } | null;
    if (payload?.code !== 409 || payload.data?.reason !== 'composer_conflict' || !Array.isArray(payload.data.conflicts)) throw error;
    const conflicts = payload.data.conflicts;
    await ElMessageBox.confirm(
      h('div', { style: 'max-height:60vh;overflow:auto;overflow-wrap:anywhere' }, [
        h('p', tr('plugin.composerConflictNotice', '检测到依赖冲突。与宿主冲突时使用宿主版本；插件之间冲突时使用先加载的版本。继续可能导致插件异常。')),
        h('ul', conflicts.map(item => h('li', { style: 'margin:10px 0' }, [
          h('strong', item.package),
          h('div', `${tr('plugin.composerOwnVersion', '插件内版本')}：${item.pluginVersion}`),
          h('div', `${item.source === 'host' ? tr('plugin.composerHost', '宿主') : item.source}：${item.otherVersion}`),
          h('div', item.reason)
        ]))),
        h('p', tr('plugin.composerRemember', '确认后会记住本次冲突，冲突清单变化时将重新提示。'))
      ]),
      tr('plugin.composerConflictTitle', 'Composer 依赖冲突'),
      { type: 'warning', confirmButtonText: tr('plugin.composerContinue', '仍然继续'), cancelButtonText: tr('common.cancel', '取消'), closeOnClickModal: false }
    );
    return run(true); // Only one retry; new failures must propagate.
  }
}
