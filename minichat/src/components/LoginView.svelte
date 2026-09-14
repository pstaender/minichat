<script>
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
      error = e.message || 'Login failed.'
    } finally {
      busy = false
    }
  }
</script>

<div class="flex h-svh items-center justify-center bg-neutral-50 px-4 dark:bg-neutral-950">
  <form
    onsubmit={submit}
    class="w-full max-w-sm rounded-2xl border border-neutral-200 bg-white p-8 shadow-sm dark:border-neutral-800 dark:bg-neutral-900"
  >
    <h1 class="mb-1 text-lg font-semibold text-neutral-900 dark:text-neutral-100">Sign in</h1>
    <p class="mb-6 text-sm text-neutral-500 dark:text-neutral-400">
      Use the password you were given. Email is optional.
    </p>

    {#if error}
      <p class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">
        {error}
      </p>
    {/if}

    <label class="mb-3 block text-sm">
      <span class="mb-1 block text-neutral-600 dark:text-neutral-400">
        Email <span class="text-neutral-400 dark:text-neutral-500">(optional)</span>
      </span>
      <input
        type="email"
        bind:value={email}
        autocomplete="username"
        class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 outline-none focus:border-neutral-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100 dark:focus:border-neutral-500"
        placeholder="you@example.com"
      />
    </label>

    <label class="mb-6 block text-sm">
      <span class="mb-1 block text-neutral-600 dark:text-neutral-400">Password</span>
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
      {busy ? 'Signing in…' : 'Sign in'}
    </button>
  </form>
</div>
