import { defineConfig, loadEnv } from 'vite';
import vue from '@vitejs/plugin-vue';
import vueJsx from '@vitejs/plugin-vue-jsx';
import AutoImport from 'unplugin-auto-import/vite';
import Components from 'unplugin-vue-components/vite';
import { ElementPlusResolver } from 'unplugin-vue-components/resolvers';
import tailwindcss from '@tailwindcss/vite';
import compression from 'vite-plugin-compression';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd());
  const isProd = mode === 'production';
  const apiBase = (env.VITE_APP_BASE_API || '/admin').replace(/\/$/, '');
  const apiProxyPattern = `^${apiBase.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}(?:/|$|\\?)`;

  return {
    base: env.VITE_APP_BASE || '/',
    // 显式纳入 main.ts 里手动引入的 EP 样式子路径，减少 dev 下预构建哈希漂移导致的
    // 「504 Outdated Optimize Dep」（浏览器还请求旧的 /node_modules/.vite/deps/*.js）
    optimizeDeps: {
      include: [
        'element-plus/es/components/message-box/style/css',
        'element-plus/es/components/message/style/css',
        'element-plus/es/components/notification/style/css',
        'element-plus/es/components/loading/style/css',
        'element-plus/es/components/overlay/style/css'
      ]
    },
    resolve: {
      // 插件源码包（admin/plugins/*）安装后才复制进 web/src；未安装时其第三方依赖统一从宿主 web/node_modules 解析。
      dedupe: ['vue', 'vue-router', 'vue-i18n', 'pinia', 'element-plus', '@tiptap/core', '@tiptap/pm', '@tiptap/vue-3', '@tiptap/starter-kit', '@tiptap/extension-image', '@tiptap/extension-table', '@tiptap/extension-list', '@tiptap/markdown', '@tiptap/extension-code-block-lowlight', 'lowlight', 'highlight.js', 'dompurify', 'mermaid'],
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
    server: {
      host: '0.0.0.0',
      port: Number(env.VITE_APP_PORT) || 5173,
      open: env.VITE_APP_OPEN === 'true',
      proxy: env.VITE_APP_PROXY_TARGET
        ? {
            [apiProxyPattern]: {
              target: env.VITE_APP_PROXY_TARGET,
              changeOrigin: true,
              ws: true
            },
            '^/(?:cms/member|qa/api)(?:/|$)': {
              target: env.VITE_APP_PROXY_TARGET,
              changeOrigin: true
            },
            '^/api(?:/|$)': {
              target: env.VITE_APP_PROXY_TARGET,
              changeOrigin: true,
              ws: true
            },
            '^/plugins/hooks/frontend(?:[?]|$)': {
              target: env.VITE_APP_PROXY_TARGET,
              changeOrigin: true
            },
            '^/docs(?:/|$|[?])': {
              target: env.VITE_APP_PROXY_TARGET,
              changeOrigin: true
            },
            '^/install(?:/|$)': {
              target: env.VITE_APP_PROXY_TARGET,
              changeOrigin: true
            },
            '/install.php': {
              target: env.VITE_APP_PROXY_TARGET,
              changeOrigin: true
            },
            // 上传接口返回后端相对路径 /storage/...，开发环境需代理到后端才能显示头像等文件
            '^/storage(?:/|$)': {
              target: env.VITE_APP_PROXY_TARGET,
              changeOrigin: true
            },
            '^/plugin-assets(?:/|$)': {
              target: env.VITE_APP_PROXY_TARGET,
              changeOrigin: true
            }
          }
        : undefined
    },
    css: {
      preprocessorOptions: {
        scss: {}
      }
    },
    plugins: [
      vue(),
      vueJsx(),
      tailwindcss(),
      AutoImport({
        imports: ['vue', 'vue-router', 'pinia', '@vueuse/core', 'vue-i18n'],
        resolvers: [ElementPlusResolver()],
        dts: 'auto-imports.d.ts',
        eslintrc: { enabled: true }
      }),
      Components({
        // 按需引入 Element Plus 组件及其 CSS（dark/css-vars 已在 main.ts 全局引入）
        resolvers: [ElementPlusResolver()],
        dts: 'components.d.ts',
        dirs: ['src/shared/components']
      }),
      isProd &&
        env.VITE_APP_BUILD_COMPRESS === 'true' &&
        compression({
          algorithm: 'gzip',
          ext: '.gz',
          threshold: 10240,
          deleteOriginFile: false
        })
    ].filter(Boolean),
    build: {
      outDir: mode === 'webman' ? '../admin/public' : 'dist',
      // Webman public 同时存放安装锁、上传文件和插件资源，不能清空整个目录。
      emptyOutDir: mode !== 'webman',
      target: 'es2015',
      sourcemap: env.VITE_APP_BUILD_SOURCEMAP === 'true',
      chunkSizeWarningLimit: 2000,
      rollupOptions: {
        output: {
          chunkFileNames: 'assets/js/[name]-[hash].js',
          entryFileNames: 'assets/js/[name]-[hash].js',
          assetFileNames: 'assets/[ext]/[name]-[hash].[ext]',
          // 仅稳定拆分第三方依赖；业务路由交给 Rollup 按动态 import 自然拆包
          manualChunks(id) {
            if (id.includes('node_modules')) {
              // 关键三方库单独成 chunk；其余统一归到 vendor，避免互相 import 形成循环 chunk
              if (/[\\/]node_modules[\\/](vue|vue-router|pinia|pinia-plugin-persistedstate)[\\/]/.test(id)) return 'vue';
              if (/[\\/]node_modules[\\/](element-plus|@element-plus)[\\/]/.test(id)) return 'element';
              if (/[\\/]node_modules[\\/]echarts[\\/]/.test(id)) return 'echarts';
              if (/[\\/]node_modules[\\/]@vueuse[\\/]/.test(id)) return 'vueuse';
              if (/[\\/]node_modules[\\/]@iconify[\\/]/.test(id)) return 'iconify';
              return 'vendor';
            }
          }
        }
      }
    }
  };
});
