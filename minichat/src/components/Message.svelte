<script>
  import { renderMarkdown } from '../lib/markdown.js'

  let { message } = $props()

  const isUser = $derived(message.role === 'user')
</script>

<div class="flex gap-3 px-4 py-5 {isUser ? '' : 'bg-neutral-50 dark:bg-neutral-900/60'}">
  <div
    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold {isUser
      ? 'bg-neutral-800 text-white dark:bg-neutral-200 dark:text-neutral-900'
      : 'bg-emerald-600 text-white'}"
  >
    {isUser ? 'You' : 'AI'}
  </div>

  <div class="min-w-0 flex-1 pt-0.5">
    {#if isUser}
      <p class="whitespace-pre-wrap break-words text-sm text-neutral-900 dark:text-neutral-100">
        {message.text}
      </p>
    {:else if message.text}
      <div class="markdown max-w-none text-sm text-neutral-900 dark:text-neutral-100">
        {@html renderMarkdown(message.text)}
      </div>
    {:else if !message.error}
      <span class="inline-flex gap-1 text-neutral-400">
        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-current [animation-delay:-0.3s]"></span>
        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-current [animation-delay:-0.15s]"></span>
        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-current"></span>
      </span>
    {/if}

    {#if message.error}
      <p class="mt-2 rounded-lg bg-red-50 px-3 py-2 text-xs text-red-700 dark:bg-red-950 dark:text-red-300">
        {message.error}
      </p>
    {/if}
  </div>
</div>
