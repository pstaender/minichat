<script>
  import LanguageToggle from './LanguageToggle.svelte'
  import { _, apiErrorMessage } from '../lib/i18n.js'
  import { login } from '../lib/store.svelte.js'

  let email = $state('')
  let password = $state('')
  let error = $state('')
  let busy = $state(false)

  async function submit(event) {
    event.preventDefault()
    if (busy) return
    error = ''
    busy = true
    try {
      await login(email.trim() || null, password)
    } catch (e) {
      error = apiErrorMessage(e, 'error.loginFailed')
    } finally {
      busy = false
    }
  }
</script>

<div class="flex h-svh items-center justify-center bg-neutral-50 px-4 dark:bg-neutral-950">
  <form
    onsubmit={submit}
    class="relative w-full max-w-sm rounded-2xl border border-neutral-200 bg-white p-8 shadow-sm dark:border-neutral-800 dark:bg-neutral-900"
  >
    <div class="absolute top-4 right-4">
      <LanguageToggle />
    </div>

    <h1 class="mb-1 text-lg font-semibold text-neutral-900 dark:text-neutral-100">{$_('login.title')}</h1>
    <p class="mb-6 text-sm text-neutral-500 dark:text-neutral-400">
      {$_('login.subtitle')}
    </p>

    {#if error}
      <p class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">
        {error}
      </p>
    {/if}

    <label class="mb-3 block text-sm">
      <span class="mb-1 block text-neutral-600 dark:text-neutral-400">
        {$_('login.email')} <span class="text-neutral-400 dark:text-neutral-500">{$_('login.optional')}</span>
      </span>
      <input
        type="email"
        bind:value={email}
        autocomplete="username"
        class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 outline-none focus:border-neutral-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100 dark:focus:border-neutral-500"
        placeholder={$_('login.emailPlaceholder')}
      />
    </label>

    <label class="mb-6 block text-sm">
      <span class="mb-1 block text-neutral-600 dark:text-neutral-400">{$_('login.password')}</span>
      <input
        type="password"
        bind:value={password}
        required
        autocomplete="current-password"
        class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 outline-none focus:border-neutral-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100 dark:focus:border-neutral-500"
        placeholder="••••••••"
      />
    </label>

    <button
      type="submit"
      disabled={busy}
      class="w-full rounded-lg bg-neutral-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-neutral-700 disabled:opacity-50 dark:bg-neutral-100 dark:text-neutral-900 dark:hover:bg-neutral-300"
    >
      {busy ? $_('login.signingIn') : $_('login.signIn')}
    </button>
  </form>
</div>
