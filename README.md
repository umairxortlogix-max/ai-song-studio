# AI Song Studio

A Laravel 13 application that generates complete songs (lyrics, music, vocals,
and a final mixed MP3/WAV) from a text prompt, using a **multi-provider AI
architecture with automatic fallback**.

If one AI provider hits its free-tier quota, rate limit, or goes down, the
system automatically moves to the next configured provider — in the same
request, with no action needed from the user — down a chain like:

```
Provider A (priority 1) --quota/limit/error--> Provider B (priority 2)
                                    --fails--> Provider C (priority 3)
                                    --fails--> Provider D: local/open-source model (priority 4)
```

Only officially available APIs and configured credentials are used. The app
never bypasses provider limits, terms, authentication, or CAPTCHAs.

---

## 1. Architecture at a glance

```
User → SongController → GenerateLyricsJob → AiRouterService
                                                   │
                                     ┌─────────────┼──────────────┐
                                     ▼             ▼              ▼
                              Provider A     Provider B      Provider D
                            (Hugging Face)  (Replicate)    (self-hosted /
                                                             open-source)
```

The **only** place that ever names a concrete provider class is
`config/ai.php` (`provider_classes` map). Everything else — the router,
jobs, controllers — talks exclusively through `App\AI\Contracts\
MusicProviderInterface`. Add, remove, or reorder providers by editing one
config array and a database row; no other code changes are required.

Key pieces:

| Path | Purpose |
|---|---|
| `app/AI/Contracts/MusicProviderInterface.php` | The contract every provider implements |
| `app/AI/DTOs/*` | Provider-independent request/response objects |
| `app/AI/Exceptions/*` | `RateLimitException`, `QuotaExceededException`, `TemporaryProviderException`, `ProviderAuthException`, `AllProvidersUnavailableException` |
| `app/AI/Services/AiRouterService.php` | **The fallback engine.** Walks providers by priority, retries transient failures with backoff, skips exhausted/disabled providers, logs everything |
| `app/AI/Services/ProviderResolver.php` | Maps a DB row → concrete provider class via `config('ai.provider_classes')` |
| `app/AI/Services/MusicPromptBuilder.php` | Builds one structured, provider-independent prompt from user input |
| `app/AI/Providers/*` | Example providers: `HuggingFaceProvider` (lyrics), `ReplicateProvider` (async music), `StabilityAudioProvider` (sync music), `LocalModelProvider` (self-hosted fallback, no quota) |
| `app/Jobs/*` | `GenerateLyricsJob → GenerateMusicJob → GenerateVocalsJob → MixAudioJob (FFmpeg) → FinalizeSongJob` |
| `app/Console/Commands/AiHealthCheck.php` | `php artisan ai:health-check` — pings every provider, updates status |
| `app/Console/Commands/ResetProviderUsage.php` | Resets daily/monthly usage counters |

---

## 2. Requirements

- PHP 8.3+
- Composer 2.x
- MySQL 8.x (or SQLite for local dev/testing)
- Redis (optional — database queue driver works out of the box)
- **FFmpeg** installed and on `PATH` (used by `MixAudioJob` to mix instrumental + vocals and transcode to WAV)
- Node.js 18+ / npm (only needed if you want a Vite build instead of the Tailwind CDN already wired into the Blade layout)

Install FFmpeg:

```bash
# Ubuntu/Debian
sudo apt update && sudo apt install -y ffmpeg

# macOS
brew install ffmpeg
```

---

## 3. Installation

```bash
git clone <your-repo-url> ai-song-studio
cd ai-song-studio

composer install
npm install          # optional, only if you switch off the Tailwind CDN
npm run build        # optional

cp .env.example .env
php artisan key:generate
```

### Database

Create a MySQL database (or switch `DB_CONNECTION=sqlite` for local dev),
then set the `DB_*` values in `.env` and run:

```bash
php artisan migrate
php artisan db:seed
```

`db:seed` creates:
- An admin user: `admin@example.com` / `password` (change this immediately)
- The 4-provider fallback chain (`AiProviderSeeder`) with priorities 1–4
- Matching capability rows in `ai_models` (`AiModelSeeder`)

### Storage

```bash
php artisan storage:link
```

Generated audio is stored under:

```
storage/app/public/songs/{year}/{month}/{user_id}/{song_id}/final.mp3
```

### AI provider credentials

Edit `.env` with your **real, officially issued** API keys. Never commit
these to version control.

