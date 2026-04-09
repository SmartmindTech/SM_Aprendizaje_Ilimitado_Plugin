<template>
  <div class="smgp-restore">
    <div class="d-flex align-items-start gap-3">
      <button
        type="button"
        class="btn btn-outline-secondary mt-1"
        @click="$router.back()"
      >
        <i class="icon-arrow-left" />
      </button>
      <h1 class="smgp-restore__title mb-0">{{ $t('restore.title') || 'Restore a course backup' }}</h1>
    </div>

    <!-- Step indicator -->
    <ol class="smgp-restore__steps">
      <li
        v-for="(stepname, idx) in stepNames"
        :key="idx"
        :class="{
          'is-current': step === idx + 1,
          'is-done': step > idx + 1,
        }"
      >
        <span class="smgp-restore__step-num">{{ idx + 1 }}.</span>
        <span class="smgp-restore__step-name">{{ stepname }}</span>
      </li>
    </ol>

    <!-- ─── Step 1: Upload / Confirm ─────────────────────────── -->
    <section v-if="step === 1" class="smgp-restore__section">
      <h2>1. {{ stepNames[0] }}</h2>
      <p class="text-muted">{{ $t('restore.upload_desc') || 'Upload an MBZ backup file.' }}</p>

      <div class="smgp-restore__field">
        <label for="mbz-file">{{ $t('restore.mbz_file') || 'MBZ file' }}</label>
        <input id="mbz-file" type="file" accept=".mbz" class="form-control" @change="onFileChange">
      </div>

      <button class="btn btn-primary" :disabled="!selectedFile || uploading" @click="onUpload">
        {{ uploading ? ($t('restore.uploading') || 'Uploading…') : ($t('restore.upload') || 'Upload & confirm') }}
      </button>

      <div v-if="prepareResult && prepareResult.success" class="alert alert-success mt-3">
        <strong>{{ $t('restore.backup_details') || 'Backup details' }}</strong>
        <dl class="mb-0 mt-2">
          <dt>{{ $t('restore.original_name') || 'Original course' }}</dt>
          <dd>{{ prepareResult.original_fullname }} ({{ prepareResult.original_shortname }})</dd>
          <dt>{{ $t('restore.backup_date') || 'Backup date' }}</dt>
          <dd>{{ new Date(prepareResult.backup_date * 1000).toLocaleString() }}</dd>
          <dt>{{ $t('restore.moodle_release') || 'Moodle release' }}</dt>
          <dd>{{ prepareResult.moodle_release }}</dd>
        </dl>
      </div>

      <div v-if="prepareResult && prepareResult.success" class="smgp-restore__actions">
        <button class="btn btn-primary" @click="step = 2">
          {{ $t('restore.next') || 'Next' }} →
        </button>
      </div>
    </section>

    <!-- ─── Step 2: Destination ──────────────────────────────── -->
    <section v-else-if="step === 2" class="smgp-restore__section">
      <h2>2. {{ stepNames[1] }}</h2>
      <p class="text-muted">{{ $t('restore.destination_desc') || 'Pick a destination category and new course name.' }}</p>

      <div class="smgp-restore__field">
        <label>{{ $t('restore.fullname') || 'New course fullname' }}</label>
        <input v-model="destination.fullname" type="text" class="form-control">
      </div>
      <div class="smgp-restore__field">
        <label>{{ $t('restore.shortname') || 'New course shortname' }}</label>
        <input v-model="destination.shortname" type="text" class="form-control">
      </div>
      <div class="smgp-restore__field">
        <label>{{ $t('restore.category') || 'Destination category' }}</label>
        <select v-model.number="destination.categoryid" class="form-control">
          <option :value="1">Top (Miscellaneous)</option>
        </select>
      </div>

      <!-- Target companies (mirrors the courseloader picker; selection
           is persisted in sessionStorage so courseloader → restore round
           trips don't lose it). -->
      <div class="smgp-restore__field">
        <label>{{ $t('courseloader.companies_card') || 'Target companies' }}</label>
        <div class="smgp-restore__company-search">
          <input
            v-model="companyFilter"
            type="text"
            class="form-control"
            :placeholder="$t('courseloader.company_search_placeholder') || 'Search company...'"
          >
        </div>
        <div class="smgp-restore__company-table">
          <table class="table">
            <thead>
              <tr>
                <th class="smgp-restore__toggle-cell" style="width:60px">
                  <span class="smgp-restore__toggle-wrap form-switch">
                    <input
                      class="form-check-input"
                      type="checkbox"
                      :checked="allFilteredSelected"
                      :indeterminate.prop="someFilteredSelected && !allFilteredSelected"
                      @change="toggleAllCompanies(($event.target as HTMLInputElement).checked)"
                    >
                  </span>
                </th>
                <th>{{ $t('courseloader.company_col') || 'Company' }}</th>
                <th>{{ $t('courseloader.shortname_col') || 'Short name' }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in filteredCompanies" :key="c.id">
                <td class="smgp-restore__toggle-cell">
                  <span class="smgp-restore__toggle-wrap form-switch">
                    <input
                      v-model="selectedCompanyIds"
                      class="form-check-input"
                      type="checkbox"
                      :value="c.id"
                    >
                  </span>
                </td>
                <td><strong>{{ c.name }}</strong></td>
                <td class="text-muted">{{ c.shortname }}</td>
              </tr>
              <tr v-if="!filteredCompanies.length">
                <td colspan="3" class="text-center text-muted small">
                  {{ $t('courseloader.companies_empty') || 'No companies found.' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="smgp-restore__actions">
        <button class="btn btn-outline-secondary" @click="step = 1">← {{ $t('restore.back') || 'Back' }}</button>
        <button class="btn btn-primary" :disabled="!destination.fullname || !destination.shortname" @click="loadSettings">
          {{ $t('restore.next') || 'Next' }} →
        </button>
      </div>
    </section>

    <!-- ─── Step 3: Settings ─────────────────────────────────── -->
    <section v-else-if="step === 3" class="smgp-restore__section">
      <h2>3. {{ stepNames[2] }}</h2>
      <p class="text-muted">{{ $t('restore.settings_desc') || 'Customize what to include in the restore.' }}</p>

      <div v-if="loadingSettings" class="text-center py-4">
        <div class="spinner-border text-primary" role="status" />
      </div>
      <div v-else>
        <div v-for="setting in settingsList" :key="setting.name" class="smgp-restore__setting">
          <label>{{ setting.label || setting.name }}</label>
          <input
            v-if="setting.type === 'checkbox' || setting.type === 'select'"
            type="checkbox"
            :checked="setting.value == '1'"
            @change="updateSetting(setting.name, ($event.target as HTMLInputElement).checked ? '1' : '0')"
          >
          <input
            v-else
            type="text"
            class="form-control form-control-sm"
            :value="setting.value"
            @input="updateSetting(setting.name, ($event.target as HTMLInputElement).value)"
          >
        </div>
      </div>

      <div class="smgp-restore__actions">
        <button class="btn btn-outline-secondary" @click="step = 2">← {{ $t('restore.back') || 'Back' }}</button>
        <button class="btn btn-primary" @click="loadSchema">{{ $t('restore.next') || 'Next' }} →</button>
      </div>
    </section>

    <!-- ─── Step 4: Schema (structure editor + SmartMind fields) ─── -->
    <section v-else-if="step === 4" class="smgp-restore__section">
      <h2>4. {{ stepNames[3] }}</h2>
      <p class="text-muted">{{ $t('restore.schema_desc') || 'Review the course structure and set SmartMind metadata.' }}</p>

      <div v-if="loadingSchema" class="text-center py-4">
        <div class="spinner-border text-primary" role="status" />
      </div>

      <div v-else class="smgp-restore__schema">
        <!-- Sections preview (simple list — full drag/drop editor can be added later) -->
        <div class="smgp-restore__sections">
          <h3>{{ $t('restore.sections_in_backup') || 'Sections in backup' }}</h3>
          <div v-for="sec in schemaSections" :key="sec.section_id" class="smgp-restore__section-card">
            <h4>{{ sec.title }}</h4>
            <ul v-if="sec.activities.length">
              <li v-for="act in sec.activities" :key="act.cmid">
                <i class="icon-file" />
                {{ act.name }}
                <small class="text-muted">({{ act.modname }})</small>
              </li>
            </ul>
            <p v-else class="text-muted"><em>{{ $t('restore.no_activities') || 'No activities' }}</em></p>
          </div>
        </div>

        <!-- SmartMind metadata -->
        <div class="smgp-restore__metadata">
          <MetadataFields v-model="smgpMeta" :categories="[]" />
        </div>

        <div class="smgp-restore__objectives">
          <ObjectivesEditor v-model="smgpObjectives" />
        </div>
      </div>

      <div class="smgp-restore__actions">
        <button class="btn btn-outline-secondary" @click="step = 3">← {{ $t('restore.back') || 'Back' }}</button>
        <button class="btn btn-primary" @click="step = 5">{{ $t('restore.next') || 'Next' }} →</button>
      </div>
    </section>

    <!-- ─── Step 5: Review ───────────────────────────────────── -->
    <section v-else-if="step === 5" class="smgp-restore__section">
      <h2>5. {{ stepNames[4] }}</h2>
      <p class="text-muted">{{ $t('restore.review_desc') || 'Review your choices before executing the restore.' }}</p>

      <dl class="smgp-restore__review">
        <dt>{{ $t('restore.fullname') || 'New course fullname' }}</dt>
        <dd>{{ destination.fullname }}</dd>
        <dt>{{ $t('restore.shortname') || 'New course shortname' }}</dt>
        <dd>{{ destination.shortname }}</dd>
        <dt>{{ $t('restore.category') || 'Destination category' }}</dt>
        <dd>{{ destination.categoryid }}</dd>
        <dt>{{ $t('restore.sections_count') || 'Sections' }}</dt>
        <dd>{{ schemaSections.length }}</dd>
        <dt>{{ $t('restore.level') || 'Level' }}</dt>
        <dd>{{ smgpMeta.level }}</dd>
        <dt>{{ $t('restore.duration') || 'Duration' }}</dt>
        <dd>{{ smgpMeta.duration_hours }}h</dd>
        <dt>{{ $t('restore.objectives') || 'Objectives' }}</dt>
        <dd>{{ smgpObjectives.length }}</dd>
      </dl>

      <div class="smgp-restore__actions">
        <button class="btn btn-outline-secondary" @click="step = 4">← {{ $t('restore.back') || 'Back' }}</button>
        <button class="btn btn-success" @click="executeRestore">
          <i class="icon-play" /> {{ $t('restore.execute') || 'Perform restore' }}
        </button>
      </div>
    </section>

    <!-- ─── Step 6: Process ──────────────────────────────────── -->
    <section v-else-if="step === 6" class="smgp-restore__section">
      <h2>6. {{ stepNames[5] }}</h2>

      <div v-if="executing" class="text-center py-4">
        <div class="spinner-border text-success" role="status" />
        <p class="mt-2">{{ $t('restore.executing') || 'Restoring course — please wait…' }}</p>
      </div>

      <div v-else-if="executeError" class="alert alert-danger">
        <strong>{{ $t('restore.error') || 'Error' }}</strong>
        <p>{{ executeError }}</p>
      </div>
    </section>

    <!-- ─── Step 7: Complete ─────────────────────────────────── -->
    <section v-else-if="step === 7" class="smgp-restore__section">
      <h2>7. {{ stepNames[6] }}</h2>
      <div class="alert alert-success">
        <strong>{{ $t('restore.success') || 'Course restored successfully!' }}</strong>
        <p>{{ $t('restore.complete_desc') || 'The course has been created with your SmartMind metadata applied.' }}</p>
      </div>
      <NuxtLink v-if="executeResult" :to="`/courses/${executeResult.courseid}/landing`" class="btn btn-primary">
        {{ $t('restore.view_course') || 'View course' }}
      </NuxtLink>
    </section>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed } from 'vue'
import { useMoodleAjax } from '~/composables/api_calls/useMoodleAjax'
import ObjectivesEditor from '~/components/editor/ObjectivesEditor.vue'
import MetadataFields from '~/components/editor/MetadataFields.vue'

definePageMeta({ middleware: ['auth'] })

const { call } = useMoodleAjax()

const stepNames = [
  'Confirm',
  'Destination',
  'Settings',
  'Schema',
  'Review',
  'Process',
  'Complete',
]

const step = ref(1)

// Step 1
const selectedFile = ref<File | null>(null)
const uploading = ref(false)
const prepareResult = ref<any>(null)

// Step 2 — destination + target companies.
const destination = reactive({
  fullname: '',
  shortname: '',
  categoryid: 1,
})

// Companies picker mirrors the courseloader page. The selection is
// persisted in sessionStorage under SMGP_RESTORE_COMPANIES so that
// navigating courseloader → restore (or back-and-forth between restore
// steps) doesn't lose what the admin already selected.
const SMGP_RESTORE_COMPANIES = 'smgp_restore_company_ids'
interface CompanyRow { id: number; name: string; shortname: string }
const companies = ref<CompanyRow[]>([])
const companyFilter = ref('')
const selectedCompanyIds = ref<number[]>(loadInitialCompanies())

function loadInitialCompanies(): number[] {
  if (typeof window === 'undefined') return []
  try {
    const raw = window.sessionStorage.getItem(SMGP_RESTORE_COMPANIES)
    if (!raw) return []
    const parsed = JSON.parse(raw)
    return Array.isArray(parsed) ? parsed.map((n: unknown) => Number(n)).filter(Boolean) : []
  } catch {
    return []
  }
}

watch(selectedCompanyIds, (ids) => {
  if (typeof window === 'undefined') return
  window.sessionStorage.setItem(SMGP_RESTORE_COMPANIES, JSON.stringify(ids))
}, { deep: true })

function normalize(s: string): string {
  return s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
}
const filteredCompanies = computed(() => {
  const term = normalize(companyFilter.value.trim())
  if (!term) return companies.value
  return companies.value.filter(c =>
    normalize(c.name).includes(term) || normalize(c.shortname).includes(term),
  )
})
const allFilteredSelected = computed(() =>
  filteredCompanies.value.length > 0 &&
  filteredCompanies.value.every(c => selectedCompanyIds.value.includes(c.id)),
)
const someFilteredSelected = computed(() =>
  filteredCompanies.value.some(c => selectedCompanyIds.value.includes(c.id)),
)
function toggleAllCompanies(checked: boolean) {
  const ids = filteredCompanies.value.map(c => c.id)
  if (checked) {
    selectedCompanyIds.value = Array.from(new Set([...selectedCompanyIds.value, ...ids]))
  } else {
    selectedCompanyIds.value = selectedCompanyIds.value.filter(id => !ids.includes(id))
  }
}

interface CompanyStatsResponse extends Array<{ id: number | string; name: string; shortname: string }> {}
async function loadCompanies() {
  const result = await call<CompanyStatsResponse>('local_sm_graphics_plugin_get_company_stats', {})
  if (!result.error && Array.isArray(result.data)) {
    companies.value = result.data.map((c) => ({
      id: Number(c.id),
      name: String(c.name),
      shortname: String(c.shortname),
    }))
  }
}
loadCompanies()

// SharePoint courseloader → restore wizard handoff. The courseloader
// page calls sharepoint_prepare_restore to download the MBZ from
// SharePoint and stash its filename in sessionStorage, then navigates
// here. We pick that filename up on mount, run restore_prepare with it,
// and skip step 1 entirely so the user lands on step 2 (Destination)
// with the SharePoint course already loaded.
function consumeSharepointHandoff() {
  if (typeof window === 'undefined') return
  const filename = window.sessionStorage.getItem('smgp_restore_sp_filename')
  if (!filename) return
  // Clear immediately so a back-button bounce doesn't re-trigger prepare.
  window.sessionStorage.removeItem('smgp_restore_sp_filename')

  uploading.value = true
  call<{
    success: boolean
    backupid?: string
    original_fullname?: string
    original_shortname?: string
    error?: string
  }>('local_sm_graphics_plugin_restore_prepare', {
    filename,
    draftitemid: 0,
  }).then((result) => {
    uploading.value = false
    if (result.error || !result.data?.success) {
      executeError.value = result.error || result.data?.error || 'Failed to load SharePoint backup.'
      return
    }
    prepareResult.value = result.data
    destination.fullname = result.data.original_fullname || ''
    destination.shortname = (result.data.original_shortname || '') + '_' + Date.now()
    // Stay on step 1 — it already renders the prepared backup details
    // and a Next button when prepareResult.success is true. The user
    // can review the original-name/date/release info, then click
    // through 1 → 2 → ... → 7 like a normal restore.
    step.value = 1
  })
}
consumeSharepointHandoff()

// Step 3
const loadingSettings = ref(false)
const settingsList = ref<any[]>([])
const customSettings = reactive<Record<string, string>>({})

// Step 4
const loadingSchema = ref(false)
const schemaSections = ref<any[]>([])
const smgpMeta = reactive({
  duration_hours: 0,
  level: 'beginner',
  completion_percentage: 100,
  smartmind_code: '',
  sepe_code: '',
  description: '',
  course_category: 0,
})
const smgpObjectives = ref<string[]>([])

// Step 6/7
const executing = ref(false)
const executeError = ref<string | null>(null)
const executeResult = ref<any>(null)

function onFileChange(e: Event) {
  const target = e.target as HTMLInputElement
  if (target.files && target.files.length > 0) {
    selectedFile.value = target.files[0] ?? null
  }
}

async function onUpload() {
  if (!selectedFile.value) return
  uploading.value = true
  // NOTE: real draft file upload would need a repository upload endpoint.
  // For this thin version, we assume the file is handed to Moodle via a separate
  // PHP endpoint; here we just pass filename and let restore_prepare find it in
  // the backup temp dir (works for SharePoint flow; manual upload requires more
  // wiring via draft file areas + repository API).
  const result = await call('local_sm_graphics_plugin_restore_prepare', {
    filename: selectedFile.value.name,
    draftitemid: 0,
  })
  uploading.value = false
  if (result.error || !result.data?.success) {
    executeError.value = result.error || result.data?.error
  } else {
    prepareResult.value = result.data
    destination.fullname = result.data.original_fullname
    destination.shortname = result.data.original_shortname + '_' + Date.now()
  }
}

async function loadSettings() {
  step.value = 3
  loadingSettings.value = true
  const result = await call('local_sm_graphics_plugin_restore_get_settings', {
    backupid: prepareResult.value.backupid,
    categoryid: destination.categoryid,
  })
  loadingSettings.value = false
  if (!result.error && result.data?.success) {
    settingsList.value = result.data.settings
  }
}

function updateSetting(name: string, value: string) {
  customSettings[name] = value
}

async function loadSchema() {
  step.value = 4
  loadingSchema.value = true
  const result = await call('local_sm_graphics_plugin_restore_get_schema', {
    backupid: prepareResult.value.backupid,
    categoryid: destination.categoryid,
  })
  loadingSchema.value = false
  if (!result.error && result.data?.success) {
    schemaSections.value = result.data.sections
  }
}

async function executeRestore() {
  step.value = 6
  executing.value = true
  executeError.value = null
  const result = await call('local_sm_graphics_plugin_restore_execute', {
    backupid: prepareResult.value.backupid,
    categoryid: destination.categoryid,
    fullname: destination.fullname,
    shortname: destination.shortname,
    companyids: selectedCompanyIds.value.join(','),
    smgp_fields_json: JSON.stringify({
      smgp_duration_hours: smgpMeta.duration_hours,
      smgp_level: smgpMeta.level,
      smgp_completion_percentage: smgpMeta.completion_percentage,
      smgp_smartmind_code: smgpMeta.smartmind_code,
      smgp_sepe_code: smgpMeta.sepe_code,
      smgp_description: smgpMeta.description,
      smgp_catalogue_cat: smgpMeta.course_category,
      smgp_objectives_data: JSON.stringify(smgpObjectives.value),
    }),
  })
  executing.value = false
  if (result.error || !result.data?.success) {
    executeError.value = result.error || result.data?.error
  } else {
    executeResult.value = result.data
    step.value = 7
  }
}
</script>

<style scoped lang="scss">
.smgp-restore {
  max-width: 1100px;
  margin: 2rem auto;
  padding: 0 1rem;

  &__title { font-size: 1.75rem; font-weight: 700; color: #1e293b; margin-bottom: 1rem; }
  &__steps {
    display: flex;
    gap: 0.5rem;
    list-style: none;
    padding: 0;
    margin: 0 0 1.5rem;
    border-bottom: 2px solid #e2e8f0;
    li {
      padding: 0.5rem 0.75rem;
      color: #94a3b8;
      border-bottom: 3px solid transparent;
      &.is-current { color: #10b981; border-color: #10b981; font-weight: 600; }
      &.is-done { color: #64748b; }
    }
  }
  &__step-num { font-weight: 700; margin-right: 0.25rem; }
  &__section {
    background: #fff;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    margin-bottom: 1.25rem;
  }
  &__field {
    margin-bottom: 1rem;
    label { display: block; font-weight: 600; margin-bottom: 0.25rem; }
  }

  &__company-search {
    margin-bottom: 0.85rem;
    max-width: 320px;

    .form-control {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 0.55rem 0.85rem;
      font-size: 0.9rem;
      &:focus { background: #fff; box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.15); border-color: #10b981; }
    }
  }
  &__company-table {
    max-height: 360px;
    overflow-y: auto;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #fff;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);

    table {
      margin-bottom: 0;
      border-collapse: separate;
      border-spacing: 0;
      width: 100%;
    }
    thead th {
      position: sticky;
      top: 0;
      background: #f8fafc;
      font-size: 0.72rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: #94a3b8;
      padding: 0.85rem 1rem;
      border-bottom: 1px solid #e2e8f0;
      border-right: 1px solid #eef2f7;
      vertical-align: middle;
      text-align: left;
      &:last-child { border-right: none; }
    }
    tbody td {
      padding: 0.85rem 1rem;
      border-top: 1px solid #eef2f7;
      border-right: 1px solid #eef2f7;
      vertical-align: middle;
      background: #fff;
      &:last-child { border-right: none; }
    }
    tbody tr:first-child td { border-top: none; }
    tbody tr:hover td { background: #f0fdf4; }
    tbody td strong { color: #1e293b; font-weight: 600; }
    tbody td.text-muted { color: #94a3b8 !important; font-size: 0.9rem; }

    th.smgp-restore__toggle-cell,
    td.smgp-restore__toggle-cell {
      position: relative;
      width: 60px;
      min-width: 60px;
      height: 48px;
      padding: 0 !important;
      vertical-align: middle;
    }
  }

  &__toggle-wrap {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    display: block;
    padding: 0 !important;
    margin: 0 !important;
    min-height: 0;
    line-height: 0;
    &.form-switch { padding-left: 0 !important; }

    .form-check-input {
      display: block;
      float: none !important;
      margin: 0 !important;
      width: 2.4rem;
      height: 1.3rem;
      cursor: pointer;
      border-color: #cbd5e1;
      background-color: #e2e8f0;
      &:checked {
        background-color: #10b981;
        border-color: #10b981;
      }
    }
  }

  &__actions {
    display: flex;
    gap: 0.5rem;
    margin-top: 1rem;
  }
  &__setting {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.4rem 0;
    border-bottom: 1px solid #f1f5f9;
    label { margin: 0; flex: 1; }
  }
  &__sections { margin-bottom: 1.5rem; }
  &__section-card {
    background: #f8fafc;
    border-left: 3px solid #10b981;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 0.75rem;
    h4 { margin: 0 0 0.5rem; font-size: 1rem; }
    ul { margin: 0; padding-left: 1rem; list-style: none; }
    li { padding: 0.25rem 0; font-size: 0.85rem; color: #475569; }
  }
  &__review dt { font-weight: 600; color: #1e293b; }
  &__review dd { margin-left: 0; margin-bottom: 0.5rem; color: #475569; }
}
</style>
