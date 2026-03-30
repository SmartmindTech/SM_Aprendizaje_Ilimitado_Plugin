<template>
  <div class="p-4">
    <h2 class="mb-4">Administration du site</h2>
    <p class="text-muted mb-4">
      Site administration is managed through Moodle's built-in admin panel.
    </p>
    <a
      :href="authStore.wwwroot + '/admin/search.php'"
      class="btn btn-primary btn-lg"
      target="_blank"
    >
      <i class="fa fa-external-link me-2" />
      Open Moodle Site Administration
    </a>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ layout: 'default', middleware: 'auth' })
const authStore = useAuthStore()
</script>
