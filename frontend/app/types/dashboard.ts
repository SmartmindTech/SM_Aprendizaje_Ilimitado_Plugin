/**
 * Shape of a course as returned by the dashboard endpoints
 * (`get_dashboard_data` and `get_browsed_courses`).
 *
 * Both endpoints return the same struct so the dashboard's reusable
 * card components don't need to branch on field availability.
 */
export interface DashboardCourse {
  id: number
  fullname: string
  shortname: string
  categoryname: string
  sm_category: string
  image: string
  progress: number
  lastcmid: number
  lastaccess: number
}

export interface CategorySection {
  categoryname: string
  categoryid: number
  image_src: string
  courses: DashboardCourse[]
  count: number
}

export interface DashboardData {
  courses: DashboardCourse[]
  finished: DashboardCourse[]
  categories: CategorySection[]
  recommended: DashboardCourse[]
  recommended_for_you: DashboardCourse[]
  news: DashboardCourse[]
  recently_viewed: DashboardCourse[]
  enrolled_count: number
  completed_count: number
  streakdays?: number
  xppoints?: number
  userlevel?: number
}
