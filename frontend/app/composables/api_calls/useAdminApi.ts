import { useMoodleAjax } from './useMoodleAjax'

/**
 * API composable for admin-related Moodle AJAX calls.
 */
export const useAdminApi = () => {
  const { call } = useMoodleAjax()

  const getPluginSettings = () =>
    call('local_sm_graphics_plugin_get_plugin_settings', {}, { deduplicate: true })

  const updatePluginSettings = (settings: {
    enabled: boolean
    color_primary: string
    color_header_bg: string
    color_sidebar_bg: string
    logo_url: string
  }) => call('local_sm_graphics_plugin_update_plugin_settings', settings)

  const getCompanyLimits = () =>
    call('local_sm_graphics_plugin_get_company_limits', {}, { deduplicate: true })

  const updateCompanyLimit = (companyid: number, maxstudents: number) =>
    call('local_sm_graphics_plugin_update_company_limit', { companyid, maxstudents })

  const getIomadDashboard = () =>
    call('local_sm_graphics_plugin_get_iomad_dashboard_data', {}, { deduplicate: true })

  const checkPluginUpdate = () =>
    call('local_sm_graphics_plugin_check_plugin_update', {})

  return {
    getPluginSettings,
    updatePluginSettings,
    getCompanyLimits,
    updateCompanyLimit,
    getIomadDashboard,
    checkPluginUpdate,
  }
}
