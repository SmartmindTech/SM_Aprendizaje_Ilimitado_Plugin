<template>
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
</template>

<script setup lang="ts">
import '~/types/bootstrap'

const authStore = useAuthStore()

// Initialize auth from Moodle bootstrap data on app startup.
onMounted(() => {
  authStore.init()
})
</script>
