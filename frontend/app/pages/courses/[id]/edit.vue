<template>
  <div v-if="loading" class="text-center py-5">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">{{ $t('app.loading') }}</span>
    </div>
  </div>

  <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

  <form v-else class="smgp-mgmt-page smgp-course-editor" @submit.prevent="onSubmit">
    <header class="smgp-mgmt-page__header smgp-course-editor__header">
      <NuxtLink
        :to="`/courses/${courseid || '0'}/landing`"
        class="btn btn-outline-secondary mt-1"
      >
        <i class="icon-arrow-left" />
      </NuxtLink>
      <div class="flex-grow-1">
        <h1 class="smgp-mgmt-page__title">
          {{ isNew ? ($t('editor.new_course') || 'New course') : ($t('editor.edit_course') || 'Edit course') }}
        </h1>
        <p class="smgp-mgmt-page__desc">
          {{ isNew
            ? ($t('editor.new_course_desc') || 'Create a new course or pill in the SmartMind catalogue.')
            : ($t('editor.edit_course_desc') || 'Update the course details, metadata and learning objectives.') }}
        </p>
      </div>
      <button type="submit" class="btn btn-primary" :disabled="saving">
        <i class="icon-save me-1" />
        {{ saving ? ($t('editor.saving') || 'Saving…') : ($t('editor.save') || 'Save course') }}
      </button>
    </header>

    <!-- ──────────────────────────────────────────────────────────── -->
    <!-- Section 1: Course details (core fields) -->
    <!-- ──────────────────────────────────────────────────────────── -->
    <h2 class="smgp-mgmt-page__section-title">
      <i class="icon-info" />
      {{ $t('editor.core_fields') || 'Core fields' }}
    </h2>
    <div class="smgp-editor-card">
      <div class="smgp-editor-grid">
        <div class="smgp-editor-field">
          <label>{{ $t('editor.fullname') || 'Full name' }}</label>
          <input v-model="form.fullname" type="text" class="form-control" required>
        </div>
        <div class="smgp-editor-field">
          <label>{{ $t('editor.shortname') || 'Short name' }}</label>
          <input v-model="form.shortname" type="text" class="form-control" required>
        </div>
        <div class="smgp-editor-field">
          <label>{{ $t('editor.moodle_category') || 'Moodle category' }}</label>
          <select v-model="form.categoryid" class="form-control" required>
            <option v-for="cat in data?.moodle_categories ?? []" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
          </select>
        </div>
        <div class="smgp-editor-field">
          <label>{{ $t('editor.idnumber') || 'ID number' }}</label>
          <input v-model="form.idnumber" type="text" class="form-control" maxlength="100">
        </div>
        <div class="smgp-editor-field">
          <label>{{ $t('editor.visible') || 'Visible' }}</label>
          <select v-model.number="form.visible" class="form-control">
            <option :value="1">{{ $t('editor.yes') || 'Yes' }}</option>
            <option :value="0">{{ $t('editor.no') || 'No' }}</option>
          </select>
        </div>
        <div class="smgp-editor-field">
          <label>{{ $t('editor.enablecompletion') || 'Completion tracking' }}</label>
          <select v-model.number="form.enablecompletion" class="form-control">
            <option :value="1">{{ $t('editor.yes') || 'Yes' }}</option>
            <option :value="0">{{ $t('editor.no') || 'No' }}</option>
          </select>
        </div>
        <div class="smgp-editor-field">
          <label>{{ $t('editor.format') || 'Course format' }}</label>
          <select v-model="form.format" class="form-control">
            <option value="topics">{{ $t('editor.format_topics') || 'Topics' }}</option>
            <option value="weekly">{{ $t('editor.format_weekly') || 'Weekly' }}</option>
            <option value="social">{{ $t('editor.format_social') || 'Social' }}</option>
            <option value="singleactivity">{{ $t('editor.format_singleactivity') || 'Single activity' }}</option>
          </select>
        </div>
        <div v-if="showNumsections" class="smgp-editor-field">
          <label>{{ $t('editor.numsections') || 'Number of sections' }}</label>
          <input v-model.number="form.numsections" type="number" min="1" max="52" class="form-control">
        </div>
        <div class="smgp-editor-field">
          <label>{{ $t('editor.startdate') || 'Start date' }}</label>
          <input
            type="date"
            class="form-control"
            :value="tsToDateString(form.startdate)"
            @input="form.startdate = dateStringToTs(($event.target as HTMLInputElement).value)"
          >
        </div>
        <div class="smgp-editor-field">
          <label>{{ $t('editor.enddate') || 'End date' }}</label>
          <input
            type="date"
            class="form-control"
            :value="tsToDateString(form.enddate)"
            @input="form.enddate = dateStringToTs(($event.target as HTMLInputElement).value)"
          >
        </div>
        <div class="smgp-editor-field smgp-editor-field--full">
          <label>{{ $t('editor.lang') || 'Force language' }}</label>
          <select v-model="form.lang" class="form-control">
            <option value="">{{ $t('editor.lang_default') || 'Use site default' }}</option>
            <option v-for="l in data?.languages ?? []" :key="l.code" :value="l.code">{{ l.name }}</option>
          </select>
        </div>
        <div class="smgp-editor-field smgp-editor-field--full">
          <label>{{ $t('editor.summary') || 'Summary' }}</label>
          <textarea v-model="form.summary" rows="4" class="form-control" />
        </div>
        <!-- Course image: full-width row with preview + file picker. -->
        <div class="smgp-editor-field smgp-editor-field--full">
          <label>{{ $t('editor.course_image') || 'Course image' }}</label>
          <div class="smgp-editor-image-row">
            <div class="smgp-editor-image-preview">
              <img
                v-if="newImagePreview || currentImageUrl"
                :src="newImagePreview || currentImageUrl"
                alt=""
              >
              <span v-else class="smgp-editor-image-placeholder">
                <i class="icon-image" />
              </span>
            </div>
            <div class="smgp-editor-image-controls">
              <input
                type="file"
                accept="image/jpeg,image/png,image/webp,image/gif"
                class="form-control"
                @change="onImageChange"
              >
              <small class="form-text text-muted">
                {{ $t('editor.course_image_help') || 'JPEG, PNG, WebP or GIF. The picked file replaces the current image when you save.' }}
              </small>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ──────────────────────────────────────────────────────────── -->
    <!-- Section 2: SmartMind metadata (component supplies its own card) -->
    <!-- ──────────────────────────────────────────────────────────── -->
    <h2 class="smgp-mgmt-page__section-title">
      <i class="icon-settings" />
      {{ $t('editor.metadata') || 'SmartMind metadata' }}
    </h2>
    <MetadataFields v-model="metaModel" :categories="data?.smgp_categories ?? []" />

    <!-- ──────────────────────────────────────────────────────────── -->
    <!-- Section 3: Learning objectives (component supplies its own card) -->
    <!-- ──────────────────────────────────────────────────────────── -->
    <h2 class="smgp-mgmt-page__section-title">
      <i class="icon-list-checks" />
      {{ $t('editor.objectives') || 'Learning objectives' }}
    </h2>
    <ObjectivesEditor v-model="objectivesModel" />

    <!-- Save button repeated at the bottom for long-form ergonomics. -->
    <div class="smgp-editor-actions">
      <button type="submit" class="btn btn-primary" :disabled="saving">
        <i class="icon-save me-1" />
        {{ saving ? ($t('editor.saving') || 'Saving…') : ($t('editor.save') || 'Save course') }}
      </button>
    </div>

    <div v-if="saveError" class="alert alert-danger mt-3">{{ saveError }}</div>
  </form>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useMoodleAjax } from '~/composables/api_calls/useMoodleAjax'
