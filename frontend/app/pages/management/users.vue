<template>
  <div v-if="loading" class="text-center py-5">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">{{ $t('app.loading') }}</span>
    </div>
  </div>

  <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

  <template v-else-if="data">
    <div class="sm-usermanagement p-4 w-100">
      <!-- Management option cards -->
      <h2 class="mb-4">{{ data.heading || $t('nav.management') }}</h2>
      <div class="mb-5">
        <div class="sm-admin-cards">
          <template v-for="option in data.options" :key="option.title">
            <div v-if="option.disabled" class="card shadow-sm sm-admin-card sm-admin-card--disabled">
              <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-3">
                <i :class="'fa ' + option.icon + ' fa-2x mb-2 text-muted'" />
                <h6 class="card-title mb-1 text-muted">{{ option.title }}</h6>
                <p class="card-text text-muted small mb-0">{{ option.description }}</p>
              </div>
            </div>
            <NuxtLink
              v-else
              :to="option.vue_route || option.url"
              class="card text-decoration-none shadow-sm sm-admin-card"
            >
              <div class="card-body d-flex flex-column align-items-center justify-content-center text-center p-3">
                <i :class="'fa ' + option.icon + ' fa-2x mb-2 text-primary'" />
                <h6 class="card-title mb-1">{{ option.title }}</h6>
                <p class="card-text text-muted small mb-0">{{ option.description }}</p>
              </div>
            </NuxtLink>
          </template>
        </div>
      </div>
    </div>

    <!-- Registered users -->
    <div class="sm-userlist p-4 w-100">
      <h3 class="mb-3 d-flex justify-content-between align-items-center">
        <span>{{ data.userlist_heading || 'Registered users' }}</span>
        <template v-if="data.haslimit">
          <span
            class="small border rounded px-2 py-1"
            :class="data.limitreached ? 'text-danger fw-bold border-danger' : 'text-dark border-secondary'"
          >
            {{ data.studentcount }} / {{ data.maxstudents }}
          </span>
        </template>
        <template v-else>
          <span class="small text-dark border border-secondary rounded px-2 py-1">
            {{ data.usercount }}
          </span>
        </template>
      </h3>

      <div v-if="data.limitreached" class="alert alert-warning">
        <i class="fa fa-exclamation-triangle" />
        {{ data.limit_reached_msg || 'Student limit reached.' }}
      </div>

      <template v-if="data.hasusers">
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle sm-usermgmt-table">
            <thead class="table-light">
              <tr>
                <th>{{ data.th_name || 'Name' }}</th>
                <th>{{ data.th_email || 'Email' }}</th>
                <th>{{ data.th_lastaccess || 'Last access' }}</th>
                <th class="text-center">{{ data.th_actions || 'Actions' }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="user in data.users" :key="user.id || user.email">
                <td>{{ user.fullname }}</td>
                <td>{{ user.email }}</td>
                <td>{{ user.lastaccess }}</td>
                <td class="text-center text-nowrap">
                  <a
                    v-if="user.editurl"
                    :href="user.editurl"
                    class="btn btn-sm btn-outline-primary me-1"
                  >
                    <i class="fa fa-pen" />
                    {{ data.edit_label || 'Edit' }}
                  </a>
                  <button
                    v-if="user.deleteurl"
                    class="btn btn-sm btn-outline-danger"
                    @click="confirmDelete(user)"
                  >
                    <i class="fa fa-trash-can" />
                    {{ data.delete_label || 'Delete' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>

      <div v-else class="alert alert-info">
        {{ data.nousers || 'No users found.' }}
      </div>
    </div>
  </template>
</template>

<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

const { getCompanyUsers, deleteCompanyUser } = useManagementApi()

const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<any>(null)

const fetchData = async () => {
  loading.value = true
  const result = await getCompanyUsers()
  loading.value = false
  if (result.error) {
    error.value = result.error
  } else {
    data.value = result.data
  }
}

const confirmDelete = async (user: any) => {
  if (!confirm(data.value?.delete_confirm || `Delete user ${user.fullname}?`)) return
  const result = await deleteCompanyUser(user.id)
  if (result.error) {
    alert(result.error)
  } else {
    await fetchData()
  }
}

fetchData()
</script>

<style scoped>
.sm-usermanagement,
.sm-userlist {
  width: 100%;
  max-width: 100%;
}

.sm-admin-cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 1rem;
  width: 100%;
}
@media (min-width: 992px) {
  .sm-admin-cards {
    grid-template-columns: repeat(5, 1fr);
  }
}

.sm-admin-card {
  border: 1px solid #dee2e6;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.sm-admin-card .card-body {
  min-height: 140px;
}
.sm-admin-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12) !important;
}
.sm-admin-card .card-title {
  color: #1a1f35;
}
.sm-admin-card--disabled {
  opacity: 0.5;
  cursor: not-allowed;
  pointer-events: none;
}

.sm-usermgmt-table {
  width: 100%;
}
.sm-usermgmt-table th {
  white-space: nowrap;
}
</style>
