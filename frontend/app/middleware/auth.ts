/**
 * Route guard for management and admin pages.
 *
 * Since spa.php already calls require_login(), the user is always authenticated.
 * This middleware only checks role-based access for restricted routes.
 */
export default defineNuxtRouteMiddleware((to) => {
  const authStore = useAuthStore()

  // Ensure bootstrap data is loaded.
  if (!authStore.initialized) {
    authStore.init()
  }

  // Admin routes require site admin role.
  if (to.path.startsWith('/admin') && !authStore.canAccessAdmin()) {
    return navigateTo('/')
  }

  // Management routes require manager or admin role.
  if (to.path.startsWith('/management') && !authStore.canAccessManagement()) {
    return navigateTo('/')
  }
})
