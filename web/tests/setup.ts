import { config } from '@vue/test-utils';
import { i18n } from '@/locales';

// 组件内普遍直接调用 useI18n()，未在 mount 时显式传入 i18n 的用例依赖此全局安装。
config.global.plugins.push(i18n);
