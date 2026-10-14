<script>
  import { tick } from 'svelte'
  import IconMenu from '~icons/line-md/menu'
  import { _ } from '../lib/i18n.js'
  import Composer from './Composer.svelte'
  import Message from './Message.svelte'

  // `shared`: someone else's chat, joined by code
  let { session, roles, models, shared = false, sending, blocked, onSend, onChangeModel, onToggleSidebar } = $props()

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

  <div bind:this={scrollEl} class="flex-1 overflow-y-auto">
    {#if session}
      {#if session.messages.length === 0}
        <div class="flex h-full items-center justify-center px-4 text-center text-sm text-neutral-400 dark:text-neutral-500">
          {$_('chat.emptyPrompt')}
        </div>
      {:else}
        <div class="mx-auto max-w-3xl divide-y divide-neutral-100 dark:divide-neutral-900">
          {#each session.messages as message (message.id)}
            <Message {message} />
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
