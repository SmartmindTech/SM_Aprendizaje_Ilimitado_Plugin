<template>
  <div v-if="loading" class="text-center py-5">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">{{ $t('app.loading') }}</span>
    </div>
  </div>

  <div v-else-if="error" class="alert alert-danger">{{ error }}</div>

  <div v-else-if="data" class="sm-companylimits p-4">
    <h2 class="mb-2">{{ data.heading || 'Company Limits' }}</h2>
    <p class="text-muted mb-4">{{ data.help || 'Set the maximum number of students per company. 0 = unlimited.' }}</p>

    <template v-if="data.hascompanies">
      <form @submit.prevent="saveAll">
        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light">
              <tr>
                <th>{{ data.th_company || 'Company' }}</th>
                <th>{{ data.th_shortname || 'Short name' }}</th>
                <th class="text-center">{{ data.th_students || 'Students' }}</th>
                <th class="text-center">{{ data.th_maxlimit || 'Max limit' }}</th>
                <th class="text-center">{{ data.th_status || 'Status' }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="company in data.companies" :key="company.companyid">
                <td>{{ company.companyname }}</td>
                <td class="text-muted">{{ company.shortname }}</td>
                <td class="text-center">{{ company.studentcount }}</td>
                <td class="text-center" style="width:140px">
                  <input
                    v-model.number="editLimits[company.companyid]"
                    type="number"
                    min="0"
                    class="form-control form-control-sm text-center"
                  >
                </td>
                <td class="text-center">
                  <span v-if="company.unlimited" class="badge bg-secondary">
                    {{ data.unlimited_label || 'Unlimited' }}
                  </span>
                  <span v-else-if="company.limitreached" class="badge bg-danger">
                    {{ data.full_label || 'Full' }}
                  </span>
                  <span v-else class="badge bg-success">
                    {{ data.ok_label || 'OK' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <button type="submit" class="btn btn-primary" :disabled="saving">
          <span v-if="saving" class="spinner-border spinner-border-sm me-1" />
          <i v-else class="fa fa-save" />
          {{ data.save_label || 'Save' }}
        </button>
      </form>
    </template>

    <div v-else class="alert alert-info">No companies found.</div>
  </div>
</template>

<script setup lang="ts">
definePageMeta({ middleware: 'auth' })

const { getCompanyLimits, updateCompanyLimit } = useAdminApi()

const loading = ref(true)
const error = ref<string | null>(null)
const data = ref<any>(null)
const editLimits = ref<Record<number, number>>({})
const saving = ref(false)

const fetchData = async () => {
  loading.value = true
  const result = await getCompanyLimits()
  loading.value = false
  if (result.error) {
    error.value = result.error
  } else {
    data.value = result.data
    for (const company of data.value?.companies || []) {
      editLimits.value[company.companyid] = company.maxstudents
    }
  }
}

const saveAll = async () => {
  saving.value = true
  for (const company of data.value?.companies || []) {
    const newLimit = editLimits.value[company.companyid]
    if (newLimit !== company.maxstudents) {
      const result = await updateCompanyLimit(company.companyid, newLimit ?? 0)
      if (result.error) {
        alert(result.error)
        saving.value = false
        return
      }
    }
  }
  saving.value = false
  await fetchData()
}

fetchData()
</script>
