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

export interface InlineData {
  kind: 'page' | 'book' | 'resource' | 'label' | 'unsupported'
  content?: string
  intro?: string
  empty?: boolean
  chapter?: InlineChapter
  file?: InlineFile
}

export type ActivityRender = 'inline' | 'iframe' | 'redirect' | null
