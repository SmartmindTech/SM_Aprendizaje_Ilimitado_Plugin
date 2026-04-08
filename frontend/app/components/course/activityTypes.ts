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
      { mod: 'video',       iconClass: 'icon-film',          i18nKey: 'landing.activityType.video', isVideo: true },
      { mod: 'resource',    iconClass: 'icon-file-up',       i18nKey: 'landing.activityType.resource' },
      { mod: 'label',       iconClass: 'icon-type',          i18nKey: 'landing.activityType.label' },
      { mod: 'folder',      iconClass: 'icon-folder',        i18nKey: 'landing.activityType.folder' },
      { mod: 'h5pactivity', iconClass: 'icon-circle-play',   i18nKey: 'landing.activityType.h5pactivity' },
      { mod: 'book',        iconClass: 'icon-book-open',     i18nKey: 'landing.activityType.book' },
      { mod: 'page',        iconClass: 'icon-file-text',     i18nKey: 'landing.activityType.page' },
      { mod: 'imscp',       iconClass: 'icon-package',       i18nKey: 'landing.activityType.imscp' },
      { mod: 'url',         iconClass: 'icon-link',          i18nKey: 'landing.activityType.url' },
    ],
  },
  {
    i18nKey: 'landing.activityGroup.activities',
    color: '#f97316',
    types: [
      { mod: 'genially',         iconClass: 'icon-presentation',        i18nKey: 'landing.activityType.genially', isGenially: true },
      { mod: 'quiz',             iconClass: 'icon-circle-help',         i18nKey: 'landing.activityType.quiz' },
      { mod: 'assign',           iconClass: 'icon-file-text',           i18nKey: 'landing.activityType.assign' },
      { mod: 'lesson',           iconClass: 'icon-graduation-cap',      i18nKey: 'landing.activityType.lesson' },
      { mod: 'scorm',            iconClass: 'icon-box',                 i18nKey: 'landing.activityType.scorm' },
      { mod: 'workshop',         iconClass: 'icon-users',               i18nKey: 'landing.activityType.workshop' },
      { mod: 'forum',            iconClass: 'icon-message-circle',      i18nKey: 'landing.activityType.forum' },
      { mod: 'glossary',         iconClass: 'icon-notebook-text',       i18nKey: 'landing.activityType.glossary' },
      { mod: 'wiki',             iconClass: 'icon-book-open',           i18nKey: 'landing.activityType.wiki' },
      { mod: 'data',             iconClass: 'icon-database',            i18nKey: 'landing.activityType.data' },
      { mod: 'choice',           iconClass: 'icon-circle-check',        i18nKey: 'landing.activityType.choice' },
      { mod: 'survey',           iconClass: 'icon-clipboard-check',     i18nKey: 'landing.activityType.survey' },
      { mod: 'feedback',         iconClass: 'icon-message-square-text', i18nKey: 'landing.activityType.feedback' },
      { mod: 'lti',              iconClass: 'icon-external-link',       i18nKey: 'landing.activityType.lti' },
      { mod: 'iomadcertificate', iconClass: 'icon-award',               i18nKey: 'landing.activityType.iomadcertificate' },
    ],
  },
  {
    i18nKey: 'landing.activityGroup.events',
    color: '#ec4899',
    types: [
      { mod: 'trainingevent', iconClass: 'icon-video', i18nKey: 'landing.activityType.trainingevent' },
    ],
  },
]
