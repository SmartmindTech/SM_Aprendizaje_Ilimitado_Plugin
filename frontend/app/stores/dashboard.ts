import type { DashboardData, DashboardCourse, CategorySection } from '~/types/dashboard'
import type { CacheEntry } from '~/utils/cache'
import { isCacheValid, createCacheEntry } from '~/utils/cache'

const TTL = 5 * 60 * 1000 // 5 minutes

/**
 * Dashboard store — caches the bulk `get_dashboard_data` response so
 * navigating away from the dashboard and back is instant.
 *
 * Prefetched in `app.vue` on startup; pages call `fetch()` which is a
 * no-op when the cache is still valid.
 */
export const useDashboardStore = defineStore('dashboard', () => {
  // ── State ──
  const cache = ref<CacheEntry<DashboardData> | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  // In-flight guard (not reactive — just prevents duplicate requests).
  let fetchPromise: Promise<void> | null = null

  // ── Getters ──
  const data = computed<DashboardData | null>(() => cache.value?.data ?? null)

  const enrolledCourses    = computed<DashboardCourse[]>(() => data.value?.courses ?? [])
  const finishedCourses    = computed<DashboardCourse[]>(() => data.value?.finished ?? [])
  const recommendedCourses = computed<DashboardCourse[]>(() => data.value?.recommended ?? [])
  const recommendedForYou  = computed<DashboardCourse[]>(() => data.value?.recommended_for_you ?? [])
  const newsCourses        = computed<DashboardCourse[]>(() => data.value?.news ?? [])
  const recentlyViewed     = computed<DashboardCourse[]>(() => data.value?.recently_viewed ?? [])
  const allCategories      = computed<CategorySection[]>(() => data.value?.categories ?? [])

  const enrolledCount  = computed(() => data.value?.enrolled_count ?? 0)
  const completedCount = computed(() => data.value?.completed_count ?? 0)
  const streakDays     = computed(() => data.value?.streakdays ?? 0)
  const xpPoints       = computed(() => data.value?.xppoints ?? 0)
  const userLevel      = computed(() => data.value?.userlevel ?? 1)

  // ── Actions ──

  /**
   * Fetch dashboard data. Serves from cache when valid unless `force` is set.
   * Concurrent calls while a fetch is in-flight share the same promise.
   */
  async function fetch(options?: { force?: boolean }) {
    const force = options?.force ?? false

    if (!force && isCacheValid(cache.value)) return
    if (fetchPromise && !force) return fetchPromise

    loading.value = true
    error.value = null

    fetchPromise = (async () => {
      try {
        const { getDashboard } = useDashboardApi()
        const result = await getDashboard()

        if (result.error) {
          error.value = result.error
        } else if (result.data) {
          cache.value = createCacheEntry(result.data as DashboardData, TTL)
        }
      } catch (e) {
        error.value = e instanceof Error ? e.message : 'Unexpected error'
      } finally {
        loading.value = false
        fetchPromise = null
      }
    })()

    return fetchPromise
  }

  /** Clear the cache — next `fetch()` will hit the API. */
  function invalidate() {
    cache.value = null
  }

  /** Force a fresh fetch regardless of TTL. */
  async function refresh() {
    return fetch({ force: true })
  }

  return {
    // State
    cache, loading, error,
    // Getters
    data,
    enrolledCourses, finishedCourses, recommendedCourses, recommendedForYou,
    newsCourses, recentlyViewed, allCategories,
    enrolledCount, completedCount, streakDays, xpPoints, userLevel,
    // Actions
    fetch, invalidate, refresh,
  }
})
