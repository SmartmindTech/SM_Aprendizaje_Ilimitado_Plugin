<template>
  <div v-if="loading" class="smgp-scorm-player__loading">
    <div class="spinner-border text-primary" role="status" />
  </div>

  <div v-else-if="error" class="smgp-scorm-player__error">
    <p>{{ error }}</p>
  </div>

  <div v-else class="smgp-scorm-player">
    <iframe
      ref="scormFrame"
      :src="launchUrl"
      class="smgp-scorm-player__frame"
      allow="fullscreen; autoplay; encrypted-media"
      allowfullscreen
      @load="onFrameLoad"
    />
  </div>
</template>

<script setup lang="ts">
/**
 * Vue-native SCORM player.
 *
 * Replaces the Moodle player.php iframe with a bare iframe that only
 * loads the SCORM package content. Provides the SCORM RTE API
 * (window.API for 1.2, window.API_1484_11 for 2004) so the package
 * can communicate grades and progress.
 *
 * All CMI writes go through save_scorm_cmi_data which calls
 * scorm_insert_track() — the same function Moodle's native player uses.
 */

const props = defineProps<{
  cmid: number
}>()

const emit = defineEmits<{
  (e: 'progress', completed: number, total: number): void
  (e: 'complete'): void
}>()

const { call } = useMoodleAjax()

const loading = ref(true)
const error = ref('')
const launchUrl = ref('')
const scormFrame = ref<HTMLIFrameElement | null>(null)

// SCORM session state.
let scormType = 'scorm_12'
let scoid = 0
let attempt = 1
let slideCount = 0
let cmiCache: Record<string, string> = {}
let dirtyKeys: Set<string> = new Set()
let commitTimer: ReturnType<typeof setTimeout> | null = null
let initialized = false

onMounted(async () => {
  const result = await call('local_sm_graphics_plugin_get_scorm_cmi_data', {
    cmid: props.cmid,
  })

  if (result.error || !result.data?.success) {
    error.value = result.error || result.data?.message || 'Failed to load SCORM data'
    loading.value = false
    return
  }

  const data = result.data as any
  scormType = data.scormtype
  scoid = data.scoid
  attempt = data.attempt
  slideCount = data.slidecount
  launchUrl.value = data.launchurl

  // Populate CMI cache from stored data.
  for (const item of data.cmidata) {
    cmiCache[item.element] = item.value
  }

  // Inject the RTE API before the iframe loads.
  injectRteApi()

  loading.value = false
})

onBeforeUnmount(() => {
  // Flush any pending CMI data.
  flushCmiData()
  if (commitTimer) clearTimeout(commitTimer)
  // Clean up global API objects.
  if (typeof window !== 'undefined') {
    delete (window as any).API
    delete (window as any).API_1484_11
  }
})

/**
 * Inject the SCORM RTE API object into the window scope.
 * The SCORM package running in the iframe will look for
 * window.parent.API (1.2) or window.parent.API_1484_11 (2004).
 */
function injectRteApi() {
  if (typeof window === 'undefined') return

  const api = {
    // ── SCORM 1.2 API ──
    LMSInitialize: (_param: string) => {
      initialized = true
      return 'true'
    },
    LMSFinish: (_param: string) => {
      flushCmiData()
      finishAttempt()
      return 'true'
    },
    LMSGetValue: (element: string) => {
      return cmiCache[element] ?? ''
    },
    LMSSetValue: (element: string, value: string) => {
      cmiCache[element] = value
      dirtyKeys.add(element)
      scheduleCommit()
      onCmiValueChanged(element, value)
      return 'true'
    },
    LMSCommit: (_param: string) => {
      flushCmiData()
      return 'true'
    },
    LMSGetLastError: () => '0',
    LMSGetErrorString: (_code: string) => '',
    LMSGetDiagnostic: (_code: string) => '',

    // ── SCORM 2004 API ──
    Initialize: (_param: string) => {
      initialized = true
      return 'true'
    },
    Terminate: (_param: string) => {
      flushCmiData()
      finishAttempt()
      return 'true'
    },
    GetValue: (element: string) => {
      return cmiCache[element] ?? ''
    },
    SetValue: (element: string, value: string) => {
      cmiCache[element] = value
      dirtyKeys.add(element)
      scheduleCommit()
      onCmiValueChanged(element, value)
      return 'true'
    },
    Commit: (_param: string) => {
      flushCmiData()
      return 'true'
    },
    GetLastError: () => '0',
    GetErrorString: (_code: string) => '',
    GetDiagnostic: (_code: string) => '',
  }

  // SCORM 1.2 packages look for window.API on parent.
  ;(window as any).API = api
  // SCORM 2004 packages look for window.API_1484_11 on parent.
  ;(window as any).API_1484_11 = api
}

/**
 * Schedule a debounced commit (2 seconds after last SetValue).
 */
function scheduleCommit() {
  if (commitTimer) clearTimeout(commitTimer)
  commitTimer = setTimeout(() => flushCmiData(), 2000)
}

/**
 * Flush all dirty CMI values to the backend.
 */
async function flushCmiData() {
  if (dirtyKeys.size === 0) return
  if (commitTimer) {
    clearTimeout(commitTimer)
    commitTimer = null
  }

  const data = Array.from(dirtyKeys).map(key => ({
    element: key,
    value: cmiCache[key] ?? '',
  }))
  dirtyKeys.clear()

  await call('local_sm_graphics_plugin_save_scorm_cmi_data', {
    cmid: props.cmid,
    scoid,
    attempt,
    data,
  })
}

/**
 * Finish the SCORM attempt.
 */
async function finishAttempt() {
  await call('local_sm_graphics_plugin_finish_scorm_attempt', {
    cmid: props.cmid,
    scoid,
    attempt,
  })
  emit('complete')
}

/**
 * React to CMI value changes for progress tracking.
 */
function onCmiValueChanged(element: string, value: string) {
  // Track slide progress from lesson_location.
  if (element === 'cmi.core.lesson_location' || element === 'cmi.location') {
    const slide = parseSlideFromLocation(value)
    if (slide > 0 && slideCount > 0) {
      emit('progress', slide, slideCount)
    }
  }

  // Detect completion.
  if (element === 'cmi.core.lesson_status' || element === 'cmi.completion_status') {
    if (value === 'completed' || value === 'passed') {
      emit('complete')
    }
  }
}

/**
 * Parse a slide number from a lesson_location value.
 */
function parseSlideFromLocation(location: string): number {
  if (!location) return 0
  // Pure number.
  if (/^\d+$/.test(location)) return parseInt(location, 10) || 1
  // Rise 360: "section_0".
  const sectionMatch = location.match(/^section_(\d+)$/i)
  if (sectionMatch) return parseInt(sectionMatch[1], 10) + 1
  // Storyline: "slide5", "Slide_5".
  const slideMatch = location.match(/slide[_-]?(\d+)/i)
  if (slideMatch) return parseInt(slideMatch[1], 10)
  // Scene_slide: "1_5".
  const sceneMatch = location.match(/^(\d+)_(\d+)$/)
  if (sceneMatch) return parseInt(sceneMatch[2], 10) + 1
  // Trailing number.
  const trailingMatch = location.match(/(\d+)$/)
  if (trailingMatch) return parseInt(trailingMatch[1], 10)
  return 0
}

function onFrameLoad() {
  // The frame loaded — SCORM package should now find window.parent.API.
  // Dispatch resize to ensure content fills the frame.
  setTimeout(() => {
    scormFrame.value?.contentWindow?.dispatchEvent(new Event('resize'))
  }, 500)
}
</script>
