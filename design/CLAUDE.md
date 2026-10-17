# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with files in this directory.

## Overview

`design/` holds visual design work for **flc.pca.org**: static HTML explorations today,
and actual WordPress themes once a direction is chosen. Nothing in here is deployed to the
site yet — these are mockups and references, not production code.

- `index.html` — static review gallery: the official palette, then thumbnails of all 8 concept
  pages linking to them. Meant to be served off flc.pca.org for client review. Hand-written, no build step.
- `Citrus Color Palette.pdf` — the club's official color palette (source of truth; see "Brand palette").
- `screenshots/` — PNGs backing the gallery (`<concept>-<index|post>.png`), captured headless
  at 1280&nbsp;px width. Regenerate whenever a concept changes.
- `concepts/` — alternate style directions exported from Claude Design. **Unvetted.** See below.
- `../design-old/` — the previous generation of home-page explorations (`flc-home-*.html`),
  kept for reference. Superseded by `concepts/`.

Planned: `themes/` — real WordPress themes, added when a concept graduates (see "Graduating a concept to a theme").

## Brand palette

Official colors from `Citrus Color Palette.pdf`. The PDF defines **CMYK only** (no hex/RGB), so the
hex values below are screen equivalents chosen by us — not official. If the club has official
RGB/hex values, they replace these.

| Name | Hex | Source (CMYK, %) | CSS token |
|---|---|---|---|
| **Primary** | | | |
| Citrus Green | `#4A864E` | spot "PCA Citrus Green" — C74 M26 Y86 K11 | `--flc-green` |
| Citrus Orange | `#DB9336` | spot "PCA Cirus Orange" (sic) — C13 M46 Y93 K1 | `--flc-orange` |
| Navy | `#424562` | process — C79 M73 Y39 K26 | `--flc-navy` |
| **Secondary** | | | |
| Rust | `#CE6423` | C20 M67 Y100 K7 | `--flc-rust` |
| Blue | `#2279C2` | C84 M50 Y0 K0 | `--flc-blue` |
| Teal | `#6DD0B9` | C54 M0 Y35 K0 | `--flc-teal` |
| Lime | `#A2D052` | C40 M0 Y93 K0 | `--flc-lime` |
| Sky | `#92CEED` | C39 M12 Y0 K0 | `--flc-sky` |

The PDF doesn't name the secondary colors; Rust/Blue/Teal/Lime/Sky are our working labels.

