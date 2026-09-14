<script>
  import { onMount } from 'svelte'
  import { api, streamChat } from '../lib/api.js'
  import { apiErrorMessage } from '../lib/i18n.js'
  import { auth } from '../lib/store.svelte.js'
  import ChatView from './ChatView.svelte'
  import RoleEditor from './RoleEditor.svelte'
  import Sidebar from './Sidebar.svelte'

  let sessions = $state([])
  let roles = $state([])
  let expandedRoles = $state({})
  let editingRole = $state(null) // null | { id: null|string, name, prompt }
  let activeSession = $state(null)
  let sending = $state(false)
  let blocked = $state('')
  let sidebarOpen = $state(false)

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
    const [sessionsData, rolesData] = await Promise.all([api.listSessions(), api.listRoles()])
    sessions = sessionsData.sessions
    roles = rolesData.roles
    if (sessions.length) {
      await selectSession(sessions[0].id)
    }
  })

  async function selectSession(id) {
    sidebarOpen = false
    blocked = ''
    const data = await api.getSession(id)
    activeSession = data.session
  }

  async function newChat(roleId = null) {
    const model = auth.user.model[0]
    const data = await api.createSession(model, undefined, roleId)
    sessions = [{ ...data.session, message_count: 0 }, ...sessions]
    activeSession = data.session
    blocked = ''
    sidebarOpen = false
    if (roleId) expandedRoles[roleId] = true
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
    if (editingRole.id) {
      const data = await api.updateRole(editingRole.id, values)
      roles = roles.map((r) => (r.id === editingRole.id ? data.role : r))
    } else {
      const data = await api.createRole(values.name, values.prompt)
      roles = [...roles, data.role]
      expandedRoles[data.role.id] = true
    }
    editingRole = null
  }

  async function deleteRole(id) {
    await api.deleteRole(id)
    roles = roles.filter((r) => r.id !== id)
  }

  async function renameChat(id, title) {
    await api.renameSession(id, title)
    sessions = sessions.map((s) => (s.id === id ? { ...s, title } : s))
    if (activeSession?.id === id) activeSession.title = title
  }

  async function deleteChat(id) {
    const wasActive = activeSession?.id === id
    await api.deleteSession(id)
    sessions = sessions.filter((s) => s.id !== id)
    if (wasActive) {
      activeSession = null
      if (sessions.length) await selectSession(sessions[0].id)
    }
  }

  async function sendMessage(text) {
    if (!activeSession) return
    const session = activeSession
    blocked = ''

    const userMsg = { id: `local-${Date.now()}`, role: 'user', text, created_at: new Date().toISOString() }
    const assistantMsg = {
      id: `local-assistant-${Date.now()}`,
      role: 'assistant',
      text: '',
      error: null,
      created_at: new Date().toISOString(),
    }

    session.messages = [...session.messages, userMsg, assistantMsg]
    // Index into the reactive array (not the plain `assistantMsg` object
    // above) so mutations go through Svelte's $state proxy and the UI
    // actually updates as chunks arrive.
    const assistantIndex = session.messages.length - 1
    sending = true

    await streamChat(
      { sessionId: session.id, text },
      {
        onDelta: (chunk) => {
          session.messages[assistantIndex].text += chunk
        },
        onDone: (payload) => {
          sending = false
          auth.usage = payload.usage
          if (payload.title && payload.title !== session.title) {
            session.title = payload.title
            sessions = sessions.map((s) => (s.id === session.id ? { ...s, title: payload.title } : s))
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
    activeId={activeSession?.id}
    open={sidebarOpen}
    {expandedRoles}
    onSelect={selectSession}
    onNewChat={newChat}
    onRename={renameChat}
    onDelete={deleteChat}
    onToggleRole={toggleRole}
    onNewRole={newRole}
    onEditRole={editRole}
    onDeleteRole={deleteRole}
    onToggle={() => (sidebarOpen = !sidebarOpen)}
  />
  <ChatView
    session={activeSession}
    {roles}
    {sending}
    {blocked}
    onSend={sendMessage}
    onToggleSidebar={() => (sidebarOpen = !sidebarOpen)}
  />
</div>

{#if editingRole}
  <RoleEditor role={editingRole} onSave={saveRole} onClose={() => (editingRole = null)} />
{/if}
