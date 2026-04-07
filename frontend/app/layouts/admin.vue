<template>
  <div class="d-flex min-vh-100">
    <!-- Admin sidebar -->
    <aside class="sidebar border-end d-flex flex-column">
      <div class="sidebar__header p-3 border-bottom">
        <h5 class="mb-0 fw-bold text-primary">SmartMind</h5>
        <small class="text-muted">{{ $t('nav.admin') }}</small>
      </div>
      <nav class="sidebar__nav flex-grow-1 p-2">
        <NuxtLink to="/admin/settings" class="sidebar__link" active-class="sidebar__link--active">
          <i class="bi bi-gear me-2" />{{ $t('nav.settings') }}
        </NuxtLink>
        <NuxtLink to="/admin/company-limits" class="sidebar__link" active-class="sidebar__link--active">
          <i class="bi bi-building me-2" />Company Limits
        </NuxtLink>
        <NuxtLink to="/admin/iomad-dashboard" class="sidebar__link" active-class="sidebar__link--active">
          <i class="bi bi-speedometer2 me-2" />IOMAD Dashboard
        </NuxtLink>
        <NuxtLink to="/admin/updates" class="sidebar__link" active-class="sidebar__link--active">
          <i class="bi bi-cloud-download me-2" />Updates
        </NuxtLink>
        <NuxtLink to="/admin/course-editor" class="sidebar__link" active-class="sidebar__link--active">
          <i class="bi bi-pencil-square me-2" />Course Editor
        </NuxtLink>
        <NuxtLink to="/admin/course-restore" class="sidebar__link" active-class="sidebar__link--active">
          <i class="bi bi-arrow-counterclockwise me-2" />Restore Course
        </NuxtLink>
        <hr class="my-2">
        <NuxtLink to="/" class="sidebar__link">
          <i class="bi bi-arrow-left me-2" />Back to App
        </NuxtLink>
      </nav>
      <div class="sidebar__footer p-3 border-top small text-muted">
        {{ authStore.fullname }}
      </div>
    </aside>

    <!-- Main content -->
    <main class="flex-grow-1 p-4">
      <slot />
    </main>
  </div>
</template>

<script setup lang="ts">
const authStore = useAuthStore()
</script>

<style lang="scss" scoped>
.sidebar {
  width: 260px;
  background: #fff;
}

.sidebar__link {
  display: flex;
  align-items: center;
  padding: 0.5rem 0.75rem;
  border-radius: 0.375rem;
  color: #374151;
  text-decoration: none;
  font-size: 0.875rem;

  &:hover {
    background: #f3f4f6;
  }

  &--active {
    background: #eef2ff;
    color: #10b981;
    font-weight: 500;
  }
}
</style>
