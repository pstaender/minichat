import { api } from './api.js'

export const auth = $state({
  user: null,
  usage: null,
  loading: true,
})

export async function loadAuth() {
  auth.loading = true
  try {
    const data = await api.me()
    auth.user = data.user
    auth.usage = data.usage
  } catch {
    auth.user = null
    auth.usage = null
  } finally {
    auth.loading = false
  }
}

export async function login(email, password) {
  const data = await api.login(email, password)
  auth.user = data.user
  auth.usage = data.usage
}

export async function logout() {
  try {
    await api.logout()
  } finally {
    auth.user = null
    auth.usage = null
  }
}
