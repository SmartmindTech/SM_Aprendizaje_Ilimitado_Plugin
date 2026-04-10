/**
 * Route guard — redirects to /login when unauthenticated, and enforces
 * role-based access for management/admin pages.
 */
export default defineNuxtRouteMiddleware((to) => {
  const authStore = useAuthStore()

  // Ensure bootstrap data is loaded.
  if (!authStore.initialized) {
    authStore.init()
  }

  // Allow the login page without authentication.
  if (to.path === '/login') {
    // If already authenticated, skip login and go to dashboard.
    if (authStore.isAuthenticated) {
      return navigateTo('/dashboard', { replace: true })
    }
    return
  }

  // All other pages require authentication.
  if (!authStore.isAuthenticated) {
    return navigateTo('/login', { replace: true })
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
