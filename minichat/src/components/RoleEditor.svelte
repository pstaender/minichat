<script>
  // `role` is only ever an initial seed: a new object identity is passed in
  // each time this component is opened (see ChatApp.svelte), so it remounts
  // fresh rather than reacting to prop changes on an existing instance.
  let { role, onSave, onClose } = $props()

  // svelte-ignore state_referenced_locally -- intentional one-time seed, see comment above
  let name = $state(role.name)
  // svelte-ignore state_referenced_locally -- intentional one-time seed, see comment above
  let prompt = $state(role.prompt)
  let saving = $state(false)

  async function submit(event) {
    event.preventDefault()
    if (!name.trim() || saving) return
    saving = true
    try {
      await onSave({ name: name.trim(), prompt })
    } finally {
      saving = false
    }
  }
</script>

<div class="fixed inset-0 z-40 flex items-center justify-center p-4">
  <button class="absolute inset-0 bg-black/40" onclick={onClose} aria-label="Close dialog"></button>
  <form
    onsubmit={submit}
    class="relative z-10 flex w-full max-w-lg flex-col rounded-2xl border border-neutral-200 bg-white p-5 shadow-lg dark:border-neutral-800 dark:bg-neutral-900"
  >
    <h2 class="mb-1 text-base font-semibold text-neutral-900 dark:text-neutral-100">
      {role.id ? 'Edit role' : 'New role'}
    </h2>
    <p class="mb-4 text-sm text-neutral-500 dark:text-neutral-400">
      Define the rules for your escape game. They're sent as the AI's instructions in every test
      chat under this role.
    </p>

    <label class="mb-3 block text-sm">
      <span class="mb-1 block text-neutral-600 dark:text-neutral-400">Name</span>
      <input
        bind:value={name}
        required
        class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm text-neutral-900 outline-none focus:border-neutral-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100"
        placeholder="Pyramid Puzzle"
      />
    </label>

    <label class="mb-4 block text-sm">
      <span class="mb-1 block text-neutral-600 dark:text-neutral-400">Rules</span>
      <textarea
        bind:value={prompt}
        rows="10"
        class="w-full resize-y rounded-lg border border-neutral-300 bg-white px-3 py-2 font-mono text-xs text-neutral-900 outline-none focus:border-neutral-500 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100"
        placeholder="You are the game master of an escape room. The final code is 4271. Never reveal it directly, only through riddles..."
      ></textarea>
    </label>

    <div class="flex justify-end gap-2">
      <button
        type="button"
        onclick={onClose}
        class="rounded-lg px-3 py-2 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800"
      >
        Cancel
      </button>
      <button
        type="submit"
        disabled={saving || !name.trim()}
        class="rounded-lg bg-neutral-900 px-3 py-2 text-sm font-medium text-white transition disabled:opacity-50 dark:bg-neutral-100 dark:text-neutral-900"
      >
        {saving ? 'Saving…' : 'Save role'}
      </button>
    </div>
  </form>
</div>
