<template>
  <div v-if="open" class="smgp-modal">
    <div class="smgp-modal__backdrop" @click="$emit('close')" />
    <div class="smgp-modal__card">
      <div class="smgp-modal__header">
        <h3>
          <i
            :class="headerIcon"
            :style="{ color: headerColor, marginRight: '0.5rem' }"
            aria-hidden="true"
          />
          {{ headerTitle }}
        </h3>
        <button class="smgp-modal__close" @click="$emit('close')">
          <i class="icon-x" />
        </button>
      </div>

      <div class="smgp-modal__body">
        <!-- Activity name (shared across every layout) -->
        <div class="smgp-form-group">
          <label class="smgp-form-label">{{ $t('landing.activity_name_label') }}</label>
          <input
            ref="nameInput"
            v-model="name"
            type="text"
            class="form-control"
            :placeholder="$t('landing.activity_name_label')"
          >
        </div>

        <!-- ─── URL layout (genially / video / plain url) ───────────────── -->
        <template v-if="layout === 'url'">
          <!-- Tabs (only shown for video — Genially has a single URL flow) -->
          <div v-if="mode === 'video'" class="smgp-tab-row">
            <button
              type="button"
              class="smgp-tab"
              :class="{ 'smgp-tab--active': uploadMode === 'url' }"
              @click="uploadMode = 'url'"
            >
              {{ $t('landing.video_tab_url') }}
            </button>
            <button
              type="button"
              class="smgp-tab"
              :class="{ 'smgp-tab--active': uploadMode === 'upload' }"
              @click="uploadMode = 'upload'"
            >
              {{ $t('landing.video_tab_upload') }}
            </button>
          </div>

          <div v-if="mode !== 'video' || uploadMode === 'url'" class="smgp-form-group">
            <label class="smgp-form-label">{{ $t('landing.activity_url_label') }}</label>
            <input
              v-model="url"
              type="url"
              class="form-control"
              :placeholder="urlPlaceholder"
            >
            <small class="smgp-form-hint">{{ urlHint }}</small>
          </div>

          <div v-if="mode === 'video' && uploadMode === 'upload'" class="smgp-form-group">
            <div class="smgp-upload-zone smgp-upload-zone--decorative">
              <i class="icon-upload" aria-hidden="true" />
              <p>{{ $t('landing.video_upload_hint') }}</p>
            </div>
          </div>
        </template>

        <!-- ─── File layout (scorm / resource / folder / imscp / h5p) ────── -->
        <template v-else-if="layout === 'file'">
          <div class="smgp-form-group">
            <label class="smgp-form-label">{{ $t('restore.activity_file_label') || 'File' }}</label>
            <input
              ref="fileInput"
              type="file"
              class="form-control"
              :accept="fileAccept || ''"
              @change="onFilePick"
            >
            <small v-if="fileAccept" class="smgp-form-hint">{{ fileAccept }}</small>
            <div v-if="uploadedFilename" class="smgp-file-picked">
              <i class="icon-file" aria-hidden="true" />
              <span>{{ uploadedFilename }}</span>
              <span v-if="draftitemid" class="text-muted small">(staged #{{ draftitemid }})</span>
            </div>
            <div v-if="uploadError" class="alert alert-danger mt-2 mb-0 py-1 px-2 small">
              {{ uploadError }}
            </div>
            <div v-if="uploading" class="text-muted small mt-2">
              <i class="icon-loader" aria-hidden="true" /> {{ $t('restore.uploading') || 'Uploading…' }}
            </div>
          </div>
        </template>

        <!-- ─── Body layout (label / page) ──────────────────────────────── -->
        <template v-else-if="layout === 'body'">
          <div class="smgp-form-group">
            <label class="smgp-form-label">{{ $t('restore.activity_body_label') || 'Content' }}</label>
            <div class="smgp-rte">
              <div class="smgp-rte__toolbar">
                <button type="button" :title="$t('editor.rte_bold')" @click="rteExec('bold')"><i class="bi bi-type-bold" /></button>
                <button type="button" :title="$t('editor.rte_italic')" @click="rteExec('italic')"><i class="bi bi-type-italic" /></button>
                <button type="button" :title="$t('editor.rte_underline')" @click="rteExec('underline')"><i class="bi bi-type-underline" /></button>
                <button type="button" :title="$t('editor.rte_strike')" @click="rteExec('strikeThrough')"><i class="bi bi-type-strikethrough" /></button>
                <span class="smgp-rte__sep" />
                <button type="button" :title="$t('editor.rte_ul')" @click="rteExec('insertUnorderedList')"><i class="bi bi-list-ul" /></button>
                <button type="button" :title="$t('editor.rte_ol')" @click="rteExec('insertOrderedList')"><i class="bi bi-list-ol" /></button>
                <span class="smgp-rte__sep" />
                <button type="button" :title="$t('editor.rte_link')" @click="rteInsertLink"><i class="bi bi-link-45deg" /></button>
                <button type="button" :title="$t('editor.rte_clear')" @click="rteExec('removeFormat')"><i class="bi bi-eraser" /></button>
              </div>
              <div
                ref="rteEl"
                class="smgp-rte__body form-control"
                contenteditable="true"
                @input="onRteInput"
              />
            </div>
          </div>
        </template>

        <!-- ─── Deferred-config layout (quiz / assign / forum / ...) ────── -->
        <template v-else-if="layout === 'deferred'">
          <div class="smgp-form-group">
            <div class="alert alert-warning py-2 px-3 mb-0 small">
              <i class="bi bi-exclamation-triangle-fill" />
              {{ $t('restore.activity_deferred_hint') || 'This activity type will be created as a blank, pre-named module. Open it in Moodle after the restore finishes to configure it.' }}
            </div>
          </div>
        </template>
      </div>

      <div class="smgp-modal__footer">
        <button type="button" class="btn btn-secondary" @click="$emit('close')">
          {{ $t('landing.cancel') }}
        </button>
        <button
          v-if="mode === 'video' && layout === 'url' && uploadMode === 'upload'"
          type="button"
          class="btn btn-primary"
          @click="$emit('uploadFallback', { sectionnum })"
        >
          <i class="icon-external-link" aria-hidden="true" />
          {{ $t('landing.video_upload_continue') }}
        </button>
        <button
          v-else
          type="button"
          class="btn btn-primary"
          :disabled="!canSave || saving || uploading"
          @click="onSave"
        >
          <i class="icon-check" aria-hidden="true" />
          {{ $t('landing.save') }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

export interface ActivityModalInitial {
  name?: string
  url?: string
  intro?: string
  draftitemid?: number
  filename?: string
}

const props = defineProps<{
  open: boolean
  /**
   * Back-compat: landing.vue passes 'genially' | 'video'. The restore
   * wizard passes the layout directly as `layout` + `mode` set to either
   * 'genially' or 'video' for URL layouts, or the raw modname otherwise.
   */
  mode: 'genially' | 'video' | string
  /**
   * Which layout to render. Inferred from `mode` when omitted so the
   * existing landing.vue usage (`mode: 'video'`) keeps working.
   */
  layout?: 'url' | 'file' | 'body' | 'deferred'
  sectionnum: number
  saving?: boolean
  editing?: boolean
  fileAccept?: string
  /** Preload for edit mode. */
  initial?: ActivityModalInitial | null
}>()

const emit = defineEmits<{
  (e: 'close'): void
  (e: 'save', payload: {
    name: string
    url?: string
    intro?: string
    draftitemid?: number
    filename?: string
  }): void
  (e: 'uploadFallback', payload: { sectionnum: number }): void
}>()

const { t } = useI18n()

// Infer layout from mode when not explicitly given (landing.vue compat).
const layout = computed<'url' | 'file' | 'body' | 'deferred'>(() => {
  if (props.layout) return props.layout
  return 'url'
})

const name = ref('')
const url = ref('')
const intro = ref('')
const draftitemid = ref(0)
const uploadedFilename = ref('')
const uploading = ref(false)
const uploadError = ref('')
const uploadMode = ref<'url' | 'upload'>('url')
const nameInput = ref<HTMLInputElement | null>(null)
const fileInput = ref<HTMLInputElement | null>(null)
const rteEl = ref<HTMLDivElement | null>(null)

// ── URL layout helpers ───────────────────────────────────────────────
const urlPlaceholder = computed(() =>
  props.mode === 'video'
    ? 'https://www.youtube.com/watch?v=...'
    : props.mode === 'genially'
      ? 'https://view.genial.ly/...'
      : 'https://…',
)

const urlHint = computed(() => {
  if (props.mode === 'video') return t('landing.activity_url_hint_video')
  if (props.mode === 'genially') return t('landing.activity_url_hint_genially')
  return ''
})

// ── Header ───────────────────────────────────────────────────────────
const headerTitle = computed(() => {
  if (props.mode === 'video') return t('landing.add_activity_modal_title_video')
  if (props.mode === 'genially') return t('landing.add_activity_modal_title_genially')
  if (layout.value === 'file') return t('restore.add_activity_modal_title_file') || 'Add file activity'
  if (layout.value === 'body') return t('restore.add_activity_modal_title_label') || 'Add label / page'
  if (layout.value === 'deferred') return t('restore.add_activity_modal_title_deferred') || 'Add activity'
  return t('landing.add_activity')
})
const headerIcon = computed(() => {
  if (props.mode === 'video') return 'icon-film'
  if (props.mode === 'genially') return 'icon-presentation'
  if (layout.value === 'file') return 'icon-upload'
  if (layout.value === 'body') return 'icon-type'
  return 'icon-plus'
})
const headerColor = computed(() => {
  if (props.mode === 'video') return '#3b82f6'
  if (props.mode === 'genially') return '#f97316'
  if (layout.value === 'file') return '#10b981'
  if (layout.value === 'body') return '#7c3aed'
  return '#64748b'
})

// ── Save gating ──────────────────────────────────────────────────────
const canSave = computed(() => {
  const hasName = name.value.trim().length > 0
  if (!hasName) return false
  if (layout.value === 'url') return url.value.trim().length > 0
  if (layout.value === 'file') return draftitemid.value > 0
  if (layout.value === 'body') return true
  if (layout.value === 'deferred') return true
  return false
})

const onSave = () => {
  if (!canSave.value) return
  emit('save', {
    name: name.value.trim(),
    url: layout.value === 'url' ? url.value.trim() : undefined,
    intro: layout.value === 'body' ? intro.value : undefined,
    draftitemid: layout.value === 'file' ? draftitemid.value : undefined,
    filename: layout.value === 'file' ? uploadedFilename.value : undefined,
  })
}

// ── File upload ──────────────────────────────────────────────────────
async function onFilePick(e: Event) {
  const input = e.target as HTMLInputElement
  const file = input.files?.[0]
  if (!file) return
  uploadError.value = ''
  uploading.value = true

  const form = new FormData()
  form.append('file', file)
  form.append('sesskey', (window as unknown as { M?: { cfg?: { sesskey?: string } } }).M?.cfg?.sesskey ?? '')

  try {
    const wwwroot = (window as unknown as { M?: { cfg?: { wwwroot?: string } } }).M?.cfg?.wwwroot ?? ''
    const res = await fetch(`${wwwroot}/local/sm_graphics_plugin/pages/upload_activity_file.php`, {
      method: 'POST',
      body: form,
      credentials: 'same-origin',
    })
    const data = await res.json() as { success: boolean; draftitemid: number; filename: string; error: string }
    if (!data.success) {
      uploadError.value = data.error || 'Upload failed'
      draftitemid.value = 0
      uploadedFilename.value = ''
    } else {
      draftitemid.value = data.draftitemid
      uploadedFilename.value = data.filename
    }
  } catch (err) {
    uploadError.value = (err as Error).message
  } finally {
    uploading.value = false
  }
}

// ── Rich-text body editor ────────────────────────────────────────────
function syncRteFromIntro() {
  if (rteEl.value && rteEl.value.innerHTML !== intro.value) {
    rteEl.value.innerHTML = intro.value
  }
}
function onRteInput() {
  if (!rteEl.value) return
  intro.value = rteEl.value.innerHTML
}
function rteExec(cmd: string) {
  rteEl.value?.focus()
  document.execCommand(cmd, false)
  onRteInput()
}
function rteInsertLink() {
  const u = window.prompt('URL', 'https://')
  if (!u) return
  rteEl.value?.focus()
  document.execCommand('createLink', false, u)
  onRteInput()
}

// ── Reset / preload on open ──────────────────────────────────────────
watch(
  () => props.open,
  async (isOpen) => {
    if (!isOpen) return
    const init = props.initial ?? null
    name.value = init?.name ?? ''
    url.value = init?.url ?? ''
    intro.value = init?.intro ?? ''
    draftitemid.value = init?.draftitemid ?? 0
    uploadedFilename.value = init?.filename ?? ''
    uploadError.value = ''
    uploading.value = false
    uploadMode.value = 'url'
    await nextTick()
    if (layout.value === 'body') syncRteFromIntro()
    nameInput.value?.focus()
  },
)
</script>

<style scoped lang="scss">
.smgp-file-picked {
  margin-top: 0.5rem;
  display: flex;
  align-items: center;
  gap: 0.4rem;
  font-size: 0.85rem;
  color: #1e293b;
  i { color: #10b981; }
}
.smgp-rte {
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  overflow: hidden;
  background: #fff;
  &__toolbar {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.4rem 0.5rem;
    border-bottom: 1px solid #e2e8f0;
    background: #f8fafc;
    button {
      background: transparent;
      border: none;
      width: 28px;
      height: 28px;
      border-radius: 4px;
      color: #475569;
      cursor: pointer;
      font-size: 0.85rem;
      &:hover { background: #e2e8f0; }
    }
  }
  &__sep {
    width: 1px;
    height: 18px;
    background: #cbd5e1;
    margin: 0 0.25rem;
  }
  &__body {
    min-height: 140px;
    padding: 0.65rem 0.85rem;
    background: #fff !important;
    border: none !important;
    border-radius: 0 !important;
    outline: none;
    &:focus { box-shadow: none; }
  }
}
</style>
