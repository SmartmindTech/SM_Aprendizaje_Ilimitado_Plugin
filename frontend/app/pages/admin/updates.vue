<template>
  <div>
    <h1 class="mb-4">Plugin Updates</h1>

    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status" />
    </div>

    <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

    <template v-else-if="data">
      <div class="card" style="max-width:500px">
        <div class="card-body">
          <div class="mb-3">
            <span class="text-muted">Current version:</span>
            <strong class="ms-2">{{ data.currentrelease }}</strong>
            <small class="text-muted ms-1">({{ data.currentversion }})</small>
          </div>

          <div v-if="data.hasupdate" class="alert alert-info">
            <strong>Update available!</strong>
            <div>New version: <strong>{{ data.newrelease }}</strong> ({{ data.newversion }})</div>
            <a :href="data.downloadurl" class="btn btn-primary mt-2" target="_blank">
              <i class="bi bi-download me-1" />Download Update
            </a>
          </div>

          <div v-else class="alert alert-success">
            <i class="bi bi-check-circle me-1" />Plugin is up to date.
          </div>

          <button class="btn btn-outline-secondary" :disabled="checking" @click="checkUpdate">
            <span v-if="checking" class="spinner-border spinner-border-sm me-1" />
            Check for Updates
          </button>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'admin', middleware: 'auth' })

const { checkPluginUpdate } = useAdminApi()

const loading = ref(true)
const checking = ref(false)
const error = ref<string | null>(null)
const data = ref<any>(null)

const checkUpdate = async () => {
  checking.value = true
  const result = await checkPluginUpdate()
  checking.value = false
  if (result.error) { error.value = result.error } else { data.value = result.data }
}

onMounted(async () => {
  const result = await checkPluginUpdate()
  loading.value = false
  if (result.error) { error.value = result.error } else { data.value = result.data }
})
</script>
