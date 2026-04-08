<template>
  <div />
</template>

<script setup lang="ts">
// Root route is just a redirect to the dashboard. We use a route middleware so
// the redirect happens BEFORE the page component mounts — using `await
// navigateTo()` inside <script setup> made the setup async, which suspends the
// whole app render and leaves a blank gray screen.
definePageMeta({
  middleware: [() => navigateTo('/dashboard', { replace: true })],
})
</script>
