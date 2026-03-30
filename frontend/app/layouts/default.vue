<template>
  <div class="d-flex">
    <!-- Collapsible sidebar (matches dump_*.html sidebar structure) -->
    <nav class="d-flex flex-column vh-100 w-25 align-items-start" aria-label="SmartMind navigation">
      <div class="d-flex flex-column gap-4 card shadow p-4 vh-100 position-fixed">
        <!-- Logo -->
        <NuxtLink to="/dashboard" class="text-decoration-none text-dark d-block text-center mt-4 mb-2">
          <img src="/img/smartmind_logo.png" class="logo me-1" alt="SmartMind" style="width: 80px;">
        </NuxtLink>

        <!-- Primary navigation -->
        <div class="primary-navigation flex-grow-1 d-flex flex-column justify-content-between align-items-center">
          <ul class="d-flex flex-column gap-4 list-unstyled">

            <!-- STUDENT nav items -->
            <template v-if="!authStore.isManager && !authStore.isAdmin">
              <li data-key="myhome" class="nav-item" role="none">
                <NuxtLink to="/dashboard" class="nav-link" active-class="active" data-disableactive="true">
                  <span class="smartmind-nav-icon" aria-hidden="true">
                    <i class="fa fa-fw fa-circle-o smartmind-nav-icon-default" />
                  </span>
                  <span class="smartmind-nav-text">Personal space</span>
                </NuxtLink>
              </li>
              <li data-key="home" class="nav-item" role="none">
                <NuxtLink to="/catalogue" class="nav-link" active-class="active">
                  <span class="smartmind-nav-icon" aria-hidden="true">
                    <i class="fa fa-fw fa-circle-o smartmind-nav-icon-default" />
                  </span>
                  <span class="smartmind-nav-text">Catalogue</span>
                </NuxtLink>
              </li>
              <li data-key="sm-mycourses" class="nav-item" role="none">
                <NuxtLink to="/courses" class="nav-link" active-class="active">
                  <span class="smartmind-nav-icon" aria-hidden="true">
                    <i class="fa fa-fw fa-circle-o smartmind-nav-icon-default" />
                  </span>
                  <span class="smartmind-nav-text">My courses</span>
                </NuxtLink>
              </li>
              <li data-key="sm-gradescerts" class="nav-item" role="none">
                <NuxtLink to="/grades-certificates" class="nav-link" active-class="active">
                  <span class="smartmind-nav-icon" aria-hidden="true">
                    <i class="fa fa-fw fa-circle-o smartmind-nav-icon-default" />
                  </span>
                  <span class="smartmind-nav-text">Grades &amp; Certificates</span>
                </NuxtLink>
              </li>
            </template>

            <!-- MANAGER nav items -->
            <template v-else-if="authStore.isManager && !authStore.isAdmin">
              <li data-key="sm-usermanagement" class="nav-item" role="none">
                <NuxtLink to="/management/users" class="nav-link" active-class="active">
                  <span class="smartmind-nav-icon" aria-hidden="true">
                    <i class="fa fa-fw fa-circle-o smartmind-nav-icon-default" />
                  </span>
                  <span class="smartmind-nav-text">User management</span>
                </NuxtLink>
              </li>
              <li data-key="sm-othermanagement" class="nav-item" role="none">
                <NuxtLink to="/admin/iomad-dashboard" class="nav-link" active-class="active">
                  <span class="smartmind-nav-icon" aria-hidden="true">
                    <i class="fa fa-fw fa-circle-o smartmind-nav-icon-default" />
                  </span>
                  <span class="smartmind-nav-text">Other management</span>
                </NuxtLink>
              </li>
              <li data-key="sm-gradescerts" class="nav-item" role="none">
                <NuxtLink to="/grades-certificates" class="nav-link" active-class="active">
                  <span class="smartmind-nav-icon" aria-hidden="true">
                    <i class="fa fa-fw fa-circle-o smartmind-nav-icon-default" />
                  </span>
                  <span class="smartmind-nav-text">Grades &amp; Certificates</span>
                </NuxtLink>
              </li>
              <li data-key="sm-statistics" class="nav-item" role="none">
                <NuxtLink to="/statistics" class="nav-link" active-class="active">
                  <span class="smartmind-nav-icon" aria-hidden="true">
                    <i class="fa fa-fw fa-circle-o smartmind-nav-icon-default" />
                  </span>
                  <span class="smartmind-nav-text">Statistics</span>
                </NuxtLink>
              </li>
            </template>

            <!-- ADMIN nav items -->
            <template v-else-if="authStore.isAdmin">
              <li data-key="myhome" class="nav-item" role="none">
                <NuxtLink to="/dashboard" class="nav-link" active-class="active" data-disableactive="true">
                  <span class="smartmind-nav-icon" aria-hidden="true">
                    <i class="fa fa-fw fa-circle-o smartmind-nav-icon-default" />
                  </span>
                  <span class="smartmind-nav-text">Personal space</span>
                </NuxtLink>
              </li>
              <li data-key="home" class="nav-item" role="none">
                <NuxtLink to="/catalogue" class="nav-link" active-class="active">
                  <span class="smartmind-nav-icon" aria-hidden="true">
                    <i class="fa fa-fw fa-circle-o smartmind-nav-icon-default" />
                  </span>
                  <span class="smartmind-nav-text">Catalogue</span>
                </NuxtLink>
              </li>
              <li data-key="mycourses" class="nav-item" role="none">
                <NuxtLink to="/management/courses" class="nav-link" active-class="active">
                  <span class="smartmind-nav-icon" aria-hidden="true">
                    <i class="fa fa-fw fa-circle-o smartmind-nav-icon-default" />
                  </span>
                  <span class="smartmind-nav-text">Course management</span>
                </NuxtLink>
              </li>
              <li data-key="siteadminnode" class="nav-item" role="none">
                <a :href="authStore.wwwroot + '/admin/search.php'" class="nav-link" target="_blank">
                  <span class="smartmind-nav-icon" aria-hidden="true">
                    <i class="fa fa-fw fa-circle-o smartmind-nav-icon-default" />
                  </span>
                  <span class="smartmind-nav-text">Administration du site</span>
                </a>
              </li>
              <li data-key="ioaddashboardnode" class="nav-item" role="none">
                <NuxtLink to="/admin/iomad-dashboard" class="nav-link" active-class="active">
                  <span class="smartmind-nav-icon" aria-hidden="true">
                    <i class="fa fa-fw fa-circle-o smartmind-nav-icon-default" />
                  </span>
                  <span class="smartmind-nav-text">IOMAD dashboard</span>
                </NuxtLink>
              </li>
            </template>
          </ul>

          <!-- User area at bottom -->
          <div class="d-flex align-items-stretch usermenu-container" data-region="usermenu">
            <div class="usermenu">
              <div class="dropdown">
                <a href="#" role="button" class="btn dropdown-toggle" aria-label="User menu">
                  <span class="userbutton">
                    <span class="avatars">
                      <span class="avatar current">
                        <span class="userinitials size-35" :title="authStore.fullname" :aria-label="authStore.fullname" role="img">
                          {{ userInitials }}
                        </span>
                        <span class="userfullname-text smartmind-nav-text">{{ authStore.fullname }}</span>
                      </span>
                    </span>
                  </span>
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </nav>

    <!-- Main content -->
    <div class="sm-main">
      <div class="sm-main__content">
        <slot />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const authStore = useAuthStore()

const userInitials = computed(() => {
  const name = authStore.fullname || ''
  const parts = name.split(' ')
  if (parts.length >= 2) {
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
  }
  return name.substring(0, 2).toUpperCase()
})
</script>

<style scoped>
.nav-link {
  display: flex !important;
  gap: 1rem !important;
  align-items: center !important;
}

.smartmind-nav-icon {
  margin-bottom: 3% !important;
}

/* User initials avatar */
.userinitials.size-35 {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: #6366f1;
  color: #fff;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 0.875rem;
  flex-shrink: 0;
}

.userfullname-text {
  margin-left: 0.5rem;
}

.dropdown-toggle {
  border: none !important;
  box-shadow: none !important;
}

.dropdown-toggle::after {
  display: none;
}
</style>
