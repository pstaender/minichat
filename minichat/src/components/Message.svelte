<script>
  import IconClipboard from '~icons/line-md/clipboard'
  import IconClipboardCheck from '~icons/line-md/clipboard-check'
  import IconDocumentCode from '~icons/line-md/document-code'
  import { _ } from '../lib/i18n.js'
  import { renderMarkdown } from '../lib/markdown.js'

  let { message } = $props()

  const isUser = $derived(message.role === 'user')

  let copied = $state(null) // null | 'markdown' | 'html'
  let copiedTimer

  function flashCopied(kind) {
    copied = kind
    clearTimeout(copiedTimer)
    copiedTimer = setTimeout(() => (copied = null), 1500)
  }

  async function copyMarkdown() {
    try {
      await navigator.clipboard.writeText(message.text)
      flashCopied('markdown')
    } catch {
      // clipboard unavailable (permissions, insecure context) — nothing we can do
    }
  }

  // Most paste targets read text/plain, so the fallback flavor must be
  // rendered plain text — not the Markdown source, or "Copy as HTML" would
  // paste raw Markdown syntax (**bold**, # heading, ...) into them.
  function htmlToPlainText(html) {
    const el = document.createElement('div')
    el.innerHTML = html
    return el.textContent ?? ''
  }

  async function copyHtml() {
    const html = renderMarkdown(message.text)
    const plainText = htmlToPlainText(html)
    try {
      await navigator.clipboard.write([
        new ClipboardItem({
          'text/html': new Blob([html], { type: 'text/html' }),
          'text/plain': new Blob([plainText], { type: 'text/plain' }),
        }),
      ])
      flashCopied('html')
    } catch {
      try {
        await navigator.clipboard.writeText(plainText)
        flashCopied('html')
      } catch {
        // clipboard truly unavailable — give up silently
      }
    }
  }
</script>

<div class="flex gap-3 px-4 py-5 {isUser ? '' : 'bg-neutral-50 dark:bg-neutral-900/60'}">
  <div
    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold {isUser
      ? 'bg-neutral-800 text-white dark:bg-neutral-200 dark:text-neutral-900'
      : 'bg-emerald-600 text-white'}"
  >
    {isUser ? $_('message.you') : $_('message.assistant')}
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

    {#if !isUser && message.text}
      <div class="mt-2 flex items-center gap-1">
        <button
          class="flex items-center gap-1 rounded px-1.5 py-1 text-xs text-neutral-400 hover:bg-neutral-200 hover:text-neutral-700 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"
          onclick={copyMarkdown}
          title={$_('message.copyMarkdownLabel')}
          aria-label={$_('message.copyMarkdownLabel')}
        >
          {#if copied === 'markdown'}
            <IconClipboardCheck class="h-3.5 w-3.5" />
            <span>{$_('message.copied')}</span>
          {:else}
            <IconClipboard class="h-3.5 w-3.5" />
            <span>{$_('message.copyMarkdown')}</span>
          {/if}
        </button>
        <button
          class="flex items-center gap-1 rounded px-1.5 py-1 text-xs text-neutral-400 hover:bg-neutral-200 hover:text-neutral-700 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"
          onclick={copyHtml}
          title={$_('message.copyHtmlLabel')}
          aria-label={$_('message.copyHtmlLabel')}
        >
          {#if copied === 'html'}
            <IconClipboardCheck class="h-3.5 w-3.5" />
            <span>{$_('message.copied')}</span>
          {:else}
            <IconDocumentCode class="h-3.5 w-3.5" />
            <span>{$_('message.copyHtml')}</span>
          {/if}
        </button>
      </div>
    {/if}
  </div>
</div>
