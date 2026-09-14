<script>
  import IconClose from '~icons/line-md/close'
  import IconEdit from '~icons/line-md/edit'
  import IconLogout from '~icons/line-md/logout'
  import IconPlus from '~icons/line-md/plus'
  import IconTrash from '~icons/line-md/trash'
  import { auth, logout } from '../lib/store.svelte.js'
  import UsageBar from './UsageBar.svelte'

  let {
    sessions,
    activeId,
    open,
    onSelect,
    onNew,
    onRename,
    onDelete,
    onToggle,
  } = $props()

  let renamingId = $state(null)
  let renameValue = $state('')

  function startRename(session) {
    renamingId = session.id
    renameValue = session.title
  }

  function commitRename() {
    if (renamingId && renameValue.trim()) {
      onRename(renamingId, renameValue.trim())
    }
    renamingId = null
  }

  function confirmDelete(id) {
    if (confirm('Delete this chat? This cannot be undone.')) {
      onDelete(id)
    }
  }

  function focusOnMount(node) {
    node.focus()
    node.select()
  }
</script>

<!-- mobile scrim -->
{#if open}
  <button
    class="fixed inset-0 z-20 bg-black/30 md:hidden"
    aria-label="Close sidebar"
    onclick={onToggle}
  ></button>
{/if}

<aside
  class="fixed inset-y-0 left-0 z-30 flex w-72 shrink-0 -translate-x-full flex-col border-r border-neutral-200 bg-neutral-50 transition-transform md:static md:translate-x-0 dark:border-neutral-800 dark:bg-neutral-950 {open ? 'translate-x-0' : ''}"
>
  <div class="flex items-center justify-between gap-2 p-3">
    <button
      onclick={onNew}
      class="flex flex-1 items-center gap-2 rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm font-medium text-neutral-800 transition hover:bg-neutral-100 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-100 dark:hover:bg-neutral-800"
    >
      <IconPlus class="h-4 w-4" /> New chat
    </button>
    <button
      class="rounded-lg p-2 text-neutral-500 hover:bg-neutral-200 md:hidden dark:text-neutral-400 dark:hover:bg-neutral-800"
      onclick={onToggle}
      aria-label="Close sidebar"
    >
      <IconClose class="h-5 w-5" />
    </button>
  </div>

  <nav class="flex-1 space-y-0.5 overflow-y-auto px-2 pb-2">
    {#each sessions as session (session.id)}
      <div
        class="group flex items-center gap-1 rounded-lg px-2 py-2 text-sm {session.id === activeId
          ? 'bg-neutral-200 dark:bg-neutral-800'
          : 'hover:bg-neutral-100 dark:hover:bg-neutral-900'}"
      >
        {#if renamingId === session.id}
          <input
            class="min-w-0 flex-1 rounded border border-neutral-400 bg-white px-1.5 py-0.5 text-sm dark:bg-neutral-800"
            bind:value={renameValue}
            onblur={commitRename}
            onkeydown={(e) => {
              if (e.key === 'Enter') commitRename()
              if (e.key === 'Escape') renamingId = null
            }}
            use:focusOnMount
          />
        {:else}
          <button
            class="min-w-0 flex-1 truncate text-left text-neutral-700 dark:text-neutral-200"
            onclick={() => onSelect(session.id)}
            title={session.title}
          >
            {session.title || 'New chat'}
          </button>
          <button
            class="invisible flex shrink-0 items-center justify-center rounded p-1 text-neutral-400 hover:bg-neutral-300 hover:text-neutral-700 group-hover:visible dark:hover:bg-neutral-700 dark:hover:text-neutral-100"
            onclick={() => startRename(session)}
            title="Rename"
            aria-label="Rename chat"
          >
            <IconEdit class="h-4 w-4" />
          </button>
          <button
            class="invisible flex shrink-0 items-center justify-center rounded p-1 text-neutral-400 hover:bg-neutral-300 hover:text-red-600 group-hover:visible dark:hover:bg-neutral-700 dark:hover:text-red-400"
            onclick={() => confirmDelete(session.id)}
            title="Delete"
            aria-label="Delete chat"
          >
            <IconTrash class="h-4 w-4" />
          </button>
        {/if}
      </div>
    {:else}
      <p class="px-2 py-4 text-center text-xs text-neutral-400 dark:text-neutral-500">No chats yet.</p>
    {/each}
  </nav>

  <div class="border-t border-neutral-200 dark:border-neutral-800">
    <UsageBar usage={auth.usage} limit={auth.user?.daily_token_limit} />
    <div class="flex items-center justify-between gap-2 px-3 py-2 text-xs text-neutral-500 dark:text-neutral-400">
      <span class="truncate" title={auth.user?.email ?? auth.user?.id}>
        {auth.user?.email ?? auth.user?.id}
      </span>
      <button
        class="flex shrink-0 items-center gap-1 rounded px-2 py-1 hover:bg-neutral-200 dark:hover:bg-neutral-800"
        onclick={logout}
      >
        <IconLogout class="h-4 w-4" /> Sign out
      </button>
    </div>
  </div>
</aside>
