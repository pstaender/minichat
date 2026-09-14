# minichat

A minimal, self-hosted chat UI for OpenAI and Anthropic, styled like ChatGPT.
Multiple people can log in with just a password (email is optional, purely
for identifying who's who), each gets their own chats and a daily token
budget defined in `chat_config.json`.

- **Backend**: PHP, routed with [dumbo](https://github.com/notrab/dumbo).
  Login state lives in the native PHP session (cookie-based). Everything
  else (chat sessions, usage) is plain JSON files under `storage/` — no
  database.
- **Frontend**: Vite + Svelte 5 + Tailwind CSS v4.

## Setup

```bash
composer install
cp chat_config.example.json chat_config.json   # then edit it, see below
cd minichat && npm install
```

Edit `chat_config.json` at the project root — one entry per person:

```json
{
  "id": "me@home.com",
  "email": "me@home.com",
  "password": "secret123",
  "provider": "openai",
  "model": ["gpt-5.6-sol", "gpt-5.6-luna"],
  "daily_token_limit": 100000,
  "daily_token_limit_output": 20000,
  "API_KEY": "sk-..."
}
```

- `id` is required and is what usage/sessions are stored under. If you skip
  `email`, use `id` as a stable identifier instead.
- `email` is optional — leave it `null` for a password-only login. Login
  matches by password alone when no email is given, or by password *and*
  email when one is provided.
- `provider` is either `"openai"` or `"anthropic"`; `model` is the list of
  models that user is allowed to pick between.
- `daily_token_limit` caps total (input + output) tokens per day;
  `daily_token_limit_output` additionally caps output tokens. Both reset at
  UTC midnight. Omit either (or set to `null`) to not enforce it.
- **Never commit your real `chat_config.json`** — it holds live API keys.
  It's already git-ignored; `chat_config.example.json` is the template.

## Running it

Two dev servers, in two terminals:

```bash
# 1. PHP API
composer start

# 2. Frontend (proxies /api to the PHP server above, see minichat/vite.config.js)
cd minichat && npm run dev
```

`composer start` just runs `php -S localhost:8811 -t public public/index.php`
(see the `scripts` entry in `composer.json`); `process-timeout` is set to
`0` there so Composer doesn't kill the server after its default 300s.

Open the URL Vite prints (typically http://localhost:5173). Because the
frontend proxies `/api/*` to PHP, both run on the same origin as far as the
browser is concerned — no CORS setup needed and the session cookie just
works.

If you deploy the two separately (no proxy in front), the backend already
reflects the request `Origin` and sends `Access-Control-Allow-Credentials`,
so cross-origin cookies work too — just make sure both sides are served
over HTTPS in that case (browsers require `Secure` cookies for real
cross-site use).

### Uploads / large requests

Not applicable — this build has no file upload feature.

## How it works

- **Auth**: `POST /api/login` with `{ email?, password }` checks
  `chat_config.json` and, on success, stores the user id in `$_SESSION`
  (see `src/Auth.php`). All other `/api/*` routes require that session.
- **Chats**: each user's conversations live in
  `storage/sessions/{user_id}.json`, one JSON blob with all sessions and
  their messages (`src/SessionStore.php`).
- **Roles**: a reusable name + rules text a user defines once (e.g. the
  rules of an escape game) and stored in `storage/roles/{user_id}.json`
  (`src/RoleStore.php`). A chat session can optionally be scoped to a role
  (`role_id`); the role's rules text is then prepended as a `system`
  message on every send in that chat, so you can test-play a role across
  as many chats as you like — see the role handling in
  `POST /api/chat/stream` in `public/index.php`. The sidebar groups a
  user's chats under their role, collapsible per role, with role-less
  chats listed separately underneath.
- **Usage**: `storage/usage/{user_id}.json` tracks today's input/output
  token counts (`src/Usage.php`). Checked before every send; once a limit
  is hit, `POST /api/chat/stream` returns `429` and the UI shows a banner
  and disables the composer until the next UTC day.
- **Streaming**: `POST /api/chat/stream` streams the reply as
  Server-Sent-Events. dumbo buffers whole responses in memory by design, so
  this one route bypasses it — it sends headers and flushes chunks itself,
  then exits before dumbo would otherwise try to send its own response (see
  `src/Sse.php` and the route in `public/index.php`). The frontend reads it
  with `fetch` + a `ReadableStream` reader (not `EventSource`, since that
  can't send a JSON body or cookies reliably cross-setup) — see
  `minichat/src/lib/api.js`.
- **Providers**: `src/Providers/OpenAIProvider.php` and
  `AnthropicProvider.php` each stream from their respective chat/messages
  API via `curl`'s `CURLOPT_WRITEFUNCTION`, parsing SSE as it arrives and
  reporting token usage from the final chunk.
- **Paste-as-Markdown**: pasting rich text (e.g. from a web page) into the
  composer is converted to Markdown with Turndown; plain-text paste is left
  to the browser. See `minichat/src/lib/paste.js`.
- **Markdown rendering**: assistant replies are rendered with `marked` and
  sanitized with `DOMPurify` before being inserted as HTML.
- **Icons**: [line-md](https://icon-sets.iconify.design/line-md/) icons via
  `unplugin-icons` — imported per-icon (e.g. `~icons/line-md/plus`) and
  compiled to Svelte components at build time, so there's no runtime fetch
  from Iconify's API and no icon font.
- **Language**: English by default, German as the second language, via
  [svelte-i18n](https://github.com/kaisermann/svelte-i18n). The UI
  language is detected from the browser's `navigator.language` on first
  visit, then remembered in `localStorage` once changed with the EN/DE
  toggle (in the sidebar, and on the login screen). Translations live in
  `minichat/src/locales/{en,de}.json`; the bootstrap in
  `minichat/src/lib/i18n.js` also translates known backend error codes
  (e.g. the daily-limit message) via `apiErrorMessage()` — errors without
  a `code` (like a raw provider API error) are shown as-is since they
  can't be pre-translated.

## Project layout

```
public/index.php         Front controller — all routes are wired up here
src/                      PHP backend (PSR-4: Philipp\Minichat\)
  Auth.php                Session-based login state
  Config.php              Reads chat_config.json
  SessionStore.php        Per-user chat sessions (storage/sessions/*.json)
  RoleStore.php           Per-user roles/system-prompts (storage/roles/*.json)
  Usage.php               Per-user daily token accounting + limit checks
  Sse.php                 Minimal Server-Sent-Events emitter
  Providers/              OpenAI / Anthropic streaming clients
storage/                  Runtime JSON data (git-ignored, except .gitkeep)
minichat/                 Vite + Svelte 5 + Tailwind frontend
  src/lib/                api client, reactive auth store, markdown, paste, i18n
  src/locales/            en.json, de.json translation dictionaries
  src/components/         LoginView, Sidebar, SessionRow, RoleEditor,
                          ChatView, Message, Composer, LanguageToggle
```
