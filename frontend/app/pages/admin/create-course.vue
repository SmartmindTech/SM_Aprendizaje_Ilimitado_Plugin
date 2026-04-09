<template>
  <div class="smgp-mgmt-page smgp-create-course">

    <header class="smgp-create-course__header">
      <NuxtLink to="/management/courses" class="btn smgp-back-btn">
        <i class="icon-arrow-left" />
      </NuxtLink>
      <div>
        <h1 class="smgp-mgmt-page__title mb-0">{{ $t('editor.create_course_title') }}</h1>
        <p class="smgp-mgmt-page__desc mb-0">{{ $t('editor.create_course_desc') }}</p>
      </div>
    </header>

    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-success" role="status" />
    </div>

    <form v-else @submit.prevent="onSubmit">

      <!-- ─── Card 1: Course information ──────────────────────── -->
      <div class="smgp-editor-card">
        <h3 class="smgp-create-course__card-title">
          <i class="bi bi-info-square" /> {{ $t('restore.course_info') || 'Course information' }}
        </h3>

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
            <label>{{ $t('editor.visible') || 'Visible' }} <i class="bi bi-info-circle smgp-editor-tip" :title="$t('editor.tip_visible')" /></label>
            <select v-model.number="form.visible" class="form-control">
              <option :value="1">{{ $t('editor.yes') || 'Yes' }}</option>
              <option :value="0">{{ $t('editor.no') || 'No' }}</option>
            </select>
          </div>
          <div class="smgp-editor-field">
            <label>{{ $t('editor.enablecompletion') || 'Completion tracking' }} <i class="bi bi-info-circle smgp-editor-tip" :title="$t('editor.tip_enablecompletion')" /></label>
            <select v-model.number="form.enablecompletion" class="form-control">
              <option :value="1">{{ $t('editor.yes') || 'Yes' }}</option>
              <option :value="0">{{ $t('editor.no') || 'No' }}</option>
            </select>
          </div>
          <div class="smgp-editor-field">
            <label>{{ $t('editor.format') || 'Course format' }} <i class="bi bi-info-circle smgp-editor-tip" :title="$t('editor.tip_format')" /></label>
            <select v-model="form.format" class="form-control">
              <option value="topics">{{ $t('editor.format_topics') || 'Topics' }}</option>
              <option value="weekly">{{ $t('editor.format_weekly') || 'Weekly' }}</option>
              <option value="social">{{ $t('editor.format_social') || 'Social' }}</option>
              <option value="singleactivity">{{ $t('editor.format_singleactivity') || 'Single activity' }}</option>
            </select>
          </div>
          <div class="smgp-editor-field">
            <label>{{ $t('editor.lang') || 'Force language' }} <i class="bi bi-info-circle smgp-editor-tip" :title="$t('editor.tip_lang')" /></label>
            <select v-model="form.lang" class="form-control">
              <option value="">{{ $t('editor.lang_default') || 'Use site default' }}</option>
              <option v-for="l in languages" :key="l.code" :value="l.code">{{ l.name }}</option>
            </select>
          </div>
          <div class="smgp-editor-field">
            <label>{{ $t('editor.startdate') || 'Start date' }} <i class="bi bi-info-circle smgp-editor-tip" :title="$t('editor.tip_startdate')" /></label>
            <input v-model="form.startdate" type="date" class="form-control">
          </div>
          <div class="smgp-editor-field">
            <label>{{ $t('editor.enddate') || 'End date' }} <i class="bi bi-info-circle smgp-editor-tip" :title="$t('editor.tip_enddate')" /></label>
            <input v-model="form.enddate" type="date" class="form-control">
          </div>

          <!-- Companies -->
          <div class="smgp-editor-field smgp-editor-field--full">
            <div class="smgp-editor-companies-header">
              <label>{{ $t('editor.companies') || 'Companies' }} <i class="bi bi-info-circle smgp-editor-tip" :title="$t('editor.tip_companies')" /></label>
              <input
                v-model="companySearch"
                type="text"
                class="form-control form-control-sm smgp-editor-companies-search"
                :placeholder="$t('courseloader.company_search_placeholder') || 'Search company...'"
              >
            </div>
            <div class="smgp-editor-companies">
              <div v-for="c in filteredCompanies" :key="c.id" class="form-check">
                <input :id="`co-${c.id}`" v-model="selectedCompanyIds" class="form-check-input" type="checkbox" :value="c.id">
                <label class="form-check-label" :for="`co-${c.id}`">
                  {{ c.name }} <span class="text-muted small">({{ c.shortname }})</span>
                </label>
              </div>
              <p v-if="!filteredCompanies.length" class="text-muted small mb-0">
                {{ $t('courseloader.companies_empty') || 'No companies found.' }}
              </p>
            </div>
          </div>

          <!-- Course image -->
          <div class="smgp-editor-field smgp-editor-field--full">
            <label>{{ $t('editor.course_image') || 'Course image' }} <i class="bi bi-info-circle smgp-editor-tip" :title="$t('editor.tip_course_image')" /></label>
            <div class="smgp-editor-image-row">
              <div class="smgp-editor-image-preview">
                <img v-if="imagePreview" :src="imagePreview" alt="">
                <span v-else class="smgp-editor-image-placeholder"><i class="icon-image" /></span>
              </div>
              <div class="smgp-editor-image-controls">
                <input type="file" accept="image/jpeg,image/png,image/webp,image/gif" class="form-control" @change="onImageChange">
                <small class="form-text text-muted">{{ $t('editor.course_image_help') }}</small>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ─── Card 2: SmartMind metadata ──────────────────────── -->
      <MetadataFields
        :model-value="metaModel"
        :categories="smgpCategories"
        @update:model-value="onMetaUpdate"
      />

      <!-- ─── Card 3: Learning objectives ─────────────────────── -->
      <ObjectivesEditor v-model="objectives" />

      <!-- ─── Card 4: Course structure ────────────────────────── -->
      <div class="smgp-editor-card">
        <h3 class="smgp-create-course__card-title">
          <i class="bi bi-stack" /> {{ $t('editor.course_structure') || 'Course structure' }}
        </h3>
        <p class="text-muted small mb-3">{{ $t('editor.course_structure_hint') }}</p>
        <CourseStructureEditor
          v-model="structure"
          :hide-deferred-badge="true"
          :deferred-hint="$t('editor.create_deferred_hint')"
        />
      </div>

      <!-- ─── Actions ─────────────────────────────────────────── -->
      <div class="smgp-create-course__actions">
        <NuxtLink to="/management/courses" class="btn smgp-back-btn">
          ← {{ $t('restore.back') || 'Back' }}
        </NuxtLink>
        <button type="submit" class="btn btn-success smgp-create-course__save" :disabled="saving || !form.fullname || !form.shortname">
          <i v-if="saving" class="spinner-border spinner-border-sm me-1" />
          <i v-else class="bi bi-check-lg me-1" />
          {{ saving ? ($t('editor.saving') || 'Saving…') : ($t('editor.create_course_title') || 'Create course') }}
        </button>
      </div>

      <div v-if="saveError" class="alert alert-danger mt-3">{{ saveError }}</div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useMoodleAjax } from '~/composables/api_calls/useMoodleAjax'
