import { config } from '@vue/test-utils';
import { i18n } from '@/locales';

// 组件内普遍直接调用 useI18n()，未在 mount 时显式传入 i18n 的用例依赖此全局安装。
config.global.plugins.push(i18n);

// Node 25+ 自带的 localStorage 会让 Vitest 跳过 jsdom 的同名全局。
// 浏览器测试必须使用当前 jsdom 的 Storage（包括 origin 隔离和会话生命周期），而非 Node 的文件存储。
const { jsdom } = globalThis as typeof globalThis & { jsdom?: { window: Window } };
if (jsdom) {
  for (const name of ['localStorage', 'sessionStorage'] as const) {
    Object.defineProperty(globalThis, name, {
      configurable: true,
      writable: true,
      value: jsdom.window[name]
    });
  }
}
