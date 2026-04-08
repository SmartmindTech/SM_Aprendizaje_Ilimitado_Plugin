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
    saveObjectives,
    translateCourse,
    searchCourseUsers,
    updateComment,
    deleteComment,
  }
}
