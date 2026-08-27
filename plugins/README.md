# Plugins

WordPress plugins deployed to **flc.pca.org**. Each plugin is a self-contained directory with its own `README.md` and `CLAUDE.md`.

| Directory | Description |
|---|---|
| [`flc-anniversaries/`](flc-anniversaries/) | Displays club member milestone anniversaries for the current month (pure PHP, no build step) |
| [`flc-file-include/`](flc-file-include/) | Gutenberg block that includes HTML files from a server-side directory (React/Gutenberg block with `@wordpress/scripts` build) |

## Build Commands (plugins with a JS build step)

Run from within the plugin directory (e.g., `cd flc-file-include`):

```bash
npm install         # Install dependencies
npm run build       # Production build -> build/
npm run start       # Watch mode for development
npm run lint:js     # ESLint
npm run lint:css    # Stylelint
npm run plugin:zip  # Build and package plugin as a .zip (excludes dev files)
```

`flc-anniversaries` is pure PHP — no build step required.

## Deploying

Package a plugin for upload to WordPress via:

```bash
npm run plugin:zip   # run from within the plugin directory
```

This produces a `.zip` one level up from the plugin directory (i.e. directly in `plugins/`), excluding `node_modules`, `src`, and other dev-only files.

## WordPress Plugin Conventions

- Plugin header comment block required in the main `.php` file.
- Use `wp_enqueue_*` for assets.
- Use shortcodes or `register_block_type()` for output.
