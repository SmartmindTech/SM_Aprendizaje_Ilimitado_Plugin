import { useMoodleAjax } from './useMoodleAjax'

/**
 * API composable for course comments CRUD.
 */
export const useCommentsApi = () => {
  const { call } = useMoodleAjax()

  const getComments = (
    courseid: number,
    cmid: number = 0,
    page: number = 0,
    perpage: number = 20,
    sortorder: string = 'newest',
  ) =>
    call('local_sm_graphics_plugin_get_comments', {
      courseid,
      cmid,
      page,
      perpage,
      sortorder,
    })

  const addComment = (
    courseid: number,
    cmid: number,
    content: string,
    parentid: number = 0,
  ) =>
    call('local_sm_graphics_plugin_add_comment', {
      courseid,
      cmid,
      content,
      parentid,
    })

  const updateComment = (commentid: number, content: string) =>
    call('local_sm_graphics_plugin_update_comment', { commentid, content })

  const deleteComment = (commentid: number) =>
    call('local_sm_graphics_plugin_delete_comment', { commentid })

  const searchCourseUsers = (courseid: number, query: string) =>
    call('local_sm_graphics_plugin_search_course_users', { courseid, query })

  return {
    getComments,
    addComment,
    updateComment,
    deleteComment,
    searchCourseUsers,
  }
}
