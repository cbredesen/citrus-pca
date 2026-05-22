# FLC Anniversaries

A WordPress plugin that displays club member milestone anniversaries for the current month. Only members whose years of membership are divisible by 5 (5, 10, 15, 20, …) are shown, grouped by milestone in separate sections.

## Installation

1. Copy the plugin folder into `wp-content/plugins/`.
2. Run `npm install && npm run build` to compile the stylesheet.
3. Activate the plugin in **Plugins** in the WordPress admin.

## Configuration

Go to **Settings > FLC Anniversaries** and set the path to your roster CSV file, relative to the WordPress installation root (e.g. `wp-content/plugins/flc-anniversaries/data/roster.csv`). The settings page shows whether the file is found and readable.

## Roster CSV format

The CSV must have a header row followed by one member per row:

```
Name,Join Date
Jane Smith,3/1/2006
John Doe,3/15/2001
```

- **Name** — member's display name
- **Join Date** — date joined, in `M/D/YYYY` format

## Usage

Add the shortcode `[flc_anniversaries]` to any page or post where you want the anniversary list to appear.

## Development

```bash
npm install          # install dependencies
npm run build        # compile CSS to build/
npm run start        # watch mode
npm run lint:css     # lint stylesheet
npm run plugin:zip   # build and create deployable zip
```
