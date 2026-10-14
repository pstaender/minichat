<script>
  import { _ } from '../lib/i18n.js'

  // Six single-character boxes shown as "_ _ _ - _ _ _". Typing advances,
  // Backspace steps back, and pasting a whole code ("AB3-C2D") fills all.
  let { onComplete, disabled = false, autofocus = false } = $props()

  const LENGTH = 6
  let chars = $state(Array(LENGTH).fill(''))
  let inputs = $state([])

  const complete = $derived(chars.every(Boolean))

  export function value() {
    return chars.join('')
  }

  export function reset() {
    chars = Array(LENGTH).fill('')
    inputs[0]?.focus()
  }

  $effect(() => {
    if (autofocus) inputs[0]?.focus()
  })

  function handleInput(i, event) {
    const el = event.currentTarget
    const typed = el.value.toUpperCase().replace(/[^A-Z0-9]/g, '')
    if (!typed) {
      chars[i] = ''
      el.value = ''
      return
    }

    // spread multi-character input (paste, autofill) over the following boxes
    let j = i
    for (const c of typed) {
      if (j >= LENGTH) break
      chars[j++] = c
    }
    el.value = chars[i]
    inputs[Math.min(j, LENGTH - 1)]?.focus()
    if (complete) onComplete(value())
  }

  function handleKeydown(i, event) {
    if (event.key === 'Backspace' && !chars[i] && i > 0) {
      event.preventDefault()
      chars[i - 1] = ''
      inputs[i - 1].focus()
    } else if (event.key === 'ArrowLeft' && i > 0) {
      event.preventDefault()
      inputs[i - 1].focus()
    } else if (event.key === 'ArrowRight' && i < LENGTH - 1) {
      event.preventDefault()
      inputs[i + 1].focus()
    } else if (event.key === 'Enter' && complete) {
      event.preventDefault()
      onComplete(value())
    }
  }
</script>

<div class="flex items-center justify-center gap-1.5" role="group" aria-label={$_('join.codeLabel')}>
  {#each chars as char, i (i)}
    {#if i === LENGTH / 2}
      <span class="px-1 text-lg text-neutral-400 dark:text-neutral-500">-</span>
    {/if}
    <input
      bind:this={inputs[i]}
      value={char}
      oninput={(e) => handleInput(i, e)}
      onkeydown={(e) => handleKeydown(i, e)}
      onfocus={(e) => e.currentTarget.select()}
      {disabled}
      autocomplete="off"
      autocapitalize="characters"
      spellcheck="false"
      aria-label={$_('join.charLabel', { values: { n: i + 1 } })}
      class="h-11 w-9 rounded-lg border border-neutral-300 bg-white text-center font-mono text-lg font-semibold text-neutral-900 uppercase outline-none focus:border-neutral-500 disabled:opacity-50 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-100"
    />
  {/each}
</div>
