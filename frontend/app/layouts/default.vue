<template>
  <div class="smgp-shell">
    <LayoutTopNavbar />
    <main id="page-content">
      <slot />
    </main>
  </div>
</template>

<script setup lang="ts">
// Layout shell. The actual nav (TopNavbar) and user area (UserArea) are
// auto-imported by Nuxt from app/components/layout/. Page content lives
// in <slot> wrapped by #page-content, whose width contract is defined
// in assets/scss/components/_layout.scss (single source of truth).
</script>
