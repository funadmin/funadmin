/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_APP_TITLE: string;
  readonly VITE_APP_VERSION: string;
  readonly VITE_APP_ENV: 'development' | 'production' | 'test';
  readonly VITE_APP_BASE_API: string;
  readonly VITE_APP_PROXY_TARGET: string;
  readonly VITE_APP_PORT: string;
  readonly VITE_APP_OPEN: string;
  readonly VITE_APP_BUILD_COMPRESS: string;
  readonly VITE_APP_BUILD_SOURCEMAP: string;
}

interface ImportMeta {
  readonly env: ImportMetaEnv;
}
