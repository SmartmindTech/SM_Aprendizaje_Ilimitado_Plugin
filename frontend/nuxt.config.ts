// @ts-expect-error - Nuxt auto-imports defineNuxtConfig
export default defineNuxtConfig({
  compatibilityDate: '2025-10-21',

  ssr: false, // SPA mode — pages are behind Moodle's require_login()

  future: {
    compatibilityVersion: 4,
  },

  // Use hash-mode routing (#/dashboard, #/courses, etc.)
  // so the Vue router ignores the Moodle server path (/local/.../spa.php).
  router: {
    options: {
      hashMode: true,
    },
  },

  devtools: {
    enabled: false,
  },

  // Auto-import composables from nested directories (api_calls/, etc.)
  imports: {
    dirs: ['composables/**'],
  },

  css: [
    '~/assets/scss/main.scss',
  ],

  plugins: ['./app/plugins/bootstrap.client.ts'],

  modules: [
    ['@pinia/nuxt', { storesDirs: ['./stores/**'] }],
    [
      '@nuxtjs/i18n',
      {
        strategy: 'no_prefix',
        defaultLocale: 'es',
        locales: [
          { code: 'es', file: 'es.json' },
          { code: 'en', file: 'en.json' },
          { code: 'pt_br', file: 'pt_br.json' },
        ],
        langDir: '../i18n/locales',
        lazy: true,
      },
    ],
    '@nuxt/eslint',
  ],

  runtimeConfig: {
    public: {
      appName: 'SmartMind',
    },
  },

  build: {
    transpile: ['bootstrap'],
  },

  vite: {
    css: {
      preprocessorOptions: {
        scss: {
          additionalData: `
            @use "~/assets/scss/abstracts/variables" as *;
            @use "~/assets/scss/abstracts/mixins" as *;
          `,
          silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'if-function'],
          quietDeps: true,
        },
      },
    },
    optimizeDeps: {
      include: ['vue', 'vue-router', 'pinia', 'vue-i18n', 'bootstrap'],
    },
    build: {
      minify: 'esbuild',
      reportCompressedSize: false,
      sourcemap: false,
    },
    server: {
      allowedHosts: ['winhost', 'localhost'],
      hmr: {
        clientPort: 3000,
        protocol: 'ws',
      },
      watch: {
        usePolling: true,
        interval: 1000,
      },
      proxy: {
        // Proxy Moodle AJAX calls to the Docker container during development.
        // Configure MOODLE_URL in .env (default: http://localhost:8080)
        '/lib/ajax': {
          target: process.env.MOODLE_URL || 'http://localhost:8080',
          changeOrigin: true,
        },
        '/login': {
          target: process.env.MOODLE_URL || 'http://localhost:8080',
          changeOrigin: true,
        },
      },
    },
  },

  telemetry: false,

  devServer: {
    port: 3000,
    host: 'localhost',
  },

  app: {
    // When served from Moodle, assets live under the plugin's frontend_dist/ directory.
    // In dev mode (nuxt dev), this is just '/'.
    baseURL: process.env.NODE_ENV === 'production'
      ? '/local/sm_graphics_plugin/frontend_dist/'
      : '/',
    head: {
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1, maximum-scale=5' },
      ],
      link: [
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap' },
      ],
    },
  },
})
