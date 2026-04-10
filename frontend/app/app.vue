<template>
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
</template>

<script setup lang="ts">
import '~/types/bootstrap'
import { useDashboardStore } from '~/stores/dashboard'
import { useCatalogueStore } from '~/stores/catalogue'
import { useProfileStore } from '~/stores/profile'

const authStore = useAuthStore()

// Initialize auth from Moodle bootstrap data on app startup,
// then prefetch dashboard data so navigating there is instant.
// Once the dashboard arrives, prefetch catalogue and profile in
// background so those pages are also instant when visited.
onMounted(async () => {
  authStore.init()
  await useDashboardStore().fetch()
  useCatalogueStore().fetch()
  useProfileStore().fetchProfile()
})
</script>
