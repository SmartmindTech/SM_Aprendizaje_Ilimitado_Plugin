import type { ProfileData, ClaimMissionResult } from '~/types/profile'
import type { CacheEntry } from '~/utils/cache'
import { isCacheValid, createCacheEntry } from '~/utils/cache'

/** Resolve UI language without useI18n() (safe outside setup context). */
function getLang(): string {
  return document.documentElement.lang || 'es'
}

const PROFILE_TTL    = 3 * 60 * 1000 // 3 minutes
const MYCOURSES_TTL  = 5 * 60 * 1000 // 5 minutes
const GRADES_TTL     = 5 * 60 * 1000 // 5 minutes

/**
 * Profile store — three independent caches for the three lazy tabs
 * (overview, my courses, grades/certificates).
 */
export const useProfileStore = defineStore('profile', () => {
  // ── Profile overview ──
  const profileCache   = ref<CacheEntry<ProfileData> | null>(null)
  const profileLoading = ref(false)
  const profileError   = ref<string | null>(null)
  let profilePromise: Promise<void> | null = null

  const profileData = computed<ProfileData | null>(() => profileCache.value?.data ?? null)

  async function fetchProfile(options?: { force?: boolean }) {
    const force = options?.force ?? false
    if (!force && isCacheValid(profileCache.value)) return
    if (profilePromise && !force) return profilePromise

    // Set loading synchronously so the UI shows the spinner immediately.
    profileLoading.value = true
    profileError.value = null

    profilePromise = (async () => {
      try {
        const { call } = useMoodleAjax()
        const result = await call('local_sm_graphics_plugin_get_profile_data', {
          userid: 0, lang: getLang(),
        }, { deduplicate: true })

        if (result.error) {
          profileError.value = result.error
        } else if (result.data) {
          profileCache.value = createCacheEntry(result.data as ProfileData, PROFILE_TTL)
        }
      } catch (e) {
        profileError.value = e instanceof Error ? e.message : 'Unexpected error'
      } finally {
        profileLoading.value = false
        profilePromise = null
      }
    })()

    return profilePromise
  }

  // ── My Courses tab ──
  const myCoursesCache   = ref<CacheEntry<any> | null>(null)
  const myCoursesLoading = ref(false)
  const myCoursesError   = ref<string | null>(null)
  let myCoursesPromise: Promise<void> | null = null

  const myCoursesData = computed(() => myCoursesCache.value?.data ?? null)

  async function fetchMyCourses(options?: { force?: boolean }) {
    const force = options?.force ?? false
    if (!force && isCacheValid(myCoursesCache.value)) return
    if (myCoursesPromise && !force) return myCoursesPromise

    myCoursesLoading.value = true
    myCoursesError.value = null

    myCoursesPromise = (async () => {
      try {
        const { getMyCourses } = useCourseApi()
        const result = await getMyCourses()

        if (result.error) {
          myCoursesError.value = result.error
        } else if (result.data) {
          myCoursesCache.value = createCacheEntry(result.data, MYCOURSES_TTL)
        }
      } catch (e) {
        myCoursesError.value = e instanceof Error ? e.message : 'Unexpected error'
      } finally {
        myCoursesLoading.value = false
        myCoursesPromise = null
      }
    })()

    return myCoursesPromise
  }

  // ── Grades & Certificates tab ──
  const gradesCache   = ref<CacheEntry<any> | null>(null)
  const gradesLoading = ref(false)
  const gradesError   = ref<string | null>(null)
  let gradesPromise: Promise<void> | null = null

  const gradesData = computed(() => gradesCache.value?.data ?? null)

  async function fetchGrades(options?: { force?: boolean }) {
    const force = options?.force ?? false
    if (!force && isCacheValid(gradesCache.value)) return
    if (gradesPromise && !force) return gradesPromise

    gradesLoading.value = true
    gradesError.value = null

    gradesPromise = (async () => {
      try {
        const { getGradesCertificates } = useCourseApi()
        const result = await getGradesCertificates()

        if (result.error) {
          gradesError.value = result.error
        } else if (result.data) {
          gradesCache.value = createCacheEntry(result.data, GRADES_TTL)
        }
      } catch (e) {
        gradesError.value = e instanceof Error ? e.message : 'Unexpected error'
      } finally {
        gradesLoading.value = false
        gradesPromise = null
      }
    })()

    return gradesPromise
  }

  // ── Mission claim (patches profile data in-memory) ──
  async function claimMission(code: string): Promise<ClaimMissionResult | null> {
    const { call } = useMoodleAjax()
    const result = await call('local_sm_graphics_plugin_claim_mission', {
      code, lang: getLang(),
    })

    if (result.error || !result.data) return null

    const claim = result.data as ClaimMissionResult
    const pd = profileCache.value?.data
    if (pd && claim.success) {
      pd.xp_total           = claim.xp_total
      pd.level              = claim.level
      pd.xp_into_level      = claim.xp_into_level
      pd.xp_for_next        = claim.xp_for_next
      pd.xp_to_next         = claim.xp_to_next
      pd.level_progress_pct = claim.level_progress_pct

      const flip = (m: ProfileData['daily_missions'][number]) => {
        if (m.code === claim.mission_code) {
          m.claimed = true
          m.claimable = false
        }
      }
      pd.daily_missions.forEach(flip)
      pd.weekly_missions.forEach(flip)
    }

    return claim
  }

  // ── Invalidation ──
  function invalidateProfile() { profileCache.value = null }
  function invalidateMyCourses() { myCoursesCache.value = null }
  function invalidateGrades() { gradesCache.value = null }
  function invalidateAll() {
    profileCache.value = null
    myCoursesCache.value = null
    gradesCache.value = null
  }

  return {
    // Profile overview
    profileCache, profileLoading, profileError, profileData,
    fetchProfile,
    // My Courses
    myCoursesCache, myCoursesLoading, myCoursesError, myCoursesData,
    fetchMyCourses,
    // Grades
    gradesCache, gradesLoading, gradesError, gradesData,
    fetchGrades,
    // Mission
    claimMission,
    // Invalidation
    invalidateProfile, invalidateMyCourses, invalidateGrades, invalidateAll,
  }
})
