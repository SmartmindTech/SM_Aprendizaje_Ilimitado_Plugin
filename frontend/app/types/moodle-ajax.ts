/**
 * Types for Moodle's AJAX service protocol.
 */

export interface MoodleAjaxCall {
  index: number
  methodname: string
  args: Record<string, unknown>
}

export interface MoodleAjaxResponse<T = unknown> {
  error: boolean
  data?: T
  exception?: {
    message: string
    errorcode: string
    link?: string
    moreinfourl?: string
    debuginfo?: string
  }
}

export interface MoodleAjaxResult<T = unknown> {
  data: T | null
  error: string | null
}
