import type { MoodleBootstrap } from '~/types/bootstrap'

/**
 * Auth store — reads bootstrap data injected by spa.php.
 * Pinia Setup API pattern (matching inboxfrontend convention).
 */
export const useAuthStore = defineStore('auth', () => {
  // State
  const user = ref<MoodleBootstrap | null>(null)
  const initialized = ref(false)

  // Getters
  const isAuthenticated = computed(() => !!user.value && user.value.userid > 0)
  const isManager = computed(() => user.value?.ismanager ?? false)
  const isAdmin = computed(() => user.value?.isadmin ?? false)
  const fullname = computed(() => user.value?.fullname ?? '')
  const userid = computed(() => user.value?.userid ?? 0)
  const companyid = computed(() => user.value?.companyid ?? 0)
  const companyname = computed(() => user.value?.companyname ?? '')
  const lang = computed(() => user.value?.lang ?? 'es')
  const wwwroot = computed(() => user.value?.wwwroot ?? '')
  const sesskey = computed(() => user.value?.sesskey ?? '')
  const pluginbaseurl = computed(() => user.value?.pluginbaseurl ?? '')

  /**
   * Initialize from window.__MOODLE_BOOTSTRAP__ (set by spa.php).
   * Called once on app startup from app.vue.
   */
  function init() {
    if (initialized.value) return

    const bootstrap = window.__MOODLE_BOOTSTRAP__
    if (bootstrap) {
      user.value = bootstrap
    } else {
      console.warn('[AuthStore] No bootstrap data found — running in dev mode?')
      // Dev mode: create mock data for local development.
      if (import.meta.dev) {
        user.value = {
          wwwroot: '',
          sesskey: 'dev-sesskey',
          userid: 1,
          fullname: 'Dev User',
          email: 'dev@example.com',
          lang: 'es',
          ismanager: true,
          isadmin: true,
          companyid: 1,
          companyname: 'Dev Company',
          pluginbaseurl: '',
        }
      }
    }

    initialized.value = true
  }

  /**
   * Check if the user has access to management pages.
   */
  function canAccessManagement(): boolean {
    return isManager.value || isAdmin.value
  }

  /**
   * Check if the user has access to admin pages.
   */
  function canAccessAdmin(): boolean {
    return isAdmin.value
  }

  return {
    // State
    user,
    initialized,
    // Getters
    isAuthenticated,
    isManager,
    isAdmin,
    fullname,
    userid,
    companyid,
    companyname,
    lang,
    wwwroot,
    sesskey,
    pluginbaseurl,
    // Actions
    init,
    canAccessManagement,
    canAccessAdmin,
  }
})
