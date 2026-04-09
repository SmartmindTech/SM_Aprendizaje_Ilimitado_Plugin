import { useMoodleAjax } from './useMoodleAjax'

/**
 * API composable for the profile page bulk fetcher and the gamification
 * mission claim flow.
 *
 * The current SPA language is read from vue-i18n at call time and forwarded
 * to the backend as an explicit `lang` argument so the PHP-side translation
 * maps render achievements and missions in the user's selected language.
 */
export const useProfileApi = () => {
  const { call } = useMoodleAjax()
  const { locale } = useI18n()

  /**
   * Fetch the profile data for a user.
   * @param userid 0 (default) means the currently authenticated user.
   */
  const getProfileData = (userid: number = 0) =>
    call('local_sm_graphics_plugin_get_profile_data', {
      userid,
      lang: String(locale.value),
    }, { deduplicate: true })

  /**
   * Claim the XP reward for a completed mission.
   * Returns the new XP/level snapshot so the SPA can animate the bar.
   */
  const claimMission = (code: string) =>
    call('local_sm_graphics_plugin_claim_mission', {
      code,
      lang: String(locale.value),
    })

  return {
    getProfileData,
    claimMission,
  }
}
