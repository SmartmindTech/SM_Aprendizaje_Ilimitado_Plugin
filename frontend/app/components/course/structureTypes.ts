/**
 * Shared types, constants and helpers for the course structure editor
 * used by both the restore wizard and the create-course page.
 */

// ── Activity / Section interfaces ────────────────────────────────────

export interface SchemaActivity {
  actKey: string
  cmid: number
  modname: string
  name: string
  origName: string
  included: boolean
  userinfo: boolean
  userinfoAvailable: boolean
  /** External URL for mod_url / Genially / Video layouts. */
  url?: string
  /** Draft file itemid staged via upload_activity_file.php. */
  draftitemid?: number
  /** Display name of the uploaded file (for the row UI). */
  filename?: string
  /** Rich-text body for mod_label / mod_page. */
  intro?: string
  /** `true` for types the wizard creates blank and the admin finishes
   *  in Moodle's native mod form after the restore. */
  deferred?: boolean
}

export interface SpExtraDropped {
  id: string
  type: string
  name: string
  size: number
  icon: string
  colorHex: string
  badgeBg: string
  label: string
}

export interface SchemaSection {
  sectionKey: string
  section_id: number
  section_number: number
  name: string
  origName: string
  included: boolean
  userinfo: boolean
  userinfoAvailable: boolean
  activities: SchemaActivity[]
  /** SharePoint extras dropped onto this section (restore-only). */
  spExtras: SpExtraDropped[]
}

// ── Modal / confirm state ────────────────────────────────────────────

export interface AddModalState {
  open: boolean
  /** Header hint — 'genially' | 'video' for URL presets, or the raw modname. */
  mode: string
  layout: 'url' | 'file' | 'body' | 'deferred'
  sectionKey: string
  /** The modname to persist on the activity when saved. */
  modname: string
  editing: boolean
  /** actKey of the activity being edited (empty when creating). */
  editKey: string
  fileAccept: string
  initial: {
    name?: string
    url?: string
    intro?: string
    draftitemid?: number
    filename?: string
  } | null
}

export interface DeleteTarget {
  kind: 'section' | 'activity'
  name: string
  sec: SchemaSection
  act?: SchemaActivity
}

// ── Module icon + label map ──────────────────────────────────────────

export const MOD_META: Record<string, { icon: string; label: string }> = {
  forum:       { icon: 'bi-chat-square-text',  label: 'Foro' },
  scorm:       { icon: 'bi-box',               label: 'Paquete SCORM' },
  quiz:        { icon: 'bi-list-check',        label: 'Cuestionario' },
  assign:      { icon: 'bi-clipboard-check',   label: 'Tarea' },
  resource:    { icon: 'bi-file-earmark',      label: 'Archivo' },
  url:         { icon: 'bi-link-45deg',        label: 'URL' },
  page:        { icon: 'bi-file-earmark-text', label: 'Página' },
  book:        { icon: 'bi-book',              label: 'Libro' },
  folder:      { icon: 'bi-folder',            label: 'Carpeta' },
  label:       { icon: 'bi-card-text',         label: 'Etiqueta' },
  glossary:    { icon: 'bi-journal-text',      label: 'Glosario' },
  wiki:        { icon: 'bi-card-list',         label: 'Wiki' },
  choice:      { icon: 'bi-check2-square',     label: 'Encuesta' },
  feedback:    { icon: 'bi-chat-dots',         label: 'Retroalimentación' },
  lesson:      { icon: 'bi-mortarboard',       label: 'Lección' },
  workshop:    { icon: 'bi-people-fill',       label: 'Taller' },
  h5pactivity: { icon: 'bi-puzzle',            label: 'Actividad H5P' },
  genially:    { icon: 'bi-easel',             label: 'Genially' },
}

// ── Helpers ──────────────────────────────────────────────────────────

export function sectionDisplayName(sec: SchemaSection): string {
  if (sec.name && !/^\d+$/.test(sec.name.trim())) return sec.name
  return `Sección ${sec.section_number}`
}

export function activityCountLabel(n: number): string {
  return n === 1 ? 'actividad' : 'actividades'
}

export function modIcon(modname: string): string {
  return MOD_META[modname]?.icon ?? 'bi-app'
}

export function modLabel(modname: string): string {
  return MOD_META[modname]?.label ?? modname
}

/** Default AddModalState for initialising refs. */
export function defaultAddModalState(): AddModalState {
  return {
    open: false,
    mode: 'genially',
    layout: 'url',
    sectionKey: '',
    modname: 'genially',
    editing: false,
    editKey: '',
    fileAccept: '',
    initial: null,
  }
}

/** Create a blank SchemaSection for the "add section" button. */
export function createBlankSection(existingSections: SchemaSection[]): SchemaSection {
  const nextNum = (existingSections[existingSections.length - 1]?.section_number ?? -1) + 1
  return {
    sectionKey: `new-${Date.now()}`,
    section_id: 0,
    section_number: nextNum,
    name: '',
    origName: '',
    included: true,
    userinfo: false,
    userinfoAvailable: false,
    activities: [],
    spExtras: [],
  }
}

/** Serialise a sections array into the JSON shape the backend expects. */
export function serializeStructure(sections: SchemaSection[]): string {
  return JSON.stringify(
    sections.map(s => ({
      sectionKey: s.sectionKey,
      section_id: s.section_id,
      section_number: s.section_number,
      name: s.name,
      origName: s.origName,
      included: s.included,
      userinfo: s.userinfo,
      activities: s.activities.map(a => ({
        actKey: a.actKey,
        cmid: a.cmid,
        modname: a.modname,
        name: a.name,
        origName: a.origName,
        included: a.included,
        userinfo: a.userinfo,
        url: a.url ?? '',
        intro: a.intro ?? '',
        draftitemid: a.draftitemid ?? 0,
        filename: a.filename ?? '',
        deferred: !!a.deferred,
      })),
      spExtras: (s.spExtras ?? []).map(x => ({
        type: x.type,
        name: x.name,
        size: x.size,
      })),
    })),
  )
}
