<template>
  <div class="text-center" style="max-width:500px;margin:0 auto">
    <h2 class="mb-4">Certificate Verification</h2>
    <p class="text-muted">Verifying certificate code: <code>{{ code }}</code></p>
    <div class="alert alert-info">
      <i class="bi bi-info-circle me-2" />
      Redirecting to Moodle verification page...
    </div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'minimal' })

const route = useRoute()
const authStore = useAuthStore()
const code = computed(() => route.params.code as string)

onMounted(() => {
  // Redirect to the existing Moodle verification page.
  const base = authStore.wwwroot || ''
  window.location.href = `${base}/local/sm_graphics_plugin/pages/verify_certificate.php?code=${code.value}`
})
</script>