```env
# Provider A (priority 1)
AI_PROVIDER_A_KEY=your_huggingface_token
AI_PROVIDER_A_URL=https://api-inference.huggingface.co
AI_PROVIDER_A_MODEL=mistralai/Mistral-7B-Instruct-v0.2

# Provider B (priority 2)
AI_PROVIDER_B_KEY=your_replicate_token
AI_PROVIDER_B_URL=https://api.replicate.com/v1
AI_PROVIDER_B_MODEL=your-model-version-id

# Provider C (priority 3)
AI_PROVIDER_C_KEY=your_stability_key
AI_PROVIDER_C_URL=https://api.stability.ai/v2beta
AI_PROVIDER_C_MODEL=stable-audio

# Provider D (priority 4) — self-hosted, no key needed
AI_LOCAL_MODEL_URL=http://127.0.0.1:8800

AI_USER_DAILY_LIMIT=5
```

Re-run the seeder after changing `.env` provider values:

```bash
php artisan db:seed --class=AiProviderSeeder
```

Or manage providers entirely from the admin panel at `/admin/providers`
(add, edit, enable/disable, reset usage, test connection, view logs) —
no redeploy needed.

### Queue worker

Song generation always runs through the queue, never inline in an HTTP
request:

```bash
php artisan queue:work --queue=default --tries=1
```

For production, run this under Supervisor so it restarts automatically.
Example Supervisor config:

```ini
[program:ai-song-studio-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/ai-song-studio/artisan queue:work --sleep=3 --tries=1 --max-time=3600
autostart=true
autorestart=true
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/ai-song-studio/storage/logs/worker.log
```

### Scheduler

Add this single cron entry (runs `ai:health-check` every 5 minutes and
`ai:reset-usage` daily, as defined in `routes/console.php`):

```
* * * * * cd /var/www/ai-song-studio && php artisan schedule:run >> /dev/null 2>&1
```

### Local/open-source model server (Provider D)

`LocalModelProvider` expects a simple HTTP server exposing:

- `GET  /health` → 200 OK when ready
- `POST /generate/lyrics` → `{ lyrics }`
- `POST /generate/music` → `{ audio_base64 }`
- `POST /generate/vocals` → `{ audio_base64 }`

This can be a small Python FastAPI service wrapping an open-source model
(Ollama, a local MusicGen checkpoint, ComfyUI, etc.) — Laravel only ever
talks to it over plain HTTP, so any inference stack that speaks this
tiny contract works.

---

## 4. Running the app

```bash
php artisan serve
```

Visit `http://127.0.0.1:8000`, register an account, and create your first
song from the dashboard. Generation status updates live on the song page
(polling `/songs/{song}/status` every 3 seconds) — you'll see:

```
✓ Lyrics generated
✓ Instrumental generated
⏳ Generating vocals...
○ Mixing
○ Finalizing
```

---

## 5. Testing

```bash
php artisan test
```

`tests/Feature/AiRouterFallbackTest.php` is the key test suite — it proves
the "most important feature" directly: Provider A fails (rate limit /
quota exceeded) → Provider B automatically picks up the same request →
generation completes, without the user retrying anything. It also covers
locally-tracked quota skipping, disabled providers being skipped, and the
`AllProvidersUnavailableException` path when every provider fails.

Other suites cover song ownership (`SongOwnershipTest`) and admin-only
routes (`AdminPermissionsTest`).

---

## 6. Adding a new AI provider

1. Create `app/AI/Providers/YourProvider.php` implementing
   `MusicProviderInterface` (extend `AbstractProvider` for the HTTP/quota
   plumbing already provided).
2. Map it in `config/ai.php`:
   ```php
   'provider_classes' => [
       // ...
       'your_provider' => \App\AI\Providers\YourProvider::class,
   ],
   ```
3. Add a row via the admin panel (`/admin/providers`) or a seeder, with
   `provider_type = 'your_provider'`, credentials, model, and a priority.

No changes to `AiRouterService`, jobs, or controllers are ever needed.

---

## 7. Security notes

- Provider API keys are stored **encrypted** (`encrypted` cast on
  `AiProvider::api_key`) and hidden from all JSON/array output.
- The public `/api/providers` endpoint only ever exposes name/slug/status/
  priority — never keys or base URLs.
- `SongPolicy` enforces that users can only view, edit, delete, or
  download their own songs.
- All internal exceptions are caught inside `AiRouterService`; the user
  only ever sees "All available AI generation providers are currently
  unavailable. Please try again later." — never a raw stack trace or
  provider error message.
- Lyrics prompts explicitly instruct providers to generate **original**
  content and never reproduce existing copyrighted songs.

---

## 8. License

MIT — see `LICENSE`.