import MetadataFields from '~/components/editor/MetadataFields.vue'
import ObjectivesEditor from '~/components/editor/ObjectivesEditor.vue'
import CourseStructureEditor from '~/components/course/CourseStructureEditor.vue'
import { type SchemaSection, serializeStructure } from '~/components/course/structureTypes'

definePageMeta({ middleware: ['auth'] })

const router = useRouter()
const { call } = useMoodleAjax()

const loading = ref(true)
const saving = ref(false)
const saveError = ref<string | null>(null)

// ── Form state ───────────────────────────────────────────────────────
const form = reactive({
  fullname: '',
  shortname: '',
  visible: 1,
  enablecompletion: 1,
  format: 'topics',
  lang: '',
  startdate: '',
  enddate: '',
})

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
function onMetaUpdate(next: Partial<typeof metaModel>) {
  Object.assign(metaModel, next)
}

const objectives = ref<string[]>([])
const structure = ref<SchemaSection[]>([])

// ── Companies ────────────────────────────────────────────────────────
const selectedCompanyIds = ref<number[]>([])
const companySearch = ref('')
const allCompanies = ref<Array<{ id: number; name: string; shortname: string }>>([])
const filteredCompanies = computed(() => {
  const q = companySearch.value.trim().toLowerCase()
  if (!q) return allCompanies.value
  return allCompanies.value.filter(c =>
    c.name.toLowerCase().includes(q) || c.shortname.toLowerCase().includes(q),
  )
})

// ── Categories + languages ───────────────────────────────────────────
const smgpCategories = ref<Array<{ id: number; name: string }>>([])
const languages = ref<Array<{ code: string; name: string }>>([])

// ── Image ────────────────────────────────────────────────────────────
const imageFile = ref<File | null>(null)
const imagePreview = ref('')

function onImageChange(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0]
  if (!file) { imageFile.value = null; imagePreview.value = ''; return }
  imageFile.value = file
  const reader = new FileReader()
  reader.onload = () => { imagePreview.value = reader.result as string }
  reader.readAsDataURL(file)
}

function fileToBase64(file: File): Promise<string> {
  return new Promise((resolve) => {
    const r = new FileReader()
    r.onload = () => resolve((r.result as string).replace(/^data:[^;]+;base64,/, ''))
    r.readAsDataURL(file)
  })
}

