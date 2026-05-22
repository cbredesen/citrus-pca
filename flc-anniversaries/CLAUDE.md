# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A WordPress plugin that reads a CSV file of club members (name, join date) and renders a page listing all anniversaries for the current month where the years of membership are divisible by 5 (5, 10, 15, 20, etc.).

## Architecture

This is a pure PHP WordPress plugin — no Node.js, no build step, no bundler. All assets are plain PHP, HTML, and CSS.

**Expected structure:**
- Main plugin file (e.g., `flc-anniversaries.php`) — WordPress plugin header + shortcode registration
- CSV data file — two columns: `name`, `join_date`
- Template/render logic — filters members whose anniversary year % 5 === 0 and whose anniversary month matches the current month, then groups by anniversary year
- CSS — responsive multi-column layout; each anniversary-year group is a visually distinct section

## Key Requirements

- **Data source:** CSV with columns `name` (string) and `date` (the date the person joined the club)
- **Filter logic:** show only members where `(current_year - join_year) % 5 === 0` AND `current_month === join_month`
- **Grouping:** group results by anniversary milestone (5 years, 10 years, etc.), each in its own semantic HTML section
- **Layout:** names displayed in multiple columns that adapt to page width (CSS `column-count` or CSS Grid)
- **Output mechanism:** WordPress shortcode so editors can embed the output in any page/post

## WordPress Plugin Conventions

- Plugin header comment block in the main PHP file is required for WordPress to recognize the plugin
- Use `add_shortcode()` to register the output; avoid `the_content` filters
- Enqueue CSS via `wp_enqueue_style()` hooked to `wp_enqueue_scripts`
- The CSV file path should be defined relative to `plugin_dir_path(__FILE__)`, not a hardcoded absolute path
