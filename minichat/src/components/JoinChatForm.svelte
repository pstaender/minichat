<script>
  import { _, apiErrorMessage } from '../lib/i18n.js'
  import CodeInput from './CodeInput.svelte'

  // `onJoin(code)` may throw an API error, which is shown here.
  let { onJoin, onCancel = null, autofocus = false } = $props()

  let codeInput = $state(null)
  let error = $state('')
  let busy = $state(false)

  async function join(code) {
    if (busy || code.length !== 6) return
    error = ''
    busy = true
    try {
      await onJoin(code)
    } catch (e) {
      error = apiErrorMessage(e)
      busy = false
      codeInput.reset()
    }
  }
</script>

<h2 class="mb-1 text-base font-semibold text-neutral-900 dark:text-neutral-100">{$_('join.title')}</h2>
<p class="mb-4 text-sm text-neutral-500 dark:text-neutral-400">{$_('join.description')}</p>

{#if error}
  <p class="mb-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">
    {error}
  </p>
{/if}

<CodeInput bind:this={codeInput} onComplete={join} disabled={busy} {autofocus} />

<div class="mt-5 flex gap-2">
  {#if onCancel}
    <button
      type="button"
      onclick={onCancel}
      class="flex-1 rounded-lg border border-neutral-300 px-3 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800"
    >
      {$_('join.cancel')}
    </button>
  {/if}
  <button
    type="button"
    onclick={() => join(codeInput.value())}
    disabled={busy}
    class="flex-1 rounded-lg bg-neutral-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-neutral-700 disabled:opacity-50 dark:bg-neutral-100 dark:text-neutral-900 dark:hover:bg-neutral-300"
  >
    {busy ? $_('join.joining') : $_('join.submit')}
  </button>
</div>
