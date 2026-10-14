# minichat

Self-hosted chat UI for ChatGPT/Claude via API keys, multi-user
(email-optional, password-based login), one PHP backend + one Vite
frontend.

## Stack — non-negotiable

- **PHP backend**, routed with **dumbo** (`vendor/notrab/dumbo`). Modern
  PHP (8.3+): typed properties, `match`, constructor promotion, no
  boilerplate frameworks beyond dumbo.
- **Frontend**: Vite + **Svelte 5** (runes: `$state`, `$props`, `$derived`,
  `$effect` — not legacy `export let`/stores) + **Tailwind CSS v4**
  (`@tailwindcss/vite`, no config file, no SCSS/LESS).
- Modern JS only: ES modules, no CommonJS, no jQuery/legacy patterns.
- No TypeScript, no build step beyond Vite/composer.

## Storage & auth

- No database. Config in `chat_config.json` (array of users: id, email?,
  password, models[], daily_token_limit(_output), api_keys). No per-account
  `provider` field — each entry in `models[]` is a bare or
  `provider.`-prefixed model name, resolved per-model via
  `Config::parseModelSpec()` (prefix wins; else guessed from the name: has
  "gpt" → openai, "claude" → anthropic). `api_keys` maps provider name to
  key, so one account can freely mix providers across its `models[]`.
- Login state = native **PHP session** (`src/Auth.php`), not tokens/JWT.
- Chat sessions, roles, and usage are plain JSON files under `storage/`
  (`SessionStore.php`, `RoleStore.php`, `Usage.php`), one file per user,
  written with file-locked read-modify-write (`Json.php`).

## Behavior

- Chat streams token-by-token via SSE (`src/Sse.php` + `/api/chat/stream`).
  dumbo buffers whole responses in memory, so this one route bypasses it:
  emit headers/chunks directly, `exit` before dumbo sends its own response.
- Daily token limits (`Usage::checkLimit`) enforced before every send;
  over-limit returns `429` and the UI disables the composer.
- Multiple chats per user, rename/delete, paste HTML→Markdown (Turndown),
  markdown rendering (`marked` + `DOMPurify`).
- Each assistant reply has copy buttons (`Message.svelte`): "Markdown"
  copies the raw source via `writeText`; "HTML" writes both `text/html`
  (rendered via `renderMarkdown`) and a `text/plain` fallback with
  `navigator.clipboard.write([new ClipboardItem(...)])`, so rich targets
  (email/docs) keep formatting and plain-text targets get the Markdown.
- Model can be changed per-chat any time via `PATCH /api/sessions/:id`
  `{model}` (shares `resolveModel()` with session creation, in
  `public/index.php`) — the ChatView header shows a `<select>` instead of
  plain text once `auth.user.models.length > 1`.
- Roles: a reusable name + rules text (e.g. escape-game rules), optionally
  attached to a session via `role_id`. The role's rules are prepended as a
  `system` message on every send (resolved live, not baked into the
  session) — see the role lookup in `/api/chat/stream`. Sidebar groups
  chats under their role, collapsible.
- Icons: `line-md` via `unplugin-icons` (`~icons/line-md/...`), never
  emoji.
- No file upload feature — explicitly removed, don't re-add it.
- i18n: German default, English second, via `svelte-i18n` (existing
  library, not hand-rolled) — `minichat/src/lib/i18n.js` registers
  `src/locales/{en,de}.json` with `addMessages` (sync, no lazy-loading
  gate); the initial locale is the `localStorage` choice if set, else
  German (`DEFAULT_LOCALE`) regardless of `navigator.language`. Every UI string goes through `$_('key')`; new
  strings need an entry in **both** locale files. Known backend error
  responses carry a `code` field precisely so the frontend can translate
  them (`apiErrorMessage()` in `i18n.js`) — add a `code` to any new
  user-facing backend error too.

## Design

Clear, businesslike, ChatGPT-like. Keep everything as simple as possible —
no premature abstractions, no extra dependencies beyond what's already
installed.

## Running

`composer start` (PHP on :8811, no timeout) + `cd minichat && npm run dev`
(Vite proxies `/api` to :8811 — same-origin in dev, no CORS needed).

<!--claude --resume 0743c1dd-0f7d-49f2-affd-9d4b39a76507-->
