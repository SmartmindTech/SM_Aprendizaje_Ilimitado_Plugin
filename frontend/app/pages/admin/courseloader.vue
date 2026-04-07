<template>
  <div class="smgp-courseloader">
    <h1 class="smgp-courseloader__title">{{ $t('courseloader.title') || 'SharePoint Course Loader' }}</h1>
    <p class="smgp-courseloader__subtitle">
      {{ $t('courseloader.subtitle') || 'Import a course from SharePoint: pick folder, scan, then import.' }}
    </p>

    <!-- Step 1: URL / course picker -->
    <section class="smgp-courseloader__section">
      <h2 class="smgp-courseloader__step-title">1. {{ $t('courseloader.step_pick') || 'Pick a course folder' }}</h2>

      <div class="smgp-courseloader__field">
        <label for="sp-course-select">{{ $t('courseloader.course_select') || 'Course (from cached list)' }}</label>
        <select id="sp-course-select" v-model="selectedCourseUrl" class="form-control" :disabled="loadingCourses">
          <option value="">{{ loadingCourses ? ($t('app.loading') || 'Loading...') : ($t('courseloader.pick_placeholder') || '— pick one —') }}</option>
          <option v-for="c in availableCourses" :key="c.web_url" :value="c.web_url">{{ c.name }}</option>
        </select>
      </div>

      <div class="smgp-courseloader__field">
        <label for="sp-course-url">{{ $t('courseloader.or_url') || 'or paste a SharePoint folder URL' }}</label>
        <input
          id="sp-course-url"
          v-model="manualUrl"
          type="url"
          class="form-control"
          :placeholder="$t('courseloader.url_placeholder') || 'https://your-tenant.sharepoint.com/...'"
        >
      </div>

      <div class="smgp-courseloader__actions">
        <button class="btn btn-primary" :disabled="scanning || !currentUrl" @click="onScan">
          <i class="icon-search" /> {{ scanning ? ($t('courseloader.scanning') || 'Scanning…') : ($t('courseloader.scan') || 'Scan folder') }}
        </button>
      </div>
    </section>

    <!-- Step 2: Scan results -->
    <section v-if="scanResult" class="smgp-courseloader__section">
      <h2 class="smgp-courseloader__step-title">2. {{ $t('courseloader.step_scan') || 'Scan results' }}</h2>
      <div v-if="scanResult.error" class="alert alert-danger">{{ scanResult.error }}</div>
      <div v-else>
        <div class="smgp-courseloader__stats">
          <div class="smgp-courseloader__stat">
            <span class="smgp-courseloader__stat-value">{{ scanResult.mbz_count || 0 }}</span>
            <span class="smgp-courseloader__stat-label">MBZ</span>
          </div>
          <div class="smgp-courseloader__stat">
            <span class="smgp-courseloader__stat-value">{{ scanResult.scorm_count || 0 }}</span>
            <span class="smgp-courseloader__stat-label">SCORM</span>
          </div>
          <div class="smgp-courseloader__stat">
            <span class="smgp-courseloader__stat-value">{{ scanResult.pdf_count || 0 }}</span>
            <span class="smgp-courseloader__stat-label">PDF</span>
          </div>
          <div class="smgp-courseloader__stat">
            <span class="smgp-courseloader__stat-value">{{ scanResult.eval_count || 0 }}</span>
            <span class="smgp-courseloader__stat-label">Evaluations</span>
          </div>
        </div>

        <div v-if="scanResult.warnings && scanResult.warnings.length" class="alert alert-warning">
          <ul class="mb-0">
            <li v-for="(w, i) in scanResult.warnings" :key="i">{{ w }}</li>
          </ul>
        </div>
      </div>
    </section>

    <!-- Step 3: Target category + import -->
    <section v-if="scanResult && !scanResult.error" class="smgp-courseloader__section">
      <h2 class="smgp-courseloader__step-title">3. {{ $t('courseloader.step_import') || 'Import into category' }}</h2>
      <div class="smgp-courseloader__field">
        <label for="sp-category">{{ $t('courseloader.category') || 'Destination category' }}</label>
        <select id="sp-category" v-model="categoryId" class="form-control">
          <option :value="1">Top (Miscellaneous)</option>
        </select>
      </div>

      <div class="smgp-courseloader__actions">
        <button class="btn btn-success" :disabled="importing" @click="onImport">
          <i class="icon-download" /> {{ importing ? ($t('courseloader.importing') || 'Importing…') : ($t('courseloader.import') || 'Import course') }}
        </button>
      </div>

      <!-- Live progress log (SSE) -->
      <div v-if="importLog.length" class="smgp-courseloader__log">
        <h3>{{ $t('courseloader.log') || 'Import log' }}</h3>
        <pre>{{ importLog.join('\n') }}</pre>
      </div>

      <!-- Final result -->
      <div v-if="importResult" :class="['alert', importResult.success ? 'alert-success' : 'alert-danger']">
        <strong v-if="importResult.success">{{ $t('courseloader.success') || 'Course imported successfully' }}</strong>
        <strong v-else>{{ $t('courseloader.error') || 'Import failed' }}</strong>
        <div v-if="importResult.course_url" class="mt-2">
          <NuxtLink :to="`/courses/${importResult.courseid}/landing`" class="btn btn-sm btn-primary">
            {{ $t('courseloader.view_course') || 'View course' }}
          </NuxtLink>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useMoodleAjax } from '~/composables/api_calls/useMoodleAjax'