import ObjectivesEditor from '~/components/editor/ObjectivesEditor.vue'
import MetadataFields from '~/components/editor/MetadataFields.vue'

definePageMeta({ middleware: ['auth'] })

const route = useRoute()
const router = useRouter()
const { call } = useMoodleAjax()

const courseid = computed(() => Number(route.params.id) || 0)
const isNew = computed(() => courseid.value === 0)

const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<any>(null)

const form = reactive({
  fullname: '',
  shortname: '',
  summary: '',
  categoryid: 0,
  startdate: 0,
  enddate: 0,
  visible: 1,
  idnumber: '',
  enablecompletion: 1,
  format: 'topics',
  numsections: 1,
  lang: '',
})

// Course image picker state — we keep the currently-saved image URL
// (for the preview when nothing has been picked yet) and the new
// File the user just selected (which gets base64-encoded on submit).
const currentImageUrl = ref<string>('')
const newImageFile = ref<File | null>(null)
const newImagePreview = ref<string>('')

// "Numsections" only applies to topics/weekly course formats — the
// input is hidden for the other two formats so it doesn't confuse
// the admin.
const showNumsections = computed(() => form.format === 'topics' || form.format === 'weekly')

const onImageChange = (e: Event) => {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) {
    newImageFile.value = null
    newImagePreview.value = ''
    return
  }
  newImageFile.value = file
  // Local data URL preview — never round-trips through the server.
  const reader = new FileReader()
  reader.onload = (ev) => {
    newImagePreview.value = (ev.target?.result as string) ?? ''
  }
  reader.readAsDataURL(file)
}

