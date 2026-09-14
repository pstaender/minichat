<script>
  import { _ } from '../lib/i18n.js'

  let { usage, limit } = $props()

  const used = $derived((usage?.input_tokens ?? 0) + (usage?.output_tokens ?? 0))
  const pct = $derived(limit ? Math.min(100, Math.round((used / limit) * 100)) : 0)
  const near = $derived(pct >= 90)
</script>

{#if limit}
  <div class="px-3 py-2">
    <div class="mb-1 flex items-center justify-between text-[11px] text-neutral-500 dark:text-neutral-400">
      <span>{$_('usage.today')}</span>
      <span>{used.toLocaleString()} / {limit.toLocaleString()}</span>
    </div>
    <div class="h-1.5 w-full overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-800">
      <div
        class="h-full rounded-full transition-all {near ? 'bg-red-500' : 'bg-neutral-500 dark:bg-neutral-400'}"
        style="width: {pct}%"
      ></div>
    </div>
  </div>
{/if}
