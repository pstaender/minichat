<script>
  import { onMount } from 'svelte'
  import ChatApp from './components/ChatApp.svelte'
  import LoginView from './components/LoginView.svelte'
  import { _ } from './lib/i18n.js'
  import { auth, loadAuth } from './lib/store.svelte.js'

  onMount(loadAuth)
</script>

{#if auth.loading}
  <div class="flex h-svh items-center justify-center text-sm text-neutral-400 dark:text-neutral-500">
    {$_('app.loading')}
  </div>
{:else if auth.user}
  <ChatApp />
{:else}
  <LoginView />
{/if}
