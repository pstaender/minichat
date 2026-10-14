<script>
  import { onMount } from 'svelte'
  import IconClose from '~icons/line-md/close'
  import { api, streamChat } from '../lib/api.js'
  import { chatEnabled, roleChatEnabled, shareableChatsEnabled } from '../lib/features.js'
  import { get } from 'svelte/store'
  import { _, apiErrorMessage, locale } from '../lib/i18n.js'
  import { auth } from '../lib/store.svelte.js'
  import ChatView from './ChatView.svelte'
  import JoinChatForm from './JoinChatForm.svelte'
  import Modal from './Modal.svelte'
  import RoleEditor from './RoleEditor.svelte'
  import ShareCodeDialog from './ShareCodeDialog.svelte'
  import Sidebar from './Sidebar.svelte'

  let sessions = $state([])
  let roles = $state([])
  let expandedRoles = $state({})
  let editingRole = $state(null) // null | { id: null|string, name, prompt }
  let joined = $state([]) // other users' chats this user joined by code
  let activeSession = $state(null)
  let activeOwn = $state(true)
  let shareCode = $state(null)
  let joinOpen = $state(false)
  let sending = $state(false)
  let blocked = $state('')
  let sidebarOpen = $state(false)
  let actionError = $state('')
  let actionErrorTimer

  // For failures outside the send-message flow (creating/switching a chat,
  // renaming, roles, ...) which otherwise had nowhere to show up in the UI.
  function showActionError(e) {
    actionError = apiErrorMessage(e)
    clearTimeout(actionErrorTimer)
    actionErrorTimer = setTimeout(() => (actionError = ''), 6000)
  }

  const grouped = $derived.by(() => {
    const byRole = {}
    for (const role of roles) byRole[role.id] = []

    const ungrouped = []
    for (const s of sessions) {
      if (s.role_id && byRole[s.role_id]) byRole[s.role_id].push(s)
      else ungrouped.push(s)
    }

    return { byRole, ungrouped }
  })

  onMount(async () => {
    const [sessionsData, rolesData, sharedData] = await Promise.all([
      api.listSessions(),
      api.listRoles(),
      shareableChatsEnabled ? api.listShared() : { sessions: [] },
    ])
    sessions = sessionsData.sessions
    roles = rolesData.roles
    joined = sharedData.sessions
    // open the most recent chat from a section the sidebar actually shows
    const { byRole, ungrouped } = grouped
    const visible = [
      ...(roleChatEnabled ? Object.values(byRole).flat() : []),
      ...(chatEnabled ? ungrouped : []),
    ]
    const first = sessions.find((s) => visible.includes(s))
    if (first) await selectSession(first.id)
  })

  async function selectSession(id) {
    sidebarOpen = false
    blocked = ''
    try {
      const data = await api.getSession(id)
      activeSession = data.session
      activeOwn = data.own
    } catch (e) {
      showActionError(e)
    }
  }

  async function newChat(roleId = null) {
    try {
      const model = auth.user.models[0]
      const data = await api.createSession(model, undefined, roleId)
      sessions = [{ ...data.session, message_count: 0 }, ...sessions]
      activeSession = data.session
      activeOwn = true
      blocked = ''
      sidebarOpen = false
      if (roleId) expandedRoles[roleId] = true
    } catch (e) {
      showActionError(e)
      return
    }

    // Let the role introduce itself so the user knows who they're talking to.
    if (roles.find((r) => r.id === roleId)?.prompt) {
      await streamReply({ intro: true })
    }
  }

  function toggleRole(id) {
    expandedRoles[id] = expandedRoles[id] === false
  }

  function newRole() {
    editingRole = { id: null, name: '', prompt: '' }
  }

  function editRole(role) {
    editingRole = { ...role }
  }

  async function saveRole(values) {
    try {
      if (editingRole.id) {
        const data = await api.updateRole(editingRole.id, values)
        roles = roles.map((r) => (r.id === editingRole.id ? data.role : r))
      } else {
        const data = await api.createRole(values.name, values.prompt)
        roles = [...roles, data.role]
        expandedRoles[data.role.id] = true
      }
      editingRole = null
    } catch (e) {
      showActionError(e)
    }
  }

  async function deleteRole(id) {
    try {
      await api.deleteRole(id)
      roles = roles.filter((r) => r.id !== id)
    } catch (e) {
      showActionError(e)
    }
  }

  async function renameChat(id, title) {
    try {
      await api.updateSession(id, { title })
      sessions = sessions.map((s) => (s.id === id ? { ...s, title } : s))
      if (activeSession?.id === id) activeSession.title = title
    } catch (e) {
      showActionError(e)
    }
  }

  async function changeSessionModel(model) {
    if (!activeSession) return
    try {
      const data = await api.updateSession(activeSession.id, { model })
      activeSession.provider = data.session.provider
      activeSession.model = data.session.model
      sessions = sessions.map((s) =>
        s.id === activeSession.id ? { ...s, provider: data.session.provider, model: data.session.model } : s
      )
    } catch (e) {
      showActionError(e)
    }
  }

  async function shareChat(id) {
    try {
      shareCode = (await api.shareSession(id)).code
    } catch (e) {
      showActionError(e)
    }
  }

  function openJoin() {
    sidebarOpen = false
    joinOpen = true
  }

  // Errors are left to JoinChatForm, which shows them next to the code.
  async function joinChat(code) {
    const data = await api.join(code)
    if (!data.own) joined = [data.session, ...joined.filter((s) => s.id !== data.session.id)]
    joinOpen = false
    await selectSession(data.session.id)
  }

  async function leaveChat(id) {
    try {
      await api.leaveShared(id)
      joined = joined.filter((s) => s.id !== id)
      if (activeSession?.id === id) activeSession = null
    } catch (e) {
      showActionError(e)
    }
  }

  async function deleteChat(id) {
    try {
      const wasActive = activeSession?.id === id
      await api.deleteSession(id)
      sessions = sessions.filter((s) => s.id !== id)
      if (wasActive) {
        activeSession = null
        if (sessions.length) await selectSession(sessions[0].id)
      }
    } catch (e) {
      showActionError(e)
    }
  }

  async function sendMessage(text) {
    if (!activeSession) return
    const userMsg = { id: `local-${Date.now()}`, role: 'user', text, created_at: new Date().toISOString() }
    activeSession.messages = [...activeSession.messages, userMsg]
    await streamReply({ text })
  }

  // Streams an assistant reply into the active session: either the answer
  // to `text`, or (with `intro`) the role's opening self-introduction.
  async function streamReply({ text, intro = false }) {
    const session = activeSession
    blocked = ''

    const assistantMsg = {
      id: `local-assistant-${Date.now()}`,
      role: 'assistant',
      text: '',
      error: null,
      created_at: new Date().toISOString(),
    }

    session.messages = [...session.messages, assistantMsg]
    // Index into the reactive array (not the plain `assistantMsg` object
    // above) so mutations go through Svelte's $state proxy and the UI
    // actually updates as chunks arrive.
    const assistantIndex = session.messages.length - 1
    sending = true

    await streamChat(
      { sessionId: session.id, text, intro, locale: get(locale) },
      {
        onDelta: (chunk) => {
          session.messages[assistantIndex].text += chunk
        },
        onDone: (payload) => {
          sending = false
          auth.usage = payload.usage
          if (payload.title && payload.title !== session.title) {
            session.title = payload.title
            const retitle = (list) => list.map((s) => (s.id === session.id ? { ...s, title: payload.title } : s))
            sessions = retitle(sessions)
            joined = retitle(joined)
          }
        },
        onError: (message, payload) => {
          sending = false
          const localized = apiErrorMessage({ message, code: payload?.code })
          session.messages[assistantIndex].error = localized
          if (payload?.usage) auth.usage = payload.usage
          if (payload?.code === 'daily_limit_reached' || payload?.code === 'daily_output_limit_reached') {
            blocked = localized
          }
        },
      }
    )
  }
