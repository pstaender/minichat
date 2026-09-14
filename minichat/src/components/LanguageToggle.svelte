<script>
  import { locale, setLocale } from '../lib/i18n.js'
</script>

<div class="inline-flex shrink-0 rounded-lg border border-neutral-300 p-0.5 text-xs dark:border-neutral-700">
  <button
    class="rounded px-2 py-1 transition {$locale === 'en'
      ? 'bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900'
      : 'text-neutral-500 hover:text-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-100'}"
    onclick={() => setLocale('en')}
    aria-pressed={$locale === 'en'}
  >
    EN
  </button>
  <button
    class="rounded px-2 py-1 transition {$locale === 'de'
      ? 'bg-neutral-900 text-white dark:bg-neutral-100 dark:text-neutral-900'
      : 'text-neutral-500 hover:text-neutral-800 dark:text-neutral-400 dark:hover:text-neutral-100'}"
    onclick={() => setLocale('de')}
    aria-pressed={$locale === 'de'}
  >
    DE
  </button>
</div>
