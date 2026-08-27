# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with files in this directory.

## Overview

`design/` holds visual design work for **flc.pca.org**: static HTML explorations today,
and actual WordPress themes once a direction is chosen. Nothing in here is deployed to the
site yet — these are mockups and references, not production code.

- `index.html` — static review gallery: thumbnails of all 8 concept pages linking to them.
  Meant to be served off flc.pca.org for client review. Hand-written, no build step.
- `screenshots/` — PNGs backing the gallery (`<concept>-<index|post>.png`), captured headless
  at 1280&nbsp;px width. Regenerate whenever a concept changes.
- `concepts/` — alternate style directions exported from Claude Design. **Unvetted.** See below.
- `../design-old/` — the previous generation of home-page explorations (`flc-home-*.html`),
  kept for reference. Superseded by `concepts/`.

Planned: `themes/` — real WordPress themes, added when a concept graduates (see "Graduating a concept to a theme").

## `concepts/` — status: needs vetting

Each subdirectory is one palette/style direction for the site, as self-contained static HTML:

| Concept | Direction | Link / accent | Primary button |
|---|---|---|---|
| `flc-white/` | Light, classic top bar, ticket-style event cards | `#2B5CC7` blue | blue |
| `flc-cream/` | Warm off-white (`#FDFCFA`), editorial, condensed caps | `#2B5CC7` blue | navy `#23283F` |
| `flc-navy/` | Dark `#262B4A` header, full-bleed photo hero, rounded poster cards | `#2B5CC7` blue | blue |
| `flc-green/` | White header, green `#49864E` section bands, citrus-orange `#DB9334` CTAs | green | orange |

Each concept contains:
- `index.html` — home page (header/nav, hero, upcoming-events grid, footer)
- `post.html` — a blog post / newsletter article page
- `flc-logo.png` — the club logo (identical copy in each folder)

Shared design language across all four:
- Fonts: **Barlow** (body) + **Barlow Condensed** (headings), loaded from Google Fonts
- ~1180px centered content column
- All CSS is inline (`<style>` block plus per-element `style=""`); no build step, no shared stylesheet
- Images are represented by `.ph` placeholder divs ("Full-width club photo", "Lead photo — 3:2", etc.)
- Copy and event data are plausible-looking filler, not real club content

### Vetting checklist

Before a concept is considered vetted / ready to pitch to the client:

- [ ] **Accessibility** — color contrast (WCAG AA), focus states, real heading hierarchy,
      `alt` text plan, keyboard nav for dropdown menus
- [ ] **Responsive** — the fixed pixel paddings and multi-column grids need mobile/tablet breakpoints;
      verify no horizontal scroll below ~380px
- [ ] **Content fit** — does the real site's IA fit? (Events, About, The Spiel/newsletter, Store & Classifieds,
      Membership). Nav labels differ between concepts and between `index.html`/`post.html` — reconcile.
- [ ] **Brand** — confirm logo usage, colors, and PCA/Zone 12 references are correct and approved
- [ ] **Naming** — `flc-cream/index.html` still carries the title `(citrus-cream)`; names should be consistent
- [ ] **Assets** — decide real image treatment; the logo is a low-res PNG, may need SVG / higher-res
- [ ] **Feasibility** — map each layout to how it would be built in WordPress (block patterns vs. custom template)

Record vetting outcomes here or in a per-concept note so the review isn't repeated.

## Graduating a concept to a theme

When a direction is chosen, it becomes a real theme under `themes/<theme-name>/`. Decide up front:

- **Block theme** (`theme.json` + block templates, `templates/`, `parts/`) vs. **classic theme**
  (`index.php`, `functions.php`, template hierarchy). Block theme is preferred for a new build.
- How the mockup's inline CSS maps to `theme.json` design tokens (palette, font families, spacing scale)
  and block-pattern markup.
- Fonts: bundle Barlow / Barlow Condensed locally (via `wp_enqueue_style` or `theme.json`) rather than
  hotlinking Google Fonts, for privacy and performance.
- Relationship to `../plugins/` — themes provide layout/styling only; dynamic features
  (anniversaries, file includes) stay in their plugins. Don't duplicate plugin logic in a theme.

### Theme conventions (once `themes/` exists)

- Each theme is self-contained with its own `style.css` header block, `README.md`, and `CLAUDE.md`.
- A theme with a JS/build step (e.g. `@wordpress/scripts`) documents its commands in that theme's `CLAUDE.md`,
  mirroring `../plugins/CLAUDE.md`.
- Package for upload as a `.zip` excluding `node_modules` and dev-only files.
- Keep the originating concept folder in `concepts/` for reference; note the link in the theme's `CLAUDE.md`.

## WordPress Context

- Site URL: flc.pca.org — WordPress, self-hosted. See the repo-root `CLAUDE.md` for site-wide context
  and `bin/pull-prod.sh` for pulling production content to a Local site to preview a theme against real data.
