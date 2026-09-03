import type { App } from 'vue';
import { createPinia } from 'pinia';
import piniaPluginPersistedstate from 'pinia-plugin-persistedstate';

const pinia = createPinia();
pinia.use(piniaPluginPersistedstate);

export function setupStore(app: App) {
  app.use(pinia);
}

export default pinia;

export * from './modules/app';
export * from './modules/user';
export * from './modules/permission';
export * from './modules/tabs';
