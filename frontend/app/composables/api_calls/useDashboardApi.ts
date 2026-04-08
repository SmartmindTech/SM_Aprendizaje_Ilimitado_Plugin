import { useMoodleAjax } from './useMoodleAjax'

/**
 * API composable for the personal dashboard (`/dashboard`).
 *
 * Wraps every Moodle web service the dashboard page needs so the page
 * itself stays free of ajax-call plumbing. Mirrors the per-page composable
 * pattern already used by useCourseApi / useMoodleAjax.
 */
export const useDashboardApi = () => {
  const { call } = useMoodleAjax()

  /**
   * Bulk fetch for the dashboard: enrolled, finished, recommended,
   * category sections, stats. Backed by `get_dashboard_data` external.
   */
  const getDashboard = () =>
    call('local_sm_graphics_plugin_get_dashboard_data', {}, { deduplicate: true })

  /**
   * Recently viewed (browsed-but-not-enrolled) courses, sorted by last
   * visit desc. Backed by `get_browsed_courses` external — populated by
   * `lib.php` whenever the user lands on `/enrol/index.php` for a course
   * they're not enrolled in.
   */
  const getRecentlyViewed = (limit: number = 4) =>
    call('local_sm_graphics_plugin_get_browsed_courses', { limit }, { deduplicate: true })

  return {
    getDashboard,
    getRecentlyViewed,
  }
}