How the hex values were derived (so it isn't redone differently):
- **Primaries** match the existing web logo (`flc-logo.png`) pixel colors, which agree with the PDF's
  CMYK to within a few RGB steps. That keeps the page colors identical to the logo beside them.
- **Secondaries** use a standard coated-print (SWOP) CMYK→sRGB conversion (the same approximation
  pdf.js uses). macOS ColorSync's conversion comes out noticeably darker/duller (e.g. navy `#3C3E52`),
  so different viewers will show the PDF slightly differently. Don't treat the PDF on screen as the reference.

Contrast notes (WCAG AA, 4.5:1 for normal text, 3:1 for large):
- On white: Navy 9.3 ✓, Blue 4.6 ✓, Green 4.4 (large text / UI only), Rust 3.8 (large only), Orange 2.5 ✗.
- **White text on Citrus Orange is 2.5:1 and fails even for large text.** `flc-green`'s orange buttons
  and footer do this. Navy on orange is 3.7 (large/bold only). Resolve before vetting.
- Teal, Lime and Sky are fills/accents only. Never use them for text on light backgrounds.

## `concepts/` — status: needs vetting

Each subdirectory is one palette/style direction for the site, as self-contained static HTML:

| Concept | Direction | Link / accent | Primary button |
|---|---|---|---|
| `flc-white/` | Light, classic top bar, ticket-style event cards (navy date panels, sky labels) | blue | blue |
| `flc-cream/` | Warm off-white (`#FDFCFA`), editorial, condensed caps, orange rules | blue | navy |
| `flc-navy/` | Navy header/footer, full-bleed photo hero, rounded poster cards, sky + orange on navy | blue | blue |
| `flc-green/` | White header, citrus-green section bands, citrus-orange CTAs and footer | green | orange |

All color names refer to the "Brand palette" above.

Each concept contains:
- `index.html` — home page (header/nav, hero, upcoming-events grid, promos, footer)
- `post.html` — a blog post / newsletter article page
- `base.css` — shared reset + structural primitives (`.site`, `.ph`) + the official palette as `--flc-*` tokens
- `theme.css` — **the only file that differs between concepts**: palette roles, type, layout personality.
  Brand colors are mapped from the `--flc-*` tokens (e.g. `--accent: var(--flc-blue)`). Don't hard-code
  brand hex values in a theme. Neutrals (text greys, borders, page backgrounds) and light tints stay local.
- `flc-logo.png` — the club logo (identical copy in each folder)
- `img/` — real club photos (identical copy in each folder; canonical in `_base/img/`)

**`index.html`, `post.html` and `base.css` are byte-identical across every concept.**
The canonical source lives in `concepts/_base/`; the per-concept folders hold copies so each
concept stays independently viewable (paths are relative). When you change a base file, edit
`_base/` then re-copy to all four concepts, e.g.:

```bash
cd concepts
for d in flc-white flc-cream flc-navy flc-green; do
  cp _base/index.html _base/post.html _base/base.css "$d"/
  rm -rf "$d/img" && cp -R _base/img "$d/img"
done
```

`_base/` is not itself a concept (no `theme.css`); don't add it to the table or the gallery.

Shared design language across all four:
- Fonts: **Barlow** (body) + **Barlow Condensed** (headings), loaded from Google Fonts (in the HTML `<head>`)
- ~1180px centered content column
- CSS is split into `base.css` (shared) + `theme.css` (per concept), linked from the `<head>`;
  no inline `style=""`, no `<style>` block, no build step
- Photos are real club photos from the club's own Flickr (flickr.com/photos/citruspca, club-owned
  copyright), downsized to 1600px wide in `img/`: `paddock.jpg` (home hero), `autocross.jpg` (post
  lead; also a `flc-green` event tile), and `cayman.jpg` / `drivers-meeting.jpg` / `de-track.jpg`
  (`flc-green` event tiles, set in its `theme.css`). They're `<img class="… photo">` (`object-fit: cover`).
  The author avatar is still a `.ph` placeholder, so no real member's face sits next to a fictional byline.
- Copy and event data are plausible-looking filler, not real club content
- The shared markup gives each concept the same DOM; `theme.css` may hide elements it doesn't use
  (e.g. `flc-navy` hides the hero lead paragraph, `flc-green` hides event dates) via `display:none`

### Vetting checklist

Before a concept is considered vetted / ready to pitch to the client:

- [ ] **Accessibility** — color contrast (WCAG AA), focus states, real heading hierarchy,
      `alt` text plan, keyboard nav for dropdown menus
- [ ] **Responsive** — each `theme.css` has starter `@media` breakpoints; sanity-check tablet/mobile
      and verify no horizontal scroll below ~380px
- [ ] **Content fit** — does the real site's IA fit? (Events, About, The Spiel/newsletter, Store & Classifieds,
      Membership). Nav labels/casing now come from the shared markup — confirm they match the real IA.
- [ ] **Brand** — confirm logo usage, PCA/Zone 12 references, and the screen hex values in "Brand palette"
      (the PDF is CMYK-only) are correct and approved
- [ ] **Assets** — decide real image treatment; the logo is a low-res PNG, may need SVG / higher-res
- [ ] **Feasibility** — map each layout to how it would be built in WordPress (block patterns vs. custom template)

Record vetting outcomes here or in a per-concept note so the review isn't repeated.
Open suggestions (contrast, layout, photo issues) are tracked in `concept-notes.md`.

## Graduating a concept to a theme

When a direction is chosen, it becomes a real theme under `themes/<theme-name>/`. Decide up front:

- **Block theme** (`theme.json` + block templates, `templates/`, `parts/`) vs. **classic theme**
  (`index.php`, `functions.php`, template hierarchy). Block theme is preferred for a new build.
- How the concept's `theme.css` maps to `theme.json` design tokens (palette, font families, spacing scale)
  and block-pattern markup. The `--flc-*` palette in `base.css` becomes `settings.color.palette`;
  the chosen concept's `theme.css` role variables are a good starting point for the rest.
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