</script>

<div class="flex h-svh bg-white text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100">
  <Sidebar
    {roles}
    sessionsByRole={grouped.byRole}
    ungroupedSessions={grouped.ungrouped}
    joinedSessions={joined}
    activeId={activeSession?.id}
    open={sidebarOpen}
    {expandedRoles}
    onSelect={selectSession}
    onNewChat={newChat}
    onRename={renameChat}
    onDelete={deleteChat}
    onShare={shareableChatsEnabled ? shareChat : null}
    onJoin={openJoin}
    onLeave={leaveChat}
    onToggleRole={toggleRole}
    onNewRole={newRole}
    onEditRole={editRole}
    onDeleteRole={deleteRole}
    onToggle={() => (sidebarOpen = !sidebarOpen)}
  />
  <!-- someone else's chat runs on their account: no model switching here -->
  <ChatView
    session={activeSession}
    {roles}
    models={activeOwn ? auth.user.models : []}
    shared={!activeOwn}
    {sending}
    {blocked}
    onSend={sendMessage}
    onChangeModel={changeSessionModel}
    onToggleSidebar={() => (sidebarOpen = !sidebarOpen)}
  />
</div>

{#if shareCode}
  <ShareCodeDialog code={shareCode} onClose={() => (shareCode = null)} />
{/if}

{#if joinOpen}
  <Modal onClose={() => (joinOpen = false)}>
    <JoinChatForm onJoin={joinChat} onCancel={() => (joinOpen = false)} autofocus />
  </Modal>
{/if}

{#if editingRole}
  <RoleEditor role={editingRole} onSave={saveRole} onClose={() => (editingRole = null)} />
{/if}

{#if actionError}
  <div
    class="fixed top-3 left-1/2 z-50 flex max-w-md -translate-x-1/2 items-start gap-2 rounded-lg bg-red-600 px-4 py-2.5 text-sm text-white shadow-lg"
  >
    <span class="min-w-0">{actionError}</span>
    <button
      class="shrink-0 opacity-80 hover:opacity-100"
      onclick={() => (actionError = '')}
      aria-label={$_('common.dismiss')}
    >
      <IconClose class="h-4 w-4" />
    </button>
  </div>
{/if}
