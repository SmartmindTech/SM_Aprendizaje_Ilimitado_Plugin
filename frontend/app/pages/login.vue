<template>
  <div class="smgp-login-page">
    <!-- Background orbs -->
    <div class="smgp-login-bg" aria-hidden="true">
      <div class="smgp-login-orb smgp-login-orb--1" />
      <div class="smgp-login-orb smgp-login-orb--2" />
      <div class="smgp-login-orb smgp-login-orb--3" />
    </div>

    <!-- Top nav: dark mode + language -->
    <div class="smgp-login-topnav">
      <button type="button" class="smgp-login-topnav__btn" @click="toggleDark">
        <i :class="isDark ? 'icon-sun' : 'icon-moon'" />
      </button>
      <button type="button" class="smgp-login-topnav__btn" @click="cycleLang">
        <i class="icon-globe" />
        <span>{{ langLabel }}</span>
      </button>
    </div>

    <!-- Panel transitions -->
    <Transition name="smgp-panel" mode="out-in">
      <!-- Welcome panel -->
      <div v-if="panel === 'welcome'" key="welcome" class="smgp-login-content">
        <div class="smgp-login-logo">
          <img :src="logoUrl" alt="SmartMind">
        </div>

        <h1 class="smgp-login-title">{{ t('login.title') }}</h1>
        <span class="smgp-login-tagline1">{{ t('login.tagline1') }}</span>
        <span class="smgp-login-tagline2">{{ t('login.tagline2') }}</span>

        <div class="smgp-login-features">
          <div v-for="i in 4" :key="i" class="smgp-login-feature">
            <div class="smgp-login-feature-icon">
              <i :class="featureIcons[i - 1]" />
            </div>
            <h3 class="login-title">{{ t(`login.feat${i}_title`) }}</h3>
            <p>{{ t(`login.feat${i}_desc`) }}</p>
          </div>
        </div>

        <div class="smgp-login-cta">
          <button class="smgp-login-btn-signin" @click="panel = 'login'">
            {{ t('login.signin') }}
            <i class="icon-arrow-right" />
          </button>
          <button class="smgp-login-btn-verify" @click="panel = 'verify'">
            <i class="icon-search" />
            {{ t('login.verify_cert') }}
          </button>
        </div>

        <div class="smgp-login-footer">{{ t('login.footer') }}</div>
      </div>

      <!-- Login form panel -->
      <div v-else-if="panel === 'login'" key="login" class="smgp-login-content">
        <div class="smgp-login-card">
          <button class="smgp-login-back" @click="panel = 'welcome'">
            <i class="icon-arrow-left" />
            {{ t('login.back') }}
          </button>

          <h2 class="smgp-login-card-title">{{ t('login.title') }}</h2>
          <p class="smgp-login-card-subtitle">{{ t('login.signin') }}</p>

          <div v-if="loginError" class="smgp-login-error">
            <i class="icon-circle-x" />
            {{ loginError }}
          </div>

          <form @submit.prevent="handleLogin">
            <div class="smgp-login-input-group">
              <label for="username">{{ t('login.username') }}</label>
              <div class="smgp-login-input-wrap">
                <i class="icon-user" />
                <input
                  id="username"
                  ref="usernameRef"
                  v-model="username"
                  type="text"
                  :placeholder="t('login.username_placeholder')"
                  autocomplete="username"
                  required
                >
              </div>
            </div>

            <div class="smgp-login-input-group">
              <label for="password">{{ t('login.password') }}</label>
              <div class="smgp-login-input-wrap">
                <i class="icon-lock" />
                <input
                  id="password"
                  v-model="password"
                  type="password"
                  :placeholder="t('login.password_placeholder')"
                  autocomplete="current-password"
                  required
                >
              </div>
            </div>

            <button
              type="submit"
              class="smgp-login-submit"
              :disabled="loggingIn"
            >
              {{ loggingIn ? t('login.logging_in') : t('login.submit') }}
            </button>

            <div class="smgp-login-forgot">
              <a :href="forgotUrl" target="_blank">{{ t('login.forgot_password') }}</a>
            </div>
          </form>

          <div class="smgp-login-switch">
            <button @click="panel = 'verify'">
              <i class="icon-search" />
              {{ t('login.verify_cert') }}
            </button>
          </div>
        </div>
      </div>

      <!-- Verify certificate panel -->
      <div v-else key="verify" class="smgp-login-content">
        <div class="smgp-login-card">
          <button class="smgp-login-back" @click="panel = 'welcome'">
            <i class="icon-arrow-left" />
            {{ t('login.back') }}
          </button>

          <h2 class="smgp-login-card-title">{{ t('login.verify_heading') }}</h2>

          <form @submit.prevent="handleVerify" style="margin-top: 1.5rem;">
            <div class="smgp-login-input-group">
              <div class="smgp-login-input-wrap">
                <i class="icon-search" />
                <input
                  v-model="verifyCode"
                  type="text"
                  :placeholder="t('login.verify_placeholder')"
                  autocomplete="off"
                  maxlength="10"
                  required
                >
              </div>
            </div>

            <button type="submit" class="smgp-login-submit" :disabled="verifying">
              {{ verifying ? t('login.verify_checking') : t('login.verify_button') }}
            </button>
          </form>

          <!-- Verify result -->
          <div v-if="verifyResult === 'success' && verifyData" class="smgp-verify-result smgp-verify-result--success">
            <div class="smgp-verify-check"><i class="icon-check-circle" /></div>
            <p class="smgp-verify-success-text">{{ t('login.verify_success') }}</p>
            <div>
              <div class="smgp-verify-row">
                <span class="smgp-verify-label">{{ t('login.verify_student') }}</span>
                <span class="smgp-verify-value">{{ verifyData.studentname }}</span>
              </div>
              <div class="smgp-verify-row">
                <span class="smgp-verify-label">{{ t('login.verify_course') }}</span>
                <span class="smgp-verify-value">{{ verifyData.coursename }}</span>
              </div>
              <div class="smgp-verify-row">
                <span class="smgp-verify-label">{{ t('login.verify_date') }}</span>
                <span class="smgp-verify-value">{{ verifyData.completiondate }}</span>
              </div>
              <div v-if="verifyData.companyname" class="smgp-verify-row">
                <span class="smgp-verify-label">{{ t('login.verify_company') }}</span>
                <span class="smgp-verify-value">{{ verifyData.companyname }}</span>
              </div>
              <div class="smgp-verify-row">
                <span class="smgp-verify-label">{{ t('login.verify_code') }}</span>
                <span class="smgp-verify-value smgp-verify-value--code">{{ verifyData.code }}</span>
              </div>
            </div>
          </div>

          <div v-else-if="verifyResult === 'notfound'" class="smgp-verify-result smgp-verify-result--error">
            <i class="icon-circle-x" style="font-size: 1.5rem; display: block; margin-bottom: 0.5rem;" />
            <p style="margin: 0;">{{ t('login.verify_notfound') }}</p>
          </div>

          <div class="smgp-login-switch">
            <button @click="panel = 'login'">
              <i class="icon-arrow-right" />
              {{ t('login.signin') }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
definePageMeta({
  layout: 'minimal',
})

const { t, locale, setLocale } = useI18n()
const authStore = useAuthStore()

// Panel state
const panel = ref<'welcome' | 'login' | 'verify'>('welcome')

// Login form
const username = ref('')
const password = ref('')
const loggingIn = ref(false)
const loginError = ref('')
const usernameRef = ref<HTMLInputElement | null>(null)

// Verify form
const verifyCode = ref('')
const verifying = ref(false)
const verifyResult = ref<'success' | 'notfound' | null>(null)
const verifyData = ref<Record<string, string> | null>(null)

// Feature icons
const featureIcons = ['icon-graduation-cap', 'icon-bar-chart', 'icon-award', 'icon-phone']

// Dark mode
const isDark = ref(false)

onMounted(() => {
  isDark.value = localStorage.getItem('smgp-dark') === '1'
  applyDark(isDark.value)

  // Check for login error passed via query param from Moodle's login layout redirect.
  const params = new URLSearchParams(window.location.search)
  if (params.get('loginerror')) {
    loginError.value = t('login.error_credentials')
    panel.value = 'login'
    // Clean up the URL without reloading.
    const clean = window.location.pathname + window.location.hash
    window.history.replaceState({}, '', clean)
  }
})

function applyDark(dark: boolean) {
  document.documentElement.classList.toggle('smgp-dark', dark)
  localStorage.setItem('smgp-dark', dark ? '1' : '0')
}

function toggleDark() {
  isDark.value = !isDark.value
  applyDark(isDark.value)
}

// Language
const langs = ['es', 'en', 'pt_br'] as const
const langLabels: Record<string, string> = { es: 'ES', en: 'EN', pt_br: 'PT' }
const langLabel = computed(() => langLabels[locale.value] || locale.value.toUpperCase().slice(0, 2))

function cycleLang() {
  const idx = langs.indexOf(locale.value as typeof langs[number])
  const next = langs[(idx + 1) % langs.length]!
  setLocale(next)
}

// URLs
const wwwroot = computed(() => authStore.wwwroot || '')
const logoUrl = computed(() => {
  if (wwwroot.value) return `${wwwroot.value}/theme/smartmind/pix/smartmind_logo.png`
  // Dev mode fallback
  return '/theme/smartmind/pix/smartmind_logo.png'
})
const forgotUrl = computed(() => {
  if (wwwroot.value) return `${wwwroot.value}/login/forgot_password.php`
  return '/login/forgot_password.php'
})

// Login handler — real form submission to Moodle's /login/index.php.
// After successful login Moodle redirects to $SESSION->wantsurl (spa.php).
// On failure, the login layout redirects back with ?loginerror=1.
function handleLogin() {
  if (!username.value || !password.value) return
  loggingIn.value = true

  const loginurl = authStore.user?.loginurl
    || `${wwwroot.value}/login/index.php`
  const logintoken = authStore.user?.logintoken ?? ''

  const form = document.createElement('form')
  form.method = 'POST'
  form.action = loginurl
  form.style.display = 'none'

  const fields: Record<string, string> = {
    username: username.value,
    password: password.value,
    logintoken,
    anchor: '',
  }

  for (const [name, value] of Object.entries(fields)) {
    const input = document.createElement('input')
    input.type = 'hidden'
    input.name = name
    input.value = value
    form.appendChild(input)
  }

  document.body.appendChild(form)
  form.submit()
}

// Verify handler
async function handleVerify() {
  const code = verifyCode.value.trim().toUpperCase()
  if (!code) return

  verifying.value = true
  verifyResult.value = null
  verifyData.value = null

  try {
    const base = wwwroot.value || ''
    const url = `${base}/local/sm_graphics_plugin/pages/verify_certificate.php?code=${encodeURIComponent(code)}&ajax=1`
    const resp = await fetch(url, { credentials: 'include' })
    const data = await resp.json()

    if (data.found) {
      verifyResult.value = 'success'
      verifyData.value = data
    }
    else {
      verifyResult.value = 'notfound'
    }
  }
  catch {
    verifyResult.value = 'notfound'
  }
  finally {
    verifying.value = false
  }
}

// Focus username when switching to login panel
watch(panel, (val) => {
  if (val === 'login') {
    nextTick(() => usernameRef.value?.focus())
  }
})
</script>

<style>
.smgp-login-feature-icon{
  align-self: center;
}

.login-title{
  align-self: center;
}

.smgp-login-tagline1{
  text-wrap: nowrap;
}
</style>