import { createApp } from 'vue';
import App from './App.vue';

import '@/styles/ep-icons.css';
import 'element-plus/theme-chalk/dark/css-vars.css';
// 按需自动引入只能识别模板组件，函数式 API（MessageBox/Message/Notification/Loading）的样式需要手动导入
import 'element-plus/es/components/message-box/style/css';
import 'element-plus/es/components/message/style/css';
import 'element-plus/es/components/notification/style/css';
import 'element-plus/es/components/loading/style/css';
import 'element-plus/es/components/overlay/style/css';
import '@/styles/index.scss';
import '@/styles/tailwind.css';
import '@/utils/nprogress';

import { setupStore } from '@/store';
import { setupRouter } from '@/router';
import { setupComponents } from '@/components';
import { setupDirectives } from '@/directives';
import { APP_CONFIG } from '@/config';
import { i18n } from '@/locales';
import { useAppStore } from '@/store/modules/app';

async function bootstrap() {
  // 开启 Mock：在创建 app 之前先注册 mock adapter，确保首屏请求能命中
  if (import.meta.env.VITE_APP_MOCK === 'true') {
    await import('@/mock');
  }

  const app = createApp(App);

  setupStore(app);
  app.use(i18n);
  const appStore = useAppStore();
  i18n.global.locale.value = appStore.locale;

  // 历史默认主色无感迁移（老 localStorage 里若是旧默认色，自动换成新默认色）
  appStore.migrateLegacyPrimary();

  // 提前把主题 CSS 变量（含 --el-color-primary 派生色）写到 :root，
  // 避免首屏 / Teleport 弹窗（ElMessageBox 等）拿到 EP 默认 #409eff
  appStore.applyTheme();

  setupRouter(app);
  setupComponents(app);
  setupDirectives(app);

  app.config.errorHandler = (err, _instance, info) => {
    console.error('[AdminWeb] uncaught error:', err, info);
  };

  app.mount('#app');

  window.__APP_INFO__ = {
    name: APP_CONFIG.title,
    version: APP_CONFIG.version,
    buildTime: new Date().toISOString()
  };
}

bootstrap();
