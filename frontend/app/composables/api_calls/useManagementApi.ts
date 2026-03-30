import { useMoodleAjax } from './useMoodleAjax'

/**
 * API composable for management-related Moodle AJAX calls.
 */
export const useManagementApi = () => {
  const { call } = useMoodleAjax()

  const getCompanyUsers = (page: number = 0, perpage: number = 20) =>
    call('local_sm_graphics_plugin_get_company_users', { page, perpage })

  const deleteCompanyUser = (userid: number) =>
    call('local_sm_graphics_plugin_delete_company_user', { userid })

  const getStatistics = () =>
    call('local_sm_graphics_plugin_get_statistics_data', {}, { deduplicate: true })

  const getCategoriesList = () =>
    call('local_sm_graphics_plugin_get_categories_list', {}, { deduplicate: true })

  const deleteCategory = (categoryid: number) =>
    call('local_sm_graphics_plugin_delete_category', { categoryid })

  const getCourseManagement = () =>
    call('local_sm_graphics_plugin_get_course_management_data', {}, { deduplicate: true })

  return {
    getCompanyUsers,
    deleteCompanyUser,
    getStatistics,
    getCategoriesList,
    deleteCategory,
    getCourseManagement,
  }
}
