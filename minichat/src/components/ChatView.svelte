<script>
  import { tick } from 'svelte'
  import IconMenu from '~icons/line-md/menu'
  import { auth } from '../lib/store.svelte.js'
  import Composer from './Composer.svelte'
  import Message from './Message.svelte'

  let { session, sending, blocked, onSend, onToggleSidebar } = $props()

  let scrollEl = $state(null)

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
      aria-label="Open sidebar"
    >
      <IconMenu class="h-5 w-5" />
    </button>
    <div class="min-w-0 flex-1">
      <p class="truncate text-sm font-medium text-neutral-800 dark:text-neutral-100">
        {session?.title || 'New chat'}
      </p>
      <p class="truncate text-xs text-neutral-400 dark:text-neutral-500">
        {auth.user?.provider} · {session?.model}
      </p>
    </div>
  </header>

  <div bind:this={scrollEl} class="flex-1 overflow-y-auto">
    {#if session}
      {#if session.messages.length === 0}
        <div class="flex h-full items-center justify-center px-4 text-center text-sm text-neutral-400 dark:text-neutral-500">
          Ask anything to start the conversation.
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
        Select or start a chat.
      </div>
    {/if}
  </div>

  <div class="mx-auto w-full max-w-3xl">
    <Composer disabled={!session || sending} {blocked} onSend={onSend} />
  </div>
</div>
