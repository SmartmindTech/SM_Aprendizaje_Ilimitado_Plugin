import { useMoodleAjax } from './useMoodleAjax'

/**
 * API composable for course-related Moodle AJAX calls.
 */
export const useCourseApi = () => {
  const { call } = useMoodleAjax()

  const getCourseLandingData = (courseid: number) =>
    call('local_sm_graphics_plugin_get_course_landing_data', { courseid }, { deduplicate: true })

  const getCoursePageData = (courseid: number) =>
    call('local_sm_graphics_plugin_get_course_page_data', { courseid }, { deduplicate: true })

  const getMyCourses = () =>
    call('local_sm_graphics_plugin_get_mycourses_data', {}, { deduplicate: true })

  const getCatalogue = (categoryid: number = 0) =>
    call('local_sm_graphics_plugin_get_catalogue_data', { categoryid }, { deduplicate: true })

  const getGradesCertificates = () =>
    call('local_sm_graphics_plugin_get_grades_certificates_data', {}, { deduplicate: true })

  const getCourseProgress = (courseid: number) =>
    call('local_sm_graphics_plugin_get_course_progress', { courseid }, { deduplicate: true })

  const enrolUser = (courseid: number) =>
    call('local_sm_graphics_plugin_enrol_user', { courseid })

  const unenrolUser = (courseid: number) =>
    call('local_sm_graphics_plugin_unenrol_user', { courseid })

  // ── Course landing inline editor (admin) ───────────────────────────
  // add_activity creates a mod_url inline when type is 'genially' or 'url',
  // otherwise returns a redirect_url to Moodle's /course/modedit.php form
  // for the requested module type. Capability: moodle/course:update.
  const addActivity = (
    courseid: number,
    sectionnum: number,
    type: string,
    name: string,
    url: string = '',
  ) =>
    call('local_sm_graphics_plugin_add_activity', { courseid, sectionnum, type, name, url })

  const deleteActivity = (cmid: number) =>
    call('local_sm_graphics_plugin_delete_activity', { cmid })

  // Phase 2 additions: objectives, translation, comments mentions ──────────
  const saveObjectives = (courseid: number, objectivesJson: string, translate: boolean = true) =>
    call('local_sm_graphics_plugin_save_objectives', {
      courseid,
      objectives_json: objectivesJson,
      translate,
    })

  const translateCourse = (courseid: number) =>
    call('local_sm_graphics_plugin_translate_course', { courseid })

  const searchCourseUsers = (courseid: number, query: string) =>
    call('local_sm_graphics_plugin_search_course_users', { courseid, query }, { deduplicate: true })

  const updateComment = (commentid: number, content: string) =>
    call('local_sm_graphics_plugin_update_comment', { commentid, content })

  const deleteComment = (commentid: number) =>
    call('local_sm_graphics_plugin_delete_comment', { commentid })

  return {
    getCourseLandingData,
    getCoursePageData,
    getMyCourses,
    getCatalogue,
    getGradesCertificates,
    getCourseProgress,
    enrolUser,
    unenrolUser,
    addActivity,
    deleteActivity,
    saveObjectives,
    translateCourse,
    searchCourseUsers,
    updateComment,
    deleteComment,
  }
}