// Read a File and return the raw base64 (no data URL prefix). Used
// to ship the new course image to update_course_full as a string
// param. Falls back to '' on read failure.
const fileToBase64 = (file: File): Promise<string> =>
  new Promise((resolve) => {
    const reader = new FileReader()
    reader.onload = () => {
      const result = reader.result as string
      // dataURL is "data:image/jpeg;base64,XXXXX..." — strip prefix.
      const comma = result.indexOf(',')
      resolve(comma >= 0 ? result.substring(comma + 1) : '')
    }
    reader.onerror = () => resolve('')
    reader.readAsDataURL(file)
  })

// Date helpers — Moodle stores course dates as Unix timestamps but
// HTML5 <input type="date"> works in YYYY-MM-DD strings, so we
// convert at the boundary.
const tsToDateString = (ts: number): string => {
  if (!ts) return ''
  const d = new Date(ts * 1000)
  if (isNaN(d.getTime())) return ''
  const yyyy = d.getFullYear()
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const dd = String(d.getDate()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}`
}

const dateStringToTs = (s: string): number => {
  if (!s) return 0
  const d = new Date(s + 'T00:00:00')
  return isNaN(d.getTime()) ? 0 : Math.floor(d.getTime() / 1000)
}

// HTML helpers — Moodle stores course summary as HTML (FORMAT_HTML) so
// it usually arrives wrapped in <p>...</p>. The form uses a plain
// <textarea> which would render those tags literally, so we strip them
// for display and re-wrap on save.
const stripHtml = (html: string): string => {
  if (!html) return ''
  const tmp = document.createElement('div')
  tmp.innerHTML = html
  return (tmp.textContent || tmp.innerText || '').trim()
}

const wrapAsHtml = (text: string): string => {
  const trimmed = (text ?? '').trim()
  if (!trimmed) return ''
  // If the user already pasted block-level HTML (uncommon from a plain
  // textarea but possible), leave it alone.
  if (/^<(p|div|h\d|ul|ol|blockquote)/i.test(trimmed)) return trimmed
  // Escape angle brackets and quotes so the user's text can't break the
  // surrounding <p>, then convert newlines to <br> for visual fidelity.
  const escaped = trimmed
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/\n/g, '<br>')
  return `<p>${escaped}</p>`
}

const metaModel = reactive({
  duration_hours: 0,
  level: 'beginner',
  completion_percentage: 100,
  is_pill: 0,
  smartmind_code: '',
  sepe_code: '',
  description: '',
  course_category: 0,
})

const objectivesModel = ref<string[]>([])

const saving = ref(false)
const saveError = ref<string | null>(null)

async function fetchData() {
  loading.value = true
  error.value = null
  const result = await call('local_sm_graphics_plugin_get_course_edit_data', { courseid: courseid.value })
  if (result.error) {
    error.value = result.error
  } else {
    data.value = result.data
    Object.assign(form, result.data.core)
    Object.assign(metaModel, result.data.meta)
    // Both summary and meta.description are stored as HTML — strip tags
    // for the textarea so the user sees plain text instead of literal
    // <p>...</p>. They get re-wrapped on submit.
    form.summary = stripHtml(result.data.core?.summary ?? '')
    metaModel.description = stripHtml(result.data.meta?.description ?? '')
    objectivesModel.value = (result.data.objectives || []).map((o: any) => o.text)
    currentImageUrl.value = result.data.core?.courseimage_url ?? ''
    newImageFile.value = null
    newImagePreview.value = ''
  }
  loading.value = false
}

async function onSubmit() {
  saving.value = true
  saveError.value = null

  // If the user picked a new image, encode it. Otherwise we send empty
  // strings and the backend leaves the existing overviewfile alone.
  let imageFilename = ''
  let imageBase64 = ''
  if (newImageFile.value) {
    imageFilename = newImageFile.value.name
    imageBase64 = await fileToBase64(newImageFile.value)
  }

  const result = await call('local_sm_graphics_plugin_update_course_full', {
    courseid: courseid.value,
    fullname: form.fullname,
    shortname: form.shortname,
    summary: wrapAsHtml(form.summary),
    categoryid: form.categoryid,
    startdate: form.startdate,
    enddate: form.enddate,
    visible: form.visible,
    idnumber: form.idnumber,
    enablecompletion: form.enablecompletion,
    format: form.format,
    numsections: form.numsections,
    lang: form.lang,
    image_filename: imageFilename,
    image_base64: imageBase64,
    duration_hours: metaModel.duration_hours,
    level: metaModel.level,
    completion_percentage: metaModel.completion_percentage,
    is_pill: metaModel.is_pill,
    smartmind_code: metaModel.smartmind_code,
    sepe_code: metaModel.sepe_code,
    description: wrapAsHtml(metaModel.description),
    course_category: metaModel.course_category,
    objectives_json: JSON.stringify(objectivesModel.value),
    translate: true,
  })
  saving.value = false
  if (result.error) {
    saveError.value = result.error
  } else if (result.data?.success) {
    router.push(`/courses/${result.data.courseid}/landing`)
  }
}

fetchData()
</script>

<style scoped lang="scss">
// Layout uses .smgp-mgmt-page from _management.scss for the wrapper +
// header + section titles. Only need scoped rules for the bits that
// don't have a global equivalent yet (the form card, the grid, the
// image picker).

.smgp-course-editor {
  // Tweak the shared header so the back button + title + save button
  // sit in a single row with breathing room. The .smgp-mgmt-page__header
  // is just `margin-bottom: 1.5rem` by default — we layer flex on top.
  &__header {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
  }
}

// White card wrapping the core fields grid. Mirrors the visual weight
// of MetadataFields/ObjectivesEditor (which supply their own cards) so
// the three sections look like siblings.
.smgp-editor-card {
  background: #fff;
  border: 1px solid #f3f4f6;
  border-radius: 14px;
  padding: 1.25rem 1.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.smgp-editor-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.85rem 1rem;

  @media (max-width: 700px) {
    grid-template-columns: 1fr;
  }
}

.smgp-editor-field {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;

  label {
    font-weight: 600;
    font-size: 0.85rem;
    color: #1e293b;
  }

  // Inputs lose the harsh Bootstrap border in favor of the SmartMind
  // pastel grey + green focus ring used elsewhere.
  :deep(.form-control) {
    border-color: #e5e7eb;
    border-radius: 8px;

    &:focus {
      border-color: #10b981;
      box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12);
    }
  }

  // Span both columns when the content needs the full row width.
  &--full {
    grid-column: 1 / -1;
  }
}

.smgp-editor-image-row {
  display: flex;
  gap: 1rem;
  align-items: flex-start;

  @media (max-width: 600px) {
    flex-direction: column;
  }
}

.smgp-editor-image-preview {
  flex: 0 0 200px;
  width: 200px;
  height: 120px;
  border: 1px dashed #cbd5e1;
  border-radius: 8px;
  background: #f8fafc;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;

  img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
}

.smgp-editor-image-placeholder {
  color: #94a3b8;
  font-size: 2rem;
}

.smgp-editor-image-controls {
  flex: 1;
  min-width: 0;
}

.smgp-editor-actions {
  margin-top: 1.5rem;
  display: flex;
  justify-content: flex-end;
}
</style>
