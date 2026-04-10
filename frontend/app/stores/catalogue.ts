import type { CatalogueData, CatalogueCourse, CatalogueCategory } from '~/types/catalogue'
import type { CacheEntry } from '~/utils/cache'
import { isCacheValid, createCacheEntry } from '~/utils/cache'

const TTL = 10 * 60 * 1000 // 10 minutes

/**
 * Catalogue store — caches the full course catalogue so returning
 * to the catalogue page after browsing a course is instant.
 *
 * Client-side filtering (search, category, type) stays in the page.
 */
export const useCatalogueStore = defineStore('catalogue', () => {
  // ── State ──
  const cache = ref<CacheEntry<CatalogueData> | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  let fetchPromise: Promise<void> | null = null

  // ── Getters ──
  const data          = computed<CatalogueData | null>(() => cache.value?.data ?? null)
  const courses       = computed<CatalogueCourse[]>(() => data.value?.courses ?? [])
  const categories    = computed<CatalogueCategory[]>(() => data.value?.categories ?? [])
  const hasCategories = computed<boolean>(() => data.value?.hascategories ?? false)

  // ── Actions ──
  async function fetch(options?: { force?: boolean }) {
    const force = options?.force ?? false

    if (!force && isCacheValid(cache.value)) return
    if (fetchPromise && !force) return fetchPromise

    loading.value = true
    error.value = null

    fetchPromise = (async () => {
      try {
        const { getCatalogue } = useCourseApi()
        const result = await getCatalogue(0)

        if (result.error) {
          error.value = result.error
        } else if (result.data) {
          cache.value = createCacheEntry(result.data as CatalogueData, TTL)
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

  function invalidate() {
    cache.value = null
  }

  async function refresh() {
    return fetch({ force: true })
  }

  return {
    cache, loading, error,
    data, courses, categories, hasCategories,
    fetch, invalidate, refresh,
  }
})
