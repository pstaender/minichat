<script>
  import IconChevronRight from '~icons/line-md/chevron-right'
  import IconClose from '~icons/line-md/close'
  import IconEdit from '~icons/line-md/edit'
  import IconLogout from '~icons/line-md/logout'
  import IconPlus from '~icons/line-md/plus'
  import IconTrash from '~icons/line-md/trash'
  import { auth, logout } from '../lib/store.svelte.js'
  import SessionRow from './SessionRow.svelte'
  import UsageBar from './UsageBar.svelte'

  let {
    roles,
    sessionsByRole,
    ungroupedSessions,
    activeId,
    open,
    expandedRoles,
    onSelect,
    onNewChat,
    onRename,
    onDelete,
    onToggleRole,
    onNewRole,
    onEditRole,
    onDeleteRole,
    onToggle,
  } = $props()

  function isExpanded(roleId) {
    return expandedRoles[roleId] !== false
  }

  function confirmDeleteRole(role) {
    if (confirm(`Delete role "${role.name}"? Its test chats move to "Chats".`)) {
      onDeleteRole(role.id)
    }
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
      onclick={() => onNewChat(null)}
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

  <nav class="flex-1 overflow-y-auto px-2 pb-2">
    <div class="mb-1 flex items-center justify-between px-2 pt-1 pb-1">
      <span class="text-[11px] font-semibold tracking-wide text-neutral-400 uppercase dark:text-neutral-500">
        Roles
      </span>
      <button
        class="rounded p-1 text-neutral-400 hover:bg-neutral-200 hover:text-neutral-700 dark:hover:bg-neutral-800 dark:hover:text-neutral-100"
        onclick={onNewRole}
        title="New role"
        aria-label="New role"
      >
        <IconPlus class="h-4 w-4" />
      </button>
    </div>

    {#each roles as role (role.id)}
      <div class="mb-0.5">
        <div
          class="group flex items-center gap-1 rounded-lg px-1 py-1.5 text-sm hover:bg-neutral-100 dark:hover:bg-neutral-900"
        >
          <button
            class="flex shrink-0 items-center justify-center rounded p-0.5 text-neutral-400 dark:text-neutral-500"
            onclick={() => onToggleRole(role.id)}
            aria-label={isExpanded(role.id) ? 'Collapse role' : 'Expand role'}
          >
            <IconChevronRight
              class="h-3.5 w-3.5 transition-transform {isExpanded(role.id) ? 'rotate-90' : ''}"
            />
          </button>
          <button
            class="min-w-0 flex-1 truncate text-left font-medium text-neutral-700 dark:text-neutral-200"
            onclick={() => onToggleRole(role.id)}
            title={role.name}
          >
            {role.name}
          </button>
          <button
            class="invisible flex shrink-0 items-center justify-center rounded p-1 text-neutral-400 hover:bg-neutral-300 hover:text-neutral-700 group-hover:visible dark:hover:bg-neutral-700 dark:hover:text-neutral-100"
            onclick={() => onNewChat(role.id)}
            title="New test chat"
            aria-label="New test chat for {role.name}"
          >
            <IconPlus class="h-3.5 w-3.5" />
          </button>
          <button
            class="invisible flex shrink-0 items-center justify-center rounded p-1 text-neutral-400 hover:bg-neutral-300 hover:text-neutral-700 group-hover:visible dark:hover:bg-neutral-700 dark:hover:text-neutral-100"
            onclick={() => onEditRole(role)}
            title="Edit role"
            aria-label="Edit role {role.name}"
          >
            <IconEdit class="h-3.5 w-3.5" />
          </button>
          <button
            class="invisible flex shrink-0 items-center justify-center rounded p-1 text-neutral-400 hover:bg-neutral-300 hover:text-red-600 group-hover:visible dark:hover:bg-neutral-700 dark:hover:text-red-400"
            onclick={() => confirmDeleteRole(role)}
            title="Delete role"
            aria-label="Delete role {role.name}"
          >
            <IconTrash class="h-3.5 w-3.5" />
          </button>
        </div>

        {#if isExpanded(role.id)}
          <div class="ml-3 space-y-0.5 border-l border-neutral-200 pl-2 dark:border-neutral-800">
            {#each sessionsByRole[role.id] ?? [] as session (session.id)}
              <SessionRow {session} active={session.id === activeId} {onSelect} {onRename} {onDelete} />
            {:else}
              <p class="px-2 py-1.5 text-xs text-neutral-400 dark:text-neutral-500">No test chats yet.</p>
            {/each}
          </div>
        {/if}
      </div>
    {:else}
      <p class="px-2 py-2 text-xs text-neutral-400 dark:text-neutral-500">No roles yet.</p>
    {/each}

    <div class="mt-3 mb-1 flex items-center px-2 pt-2 pb-1">
      <span class="text-[11px] font-semibold tracking-wide text-neutral-400 uppercase dark:text-neutral-500">
        Chats
      </span>
    </div>

    <div class="space-y-0.5">
      {#each ungroupedSessions as session (session.id)}
        <SessionRow {session} active={session.id === activeId} {onSelect} {onRename} {onDelete} />
      {:else}
        <p class="px-2 py-4 text-center text-xs text-neutral-400 dark:text-neutral-500">No chats yet.</p>
      {/each}
    </div>
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
