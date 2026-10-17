# Concept notes — open suggestions

Issues found while applying the official palette and real photos (2026-10-17).
Not yet resolved. See `CLAUDE.md` ("Brand palette", "Vetting checklist") for context.

## Palette / brand

- [ ] **Confirm screen hex values.** `Citrus Color Palette.pdf` is CMYK-only, so the hex values in
      `CLAUDE.md` / `base.css` are our conversion: primaries match the web logo, secondaries use a SWOP
      conversion. If the club has official RGB/hex values, swap them into `_base/base.css` (`--flc-*`)
      and the gallery swatches in `index.html`.
- [ ] **Secondary color names.** Rust / Blue / Teal / Lime / Sky are our working labels; the PDF doesn't
      name them. Confirm or replace.

## Accessibility (contrast)

- [ ] **`flc-green`: white text on Citrus Orange is 2.5:1** and fails WCAG AA even for large text.
      This affects the orange buttons (`.btn`, `.nav a.cta`) and the orange footer. Options:
      - navy text on orange (3.7:1, OK for large/bold only)
      - a darker orange for button fills, keeping brand orange for accents
      - move CTAs to green or navy
- [ ] **`flc-cream`: blue links on `#FDFCFA` are 4.47:1**, just under 4.5:1. Minor; nudge the page
      background whiter or the link a touch darker.
- [ ] Citrus Green on white is 4.4:1. Fine for headings and large text, borderline for small text.

## Layout

- [ ] **`flc-navy`: the header bar stops at the 1180px column** while the article masthead (`post.html`)
      and other bands run full width. It looks broken at ≥1280px. Make `.hd` full-bleed, or keep the
      masthead within the column.
- [ ] **`flc-navy`: `.post-tags` pills are invisible.** Their `#F3F5FA` background is almost the same as
      the `#F4F6FB` page. Give them a white fill or a border.

## Photos

- [ ] **`flc-green` hero: the headline sits over the red GT3** on the right side of `paddock.jpg`. It's
      readable thanks to the overlay, but a photo with open space on the right would suit that
      layout better. Candidates are on flickr.com/photos/citruspca.
- [ ] **Author avatar is still a placeholder.** It was deliberately left that way so no real member's
      face sits next to a fictional byline. Decide on real author photos or an initials treatment.
- [ ] Photos are from a single track day (2026-10-02). For the client pitch, consider a more varied set
      (a Cars & Coffee, a social dinner) so the event tiles match their labels. The "Member Dinner
      Social" tile currently shows a drivers' meeting.
