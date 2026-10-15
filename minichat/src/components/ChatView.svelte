<script>
  import { tick } from 'svelte'
  import IconChevronDown from '~icons/line-md/chevron-down'
  import IconClose from '~icons/line-md/close'
  import IconMenu from '~icons/line-md/menu'
  import IconPinned from '~icons/line-md/arrow-up-square-twotone'
  import { renderMarkdown } from '../lib/markdown.js'
  import { _ } from '../lib/i18n.js'
  import Composer from './Composer.svelte'
  import Message from './Message.svelte'

  // `shared`: someone else's chat, joined by code
  let { session, roles, models, shared = false, sending, blocked, onSend, onChangeModel, onTogglePin, onToggleSidebar } =
    $props()

  const pinned = $derived(session?.messages.filter((m) => m.pinned) ?? [])
  let expandedPins = $state({})

  let scrollEl = $state(null)

  const role = $derived(roles.find((r) => r.id === session?.role_id) ?? null)

  // Mirrors Config::parseModelSpec() in PHP: an explicit "provider." prefix
  // wins, otherwise the provider is guessed from the model name.
  const KNOWN_PROVIDERS = ['openai', 'anthropic']
  function parseModelSpec(spec) {
    if (spec.includes('.')) {
      const [prefix, ...rest] = spec.split('.')
      if (KNOWN_PROVIDERS.includes(prefix)) {
        return { provider: prefix, model: rest.join('.') }
      }
    }
    const lower = spec.toLowerCase()
    if (lower.includes('gpt')) return { provider: 'openai', model: spec }
    if (lower.includes('claude')) return { provider: 'anthropic', model: spec }
    return { provider: null, model: spec }
  }

  // Which configured spec string (e.g. "openai.gpt-5.6-luna") corresponds
  // to the session's current resolved provider + model, so the <select>
  // can preselect it.
  const currentSpec = $derived(
    models.find((spec) => {
      const parsed = parseModelSpec(spec)
      return parsed.provider === session?.provider && parsed.model === session?.model
    }) ?? session?.model ?? ''
  )

  $effect(() => {
    // re-run whenever the message list length or the last message's text changes
    const messages = session?.messages
    const last = messages?.[messages.length - 1]
    void messages?.length
    void last?.text
    tick().then(() => {
      if (scrollEl) scrollEl.scrollTop = scrollEl.scrollHeight
    })
  })
</script>

<div class="flex min-w-0 flex-1 flex-col">
  <header
    class="flex items-center gap-2 border-b border-neutral-200 px-3 py-2.5 dark:border-neutral-800"
  >
    <button
      class="rounded-lg p-1.5 text-neutral-500 hover:bg-neutral-100 md:hidden dark:text-neutral-400 dark:hover:bg-neutral-800"
      onclick={onToggleSidebar}
      aria-label={$_('chat.openSidebar')}
    >
      <IconMenu class="h-5 w-5" />
    </button>
    <div class="min-w-0 flex-1">
      <p class="truncate text-sm font-medium text-neutral-800 dark:text-neutral-100">
        {session?.title || $_('session.untitled')}
      </p>
      {#if session}
        <div class="flex min-w-0 items-center gap-1 text-xs text-neutral-400 dark:text-neutral-500">
          {#if shared}
            <span class="shrink-0">{$_('chat.shared')} ·</span>
          {/if}
          {#if role}
            <span class="shrink-0">{$_('chat.role')}: {role.name} ·</span>
          {/if}
          {#if models.length > 1}
            <select
              class="min-w-0 max-w-full rounded bg-transparent text-xs text-neutral-400 outline-none hover:text-neutral-600 dark:text-neutral-500 dark:hover:text-neutral-300"
              value={currentSpec}
              onchange={(e) => onChangeModel(e.currentTarget.value)}
              aria-label={$_('chat.selectModel')}
            >
              {#each models as spec (spec)}
                <option value={spec}>{spec}</option>
              {/each}
            </select>
          {:else}
            <span class="truncate">{session.provider} · {session.model}</span>
          {/if}
        </div>
      {/if}
    </div>
  </header>

  {#if pinned.length}
    <div class="max-h-[40%] shrink-0 overflow-y-auto border-b border-neutral-200 dark:border-neutral-800">
      <div class="mx-auto max-w-3xl space-y-1.5 px-4 py-2">
        {#each pinned as message (message.id)}
          {@const expanded = expandedPins[message.id]}
          <div
            class="flex items-start gap-2 rounded-lg border border-neutral-200 bg-neutral-50 px-3 py-2 dark:border-neutral-800 dark:bg-neutral-900"
          >
            <IconPinned class="mt-0.5 h-4 w-4 shrink-0 text-neutral-400" title={$_('chat.pinned')} />
            <div
              class="markdown min-w-0 flex-1 text-sm text-neutral-800 dark:text-neutral-200 {expanded
                ? ''
                : 'max-h-[4.5rem] overflow-hidden'}"
            >
              {@html renderMarkdown(message.text)}
            </div>
            <button
              class="shrink-0 rounded p-1 text-neutral-400 hover:bg-neutral-200 hover:text-neutral-700 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"
              onclick={() => (expandedPins[message.id] = !expanded)}
              title={expanded ? $_('chat.collapsePin') : $_('chat.expandPin')}
              aria-label={expanded ? $_('chat.collapsePin') : $_('chat.expandPin')}
            >
              <IconChevronDown class="h-4 w-4 transition-transform {expanded ? 'rotate-180' : ''}" />
            </button>
            <button
              class="shrink-0 rounded p-1 text-neutral-400 hover:bg-neutral-200 hover:text-neutral-700 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"
              onclick={() => onTogglePin(message.id)}
              title={$_('message.unpin')}
              aria-label={$_('message.unpin')}
            >
              <IconClose class="h-4 w-4" />
            </button>
          </div>
        {/each}
      </div>
    </div>
  {/if}

  <div bind:this={scrollEl} class="flex-1 overflow-y-auto">
    {#if session}
      {#if session.messages.length === 0}
        <div class="flex h-full items-center justify-center px-4 text-center text-sm text-neutral-400 dark:text-neutral-500">
          {$_('chat.emptyPrompt')}
        </div>
      {:else}
        <div class="mx-auto max-w-3xl divide-y divide-neutral-100 dark:divide-neutral-900">
          {#each session.messages as message (message.id)}
            <Message {message} {onTogglePin} />
          {/each}
        </div>
      {/if}
    {:else}
      <div class="flex h-full items-center justify-center text-sm text-neutral-400 dark:text-neutral-500">
        {$_('chat.noSession')}
      </div>
    {/if}
  </div>

  <div class="mx-auto w-full max-w-3xl">
    <Composer sessionId={session?.id} disabled={!session || sending} {blocked} onSend={onSend} />
  </div>
</div>
