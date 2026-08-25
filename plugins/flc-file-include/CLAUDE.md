# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A WordPress plugin providing a Gutenberg block that includes an HTML file from a server-side directory. The block offers two modes:
- **Latest**: auto-selects the newest file in the configured directory
- **File selector**: lets the editor pick a specific file from the configured directory

The plugin strips `<html>`, `<head>`, and `<body>` wrapper tags, injecting only the inner body content.

## Expected Plugin Structure

```
wp-file-include/
├── wp-file-include.php         # Main plugin file (register plugin, block, REST endpoints)
├── src/
│   ├── block.json              # Block metadata (name, attributes, supports)
│   ├── edit.js                 # Block editor component (React/Gutenberg)
│   ├── save.js                 # Save (dynamic block — returns null)
│   └── index.js                # Block registration entry point
├── includes/
│   ├── class-file-scanner.php  # Scans configured directory, returns file list
│   └── class-block-renderer.php # Server-side render_callback: reads file, strips HTML wrapper
├── build/                      # Compiled JS/CSS (generated, not committed)
├── package.json
└── composer.json (optional)
```

## Architecture

**Dynamic block pattern**: `save.js` returns `null`; all rendering is server-side via `render_callback` in PHP. This is necessary because file content is read at render time, not at save time.

**REST API**: A custom REST endpoint (`/wp/v2/wp-file-include/files`) returns the directory listing so the editor can populate the file selector dropdown.

**Directory configuration**: Stored as a WordPress option (e.g., `wp_file_include_directory`), configurable via the block's Inspector Controls or a Settings page.

**HTML stripping**: Use PHP DOMDocument or regex to extract content between `<body>` tags before injecting into the page. Fall back to the full file content if no `<body>` tag is found.

## Build Commands

This plugin uses `@wordpress/scripts` for the JS build toolchain.

```bash
npm install       # Install dependencies
npm run build     # Production build -> build/
npm run start     # Watch mode for development
npm run lint:js   # ESLint
npm run lint:css  # Stylelint
```

## WordPress Development Setup

- Requires a local WordPress install (e.g., LocalWP, wp-env, MAMP)
- Symlink or place this directory in `wp-content/plugins/wp-file-include/`
- Activate the plugin from WP Admin -> Plugins
- Use `@wordpress/env` for a self-contained dev environment: `npx wp-env start`

## Key WordPress APIs Used

- `register_block_type()` with `render_callback` for server-side rendering
- `register_rest_route()` for the file-listing endpoint
- `get_option()` / `update_option()` for directory config
- Automatic asset enqueueing via `block.json` `editorScript` field