import { useAuthStore } from '~/stores/auth'

definePageMeta({ middleware: ['auth'], layout: 'admin' })

const { call } = useMoodleAjax()
const auth = useAuthStore()

const loadingCourses = ref(false)
const availableCourses = ref<Array<{ name: string; web_url: string }>>([])
const selectedCourseUrl = ref('')
const manualUrl = ref('')
const categoryId = ref(1)

const scanning = ref(false)
const scanResult = ref<any>(null)

const importing = ref(false)
const importLog = ref<string[]>([])
const importResult = ref<any>(null)

const currentUrl = computed(() => selectedCourseUrl.value || manualUrl.value)

async function loadCachedCourses() {
  loadingCourses.value = true
  const result = await call('local_sm_graphics_plugin_sharepoint_list_courses', {})
  if (!result.error && result.data?.courses) {
    availableCourses.value = result.data.courses
  }
  loadingCourses.value = false
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

async function onImport() {
  if (!currentUrl.value) return
  importing.value = true
  importLog.value = []
  importResult.value = null

  // Try SSE streaming first (for live progress).
  const ssEndpoint = `${auth.wwwroot}/local/sm_graphics_plugin/pages/courseloader_sync.php`
      + `?sesskey=${encodeURIComponent(auth.sesskey)}`
      + `&folder_url=${encodeURIComponent(currentUrl.value)}`
      + `&categoryid=${categoryId.value}`

  try {
    const es = new EventSource(ssEndpoint, { withCredentials: true })
    es.onmessage = (e) => {
      try {
        const msg = JSON.parse(e.data)
        if (msg.log) importLog.value.push(msg.log)
        if (msg.done) {
          importResult.value = msg.result
          es.close()
          importing.value = false
        }
      } catch {
        importLog.value.push(e.data)
      }
    }
    es.onerror = async () => {
      es.close()
      // Fallback to plain AJAX call.
      const result = await call('local_sm_graphics_plugin_sharepoint_import', {
        folder_url: currentUrl.value,
        categoryid: categoryId.value,
      })
      importResult.value = result.error ? { success: false, error: result.error } : result.data
      importing.value = false
    }
  } catch {
    // SSE unsupported — fall back to plain AJAX.
    const result = await call('local_sm_graphics_plugin_sharepoint_import', {
      folder_url: currentUrl.value,
      categoryid: categoryId.value,
    })
    importResult.value = result.error ? { success: false, error: result.error } : result.data
    importing.value = false
  }
}

loadCachedCourses()
</script>

<style scoped lang="scss">
.smgp-courseloader {
  max-width: 960px;
  margin: 2rem auto;
  padding: 0 1rem;

  &__title { font-size: 1.75rem; font-weight: 700; color: #1e293b; }
  &__subtitle { color: #64748b; margin-bottom: 1.5rem; }
  &__section {
    background: #fff;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    margin-bottom: 1.25rem;
  }
  &__step-title {
    font-size: 1.15rem;
    font-weight: 600;
    color: #1e293b;
    margin: 0 0 1rem;
  }
  &__field {
    margin-bottom: 1rem;
    label { display: block; font-weight: 600; margin-bottom: 0.35rem; }
  }
  &__actions { margin-top: 1rem; }
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
    color: #10b981;
  }
  &__stat-label { font-size: 0.85rem; color: #64748b; }
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
</style>
