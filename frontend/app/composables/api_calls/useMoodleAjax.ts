import type { MoodleAjaxCall, MoodleAjaxResult } from '~/types/moodle-ajax'
import type { MoodleBootstrap } from '~/types/bootstrap'

/**
 * Core composable for calling Moodle's AJAX web service.
 *
 * Replaces inboxfrontend's useApi — same patterns (deduplication, error handling)
 * but adapted for Moodle's /lib/ajax/service.php protocol.
 *
 * Usage:
 *   const { call, batchCall } = useMoodleAjax()
 *   const result = await call<CourseData>('local_sm_graphics_plugin_get_course_landing_data', { courseid: 42 })
 */
export const useMoodleAjax = () => {
  // Request deduplication: prevents duplicate simultaneous GET-like calls.
  const pendingRequests = new Map<string, Promise<MoodleAjaxResult<unknown>>>()

  /**
   * Get the Moodle AJAX endpoint URL.
   * In dev mode (Vite proxy), this is relative. In production (served from Moodle), also relative.
   */
  const getAjaxUrl = (): string => {
    const bootstrap = window.__MOODLE_BOOTSTRAP__
    if (bootstrap?.wwwroot) {
      return `${bootstrap.wwwroot}/lib/ajax/service.php`
    }
    // Dev mode fallback: rely on Vite proxy
    return '/lib/ajax/service.php'
  }

  /**
   * Get the sesskey from bootstrap data.
   */
  const getSesskey = (): string => {
    const bootstrap = window.__MOODLE_BOOTSTRAP__
    if (bootstrap?.sesskey) {
      return bootstrap.sesskey
    }
    // Fallback: try M.cfg (Moodle's JS config object)
    const mcfg = (window as any).M?.cfg
    if (mcfg?.sesskey) {
      return mcfg.sesskey
    }
    console.warn('[useMoodleAjax] No sesskey found — AJAX calls will fail')
    return ''
  }

  /**
   * Resolve the current UI locale so we can forward it to Moodle as a
   * `lang` query parameter. The i18n-sync plugin keeps
   * `document.documentElement.lang` in sync with the active vue-i18n locale,
   * so reading it is safe outside of a setup() context and doesn't require
   * a Nuxt app instance. Returns an empty string when not available.
   *
   * Used as a fallback transport for callers that don't include `lang` in
   * their args object — the preferred pattern is still to pass it explicitly
   * (see useProfileApi for the canonical example).
   */
  const getUiLang = (): string => {
    if (typeof document === 'undefined') return ''
    return document.documentElement.lang || ''
  }

  /**
   * Call a single Moodle external function.
   *
   * @param methodname - Full function name (e.g., 'local_sm_graphics_plugin_enrol_user')
   * @param args - Arguments matching the function's execute_parameters()
   * @param options.deduplicate - If true, deduplicates identical concurrent calls (default: false)
   * @returns { data, error } — data is typed as T, error is null on success
   */
  const call = async <T = unknown>(
    methodname: string,
    args: Record<string, unknown> = {},
    options: { deduplicate?: boolean } = {},
  ): Promise<MoodleAjaxResult<T>> => {
    const dedupeKey = options.deduplicate
      ? `${methodname}:${JSON.stringify(args)}`
      : null

    // Return existing pending request if deduplicating.
    if (dedupeKey && pendingRequests.has(dedupeKey)) {
      return pendingRequests.get(dedupeKey) as Promise<MoodleAjaxResult<T>>
    }

    const request = executeSingle<T>(methodname, args)

    if (dedupeKey) {
      pendingRequests.set(dedupeKey, request as Promise<MoodleAjaxResult<unknown>>)
      request.finally(() => pendingRequests.delete(dedupeKey))
    }

    return request
  }

  /**
   * Execute a single AJAX call to Moodle.
   */
  const executeSingle = async <T>(
    methodname: string,
    args: Record<string, unknown>,
  ): Promise<MoodleAjaxResult<T>> => {
    const sesskey = getSesskey()
    const lang = getUiLang()
    const langSuffix = lang ? `&lang=${encodeURIComponent(lang)}` : ''
    const url = `${getAjaxUrl()}?sesskey=${encodeURIComponent(sesskey)}&info=${encodeURIComponent(methodname)}${langSuffix}`

    const body: MoodleAjaxCall[] = [
      { index: 0, methodname, args },
    ]

    try {
      const response = await fetch(url, {
        method: 'POST',
        credentials: 'include', // Send Moodle session cookie
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      })

      if (!response.ok) {
        return { data: null, error: `HTTP ${response.status}: ${response.statusText}` }
      }

      const json = await response.json()

      // Moodle returns an array of results (one per call in the batch).
      if (!Array.isArray(json) || json.length === 0) {
        return { data: null, error: 'Invalid response format from Moodle' }
      }

      const result = json[0]

      // Check for Moodle exception.
      if (result.error === true || result.exception) {
        const exc = result.exception || {}
        return {
          data: null,
          error: exc.message || exc.errorcode || 'Unknown Moodle error',
        }
      }

      return { data: result.data ?? result as T, error: null }
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Network error'
      return { data: null, error: message }
    }
  }

  /**
   * Call multiple Moodle external functions in a single HTTP request (batch).
   *
   * @param calls - Array of { methodname, args } objects
   * @returns Array of { data, error } results in the same order
   */
  const batchCall = async <T extends unknown[] = unknown[]>(
    calls: Array<{ methodname: string; args: Record<string, unknown> }>,
  ): Promise<{ [K in keyof T]: MoodleAjaxResult<T[K]> }> => {
    const sesskey = getSesskey()
    const lang = getUiLang()
    const langSuffix = lang ? `&lang=${encodeURIComponent(lang)}` : ''
    const info = calls.map(c => c.methodname).join(',')
    const url = `${getAjaxUrl()}?sesskey=${encodeURIComponent(sesskey)}&info=${encodeURIComponent(info)}${langSuffix}`

    const body: MoodleAjaxCall[] = calls.map((c, index) => ({
      index,
      methodname: c.methodname,
      args: c.args,
    }))

    try {
      const response = await fetch(url, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body),
      })

      if (!response.ok) {
        const errorResult = { data: null, error: `HTTP ${response.status}` }
        return calls.map(() => errorResult) as any
      }

      const json = await response.json()

      if (!Array.isArray(json)) {
        const errorResult = { data: null, error: 'Invalid response format' }
        return calls.map(() => errorResult) as any
      }

      return json.map((result: any) => {
        if (result.error === true || result.exception) {
          return {
            data: null,
            error: result.exception?.message || result.exception?.errorcode || 'Unknown error',
          }
        }
        return { data: result.data ?? result, error: null }
      }) as any
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Network error'
      const errorResult = { data: null, error: message }
      return calls.map(() => errorResult) as any
    }
  }

  /**
   * Get the bootstrap data injected by spa.php.
   */
  const getBootstrap = (): MoodleBootstrap | null => {
    return window.__MOODLE_BOOTSTRAP__ ?? null
  }

  return { call, batchCall, getBootstrap }
}
