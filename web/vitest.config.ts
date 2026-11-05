import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

// 单测配置：与 vite.config.ts 解耦，避免拉起 UnoCSS / AutoImport 等运行时插件
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: [
      { find: '@/api', replacement: fileURLToPath(new URL('./src/admin/api', import.meta.url)) },
      { find: '@/layout', replacement: fileURLToPath(new URL('./src/admin/layout', import.meta.url)) },
      { find: '@/mock', replacement: fileURLToPath(new URL('./src/admin/mock', import.meta.url)) },
      { find: '@/modules', replacement: fileURLToPath(new URL('./src/admin/modules', import.meta.url)) },
      { find: '@/router', replacement: fileURLToPath(new URL('./src/admin/router', import.meta.url)) },
      { find: '@/store', replacement: fileURLToPath(new URL('./src/admin/store', import.meta.url)) },
      { find: '@/views', replacement: fileURLToPath(new URL('./src/admin/views', import.meta.url)) },
      { find: '@/components', replacement: fileURLToPath(new URL('./src/shared/components', import.meta.url)) },
      { find: '@/composables', replacement: fileURLToPath(new URL('./src/shared/composables', import.meta.url)) },
      { find: '@/config', replacement: fileURLToPath(new URL('./src/shared/config', import.meta.url)) },
      { find: '@/directives', replacement: fileURLToPath(new URL('./src/shared/directives', import.meta.url)) },
      { find: '@/locales', replacement: fileURLToPath(new URL('./src/shared/locales', import.meta.url)) },
      { find: '@/styles', replacement: fileURLToPath(new URL('./src/shared/styles', import.meta.url)) },
      { find: '@/types', replacement: fileURLToPath(new URL('./src/shared/types', import.meta.url)) },
      { find: '@/utils', replacement: fileURLToPath(new URL('./src/shared/utils', import.meta.url)) },
      { find: '@/web', replacement: fileURLToPath(new URL('./src/web', import.meta.url)) },
      { find: '@', replacement: fileURLToPath(new URL('./src', import.meta.url)) },
      { find: '#', replacement: fileURLToPath(new URL('./src/shared/types', import.meta.url)) }
    ]
  },
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: ['./tests/setup.ts'],
    include: ['tests/**/*.spec.ts', 'src/**/*.spec.ts'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'html'],
      include: ['src/utils/**', 'src/composables/**']
    }
  }
});
