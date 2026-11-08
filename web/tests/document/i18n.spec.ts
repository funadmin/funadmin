import { describe, expect, it } from 'vitest';
import { i18n } from '@/locales';
import { documentMessages, registerDocumentLocales } from '../../../admin/plugins/document/web/src/admin/plugins/document/locales';

const flatten = (obj: Record<string, unknown>, prefix = ''): string[] =>
  Object.entries(obj).flatMap(([k, v]) => (v && typeof v === 'object' ? flatten(v as Record<string, unknown>, `${prefix}${k}.`) : [`${prefix}${k}`]));

describe('文档插件界面文案', () => {
  it('中英文文案键完全一致', () => {
    expect(flatten(documentMessages['en-US']).sort()).toEqual(flatten(documentMessages['zh-CN']).sort());
  });

  it('注册后按当前界面语言取文案，且不覆盖宿主已有文案', () => {
    const hostKeys = Object.keys(i18n.global.getLocaleMessage('zh-CN'));
    registerDocumentLocales();
    expect(Object.keys(i18n.global.getLocaleMessage('zh-CN'))).toEqual(expect.arrayContaining(hostKeys));
    i18n.global.locale.value = 'en-US';
    expect(i18n.global.t('pluginDocument.scope.framework')).toBe('FunAdmin framework');
    i18n.global.locale.value = 'zh-CN';
    expect(i18n.global.t('pluginDocument.scope.framework')).toBe('FunAdmin 主框架');
  });
});