// ── Load reference data ──────────────────────────────────────────────
async function loadData() {
  loading.value = true
  const result = await call<{
    smgp_categories: Array<{ id: number; name: string }>
    languages: Array<{ code: string; name: string }>
    companies: Array<{ id: number; name: string; shortname: string }>
  }>('local_sm_graphics_plugin_get_course_edit_data', { courseid: 0 })
  if (!result.error && result.data) {
    smgpCategories.value = result.data.smgp_categories ?? []
    languages.value = result.data.languages ?? []
    allCompanies.value = result.data.companies ?? []
  }
  loading.value = false
}
loadData()

// ── Submit ───────────────────────────────────────────────────────────
async function onSubmit() {
  saving.value = true
  saveError.value = null

  let imageFilename = ''
  let imageBase64 = ''
  if (imageFile.value) {
    imageFilename = imageFile.value.name
    imageBase64 = await fileToBase64(imageFile.value)
  }

  const startTs = form.startdate ? Math.floor(new Date(form.startdate).getTime() / 1000) : 0
  const endTs = form.enddate ? Math.floor(new Date(form.enddate).getTime() / 1000) : 0

  const result = await call<{ success: boolean; courseid: number }>('local_sm_graphics_plugin_update_course_full', {
    courseid: 0,
    fullname: form.fullname,
    shortname: form.shortname,
    summary: metaModel.description,
    categoryid: 1, // Moodle category (required, default Miscellaneous)
    startdate: startTs,
    enddate: endTs,
    visible: form.visible,
    idnumber: '',
    enablecompletion: form.enablecompletion,
    format: form.format,
    numsections: 1,
    lang: form.lang,
    image_filename: imageFilename,
    image_base64: imageBase64,
    duration_hours: metaModel.duration_hours,
    level: metaModel.level,
    completion_percentage: metaModel.completion_percentage,
    is_pill: metaModel.is_pill,
    smartmind_code: metaModel.smartmind_code,
    sepe_code: metaModel.sepe_code,
    description: metaModel.description,
    course_category: metaModel.course_category,
    objectives_json: JSON.stringify(objectives.value),
    companyids: selectedCompanyIds.value.join(','),
    translate: true,
    course_structure_json: serializeStructure(structure.value),
  })

  saving.value = false
  if (result.error) {
    saveError.value = result.error
  } else if (result.data?.success) {
    router.push(`/courses/${result.data.courseid}/landing`)
  }
}
</script>

<style scoped lang="scss">
.smgp-create-course {
  &__header {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    margin-bottom: 1.25rem;
  }
  &__card-title {
    font-size: 1rem;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 0.85rem;
    i { margin-right: 0.4rem; }
  }
  &__actions {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    margin-top: 1.5rem;
  }
  &__save {
    border-radius: 8px;
    padding: 0.55rem 1.5rem;
    font-weight: 600;
  }
}

.smgp-editor-tip {
  color: #94a3b8;
  font-size: 0.7rem;
  cursor: help;
  margin-left: 0.15rem;
}

.smgp-editor-companies-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 0.4rem;
  label { margin: 0; }
}
.smgp-editor-companies-search { max-width: 220px; }

.smgp-editor-companies {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.35rem 1rem;
  max-height: 220px;
  overflow-y: auto;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 0.75rem;
  background: #fff;
  @media (max-width: 768px) { grid-template-columns: 1fr; }
  .form-check {
    .form-check-input:checked { background-color: #10b981; border-color: #10b981; }
    .form-check-label { font-size: 0.85rem; }
  }
}

.smgp-editor-card {
  background: #fff;
  border: 1px solid #f3f4f6;
  border-radius: 14px;
  padding: 1.25rem 1.5rem;
  margin-bottom: 1rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.smgp-editor-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.75rem;
  @media (max-width: 700px) { grid-template-columns: 1fr; }
}

.smgp-editor-field {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
  label { font-weight: 600; font-size: 0.85rem; color: #1e293b; }
  input, select, textarea, .form-control, .form-select {
    background-color: #fff !important;
  }
  :deep(.form-control) {
    border-color: #e5e7eb;
    border-radius: 8px;
    &:focus { border-color: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.12); }
  }
  &--full { grid-column: 1 / -1; }
}

.smgp-editor-image-row {
  display: flex;
  gap: 1rem;
  align-items: center;
}
.smgp-editor-image-preview {
  width: 80px;
  height: 80px;
  border-radius: 10px;
  overflow: hidden;
  background: #f1f5f9;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  img { width: 100%; height: 100%; object-fit: cover; }
  .smgp-editor-image-placeholder { color: #94a3b8; font-size: 1.5rem; }
}
.smgp-editor-image-controls { flex: 1; }
</style>
