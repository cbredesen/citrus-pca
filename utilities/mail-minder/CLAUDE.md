# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What This App Does

mail-minder is a containerized Node.js/Express web app with two features:

1. **Calendar → Reminder pipeline** — reads upcoming events from one or more public iCal feeds and surfaces actionable posting reminders (email, social, newsletter) with configurable lead times per event. Reminders can be checked off when completed.
2. **Email template library** — MJML source templates compiled to HTML on demand, with `{{variable}}` substitution and a "Copy HTML" button for pasting into Mailchimp/Constant Contact.

The app surfaces *what to do and when* — it does **not** send email or post to social. Calendar access is read-only (public iCal feeds, no OAuth). No authentication — trusted single-user on a private network.

## Stack

- **Runtime/Framework:** Node.js 24+ + Express (`node:sqlite` is stable and unflagged in Node 24; the `Containerfile` uses `node:24-alpine`)
- **Calendar:** public iCal URLs fetched server-side (no CORS issues), parsed with `node-ical`, events cached in memory for 30 minutes
- **Persistence:** SQLite via Node's built-in `node:sqlite` (`DatabaseSync`) for reminder rules and completion state — no native compilation required
- **Frontend:** single `public/index.html` using Alpine.js — no build step
- **Email templates:** `.mjml` source files in `templates/` compiled at request time via `mjml`; managed on disk and mounted as a volume
- **Container:** Podman on Linux; `Containerfile` (Podman-native name for Dockerfile)
- **Tests:** Node.js built-in test runner (`node:test`)

## Commands

```bash
npm install            # Install dependencies
npm start              # Start server (reads PORT and ICAL_URLS from environment)
npm test               # Run all tests

# Container (run from repo root)
podman build -t mail-minder .
podman run -p 3000:3000 \
  --env-file .env \
  -v ./templates:/app/templates:Z \
  -v ./data:/app/data:Z \
  mail-minder
```

The `:Z` flag on volume mounts is required for SELinux-enabled hosts.

Environment: copy `.env.example` to `.env` and set `ICAL_URLS` (comma-separated) and `PORT` (default 3000). The SQLite database file lives at `./data/mail-minder.db` (or `/app/data/mail-minder.db` inside the container).

## Architecture

Four independent modules wired together in `src/server.js`:

- **`src/calendar.js`** — fetches iCal feeds server-side, parses events with `node-ical`, caches in memory for 30 minutes; `getEvents(forceRefresh)` returns the next 60 days sorted by start date
- **`src/reminders.js`** — CRUD for reminder rules; `getDueReminders(events, today)` returns `{ due, upcoming, past, orphaned }` — orphaned means the event UID is no longer in the feed
- **`src/templates.js`** — lists `.mjml` files from `TEMPLATES_DIR`, extracts `{{variable}}` names, compiles via `mjml`; path traversal is guarded in `safeTemplatePath()`
- **`src/db.js`** — SQLite setup and schema; call `db.initialize()` once at startup; tests use `process.env.DB_PATH = ':memory:'` before requiring this module
- **`src/server.js`** — Express entry point; all routes defined here as thin wrappers; serves `public/` as static files

API routes: `GET/POST /api/reminders`, `POST /api/reminders/:id/complete`, `POST /api/reminders/:id/uncomplete`, `DELETE /api/reminders/:id`, `GET /api/events`, `GET /api/events/:eventId/reminders`, `GET /api/templates`, `GET /api/templates/:name/render`, `GET /api/templates/:name/html`

## Key Data Shape

```js
// Reminder rule (stored in SQLite)
{
  id, event_id, channel, days_before, note,
  completed_at  // NULL or SQLite datetime string; NULL means not done
}
// Channels: 'email' | 'facebook' | 'instagram' | 'newsletter' | 'other'
```

`getDueReminders` groups by comparing `event.start - days_before` against today (midnight-aligned). Reminders are matched to events by iCal UID (`event_id`).

## Deployment

See `deploy/mail-minder.container` for the Quadlet systemd unit. Install to `~/.config/containers/systemd/` on Fedora and run `systemctl --user daemon-reload && systemctl --user enable --now mail-minder`.
