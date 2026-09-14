const BASE = '/api'

async function request(path, options = {}) {
  const res = await fetch(BASE + path, {
    credentials: 'include',
    headers: { 'Content-Type': 'application/json', ...(options.headers ?? {}) },
    ...options,
  })

  if (!res.ok) {
    throw await toError(res)
  }

  if (res.status === 204) return null
  return res.json()
}

async function toError(res) {
  let message = `Request failed (${res.status})`
  let code = null
  try {
    const data = await res.json()
    if (data?.error) message = data.error
    if (data?.code) code = data.code
  } catch {
    // non-JSON error body, keep the generic message
  }
  const err = new Error(message)
  err.status = res.status
  err.code = code
  return err
}

export const api = {
  login: (email, password) =>
    request('/login', { method: 'POST', body: JSON.stringify({ email, password }) }),
  logout: () => request('/logout', { method: 'POST' }),
  me: () => request('/me'),
  listSessions: () => request('/sessions'),
  createSession: (model, title, roleId = null) =>
    request('/sessions', { method: 'POST', body: JSON.stringify({ model, title, role_id: roleId }) }),
  getSession: (id) => request(`/sessions/${id}`),
  renameSession: (id, title) =>
    request(`/sessions/${id}`, { method: 'PATCH', body: JSON.stringify({ title }) }),
  deleteSession: (id) => request(`/sessions/${id}`, { method: 'DELETE' }),

  listRoles: () => request('/roles'),
  createRole: (name, prompt) =>
    request('/roles', { method: 'POST', body: JSON.stringify({ name, prompt }) }),
  updateRole: (id, patch) => request(`/roles/${id}`, { method: 'PATCH', body: JSON.stringify(patch) }),
  deleteRole: (id) => request(`/roles/${id}`, { method: 'DELETE' }),
}

/**
 * Sends a chat message and streams the assistant's reply. Uses fetch +
 * ReadableStream (not EventSource) because we need a POST body and cookies.
 */
export async function streamChat({ sessionId, text }, { onDelta, onDone, onError } = {}) {
  let res
  try {
    res = await fetch(`${BASE}/chat/stream`, {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ session_id: sessionId, text }),
    })
  } catch {
    onError?.('Network error. Is the server running?')
    return
  }

  if (!res.ok) {
    const err = await toError(res)
    onError?.(err.message, { code: err.code })
    return
  }

  const reader = res.body.getReader()
  const decoder = new TextDecoder()
  let buffer = ''

  for (;;) {
    const { value, done } = await reader.read()
    if (done) break
    buffer += decoder.decode(value, { stream: true })

    let sepIndex
    while ((sepIndex = buffer.indexOf('\n\n')) !== -1) {
      const rawEvent = buffer.slice(0, sepIndex)
      buffer = buffer.slice(sepIndex + 2)

      const line = rawEvent.split('\n').find((l) => l.startsWith('data:'))
      if (!line) continue

      let payload
      try {
        payload = JSON.parse(line.slice(5).trim())
      } catch {
        continue
      }

      if (payload.type === 'delta') onDelta?.(payload.text)
      else if (payload.type === 'done') onDone?.(payload)
      else if (payload.type === 'error') onError?.(payload.message, payload)
    }
  }
}
