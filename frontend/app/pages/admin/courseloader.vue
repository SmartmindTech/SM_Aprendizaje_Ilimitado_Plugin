<template>
  <div class="smgp-courseloader">
    <div class="d-flex align-items-start gap-3">
      <button
        type="button"
        class="btn smgp-back-btn mt-1"
        @click="$router.back()"
      >
        <i class="icon-arrow-left" />
      </button>
      <div class="flex-grow-1">
        <h1 class="smgp-courseloader__title">{{ $t('courseloader.title') }}</h1>
        <p class="smgp-courseloader__subtitle">{{ $t('courseloader.subtitle') }}</p>
      </div>
    </div>

    <!-- Card 1: course picker (search-as-you-type with optional manual URL) -->
    <section class="smgp-courseloader__section">
      <h2 class="smgp-courseloader__section-title">{{ $t('courseloader.course_card') }}</h2>

      <div class="smgp-courseloader__row">
        <div class="smgp-courseloader__search">
          <input
            v-if="!urlMode"
            v-model="courseFilter"
            type="text"
            class="form-control"
            :placeholder="$t('courseloader.search_placeholder')"
            autocomplete="off"
            @focus="showSuggestions = true"
            @blur="onSearchBlur"
          >
          <input
            v-else
            v-model="manualUrl"
            type="url"
            class="form-control"
            :placeholder="$t('courseloader.url_placeholder')"
          >

          <ul v-if="!urlMode && showSuggestions && filteredCourses.length" class="smgp-courseloader__suggestions">
            <li
              v-for="c in filteredCourses"
              :key="c.web_url"
              class="smgp-courseloader__suggestion"
              :class="{ 'is-selected': selectedCourse?.web_url === c.web_url }"
              @mousedown.prevent="pickCourse(c)"
            >
              <template v-for="(chunk, i) in highlightMatches(c.name)" :key="i">
                <mark v-if="chunk.match" class="smgp-courseloader__suggestion-match">{{ chunk.text }}</mark>
                <template v-else>{{ chunk.text }}</template>
              </template>
            </li>
          </ul>
        </div>

        <button class="btn smgp-courseloader__url-btn" @click="toggleUrlMode">
          <i :class="urlMode ? 'bi bi-list-ul' : 'bi bi-link-45deg'" />
          {{ urlMode ? $t('courseloader.list_toggle') : $t('courseloader.url_toggle') }}
        </button>
      </div>

      <div class="smgp-courseloader__hint">
        <span class="smgp-courseloader__hint-count">{{ availableCourses.length }} {{ $t('courseloader.courses_cached') }}</span>
        <span class="smgp-courseloader__hint-sep">·</span>
        <button class="smgp-courseloader__sync-btn" :disabled="loadingCourses" @click="loadCachedCourses(true)">
          <i :class="['bi', loadingCourses ? 'bi-arrow-repeat smgp-courseloader__spinning' : 'bi-arrow-repeat']" />
          {{ loadingCourses ? $t('app.loading') : $t('courseloader.sync') }}
        </button>
      </div>
      <div v-if="syncError" class="alert alert-danger mt-2 mb-0 py-2 small">
        {{ syncError }}
      </div>

      <!-- Live sync log (mirrors dev branch behaviour). -->
      <div v-if="showSyncLog" class="smgp-courseloader__sync-log">
        <div class="smgp-courseloader__sync-log-header">
          <span>{{ $t('courseloader.sync_log') }}</span>
          <button class="smgp-courseloader__sync-log-close" @click="showSyncLog = false">×</button>
        </div>
        <pre ref="syncLogEl">{{ syncLog.join('\n') }}</pre>
      </div>
    </section>

    <!-- Card 2: target companies -->
    <section class="smgp-courseloader__section">
      <h2 class="smgp-courseloader__section-title">{{ $t('courseloader.companies_card') }}</h2>

      <div class="smgp-courseloader__company-search">
        <input
          v-model="companyFilter"
          type="text"
          class="form-control"
          :placeholder="$t('courseloader.company_search_placeholder')"
        >
      </div>

      <div class="table-responsive smgp-courseloader__company-table">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th class="smgp-courseloader__toggle-cell" style="width:60px">
                <span class="smgp-courseloader__toggle-wrap form-switch">
                  <input
                    id="cl-toggle-all"
                    class="form-check-input"
                    type="checkbox"
                    :checked="allFilteredSelected"
                    :indeterminate.prop="someFilteredSelected && !allFilteredSelected"
                    @change="toggleAll(($event.target as HTMLInputElement).checked)"
                  >
                </span>
              </th>
              <th>{{ $t('courseloader.company_col') }}</th>
              <th>{{ $t('courseloader.shortname_col') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in filteredCompanies" :key="c.id">
              <td class="smgp-courseloader__toggle-cell">
                <span class="smgp-courseloader__toggle-wrap form-switch">
                  <input
                    :id="`cl-co-${c.id}`"
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
              <td colspan="3" class="text-center text-muted small">{{ $t('courseloader.companies_empty') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Single Scan button -->
    <div class="smgp-courseloader__scan">
      <button class="btn btn-success" :disabled="scanning || !currentUrl" @click="onScan">
        <i class="bi bi-search" /> {{ scanning ? $t('courseloader.scanning') : $t('courseloader.scan') }}
      </button>
    </div>

    <!-- Scan results -->
    <section v-if="scanResult" class="smgp-courseloader__section">
      <h2 class="smgp-courseloader__section-title">{{ $t('courseloader.scan_results') }}</h2>
      <div v-if="scanResult.error" class="alert alert-danger">{{ scanResult.error }}</div>
      <div v-else>
        <div class="smgp-courseloader__stats">
          <div class="smgp-courseloader__stat">
            <span class="smgp-courseloader__stat-value">{{ scanResult.mbz?.length || 0 }}</span>
            <span class="smgp-courseloader__stat-label">MBZ</span>
          </div>
          <div class="smgp-courseloader__stat">
            <span class="smgp-courseloader__stat-value">{{ scanResult.scorm?.length || 0 }}</span>
            <span class="smgp-courseloader__stat-label">SCORM</span>
          </div>
          <div class="smgp-courseloader__stat">
            <span class="smgp-courseloader__stat-value">{{ scanResult.pdf?.length || 0 }}</span>
            <span class="smgp-courseloader__stat-label">PDF</span>
          </div>
          <div class="smgp-courseloader__stat">
            <span class="smgp-courseloader__stat-value">{{ evalCount }}</span>
            <span class="smgp-courseloader__stat-label">{{ $t('courseloader.evals_short') }}</span>
          </div>
        </div>

        <!-- Detailed file list -->
        <div v-if="scanFiles.length" class="smgp-courseloader__file-table">
          <table class="table">
            <thead>
              <tr>
                <th style="width:48px"></th>
                <th>{{ $t('courseloader.file_col') }}</th>
                <th class="text-center" style="width:120px">{{ $t('courseloader.type_col') }}</th>
                <th class="text-center" style="width:120px">{{ $t('courseloader.size_col') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="(f, i) in scanFiles" :key="f.type + '-' + i + '-' + f.name">
                <td class="smgp-courseloader__file-icon">
                  <i :class="['bi', f.icon, 'sm-file-icon--' + f.color]" />
                </td>
                <td>{{ f.name }}</td>
                <td class="text-center">
                  <span class="smgp-courseloader__type-badge" :class="'smgp-courseloader__type-badge--' + f.color">
                    {{ f.label }}
                  </span>
                </td>
                <td class="text-center text-muted">{{ formatBytes(f.size) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-if="scanResult.warnings && scanResult.warnings.length" class="smgp-courseloader__warnings">
          <div v-for="(w, i) in scanResult.warnings" :key="i" class="smgp-courseloader__warning">
            <i :class="['bi', warningIcon(w), 'smgp-courseloader__warning-icon']" />
            <span>{{ w }}</span>
          </div>
        </div>
      </div>
    </section>

    <!-- Import button — free-standing, same style as the scan button. -->
    <div v-if="scanResult && !scanResult.error" class="smgp-courseloader__scan">
      <button class="btn btn-success" :disabled="importing" @click="onImport">
        <i class="bi bi-cloud-download" /> {{ importing ? $t('courseloader.importing') : $t('courseloader.import') }}
      </button>
    </div>

    <!-- Import log + result (only after import has started) -->
    <section v-if="scanResult && !scanResult.error && (importLog.length || importResult)" class="smgp-courseloader__section">
      <h2 class="smgp-courseloader__section-title">{{ $t('courseloader.log') }}</h2>

      <div v-if="importLog.length" class="smgp-courseloader__log">
        <pre>{{ importLog.join('\n') }}</pre>
      </div>

      <div v-if="importResult" :class="['alert', importResult.success ? 'alert-success' : 'alert-danger', 'mb-0', 'mt-2']">
        <strong v-if="importResult.success">{{ $t('courseloader.success') }}</strong>
        <strong v-else>{{ $t('courseloader.error') }}</strong>
        <div v-if="importResult.course_url" class="mt-2">
          <NuxtLink :to="`/courses/${importResult.courseid}/landing`" class="btn btn-sm btn-primary">
            {{ $t('courseloader.view_course') }}
          </NuxtLink>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
// All of `ref`, `computed`, `useMoodleAjax`, `useAuthStore` and
// `definePageMeta` are auto-imported by Nuxt 4 — no manual imports needed.

definePageMeta({ middleware: ['auth'] })

const { call } = useMoodleAjax()
const auth = useAuthStore()

// --- Course picker state ----------------------------------------------------
const loadingCourses = ref(false)
const availableCourses = ref<Array<{ name: string; web_url: string }>>([])
const courseFilter = ref('')
const showSuggestions = ref(false)
const selectedCourse = ref<{ name: string; web_url: string } | null>(null)
const urlMode = ref(false)
const manualUrl = ref('')

// Diacritic-insensitive course filter.
function normalize(s: string): string {
  return s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '')
}
// Hard cap on suggestions rendered at once. The full cache (potentially
// hundreds of entries) stays in memory; we just don't render every <li>
// because that hurts scroll perf.
const SUGGESTION_LIMIT = 200

const filteredCourses = computed(() => {
  const term = normalize(courseFilter.value.trim())
  if (!term) return availableCourses.value.slice(0, SUGGESTION_LIMIT)
  return availableCourses.value
    .filter(c => normalize(c.name).includes(term))
    .slice(0, SUGGESTION_LIMIT)
})

// Build a list of {text, match} chunks so the template can render the
// matched substring inside a <mark> with the green accent. Diacritic-
// insensitive: we run the match on the normalised string but slice the
// original (preserving accents). Returns one chunk if no match.
function highlightMatches(name: string): Array<{ text: string; match: boolean }> {
  const term = normalize(courseFilter.value.trim())
  if (!term) return [{ text: name, match: false }]
  const norm = normalize(name)
  const idx = norm.indexOf(term)
  if (idx < 0) return [{ text: name, match: false }]
  return [
    { text: name.slice(0, idx), match: false },
    { text: name.slice(idx, idx + term.length), match: true },
    { text: name.slice(idx + term.length), match: false },
  ]
}

function pickCourse(c: { name: string; web_url: string }) {
  selectedCourse.value = c
  courseFilter.value = c.name
  showSuggestions.value = false
}
function onSearchBlur() {
  setTimeout(() => { showSuggestions.value = false }, 150)
}
function toggleUrlMode() {
  urlMode.value = !urlMode.value
  if (urlMode.value) {
    selectedCourse.value = null
    courseFilter.value = ''
  } else {
    manualUrl.value = ''
  }
}

const currentUrl = computed(() => urlMode.value ? manualUrl.value.trim() : (selectedCourse.value?.web_url || ''))

// --- Companies state --------------------------------------------------------
// Persist the selection in sessionStorage under the same key the restore
// wizard reads from, so courseloader → restore keeps the selection.
const SMGP_RESTORE_COMPANIES = 'smgp_restore_company_ids'
function loadStoredCompanyIds(): number[] {
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

const companies = ref<Array<{ id: number; name: string; shortname: string }>>([])
const companyFilter = ref('')
const selectedCompanyIds = ref<number[]>(loadStoredCompanyIds())

watch(selectedCompanyIds, (ids) => {
  if (typeof window === 'undefined') return
  window.sessionStorage.setItem(SMGP_RESTORE_COMPANIES, JSON.stringify(ids))
}, { deep: true })

const filteredCompanies = computed(() => {
  const term = normalize(companyFilter.value.trim())
  if (!term) return companies.value
  return companies.value.filter(c =>
    normalize(c.name).includes(term) || normalize(c.shortname).includes(term)
  )
})
const allFilteredSelected = computed(() =>
  filteredCompanies.value.length > 0 &&
  filteredCompanies.value.every(c => selectedCompanyIds.value.includes(c.id))
)
const someFilteredSelected = computed(() =>
  filteredCompanies.value.some(c => selectedCompanyIds.value.includes(c.id))
)
function toggleAll(checked: boolean) {
  const ids = filteredCompanies.value.map(c => c.id)
  if (checked) {
    selectedCompanyIds.value = Array.from(new Set([...selectedCompanyIds.value, ...ids]))
  } else {
    selectedCompanyIds.value = selectedCompanyIds.value.filter(id => !ids.includes(id))
  }
}

// --- Scan / import ----------------------------------------------------------
const scanning = ref(false)
const scanResult = ref<any>(null)

// Backend returns evaluations split by format. Show their sum in the
// dashboard stat so admins see the total at a glance.
const evalCount = computed(() => {
  const r = scanResult.value
  if (!r) return 0
  return (r.evaluations_aiken?.length || 0) + (r.evaluations_gift?.length || 0)
})

// Per-file-type display metadata. Keep keys in sync with the analyzer's
// manifest array names so the flattening loop below picks them up.
interface FileMeta { icon: string; color: string; label: string }
const FILE_TYPE_META: Record<string, FileMeta> = {
  mbz:               { icon: 'bi-file-earmark-zip',    color: 'green',  label: 'MBZ' },
  scorm:             { icon: 'bi-file-earmark-binary', color: 'blue',   label: 'SCORM' },
  pdf:               { icon: 'bi-file-earmark-pdf',    color: 'red',    label: 'PDF' },
  documents:         { icon: 'bi-file-earmark-text',   color: 'slate',  label: 'DOC' },
  evaluations_aiken: { icon: 'bi-list-check',          color: 'amber',  label: 'AIKEN' },
  evaluations_gift:  { icon: 'bi-pencil-square',       color: 'violet', label: 'GIFT' },
}

interface ScanFileRow { type: string; name: string; size: number; icon: string; color: string; label: string }

// Flatten the per-type arrays the backend returns into a single list the
// table can iterate. The order matches the analyzer's classification
// priority (mbz first, then scorm/pdf/documents, then evaluations).
const scanFiles = computed<ScanFileRow[]>(() => {
  const r = scanResult.value
  if (!r) return []
  const out: ScanFileRow[] = []
  for (const type of Object.keys(FILE_TYPE_META)) {
    const arr = r[type]
    if (!Array.isArray(arr)) continue
    const meta = FILE_TYPE_META[type]!
    for (const f of arr) {
      out.push({
        type,
        name: String(f.name ?? ''),
        size: Number(f.size ?? 0),
        icon: meta.icon,
        color: meta.color,
        label: meta.label,
      })
    }
  }
  return out
})

function formatBytes(bytes: number): string {
  if (!bytes || bytes <= 0) return '—'
  const units = ['B', 'KB', 'MB', 'GB', 'TB']
  let i = 0
  let n = bytes
  while (n >= 1024 && i < units.length - 1) {
    n /= 1024
    i++
  }
  return `${n < 10 ? n.toFixed(1) : Math.round(n)} ${units[i]}`
}

// Pick a meaningful icon for a backend warning string. The warnings are
// hard-coded in the analyzer so we can match on substrings.
function warningIcon(msg: string): string {
  const m = msg.toLowerCase()
  if (m.includes('scorm'))       return 'bi-file-earmark-binary'
  if (m.includes('mbz'))         return 'bi-file-earmark-zip'
  if (m.includes('pdf'))         return 'bi-file-earmark-pdf'
  if (m.includes('evaluac'))     return 'bi-list-check'
  return 'bi-exclamation-triangle'
}

const importing = ref(false)
const importLog = ref<string[]>([])
const importResult = ref<any>(null)

interface CachedCoursesResponse {
  success?: boolean
  total_count?: number
  courses?: Array<{ name: string; web_url: string }>
  sync_error?: string
}

const syncError = ref<string | null>(null)
const syncLog = ref<string[]>([])
const showSyncLog = ref(false)
const syncLogEl = ref<HTMLPreElement | null>(null)

// Auto-scroll the log panel to the bottom whenever a new line lands.
watch(syncLog, async () => {
  await nextTick()
  if (syncLogEl.value) {
    syncLogEl.value.scrollTop = syncLogEl.value.scrollHeight
  }
}, { deep: true })

// Read the bare cache (no SharePoint hit) — used at page mount and after
// a successful streaming sync to repopulate availableCourses.
async function fetchCachedList() {
  const result = await call<CachedCoursesResponse>(
    'local_sm_graphics_plugin_sharepoint_list_courses',
    { sync: 0 },
  )
  if (!result.error && result.data?.courses) {
    availableCourses.value = result.data.courses
  }
}

// Live SharePoint sync via Server-Sent Events. The endpoint
// pages/courseloader_sync.php streams progress lines (one per folder)
// and sends "[DONE]" when finished, exactly like the dev branch's
// courseloader page so admins can watch the crawl in real time.
function loadCachedCourses(forceSync = false) {
  if (!forceSync) {
    loadingCourses.value = true
    fetchCachedList().finally(() => { loadingCourses.value = false })
    return
  }

  loadingCourses.value = true
  syncError.value = null
  syncLog.value = []
  showSyncLog.value = true

  const url = `${auth.wwwroot}/local/sm_graphics_plugin/pages/courseloader_sync.php`
    + `?sesskey=${encodeURIComponent(auth.sesskey)}`

  let es: EventSource | null = null
  try {
    es = new EventSource(url, { withCredentials: true })
  } catch (e) {
    syncError.value = String(e)
    loadingCourses.value = false
    return
  }

  es.onmessage = (ev) => {
    const line = ev.data ?? ''
    if (line === '[DONE]') {
      es?.close()
      loadingCourses.value = false
      // Refresh the local list now that the cache table is rebuilt.
      fetchCachedList()
      return
    }
    if (line.startsWith('ERROR:')) {
      syncError.value = line.replace(/^ERROR:\s*/, '')
    }
    syncLog.value.push(line)
  }

  es.onerror = () => {
    es?.close()
    loadingCourses.value = false
    if (!syncError.value) {
      syncError.value = 'SSE connection lost while syncing.'
    }
  }
}

async function loadCompanies() {
  const result = await call('local_sm_graphics_plugin_get_company_stats', {})
  if (!result.error && Array.isArray(result.data)) {
    companies.value = result.data.map((c: any) => ({
      id: Number(c.id),
      name: String(c.name),
      shortname: String(c.shortname),
    }))
  }
}

async function onScan() {
  if (!currentUrl.value) return
  scanning.value = true
  scanResult.value = null
  const result = await call('local_sm_graphics_plugin_sharepoint_scan', {
    folder_url: currentUrl.value,
  })
  if (result.error) {
    scanResult.value = { error: result.error }
  } else {
    scanResult.value = result.data
  }
  scanning.value = false
}

const router = useRouter()

async function onImport() {
  if (!currentUrl.value) return
  importing.value = true
  importLog.value = []
  importResult.value = null

  // Step 1: ask the backend to download the MBZ from SharePoint and stage
  // it in Moodle's backup temp directory. We already scanned the folder,
  // so we hand the MBZ item id + name straight from scanResult — the
  // backend uses those and skips the analyzer step entirely (no extra
  // Graph API call).
  const cachedMbz = scanResult.value?.mbz?.[0]
  const prepare = await call<{ success: boolean; contextid?: number; filename?: string; error?: string }>(
    'local_sm_graphics_plugin_sharepoint_prepare_restore',
    {
      folder_url: currentUrl.value,
      categoryid: 1,
      companyids: JSON.stringify(selectedCompanyIds.value),
      mbz_item_id: cachedMbz?.item_id || '',
      mbz_name: cachedMbz?.name || '',
    },
  )

  if (prepare.error || !prepare.data?.success) {
    importing.value = false
    importResult.value = {
      success: false,
      error: prepare.error || prepare.data?.error || 'Failed to prepare SharePoint download.',
    }
    return
  }

  // Step 2: persist the filename + folder URL + scan manifest so the
  // restore wizard's Confirm step can render the same file table the
  // courseloader showed (SCORMs / PDFs / evaluations / etc.). sessionStorage
  // works because both pages share the same browser tab/origin.
  if (prepare.data.filename) {
    window.sessionStorage.setItem('smgp_restore_sp_filename', prepare.data.filename)
  }
  window.sessionStorage.setItem('smgp_restore_sp_folder_url', currentUrl.value)
  if (scanResult.value && !scanResult.value.error) {
    // Strip down to the bits the wizard needs (no item_ids — those are
    // only useful for the prepare backend call we already finished).
    const slim = {
      mbz: (scanResult.value.mbz ?? []).map((f: any) => ({ name: f.name, size: f.size })),
      scorm: (scanResult.value.scorm ?? []).map((f: any) => ({ name: f.name, size: f.size })),
      pdf: (scanResult.value.pdf ?? []).map((f: any) => ({ name: f.name, size: f.size })),
      documents: (scanResult.value.documents ?? []).map((f: any) => ({ name: f.name, size: f.size })),
      evaluations_aiken: (scanResult.value.evaluations_aiken ?? []).map((f: any) => ({ name: f.name, size: f.size })),
      evaluations_gift: (scanResult.value.evaluations_gift ?? []).map((f: any) => ({ name: f.name, size: f.size })),
    }
    window.sessionStorage.setItem('smgp_restore_sp_scan', JSON.stringify(slim))
  }
  // Selected companies are already persisted under smgp_restore_company_ids
  // by the watch in the companies-state block above.

  importing.value = false
  // Step 3: navigate to the Vue restore wizard. The wizard's step 1
  // will detect the staged filename and call restore_prepare with it
  // automatically, then advance through Destination → Settings → Schema
  // → Review → Process → Complete.
  router.push('/admin/restore')
}

loadCachedCourses()
loadCompanies()
</script>

<style scoped lang="scss">
.smgp-courseloader {
  max-width: 960px;
  margin: 2rem auto;
  padding: 0 1rem;

  &__title { font-size: 1.75rem; font-weight: 700; color: #1e293b; }
  &__subtitle { color: #64748b; margin-bottom: 1.5rem; }

  &__section {
    position: relative;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-left: 4px solid #10b981;
    border-radius: 12px;
    padding: 1.25rem 1.5rem 1.25rem 1.75rem;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
    margin-bottom: 1.25rem;
  }
  &__section-title {
    font-size: 1.05rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0 0 1rem;
    letter-spacing: -0.01em;
  }

  &__row {
    display: flex;
    gap: 0.75rem;
    align-items: stretch;
    margin-bottom: 0.5rem;
  }
  &__search {
    flex: 1;
    position: relative;

    .form-control {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 8px;
      padding: 0.6rem 0.9rem;
      &:focus { background: #fff; box-shadow: 0 0 0 2px rgba(22, 163, 74, 0.15); border-color: #16a34a; }
    }
  }
  &__url-btn {
    white-space: nowrap;
    border-radius: 8px;
    padding: 0.55rem 1.1rem;
    background: #fff;
    border: 1px solid #bbf7d0;
    color: #10b981;
    font-weight: 500;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    transition: background-color 0.15s ease, border-color 0.15s ease;
    &:hover {
      background: #f0fdf4;
      border-color: #16a34a;
      color: #15803d;
    }
    i { color: #16a34a; }
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
      &:focus { background: #fff; box-shadow: 0 0 0 2px rgba(22, 163, 74, 0.15); border-color: #16a34a; }
    }
  }

  &__suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    margin: 0;
    padding: 0;
    list-style: none;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.10);
    max-height: 280px;
    overflow-y: auto;
    z-index: 20;
  }
  &__suggestion {
    padding: 0.55rem 0.85rem;
    cursor: pointer;
    border-bottom: 1px solid #f1f5f9;
    &:last-child { border-bottom: none; }
    &:hover, &.is-selected { background: #f0fdf4; }
  }
  &__suggestion-match {
    background: transparent;
    color: #10b981;
    font-weight: 600;
    padding: 0;
  }

  &__hint {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: #64748b;
    margin-top: 0.5rem;
  }
  &__hint-count { color: #94a3b8; }
  &__hint-sep   { color: #cbd5e1; }
  &__sync-btn {
    background: none;
    border: none;
    padding: 0;
    color: #10b981;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    &:hover { color: #15803d; }
    &:disabled { color: #94a3b8; cursor: default; }
  }
  &__spinning {
    animation: smgp-courseloader-spin 0.9s linear infinite;
  }

  &__sync-log {
    margin-top: 0.85rem;
    border: 1px solid #1e293b;
    border-radius: 8px;
    background: #0f172a;
    overflow: hidden;
  }
  &__sync-log-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.45rem 0.8rem;
    background: #1e293b;
    color: #94a3b8;
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
  }
  &__sync-log-close {
    background: none;
    border: none;
    color: #94a3b8;
    font-size: 1.1rem;
    line-height: 1;
    cursor: pointer;
    padding: 0 0.25rem;
    &:hover { color: #f1f5f9; }
  }
  &__sync-log pre {
    margin: 0;
    padding: 0.75rem 0.9rem;
    color: #e2e8f0;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 0.78rem;
    line-height: 1.45;
    max-height: 280px;
    overflow-y: auto;
    white-space: pre-wrap;
    word-break: break-word;
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

    // Toggle cell — fixed width, position:relative so the wrapper inside
    // can be absolutely centred regardless of cell padding / Bootstrap
    // form-switch hacks.
    th.smgp-courseloader__toggle-cell,
    td.smgp-courseloader__toggle-cell {
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

    // Kill Bootstrap's padding-left/negative-margin label-room hacks.
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
        background-color: #16a34a;
        border-color: #16a34a;
      }
    }
  }

  &__scan {
    display: flex;
    justify-content: flex-start;
    margin: 1.25rem 0;

    .btn-success {
      background-color: #10b981;
      border-color: #10b981;
      border-radius: 8px;
      padding: 0.6rem 1.4rem;
      font-weight: 600;
      color: #fff;
      i { color: #fff; }
      &:hover,
      &:focus,
      &:active {
        background-color: #15803d;
        border-color: #15803d;
        color: #fff;
        i { color: #fff; }
      }
      &:disabled {
        color: #fff;
        opacity: 0.6;
      }
    }
  }

  &__file-table {
    margin-top: 1rem;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    background: #fff;
    overflow: hidden;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);

    table {
      margin-bottom: 0;
      border-collapse: separate;
      border-spacing: 0;
      width: 100%;
    }
    thead th {
      background: #f8fafc;
      font-size: 0.72rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: #94a3b8;
      padding: 0.7rem 1rem;
      border-bottom: 1px solid #e2e8f0;
      border-right: 1px solid #eef2f7;
      vertical-align: middle;
      &:last-child { border-right: none; }
    }
    tbody td {
      padding: 0.65rem 1rem;
      border-top: 1px solid #eef2f7;
      border-right: 1px solid #eef2f7;
      vertical-align: middle;
      background: #fff;
      color: #1e293b;
      font-size: 0.9rem;
      &:last-child { border-right: none; }
    }
    tbody tr:first-child td { border-top: none; }
    tbody tr:hover td { background: #f0fdf4; }
  }
  &__file-icon {
    text-align: center;
    font-size: 1.3rem;
    line-height: 1;
  }
  .sm-file-icon--green  { color: #10b981; }
  .sm-file-icon--blue   { color: #2563eb; }
  .sm-file-icon--red    { color: #dc2626; }
  .sm-file-icon--slate  { color: #64748b; }
  .sm-file-icon--amber  { color: #d97706; }
  .sm-file-icon--violet { color: #7c3aed; }

  &__warnings {
    margin-top: 1.25rem;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 10px;
    padding: 0.75rem 1rem;
  }
  &__warning {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    color: #92400e;
    font-size: 0.9rem;
    & + & { margin-top: 0.4rem; }
  }
  &__warning-icon {
    font-size: 1.15rem;
    line-height: 1;
    color: #d97706;
    flex-shrink: 0;
  }

  &__type-badge {
    display: inline-block;
    padding: 0.18rem 0.55rem;
    border-radius: 999px;
    font-size: 0.7rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    line-height: 1.2;

    &--green  { background: rgba(16, 185, 129, 0.12); color: #10b981; }
    &--blue   { background: rgba(37, 99, 235, 0.12);  color: #2563eb; }
    &--red    { background: rgba(220, 38, 38, 0.12);  color: #dc2626; }
    &--slate  { background: rgba(100, 116, 139, 0.12); color: #64748b; }
    &--amber  { background: rgba(217, 119, 6, 0.12);  color: #d97706; }
    &--violet { background: rgba(124, 58, 237, 0.12); color: #7c3aed; }
  }

  &__stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1rem;
  }
  &__stat {
    background: #f8fafc;
    border-radius: 8px;
    padding: 1rem;
    text-align: center;
  }
  &__stat-value {
    display: block;
    font-size: 1.75rem;
    font-weight: 700;
    color: #16a34a;
  }
  &__stat-label { font-size: 0.85rem; color: #64748b; }

  &__actions { margin-top: 0.5rem; }
  &__log {
    margin-top: 1rem;
    pre {
      background: #0f172a;
      color: #e2e8f0;
      padding: 1rem;
      border-radius: 8px;
      max-height: 300px;
      overflow-y: auto;
      font-size: 0.8rem;
    }
  }
}

@keyframes smgp-courseloader-spin {
  to { transform: rotate(360deg); }
}
</style>
