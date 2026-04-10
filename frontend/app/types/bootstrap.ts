/**
 * Bootstrap data injected by spa.php into window.__MOODLE_BOOTSTRAP__.
 */
export interface MoodleBootstrap {
  wwwroot: string
  sesskey: string
  userid: number
  fullname: string
  email: string
  lang: string
  ismanager: boolean
  isadmin: boolean
  companyid: number
  companyname: string
  pluginbaseurl: string
  /** Login token (CSRF) — only present when unauthenticated. */
  logintoken?: string
  /** Moodle login URL — only present when unauthenticated. */
  loginurl?: string
}

declare global {
  interface Window {
    __MOODLE_BOOTSTRAP__?: MoodleBootstrap
  }
}
