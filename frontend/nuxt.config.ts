// Read SPA_DEV_PORT from the plugin root .env so spa.php and Nuxt agree.
// Falls back to 4173 if unset. Tiny inline parser — no extra deps.
import { readFileSync, existsSync } from 'fs'
import { resolve } from 'path'
let SPA_DEV_PORT = 4173
const rootEnvPath = resolve(__dirname, '../.env')
if (existsSync(rootEnvPath)) {
  for (const line of readFileSync(rootEnvPath, 'utf8').split('\n')) {
    const m = line.match(/^SPA_DEV_PORT=(\d+)/)
    if (m) {
      SPA_DEV_PORT = parseInt(m[1]!, 10)
      break
    }
  }
}

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
      // Make all dev-mode asset URLs absolute so they can be loaded
      // from a different origin (e.g. Moodle at http://localhost:8081
      // embedding the dev server via spa.php).
      origin: `http://localhost:${SPA_DEV_PORT}`,
      cors: true,
      allowedHosts: ['winhost', 'localhost'],
      hmr: {
        clientPort: SPA_DEV_PORT,
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
    port: SPA_DEV_PORT,
    // Bind to 0.0.0.0 so the dev server is reachable from inside the
    // Moodle Docker container via its host-gateway IP. The browser still
    // hits localhost:<port> directly because both run on the same host.
    host: '0.0.0.0',
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
