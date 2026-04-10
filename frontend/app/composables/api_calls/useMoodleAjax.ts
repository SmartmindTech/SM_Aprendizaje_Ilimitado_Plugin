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
    const url = `${getAjaxUrl()}?sesskey=${encodeURIComponent(sesskey)}&info=${encodeURIComponent(methodname)}`

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

      // Read as text first so we can show the raw response in diagnostics
      // when JSON.parse fails or the structure is unexpected. Without this
      // a misconfigured backend just yields a generic "Invalid response
      // format" with no clue about what actually came back.
      const rawText = await response.text()
      let json: unknown
      try {
        json = JSON.parse(rawText)
      } catch {
        console.error(
          `[useMoodleAjax] ${methodname} returned non-JSON. Raw response (first 1000 chars):\n${rawText.slice(0, 1000)}`
        )
        return { data: null, error: 'Invalid response format from Moodle (not JSON)' }
      }

      // Moodle returns an array of results (one per call in the batch).
      // We accept either a non-array OR an empty array as the same failure
      // mode and surface the raw payload — both the parsed JSON and the
      // original text — so the copy-paste from the console always carries
      // the full diagnostic.
      const isEmptyArray = Array.isArray(json) && json.length === 0
      if (!Array.isArray(json) || isEmptyArray) {
        const stringified = (() => {
          try {
            return JSON.stringify(json, null, 2)
          } catch {
            return String(json)
          }
        })()
        const shape = isEmptyArray
          ? 'empty array'
          : json === null
            ? 'null'
            : Array.isArray(json)
              ? 'array'
              : typeof json
        console.error(
          `[useMoodleAjax] ${methodname} returned unexpected response shape (${shape}).\n` +
          `Parsed value: ${stringified}\n` +
          `Raw text (first 1000 chars): ${rawText.slice(0, 1000)}`
        )
        const maybeException = (json as { exception?: { message?: string; errorcode?: string } } | null)?.exception
        const errorMessage = maybeException?.message || maybeException?.errorcode || 'Invalid response format from Moodle'
        return { data: null, error: errorMessage }
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
    const info = calls.map(c => c.methodname).join(',')
    const url = `${getAjaxUrl()}?sesskey=${encodeURIComponent(sesskey)}&info=${encodeURIComponent(info)}`

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
