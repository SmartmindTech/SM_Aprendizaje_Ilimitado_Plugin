<template>
  <div class="sm-welcome p-4">
    <div v-if="loading" class="text-center py-5">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">{{ $t('app.loading') }}</span>
      </div>
    </div>

    <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

    <template v-else>
      <h2>Hello, {{ data?.username }}!</h2>
      <p>Welcome to the SmartMind learning platform.</p>
      <NuxtLink to="/dashboard" class="btn btn-primary">
        Go to Dashboard
      </NuxtLink>
    </template>
  </div>
</template>

<script setup lang="ts">
const authStore = useAuthStore()
const { call } = useMoodleAjax()

const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<any>(null)

onMounted(async () => {
  const result = await call('local_sm_graphics_plugin_get_welcome_data')
  loading.value = false
  if (result.error) {
    error.value = result.error
  } else {
    data.value = result.data
  }
})
</script>
