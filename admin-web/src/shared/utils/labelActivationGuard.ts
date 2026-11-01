/**
 * 标签点击激活守卫：`<label for>` 的原生行为会把点击转发到关联控件，
 * 导致点表单标签文字时 select/date 等弹层被打开、radio/checkbox/switch 被误改值。
 * 在捕获层对选择类/弹层类控件阻止该转发；文本录入类控件保留点击标签聚焦的默认行为。
 */
const ACTIVATION_BLOCKED_SELECTORS = [
  '.el-select',
  '.el-cascader',
  '.el-date-editor',
  '.el-time-editor',
  '.el-color-picker',
  '.el-radio-group',
  '.el-checkbox-group',
  '.el-switch',
  '.el-transfer',
  '.el-rate',
  '.el-slider',
].join(', ');

let installed = false;

export function installLabelActivationGuard(target: Document = document): void {
  if (installed) return;
  installed = true;
  target.addEventListener('click', (event) => {
    const trigger = event.target as HTMLElement | null;
    const label = trigger?.closest?.('label.el-form-item__label');
    if (!label) return;
    const forId = label.getAttribute('for');
    if (!forId) return;
    const control = target.getElementById(forId);
    if (control?.closest(ACTIVATION_BLOCKED_SELECTORS)) event.preventDefault();
  }, true);
}
