<script>
  import { _ } from '../lib/i18n.js'

  let { onClose, children } = $props()
</script>

<div class="fixed inset-0 z-40 flex items-center justify-center p-4">
  <button class="absolute inset-0 bg-black/40" onclick={onClose} aria-label={$_('role.closeDialog')}></button>
  <div
    class="relative z-10 w-full max-w-sm rounded-2xl border border-neutral-200 bg-white p-5 shadow-lg dark:border-neutral-800 dark:bg-neutral-900"
  >
    {@render children()}
  </div>
</div>
