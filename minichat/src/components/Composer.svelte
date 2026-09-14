<script>
  import IconArrowUp from '~icons/line-md/arrow-up'
  import { _ } from '../lib/i18n.js'
  import { textFromClipboard } from '../lib/paste.js'

  let { disabled, blocked, onSend } = $props()

  let text = $state('')
  let textareaEl = $state(null)

  function autoGrow(node) {
    const resize = () => {
      node.style.height = 'auto'
      node.style.height = Math.min(node.scrollHeight, 240) + 'px'
    }
    resize()
    node.addEventListener('input', resize)
    return { destroy: () => node.removeEventListener('input', resize) }
  }

  function handlePaste(event) {
    const html = event.clipboardData?.getData('text/html')
    if (!html || html.trim() === '') return // let the browser handle plain-text paste natively

    event.preventDefault()
    const markdown = textFromClipboard(event) // converts the HTML clipboard content to Markdown
    const el = textareaEl
    const start = el.selectionStart ?? text.length
    const end = el.selectionEnd ?? text.length
    text = text.slice(0, start) + markdown + text.slice(end)
    queueMicrotask(() => {
      el.selectionStart = el.selectionEnd = start + markdown.length
      el.dispatchEvent(new Event('input'))
    })
  }

  function submit() {
    const value = text.trim()
    if (!value || disabled || blocked) return
    text = ''
    if (textareaEl) textareaEl.style.height = 'auto'
    onSend(value)
  }

  function handleKeydown(event) {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault()
      submit()
    }
  }
</script>

<div class="border-t border-neutral-200 bg-white p-3 dark:border-neutral-800 dark:bg-neutral-950">
  {#if blocked}
    <p class="mb-2 rounded-lg bg-amber-50 px-3 py-2 text-center text-xs text-amber-800 dark:bg-amber-950 dark:text-amber-300">
      {blocked}
    </p>
  {/if}

  <div
    class="flex items-center gap-2 rounded-2xl border border-neutral-300 bg-white px-3 py-2 focus-within:border-neutral-500 dark:border-neutral-700 dark:bg-neutral-900"
  >
    <textarea
      bind:this={textareaEl}
      use:autoGrow
      bind:value={text}
      onpaste={handlePaste}
      onkeydown={handleKeydown}
      disabled={disabled || !!blocked}
      rows="1"
      placeholder={$_('composer.placeholder')}
      class="max-h-60 flex-1 resize-none bg-transparent text-sm leading-6 text-neutral-900 outline-none placeholder:text-neutral-400 disabled:opacity-50 dark:text-neutral-100"
    ></textarea>
    <button
      onclick={submit}
      disabled={disabled || !!blocked || !text.trim()}
      class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-neutral-900 text-white transition disabled:opacity-30 dark:bg-neutral-100 dark:text-neutral-900"
      aria-label={$_('composer.send')}
    >
      <IconArrowUp class="h-5 w-5" />
    </button>
  </div>
  <p class="mt-1.5 text-center text-[11px] text-neutral-400 dark:text-neutral-600">
    {$_('composer.hint')}
  </p>
</div>
