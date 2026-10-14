<script>
  import IconClipboard from '~icons/line-md/clipboard'
  import IconClipboardCheck from '~icons/line-md/clipboard-check'
  import { _ } from '../lib/i18n.js'
  import Modal from './Modal.svelte'

  let { code, onClose } = $props()

  const formatted = $derived(`${code.slice(0, 3)}-${code.slice(3)}`)
  let copied = $state(false)

  async function copy() {
    await navigator.clipboard.writeText(formatted)
    copied = true
    setTimeout(() => (copied = false), 1500)
  }
</script>

<Modal {onClose}>
  <h2 class="mb-1 text-base font-semibold text-neutral-900 dark:text-neutral-100">{$_('share.title')}</h2>
  <p class="mb-4 text-sm text-neutral-500 dark:text-neutral-400">{$_('share.description')}</p>

  <p
    class="mb-5 rounded-lg bg-neutral-100 py-3 text-center font-mono text-2xl font-semibold tracking-[0.2em] text-neutral-900 select-all dark:bg-neutral-800 dark:text-neutral-100"
  >
    {formatted}
  </p>

  <div class="flex gap-2">
    <button
      onclick={onClose}
      class="flex-1 rounded-lg border border-neutral-300 px-3 py-2 text-sm font-medium text-neutral-700 transition hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800"
    >
      {$_('share.close')}
    </button>
    <button
      onclick={copy}
      class="flex flex-1 items-center justify-center gap-1.5 rounded-lg bg-neutral-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-neutral-700 dark:bg-neutral-100 dark:text-neutral-900 dark:hover:bg-neutral-300"
    >
      {#if copied}
        <IconClipboardCheck class="h-4 w-4" /> {$_('share.copied')}
      {:else}
        <IconClipboard class="h-4 w-4" /> {$_('share.copy')}
      {/if}
    </button>
  </div>
</Modal>
