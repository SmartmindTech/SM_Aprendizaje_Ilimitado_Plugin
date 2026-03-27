# SmartMind Theme for Moodle

Custom Moodle theme based on Boost, built for the SmartMind platform.

## Development Setup

### Enable live SCSS reloading

Add this line to your **site-wide** `config.php` (at the Moodle root, NOT the theme's config.php):

```php
$CFG->themedesignermode = true;
```

This makes Moodle recompile SCSS on every page load. Save a file, refresh the browser, and see your changes immediately.

**Remove this line before deploying to production** — it disables SCSS caching and slows down page loads.

### Purge caches manually (if not using theme designer mode)

Site Administration > Development > Purge all caches

## Project Structure

```
smartmind/
  config.php              — Theme configuration (layouts, features)
  lib.php                 — Theme PHP functions (SCSS callbacks, file serving)
  settings.php            — Admin settings (brand colour, presets, background images)
  version.php             — Theme version

  layout/                 — PHP layout files (one per page type)
    drawers.php           — Main layout (used by 11 of 19 page types)
    mydashboard.php       — Custom dashboard layout (/my/)
    login.php             — Login page
    columns1.php          — Single column (popups)
    embedded.php          — Embedded/iframe content
    maintenance.php       — Maintenance mode
    secure.php            — Secure exam browser

  templates/              — Mustache templates (HTML structure)
    drawers.mustache      — Main page template (shared by most layouts)
    mydashboard.mustache  — Custom dashboard template (/my/)
    navbar.mustache       — Top navigation bar
    footer.mustache       — Page footer
    login.mustache        — Login page
    core/                 — Core Moodle template overrides
    core_form/            — Form element overrides
    core_course/          — Course template overrides (course cards)
    block_myoverview/     — Course overview block override
    block_timeline/       — Timeline block override
    block_recentlyaccessedcourses/  — Recently accessed courses override
    block_recentlyaccesseditems/    — Recently accessed items override
    calendar/             — Calendar template overrides

  scss/
    moodle.scss           — Main SCSS entry point (imports base + overrides)
    moodle/               — Base Boost SCSS (60+ files — avoid editing directly)
    smartmind/            — SmartMind overrides (edit these instead)
      _index.scss         — Imports all override files
      _variables.scss     — Custom variables (colours, fonts, spacing)
      _layout.scss        — Page structure
      _navbar.scss        — Top navbar
      _navigation.scss    — Secondary/tertiary nav, breadcrumbs, moremenu
      _drawers.scss       — Left/right sidebars
      _footer.scss        — Footer
      _blocks.scss        — All blocks (calendar, timeline, course overview, etc.)
      _calendar.scss      — Calendar-specific
      _course.scss        — Course pages
      _modules.scss       — Activity modules (quiz, forum, assign, etc.)
      _forms.scss         — Forms, tables, buttons, modals, toasts, icons, popovers
      _login.scss         — Login page
      _dashboard.scss     — Dashboard-only styles (scoped to /my/)
      _utilities.scss     — Utility classes (.smartmind-hidden, etc.)
    bootstrap/            — Bootstrap 5 framework (don't edit)
    fontawesome/          — FontAwesome icons (don't edit)
    preset/               — Colour presets (default.scss, plain.scss)

  classes/                — PHP classes
    output/core_renderer.php  — Custom renderer overrides
    boostnavbar.php           — Navbar manipulation

  lang/                   — Language strings (en/, es/, pt_br/)
  amd/                    — JavaScript modules (AMD)
  pix/                    — Images and icons
  style/                  — Compiled CSS output
  tests/                  — Unit and Behat tests
```

## How to Customise

### Styles (SCSS)

Edit files in `scss/smartmind/` — never edit the base files in `scss/moodle/` directly. The override files load after the base styles, so your rules win via CSS cascade.

Each file has empty rule blocks for every major selector. Just type inside the braces:

```scss
// In scss/smartmind/_navbar.scss
.navbar.fixed-top {
    background-color: #1a1a2e;
}
```

### Templates (Mustache)

To override any Moodle core template, copy it into `templates/{plugin_name}/`:

| To override... | Copy from | To |
|---|---|---|
| A core template | `lib/templates/foo.mustache` | `templates/core/foo.mustache` |
| A course template | `course/templates/foo.mustache` | `templates/core_course/foo.mustache` |
| A block template | `blocks/myoverview/templates/foo.mustache` | `templates/block_myoverview/foo.mustache` |
| A calendar template | `calendar/templates/foo.mustache` | `templates/calendar/foo.mustache` |
| A module template | `mod/forum/templates/foo.mustache` | `templates/mod_forum/foo.mustache` |

### Page-specific styles

Use Moodle body classes to scope styles to a single page:

```scss
body.pagelayout-mydashboard { }   // Dashboard only
body.pagelayout-frontpage { }     // Front page only
body.pagelayout-course { }        // Course pages only
body.pagelayout-login { }         // Login page only
body.path-mod-quiz { }            // Quiz pages only
body#page-my-index { }            // Dashboard by page ID
```

## Reference Documentation

- `MOODLE_PAGES_MAP.md` — Complete page-by-page guide + component-to-file index
- `MOODLE_PAGES_MAP_ES.md` — Spanish version
- `MOODLE_PAGES_MAP_PT_BR.md` — Portuguese (BR) version
