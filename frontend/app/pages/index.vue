<template>
  <div />
</template>

<script setup lang="ts">
// Root route — redirect based on auth state.
// Uses a route middleware so the redirect happens BEFORE the component mounts
// (avoids the blank gray screen caused by async setup).
definePageMeta({
  middleware: [() => {
    const auth = useAuthStore()
    if (!auth.initialized) auth.init()
    if (auth.isAuthenticated) {
      return navigateTo('/dashboard', { replace: true })
    }
    return navigateTo('/login', { replace: true })
  }],
})
</script>
