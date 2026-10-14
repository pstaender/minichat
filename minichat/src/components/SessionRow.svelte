<script>
  import IconEdit from '~icons/line-md/edit'
  import IconLink from '~icons/line-md/link'
  import IconLogOut from '~icons/line-md/log-out'
  import IconTrash from '~icons/line-md/trash'
  import { _ } from '../lib/i18n.js'

  // Own chats pass onRename/onDelete (and onShare when sharing is on);
  // chats joined by code only pass onLeave.
  let { session, active, onSelect, onRename = null, onDelete = null, onShare = null, onLeave = null } = $props()

  let renaming = $state(false)
  let value = $state('')

  function startRename() {
    value = session.title
    renaming = true
  }

  function commit() {
    if (value.trim()) onRename(session.id, value.trim())
    renaming = false
  }

  function confirmDelete() {
    if (confirm($_('session.confirmDelete'))) {
      onDelete(session.id)
    }
  }

  function focusOnMount(node) {
    node.focus()
    node.select()
  }
</script>

<div
  class="group flex items-center gap-1 rounded-lg px-2 py-2 text-sm {active
    ? 'bg-neutral-200 dark:bg-neutral-800'
    : 'hover:bg-neutral-100 dark:hover:bg-neutral-900'}"
>
  {#if renaming}
    <input
      class="min-w-0 flex-1 rounded border border-neutral-400 bg-white px-1.5 py-0.5 text-sm dark:bg-neutral-800"
      bind:value
      onblur={commit}
      onkeydown={(e) => {
        if (e.key === 'Enter') commit()
        if (e.key === 'Escape') renaming = false
      }}
      use:focusOnMount
    />
  {:else}
    <button
      class="min-w-0 flex-1 truncate text-left text-neutral-700 dark:text-neutral-200"
      onclick={() => onSelect(session.id)}
      title={session.title}
    >
      {session.title || $_('session.untitled')}
    </button>
    {#if onShare}
      <button
        class="invisible flex shrink-0 items-center justify-center rounded p-1 text-neutral-400 hover:bg-neutral-300 hover:text-neutral-700 group-hover:visible dark:hover:bg-neutral-700 dark:hover:text-neutral-100"
        onclick={() => onShare(session.id)}
        title={$_('session.share')}
        aria-label={$_('session.share')}
      >
        <IconLink class="h-4 w-4" />
      </button>
    {/if}
    {#if onRename}
      <button
        class="invisible flex shrink-0 items-center justify-center rounded p-1 text-neutral-400 hover:bg-neutral-300 hover:text-neutral-700 group-hover:visible dark:hover:bg-neutral-700 dark:hover:text-neutral-100"
        onclick={startRename}
        title={$_('session.rename')}
        aria-label={$_('session.rename')}
      >
        <IconEdit class="h-4 w-4" />
      </button>
    {/if}
    {#if onDelete}
      <button
        class="invisible flex shrink-0 items-center justify-center rounded p-1 text-neutral-400 hover:bg-neutral-300 hover:text-red-600 group-hover:visible dark:hover:bg-neutral-700 dark:hover:text-red-400"
        onclick={confirmDelete}
        title={$_('session.delete')}
        aria-label={$_('session.delete')}
      >
        <IconTrash class="h-4 w-4" />
      </button>
    {/if}
    {#if onLeave}
      <button
        class="invisible flex shrink-0 items-center justify-center rounded p-1 text-neutral-400 hover:bg-neutral-300 hover:text-neutral-700 group-hover:visible dark:hover:bg-neutral-700 dark:hover:text-neutral-100"
        onclick={() => onLeave(session.id)}
        title={$_('session.leave')}
        aria-label={$_('session.leave')}
      >
        <IconLogOut class="h-4 w-4" />
      </button>
    {/if}
  {/if}
</div>
