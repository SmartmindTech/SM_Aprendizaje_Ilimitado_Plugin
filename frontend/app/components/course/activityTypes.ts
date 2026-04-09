/**
 * Catalogue of activity types the admin can add from the course landing
 * page picker. Mirrors the legacy array in `amd/src/course_landing.js:153-179`,
 * including the four non-Moodle-base entries:
 *
 *   - genially          → stored as a mod_url with display=embed (inline create)
 *   - video             → stored as a mod_url with display=embed (inline create)
 *   - iomadcertificate  → IOMAD plugin (redirect to /course/modedit.php)
 *   - trainingevent     → IOMAD plugin (redirect to /course/modedit.php)
 *
 * Types are organised into three semantic groups (mirroring Moodle's
 * Resources / Activities split, plus IOMAD events as a third bucket):
 *
 *   - resources   (blue   #3b82f6) — passive content the student consumes
 *   - activities  (orange #f97316) — interactive content the student does
 *   - events      (pink   #ec4899) — IOMAD scheduled training events
 *
 * Labels live in i18n (`landing.activityType.<key>`) instead of being
 * hardcoded in Spanish so en/pt_br work without ceremony.
 */

/**
 * Which layout the unified create/edit modal should render for this type.
 *
 *   - 'url'      → name + external URL (genially / video / plain mod_url)
 *   - 'file'     → name + file upload (resource / scorm / folder / imscp / h5pactivity)
 *   - 'body'     → name + rich-text body (label / page)
 *   - 'deferred' → name only, warning that the module is created blank and
 *                  must be finished in Moodle's native UI after restore
 *                  (quiz / assign / forum / ...)
 */
export type ActivityLayout = 'url' | 'file' | 'body' | 'deferred'

export interface ActivityType {
  /** Moodle modname or pseudo-type ('genially', 'video') sent to add_activity. */
  mod: string
  /** Lucide icon class rendered inside the picker tile. */
  iconClass: string
  /** vue-i18n key for the human label, e.g. `landing.activityType.genially`. */
  i18nKey: string
  /** Whether the picker should open the URL modal in Genially mode. */
  isGenially?: boolean
  /** Whether the picker should open the URL modal in Video mode. */
  isVideo?: boolean
  /**
   * Layout the unified activity create/edit modal should render when this
   * type is picked inside the restore wizard. Omit on landing-page types
   * that still rely on the redirect-to-modedit.php flow.
   */
  layout?: ActivityLayout
  /** Accept filter for file-upload layouts (e.g. '.zip' for scorm). */
  fileAccept?: string
}

export interface ActivityTypeGroup {
  /** vue-i18n key for the group title, e.g. `landing.activityGroup.resources`. */
  i18nKey: string
  /** Hex tint shared by every type in this group. */
  color: string
  /** Members of the group. */
  types: ActivityType[]
}

export const ACTIVITY_TYPE_GROUPS: ActivityTypeGroup[] = [
  {
    i18nKey: 'landing.activityGroup.resources',
    color: '#3b82f6',
    types: [
      { mod: 'video',       iconClass: 'icon-film',          i18nKey: 'landing.activityType.video', isVideo: true, layout: 'url' },
      { mod: 'resource',    iconClass: 'icon-file-up',       i18nKey: 'landing.activityType.resource', layout: 'file' },
      { mod: 'label',       iconClass: 'icon-type',          i18nKey: 'landing.activityType.label', layout: 'body' },
      { mod: 'folder',      iconClass: 'icon-folder',        i18nKey: 'landing.activityType.folder', layout: 'file' },
      { mod: 'h5pactivity', iconClass: 'icon-circle-play',   i18nKey: 'landing.activityType.h5pactivity', layout: 'file', fileAccept: '.h5p' },
      { mod: 'book',        iconClass: 'icon-book-open',     i18nKey: 'landing.activityType.book', layout: 'deferred' },
      { mod: 'page',        iconClass: 'icon-file-text',     i18nKey: 'landing.activityType.page', layout: 'body' },
      { mod: 'imscp',       iconClass: 'icon-package',       i18nKey: 'landing.activityType.imscp', layout: 'file', fileAccept: '.zip' },
      { mod: 'url',         iconClass: 'icon-link',          i18nKey: 'landing.activityType.url', layout: 'url' },
    ],
  },
  {
    i18nKey: 'landing.activityGroup.activities',
    color: '#f97316',
    types: [
      { mod: 'genially',         iconClass: 'icon-presentation',        i18nKey: 'landing.activityType.genially', isGenially: true, layout: 'url' },
      { mod: 'quiz',             iconClass: 'icon-circle-help',         i18nKey: 'landing.activityType.quiz', layout: 'deferred' },
      { mod: 'assign',           iconClass: 'icon-file-text',           i18nKey: 'landing.activityType.assign', layout: 'deferred' },
      { mod: 'lesson',           iconClass: 'icon-graduation-cap',      i18nKey: 'landing.activityType.lesson', layout: 'deferred' },
      { mod: 'scorm',            iconClass: 'icon-box',                 i18nKey: 'landing.activityType.scorm', layout: 'file', fileAccept: '.zip' },
      { mod: 'workshop',         iconClass: 'icon-users',               i18nKey: 'landing.activityType.workshop', layout: 'deferred' },
      { mod: 'forum',            iconClass: 'icon-message-circle',      i18nKey: 'landing.activityType.forum', layout: 'deferred' },
      { mod: 'glossary',         iconClass: 'icon-notebook-text',       i18nKey: 'landing.activityType.glossary', layout: 'deferred' },
      { mod: 'wiki',             iconClass: 'icon-book-open',           i18nKey: 'landing.activityType.wiki', layout: 'deferred' },
      { mod: 'data',             iconClass: 'icon-database',            i18nKey: 'landing.activityType.data', layout: 'deferred' },
      { mod: 'choice',           iconClass: 'icon-circle-check',        i18nKey: 'landing.activityType.choice', layout: 'deferred' },
      { mod: 'survey',           iconClass: 'icon-clipboard-check',     i18nKey: 'landing.activityType.survey', layout: 'deferred' },
      { mod: 'feedback',         iconClass: 'icon-message-square-text', i18nKey: 'landing.activityType.feedback', layout: 'deferred' },
      { mod: 'lti',              iconClass: 'icon-external-link',       i18nKey: 'landing.activityType.lti', layout: 'deferred' },
      { mod: 'iomadcertificate', iconClass: 'icon-award',               i18nKey: 'landing.activityType.iomadcertificate', layout: 'deferred' },
    ],
  },
  {
    i18nKey: 'landing.activityGroup.events',
    color: '#ec4899',
    types: [
      { mod: 'trainingevent', iconClass: 'icon-video', i18nKey: 'landing.activityType.trainingevent', layout: 'deferred' },
    ],
  },
]
