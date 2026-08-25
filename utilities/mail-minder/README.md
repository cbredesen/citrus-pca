# mail-minder

A small web app for managing posting reminders tied to calendar events and previewing HTML email templates.

- **Dashboard** — shows what needs to be posted and when, grouped by due / upcoming / completed. Check items off as you go.
- **Events** — lists upcoming events from your public Google Calendar (or any iCal feed) with a form to attach reminder rules per event.
- **Templates** — renders MJML email templates to HTML with variable substitution; copy the compiled HTML to paste into Mailchimp, Constant Contact, etc.

---

## Running locally (macOS)

### Prerequisites

- Node.js 24 or later (`brew install node` or [nodejs.org](https://nodejs.org))

  The app uses the built-in `node:sqlite` module, which is stable and unflagged in Node 24+.

### Setup

```bash
cd mail-minder
npm install

cp .env.example .env
# Edit .env — set ICAL_URLS to your public Google Calendar iCal URL.
# Find it in Google Calendar → Settings → your calendar → "Secret address in iCal format".

npm start
# → mail-minder running at http://localhost:3000
```

### Tests

```bash
npm test
```

### Getting your Google Calendar iCal URL

1. Open [calendar.google.com](https://calendar.google.com) → Settings (gear icon)
2. Click your calendar under "Settings for my calendars"
3. Scroll to "Integrate calendar" → copy **Secret address in iCal format**
4. Paste the URL into `ICAL_URLS` in your `.env`

To use multiple calendars, separate URLs with commas.

---

## Running on Fedora via Quadlet (Podman)

Quadlet turns a simple `.container` file into a managed systemd service. Requires Fedora 37+ (or any distro with Podman 4.4+ and systemd).

### 1. Build the image

Clone the repo on your server (or copy the files), then:

```bash
cd mail-minder
podman build -t mail-minder .
```

### 2. Create directories and config

```bash
# Persistent data (SQLite database)
mkdir -p ~/mail-minder/data

# Templates — copy the bundled samples or add your own .mjml files
mkdir -p ~/mail-minder/templates
cp templates/*.mjml ~/mail-minder/templates/

# Environment file — keep credentials out of unit files
mkdir -p ~/.config/mail-minder
cat > ~/.config/mail-minder/env <<'EOF'
PORT=3000
ICAL_URLS=https://calendar.google.com/calendar/ical/YOUR_CALENDAR_ID/public/basic.ics
EOF
```

### 3. Install the Quadlet unit

```bash
mkdir -p ~/.config/containers/systemd
cp deploy/mail-minder.container ~/.config/containers/systemd/

# Reload systemd so it picks up the new unit
systemctl --user daemon-reload

# Enable and start
systemctl --user enable --now mail-minder
```

The app is now running at `http://localhost:3000` (or whatever `PORT` you set). If the server needs to be accessible on the network, open the port in firewalld:

```bash
sudo firewall-cmd --add-port=3000/tcp --permanent
sudo firewall-cmd --reload
```

### 4. Useful commands

```bash
# View live logs
journalctl --user -u mail-minder -f

# Restart after rebuilding the image
podman build -t mail-minder /path/to/mail-minder
systemctl --user restart mail-minder

# Stop the service
systemctl --user stop mail-minder

# Check status
systemctl --user status mail-minder
```

### Keeping the service running after logout (linger)

By default, user services stop when you log out. To keep mail-minder running persistently:

```bash
loginctl enable-linger $USER
```

---

## Template authoring

Templates live in `templates/` as `.mjml` files. Use `{{variable_name}}` placeholders anywhere in the source — the app detects them automatically and shows input fields in the UI.

```xml
<mj-text>Hello {{first_name}}, join us for {{event_name}} on {{date}}!</mj-text>
```

Add a new template by dropping a `.mjml` file into the `templates/` directory and refreshing the Templates tab. No restart needed.
