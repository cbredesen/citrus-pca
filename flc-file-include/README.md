# FLC File Include

A WordPress Gutenberg block that pulls an HTML file from a server-side directory and renders its body content inline on the page. Useful for embedding generated or externally-produced HTML reports without copying and pasting.

## What it does

- **Latest mode** — automatically serves the newest `.html` file in the configured directory
- **File selector mode** — lets the editor pick a specific file from a dropdown
- Strips `<html>`, `<head>`, `<body>` wrappers and inline styles before rendering
- Optional "View original file" link above the content
- Directory is configured once at Settings > File Include

## Requirements

- WordPress 6.3+
- PHP 8.0+

## Installation

1. Place the `flc-file-include` directory in `wp-content/plugins/`
2. Activate the plugin from WP Admin > Plugins
3. Go to Settings > File Include and set the directory path (relative to WordPress root, e.g. `wp-content/uploads/html-files`)

Or use the prebuilt zip:

```
npm run plugin:zip
```

Then install via WP Admin > Plugins > Add New > Upload Plugin.

## Development

```bash
npm install
npm run start    # watch mode
npm run build    # production build
```

Requires Node.js. Uses `@wordpress/scripts` for the build toolchain.
