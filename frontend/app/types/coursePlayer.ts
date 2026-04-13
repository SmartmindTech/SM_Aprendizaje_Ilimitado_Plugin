// Structured payload returned by local_sm_graphics_plugin_get_activity_content
// for inline render mode. The Vue components render all chrome from this data;
// the legacy `html` field on the response is ignored (kept on the backend
// only for the AMD frontend until that goes away).

export interface InlineFile {
  url: string
  name: string
  size: string
  mimetype: string
  kind: 'image' | 'pdf' | 'document' | 'video' | 'audio' | 'other'
}

export interface InlineChapter {
  title: string
  current: number
  total: number
}

export interface BookChapter {
  title: string
  content: string
}

export interface GlossaryEntry {
  id: number
  concept: string
  definition: string
}

// ── Folder ──────────────────────────────────────────────────────────────
export interface FolderFile {
  url: string
  name: string
  path: string
  size: string
  mimetype: string
  icon: string
}

// ── Choice ──────────────────────────────────────────────────────────────
export interface ChoiceOption {
  id: number
  text: string
  selected: boolean
}

export interface ChoiceResult {
  optionid: number
  text: string
  count: number
}

// ── Survey ──────────────────────────────────────────────────────────────
export interface SurveyQuestion {
  id: number
  text: string
  shorttext?: string
  type: number
  options?: string
}

// ── Feedback ────────────────────────────────────────────────────────────
export interface FeedbackItem {
  id: number
  typ: string
  name: string
  label: string
  required: boolean
  options: string
  dependitem: number
  dependvalue: string
  position: number
}

// ── Wiki ────────────────────────────────────────────────────────────────
export interface WikiPage {
  id: number
  title: string
  content: string
  timemodified: number
  userid: number
}

export interface WikiPageRef {
  id: number
  title: string
}

// ── Data (database) ─────────────────────────────────────────────────────
export interface DataField {
  id: number
  name: string
  description: string
  type: string
  required: boolean
  param1: string
  param2: string
  param3: string
}

export interface DataEntry {
  id: number
  userid: number
  userfullname: string
  timecreated: number
  fields: Record<number, { content: string; content1: string; content2: string; content3: string; content4: string }>
}

// ── Quiz ────────────────────────────────────────────────────────────────
export interface QuizChoice {
  value: number
  label: string
  checked: boolean
}

export interface QuizStem {
  key: number
  text: string
}

export interface QuizQuestion {
  slot: number
  type: string
  text: string
  sequencecheck: number
  flagged: boolean
  hasresponse: boolean
  // Type-specific
  choices?: QuizChoice[]
  single?: boolean
  inputtype?: string
  responseformat?: string
  attachments?: number
  stems?: QuizStem[]
  matchoptions?: QuizChoice[]
  isinfo?: boolean
  savedresponse?: Record<string, string>
}

// ── Assignment ──────────────────────────────────────────────────────────
export interface AssignFileSubmission {
  name: string
  size: string
  url: string
}

// ── Lesson ──────────────────────────────────────────────────────────────
export interface LessonAnswer {
  id: number
  text: string
  jumpto: number
}

export interface LessonPage {
  id: number
  title: string
  content: string
  type: number
  typelabel: string
}

// ── Workshop ────────────────────────────────────────────────────────────
export interface WorkshopSubmission {
  id: number
  title: string
  content: string
  grade?: number | null
  timecreated: number
}

export interface WorkshopAssessment {
  id: number
  submissionid: number
  submissiontitle: string
  grade?: number | null
  feedbackauthor: string
}

// ── Main InlineData union ───────────────────────────────────────────────
export type ActivityKind =
  | 'page' | 'book' | 'resource' | 'label' | 'url' | 'glossary'
  | 'folder' | 'choice' | 'survey' | 'feedback' | 'wiki' | 'data'
  | 'quiz' | 'assign' | 'lesson' | 'workshop' | 'scorm'
  | 'unsupported'

export interface InlineData {
  kind: ActivityKind
  content?: string
  intro?: string
  empty?: boolean
  name?: string

  // Book
  chapter?: InlineChapter
  allchapters?: BookChapter[]
  viewedcount?: number

  // Resource
  file?: InlineFile

  // URL
  url?: string
  embedurl?: string
  urlkind?: 'link' | 'embed'

  // Glossary entries / Data entries (discriminated by `kind`)
  entries?: any[]

  // Folder
  files?: FolderFile[]

  // Choice
  choiceid?: number
  text?: string
  allowupdate?: boolean
  allowmultiple?: boolean
  hasanswered?: boolean
  isclosed?: boolean
  options?: ChoiceOption[]
  results?: ChoiceResult[]

  // Survey / Quiz questions (discriminated by `kind`)
  surveyid?: number
  questions?: any[]
  done?: boolean

  // Feedback
  feedbackid?: number
  anonymous?: boolean
  iscomplete?: boolean
  // Feedback pages / Wiki page list (discriminated by `kind`)
  pages?: any
  totalpages?: number
  savedvalues?: Record<number, string>

  // Wiki
  wikiid?: number
  wikimode?: string
  firstpage?: string
  // Wiki page content / Lesson current page (discriminated by `kind`)
  page?: any

  // Data (database)
  dataid?: number
  fields?: DataField[]
  totalentries?: number
  canaddentry?: boolean

  // Quiz
  quizid?: number
  attemptsallowed?: number
  attemptsused?: number
  timelimit?: number
  grademethod?: number
  state?: 'notstarted' | 'inprogress' | 'finished'
  attemptid?: number
  currentpage?: number
  timestarted?: number
  canstartnew?: boolean
  lastattemptid?: number
  grade?: number | null
  reviewavailable?: boolean

  // Assignment
  assignid?: number
  duedate?: number
  cutoffdate?: number
  submissiontypes?: string[]
  maxfilesubmissions?: number
  maxsubmissionsizebytes?: number
  attemptreopenmethod?: string
  maxattempts?: number
  submissionstatus?: string
  submissionid?: number | null
  onlinetext?: string
  filesubmissions?: AssignFileSubmission[]
  gradevalue?: number | null
  grademax?: number
  feedbackcomments?: string
  isgraded?: boolean

  // Lesson
  lessonid?: number
  answers?: LessonAnswer[]

  // SCORM
  scormid?: number

  // Workshop
  workshopid?: number
  phase?: string
  phasecode?: number
  cansubmit?: boolean
  canassess?: boolean
  submission?: WorkshopSubmission | null
  assessments?: WorkshopAssessment[]
}

export type ActivityRender = 'inline' | 'iframe' | 'redirect' | null
