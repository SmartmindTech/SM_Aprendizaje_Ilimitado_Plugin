import type { CacheEntry } from '~/utils/cache'
import { isCacheValid, createCacheEntry } from '~/utils/cache'

const TTL = 5 * 60 * 1000 // 5 minutes

/**
 * Course store — keyed cache for landing and player data per course ID.
 *
 * Uses a reactive Map so Vue picks up changes when entries are
 * added / removed. Components access data via the getter helpers
 * which read from the Map and return null when no valid entry exists.
 */
export const useCourseStore = defineStore('course', () => {
  // ── State ──
  const landingCache = ref(new Map<number, CacheEntry<any>>())
  const playerCache  = ref(new Map<number, CacheEntry<any>>())

  const landingLoading = ref(false)
  const landingError   = ref<string | null>(null)
  const playerLoading  = ref(false)
  const playerError    = ref<string | null>(null)

  // In-flight guards keyed by course ID.
  const landingPromises = new Map<number, Promise<void>>()
  const playerPromises  = new Map<number, Promise<void>>()

  // ── Getters ──
  function getLandingData(courseid: number) {
    const entry = landingCache.value.get(courseid)
    return isCacheValid(entry ?? null) ? entry!.data : null
  }

  function getPlayerData(courseid: number) {
    const entry = playerCache.value.get(courseid)
    return isCacheValid(entry ?? null) ? entry!.data : null
  }

  // ── Actions ──
  async function fetchLanding(courseid: number, options?: { force?: boolean }) {
    const force = options?.force ?? false

    if (!force) {
      const entry = landingCache.value.get(courseid)
      if (isCacheValid(entry ?? null)) return
      if (landingPromises.has(courseid)) return landingPromises.get(courseid)
    }

    landingLoading.value = true
    landingError.value = null

    const promise = (async () => {
      try {
        const { getCourseLandingData } = useCourseApi()
        const result = await getCourseLandingData(courseid)

        if (result.error) {
          landingError.value = result.error
        } else if (result.data) {
          const updated = new Map(landingCache.value)
          updated.set(courseid, createCacheEntry(result.data, TTL))
          landingCache.value = updated
        }
      } catch (e) {
        landingError.value = e instanceof Error ? e.message : 'Unexpected error'
      } finally {
        landingLoading.value = false
        landingPromises.delete(courseid)
      }
    })()

    landingPromises.set(courseid, promise)
    return promise
  }

  async function fetchPlayer(courseid: number, options?: { force?: boolean }) {
    const force = options?.force ?? false

    if (!force) {
      const entry = playerCache.value.get(courseid)
      if (isCacheValid(entry ?? null)) return
      if (playerPromises.has(courseid)) return playerPromises.get(courseid)
    }

    playerLoading.value = true
    playerError.value = null

    const promise = (async () => {
      try {
        const { getCoursePageData } = useCourseApi()
        const result = await getCoursePageData(courseid)

        if (result.error) {
          playerError.value = result.error
        } else if (result.data) {
          const updated = new Map(playerCache.value)
          updated.set(courseid, createCacheEntry(result.data, TTL))
          playerCache.value = updated
        }
      } catch (e) {
        playerError.value = e instanceof Error ? e.message : 'Unexpected error'
      } finally {
        playerLoading.value = false
        playerPromises.delete(courseid)
      }
    })()

    playerPromises.set(courseid, promise)
    return promise
  }

  // ── Invalidation ──
  function invalidateLanding(courseid: number) {
    const updated = new Map(landingCache.value)
    updated.delete(courseid)
    landingCache.value = updated
  }

  function invalidatePlayer(courseid: number) {
    const updated = new Map(playerCache.value)
    updated.delete(courseid)
    playerCache.value = updated
  }

  function invalidateCourse(courseid: number) {
    invalidateLanding(courseid)
    invalidatePlayer(courseid)
  }

  function invalidateAll() {
    landingCache.value = new Map()
    playerCache.value = new Map()
  }

  return {
    // State
    landingCache, playerCache,
    landingLoading, landingError, playerLoading, playerError,
    // Getters
    getLandingData, getPlayerData,
    // Actions
    fetchLanding, fetchPlayer,
    // Invalidation
    invalidateLanding, invalidatePlayer, invalidateCourse, invalidateAll,
  }
})
