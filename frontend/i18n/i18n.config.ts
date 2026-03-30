import es from './locales/es.json'
import en from './locales/en.json'
import ptBr from './locales/pt_br.json'

export default defineI18nConfig(() => ({
  legacy: false,
  locale: 'es',
  fallbackLocale: 'en',
  messages: { es, en, pt_br: ptBr },
}))
