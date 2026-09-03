/// <reference types="vite/client" />

declare module '*.vue' {
  import type { DefineComponent } from 'vue';
  const component: DefineComponent<object, object, any>;
  export default component;
}

interface ImportMetaEnv {
  readonly VITE_APP_TITLE: string;
  readonly VITE_APP_VERSION: string;
  readonly VITE_APP_ENV: 'development' | 'production' | string;
  readonly VITE_APP_BASE_API: string;
  readonly VITE_APP_PROXY_TARGET: string;
  readonly VITE_APP_PORT: string;
  readonly VITE_APP_OPEN: string;
  readonly VITE_APP_BUILD_SOURCEMAP: string;
  readonly VITE_APP_BUILD_COMPRESS: string;
  readonly VITE_APP_MOCK: string;
  // 以下为 Vite 内置变量（由 vite/client 提供，这里再声明一次便于在 d.ts 单独引用时也可用）
  readonly MODE: string;
  readonly BASE_URL: string;
  readonly PROD: boolean;
  readonly DEV: boolean;
  readonly SSR: boolean;
}
