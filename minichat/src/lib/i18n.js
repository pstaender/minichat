import { _, addMessages, init, locale } from 'svelte-i18n'
import { get } from 'svelte/store'
import de from '../locales/de.json'
import en from '../locales/en.json'

addMessages('en', en)
addMessages('de', de)

const STORAGE_KEY = 'minichat_locale'
const SUPPORTED = ['en', 'de']
const DEFAULT_LOCALE = 'de'

function detectInitialLocale() {
  try {
    const stored = localStorage.getItem(STORAGE_KEY)
    if (SUPPORTED.includes(stored)) return stored
  } catch {
    // localStorage unavailable (private browsing etc.) — fall through to the default
  }

  return DEFAULT_LOCALE
}

init({ fallbackLocale: 'en', initialLocale: detectInitialLocale() })

export function setLocale(lang) {
  if (!SUPPORTED.includes(lang)) return
  locale.set(lang)
  try {
    localStorage.setItem(STORAGE_KEY, lang)
  } catch {
    // ignore — language just won't persist across reloads
  }
}

/**
 * Turns an API error into a localized message: known backend error codes
 * (see `code` on errors thrown by lib/api.js) are translated, anything else
 * (e.g. a raw OpenAI/Anthropic error string) is shown as-is since it can't
 * be pre-translated.
 */
export function apiErrorMessage(err, fallbackKey) {
  const translate = get(_)
  const fallback = err?.message || (fallbackKey ? translate(fallbackKey) : '')
  return err?.code ? translate(`error.${err.code}`, { default: fallback }) : fallback
}

export { _, locale }
