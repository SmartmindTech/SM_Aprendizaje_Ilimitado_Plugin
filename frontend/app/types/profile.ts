/**
 * Types for the profile page payload returned by
 * `local_sm_graphics_plugin_get_profile_data`.
 */

export interface ProfileWeekDay {
  day: string
  count: number
  istoday: boolean
  ispast: boolean
  height: number
}

export interface ProfileAchievement {
  code: string
  name_key: string
  description_key: string
  name: string
  description: string
  icon: string
  condition_type: string
  condition_value: number
  xp_reward: number
  unlocked: boolean
  unlocked_at: number
  current_value: number
  progress_pct: number
}

export interface ProfileXpEntry {
  source: string
  sourceid: number
  xp_amount: number
  description: string
  label: string
  timecreated: number
}

export interface ProfileMission {
  code: string
  name: string
  description: string
  icon: string
  period: 'daily' | 'weekly'
  progress: number
  target: number
  progress_pct: number
  xp_reward: number
  claimable: boolean
  claimed: boolean
}

export interface LeaderboardRow {
  userid: number
  fullname: string
  avatarurl: string
  xp_total: number
  level: number
  position: number
  isself: boolean
}

export interface ClaimMissionResult {
  success: boolean
  reason: 'ok' | 'unknown' | 'not_completed' | 'already_claimed'
  xp_awarded: number
  mission_code: string
  xp_total: number
  level: number
  xp_into_level: number
  xp_for_next: number
  xp_to_next: number
  level_progress_pct: number
}

export interface ProfileData {
  // Identity.
  userid: number
  fullname: string
  email: string
  avatarurl: string
  department: string
  has_department: boolean
  joindate: string

  // Stats based on completion transitions.
  course_count: number
  completed_count: number
  total_hours: number
  streak: number
  week_activity: ProfileWeekDay[]

  // Gamification.
  xp_total: number
  level: number
  xp_into_level: number
  xp_for_next: number
  xp_to_next: number
  level_progress_pct: number
  achievements_unlocked: number
  achievements_total: number
  achievements: ProfileAchievement[]
  recent_xp: ProfileXpEntry[]

  // Missions.
  daily_missions: ProfileMission[]
  weekly_missions: ProfileMission[]

  // Company leaderboard.
  leaderboard: LeaderboardRow[]
}
