/**
 * Sync the active vue-i18n locale to `document.documentElement.lang` so
 * that non-component code (useMoodleAjax) can read the current UI language
 * and forward it to Moodle's /lib/ajax/service.php via the `lang=` query
 * parameter. Without this, backend calls pick up the user's saved Moodle
 * profile language and end up rendering translated course data in the
 * wrong locale.
 */
export default defineNuxtPlugin((nuxtApp) => {
  const i18n = nuxtApp.$i18n as unknown as { locale: { value: string } } | undefined
  if (!i18n) return

  const sync = (val: string) => {
    if (typeof document !== 'undefined' && val) {
      document.documentElement.lang = val
    }
  }

  sync(i18n.locale.value)

  // Watch for locale changes triggered by UserArea.vue's cycleLocale.
  if (typeof window !== 'undefined') {
    watch(
      () => i18n.locale.value,
      (val) => sync(String(val)),
    )
  }
})
