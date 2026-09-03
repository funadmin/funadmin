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

  return {
    base: env.VITE_APP_BASE || '/admin-web/',
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
      alias: {
        '@': fileURLToPath(new URL('./src', import.meta.url)),
        '#': fileURLToPath(new URL('./src/types', import.meta.url))
      }
    },
    server: {
      host: '0.0.0.0',
      port: Number(env.VITE_APP_PORT) || 5173,
      open: env.VITE_APP_OPEN === 'true',
      proxy:
        env.VITE_APP_PROXY_TARGET && env.VITE_APP_BASE_API
          ? {
              [env.VITE_APP_BASE_API]: {
                target: env.VITE_APP_PROXY_TARGET,
                changeOrigin: true,
                ws: true
                // 后端已使用 /admin 前缀，无需 rewrite
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
        dirs: ['src/components']
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
      outDir: '../public/admin-web',
      emptyOutDir: true,
      target: 'es2015',
      sourcemap: env.VITE_APP_BUILD_SOURCEMAP === 'true',
      chunkSizeWarningLimit: 2000,
      rollupOptions: {
        output: {
          chunkFileNames: 'assets/js/[name]-[hash].js',
          entryFileNames: 'assets/js/[name]-[hash].js',
          assetFileNames: 'assets/[ext]/[name]-[hash].[ext]',
          // 函数式分包：第三方库按域分组；业务视图按一级目录分组，减少首屏 js 数量
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
            // 业务视图分组：每个一级目录单独 chunk
            const m = id.match(/[\\/]src[\\/]views[\\/]([^\\/]+)[\\/]/);
            if (m) return `view-${m[1]}`;
          }
        }
      }
    }
  };
});
