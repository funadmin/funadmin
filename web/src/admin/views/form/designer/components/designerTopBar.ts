import type { DesignerSaveStatus } from '../../composables/useDesigner';

export type DesignerWorkspaceMode = 'edit' | 'desktop' | 'tablet' | 'mobile';
export type SaveIndicatorTone = 'success' | 'warning' | 'danger' | 'info';
export type SaveIndicatorKey = 'saved' | 'unsaved' | 'saving' | 'failed' | 'offline' | 'paused';

/**
 * 顶栏状态点：绿=已保存，黄=未保存/保存中，红=保存失败/冲突暂停，灰=离线草稿。
 * 冲突暂停优先：它需要用户主动核对版本，离线恢复后也不会自动解除。
 */
export function saveIndicator(state: { saveStatus: DesignerSaveStatus; online: boolean; saveBlocked: boolean }): { tone: SaveIndicatorTone; key: SaveIndicatorKey } {
  if (state.saveBlocked) return { tone: 'danger', key: 'paused' };
  if (!state.online) return { tone: 'info', key: 'offline' };
  switch (state.saveStatus) {
    case 'saved': return { tone: 'success', key: 'saved' };
    case 'saving': return { tone: 'warning', key: 'saving' };
    case 'failed': return { tone: 'danger', key: 'failed' };
    default: return { tone: 'warning', key: 'unsaved' };
  }
}

/** ⌘Z / Ctrl+Z 撤销；⇧⌘Z / Ctrl+Shift+Z 重做。 */
export function shortcutAction(event: KeyboardEvent): 'undo' | 'redo' | null {
  if (!(event.metaKey || event.ctrlKey) || event.altKey) return null;
  if (event.key.toLowerCase() !== 'z') return null;
  return event.shiftKey ? 'redo' : 'undo';
}

/** 焦点在输入控件、可编辑区域或弹窗内时交给浏览器/弹窗自身处理撤销。 */
export function isShortcutExempt(target: EventTarget | null): boolean {
  if (!(target instanceof Element)) return false;
  if (target instanceof HTMLElement && target.isContentEditable) return true;
  return Boolean(target.closest('input, textarea, select, [contenteditable]:not([contenteditable="false"]), [role="dialog"], .el-overlay'));
}
