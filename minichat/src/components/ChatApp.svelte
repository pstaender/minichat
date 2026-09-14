<script>
  import { onMount } from 'svelte'
  import { api, streamChat } from '../lib/api.js'
  import { auth } from '../lib/store.svelte.js'
  import ChatView from './ChatView.svelte'
  import Sidebar from './Sidebar.svelte'

  let sessions = $state([])
  let activeSession = $state(null)
  let sending = $state(false)
  let blocked = $state('')
  let sidebarOpen = $state(false)

  onMount(async () => {
    const data = await api.listSessions()
    sessions = data.sessions
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

  async function newChat() {
    const model = auth.user.model[0]
    const data = await api.createSession(model)
    sessions = [{ ...data.session, message_count: 0 }, ...sessions]
    activeSession = data.session
    blocked = ''
    sidebarOpen = false
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
          session.messages[assistantIndex].error = message
          if (payload?.usage) auth.usage = payload.usage
          if (payload?.code === 'daily_limit_reached' || payload?.code === 'daily_output_limit_reached') {
            blocked = message
          }
        },
      }
    )
  }
</script>

<div class="flex h-svh bg-white text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100">
  <Sidebar
    {sessions}
    activeId={activeSession?.id}
    open={sidebarOpen}
    onSelect={selectSession}
    onNew={newChat}
    onRename={renameChat}
    onDelete={deleteChat}
    onToggle={() => (sidebarOpen = !sidebarOpen)}
  />
  <ChatView
    session={activeSession}
    {sending}
    {blocked}
    onSend={sendMessage}
    onToggleSidebar={() => (sidebarOpen = !sidebarOpen)}
  />
</div>
