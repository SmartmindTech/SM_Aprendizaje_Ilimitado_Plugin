# Complete Guide: SmartMind Moodle Plugin + Theme

**Two components, two repos:**
- `local_sm_graphics_plugin` — functionality (enrollment API, custom pages, settings, updates)
- `theme_smartmind` — visuals (Boost child theme with sidebar, custom layouts, SCSS) — **separate repo:** [SmartmindTech/SM_Theme_Moodle](https://github.com/SmartmindTech/SM_Theme_Moodle)

The `theme_smartmind/` directory in this repo is a **symlink** pointing to the Moodle theme installation folder (`/mnt/c/.../moodle/theme/smartmind/`). Theme development happens in the other repo. On plugin install/upgrade, `db/install.php` copies the theme files to `/theme/smartmind/` and activates it. For production ZIP builds, the symlink is followed and theme files are included.

**Audience:** Developer with no PHP/frontend experience

---

## 1. Architecture Overview

```
local_sm_graphics_plugin (local plugin)     theme_smartmind (Boost child theme)
├── Enrollment API                          ├── Inherits all Boost styles + layouts
├── Custom pages (welcome, etc.)            ├── Sidebar navigation
├── Admin settings (colors, logo, toggle)   ├── Portfolio layout (clean, no blocks)
├── Auto-deploys theme on install/upgrade   ├── SCSS overrides (navbar color, hide elements)
└── Update mechanism (GitHub)               └── Deployed automatically by the plugin
```

**How they connect:**
- Plugin pages call `$PAGE->set_pagelayout('portfolio')` → Moodle uses the theme's `layout/portfolio.php`
- Page content templates live in the plugin; layout/visual templates live in the theme
- Theme inherits Boost's full SCSS, then appends `custom.scss` as overrides
- Moodle auto-compiles theme SCSS — no manual compilation needed

---

## 2. Project File Map

```
SM_Moodle_Graphic_Layer_Plugin/
│
│  ── Local plugin ──────────────────────────────────────────────────────────
├── version.php                             Plugin metadata and update server URL
├── lib.php                                 Enrollment API function
├── settings.php                            Admin settings UI (colors, logo, toggle, updates)
├── update.xml                              GitHub update manifest
│
├── db/
│   ├── install.php                         Post-install: deploys + activates theme
│   └── upgrade.php                         Post-upgrade: redeploys + reactivates theme
│
├── pages/
│   └── welcome.php                         Welcome page (uses portfolio layout)
│
├── templates/
│   └── welcome_page.mustache               Welcome page content template
│
├── lang/en/
│   └── local_sm_graphics_plugin.php        All UI text strings
│
├── classes/                                (reserved for future PHP classes)
│
│  ── Theme (symlink → Moodle theme folder, separate repo) ─────────────────
├── theme_smartmind/ → /mnt/c/.../moodle/theme/smartmind/  (symlink)
│   │  Theme files are managed in SmartmindTech/SM_Theme_Moodle repo
│   │  Edits happen there, reflected here via symlink
│
│  ── Repository files ──────────────────────────────────────────────────────
├── setup.sh                                Dev setup (WSL/Linux/Mac): creates theme symlink
├── setup.bat                               Dev setup (Windows): creates theme symlink (run as Admin)
├── GUIDE.md                                This file
├── README.md                               Project overview
└── sm_graphics_plugin.zip                  Distributable ZIP (plugin + bundled theme)
```

---

## 3. How to Add a New Page

**Step 1 — Create the PHP page** `pages/your_page.php`:

```php
<?php
require_once(__DIR__ . '/../../../config.php');
require_login();

global $CFG, $USER, $OUTPUT, $PAGE;

$PAGE->set_url(new moodle_url('/local/sm_graphics_plugin/pages/your_page.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('your_page_title', 'local_sm_graphics_plugin'));
$PAGE->set_heading(get_string('your_page_heading', 'local_sm_graphics_plugin'));
$PAGE->set_pagelayout('portfolio'); // Uses theme's clean layout with sidebar

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sm_graphics_plugin/your_page', [
    'username' => fullname($USER),
    'siteurl'  => $CFG->wwwroot,
    // Add any data your template needs here.
]);
echo $OUTPUT->footer();
```

**Step 2 — Create the template** `templates/your_page.mustache`:

```mustache
<div class="p-4">
    <h2>Hello, {{ username }}!</h2>
    <p>Your page content here.</p>
    <a href="{{ siteurl }}/my/" class="btn btn-primary">Dashboard</a>
</div>
```

**Step 3 — Add language strings** to `lang/en/local_sm_graphics_plugin.php`:

```php
$string['your_page_title']   = 'Your Page';
$string['your_page_heading'] = 'Your Page Heading';
```

**Step 4 — Deploy and purge caches.**

---

## 4. How to Edit Existing Pages

- **Change HTML structure:** Edit the `.mustache` template file
- **Change data passed to template:** Edit the `.php` page file (the array passed to `render_from_template`)
- **Always purge caches** after template changes

---

## 5. How to Edit the Theme (Sidebar, Styles, Layouts)

The theme is in a **separate repo** ([SmartmindTech/SM_Theme_Moodle](https://github.com/SmartmindTech/SM_Theme_Moodle)). All theme edits (sidebar, SCSS, layouts) happen there.

The `theme_smartmind/` symlink in this repo points to the Moodle theme folder, so changes from the theme repo are immediately visible here.

Always purge caches after changes.

---

## 6. How to Hide Moodle Elements

Add CSS rules to `theme_smartmind/scss/custom.scss`:

```scss
// Hide a specific block
.block_data_privacy { display: none !important; }

// Hide a nav item
.nav-item.specific-class { display: none !important; }
```

Purge caches after SCSS changes — Moodle recompiles automatically.

---

## 7. How to Change Colors/Styles

Edit `theme_smartmind/scss/custom.scss`. No manual compilation needed — Moodle handles it.

```scss
// Change navbar color
.navbar { background-color: #your-color !important; }

// Change sidebar background
.sm-sidebar { background-color: #your-color; }
```

Purge caches after changes.

---

## 8. How to Add a Custom Field to the Course Edit Form

This is the pattern used for the **course pricing** feature and serves as a reference
for any future field you need to inject into "Create course" / "Edit course".

The flow has **four layers** that you always repeat in the same order:

```
1. DATABASE     → Where the value is stored
2. HOOKS        → How the field appears in the form
3. OBSERVER     → How the value is saved after submit
4. LANG STRINGS → Labels that the user sees
```

---

### 8.1 Layer 1 — Database table (`db/install.xml`)

Define a table that links to `course.id`. Moodle reads this file on install
and creates the table automatically.

```xml
<!-- db/install.xml -->
<TABLE NAME="local_smgp_course_pricing" COMMENT="Course pricing">
  <FIELDS>
    <FIELD NAME="id"           TYPE="int"    LENGTH="10"  NOTNULL="true" SEQUENCE="true"/>
    <FIELD NAME="courseid"     TYPE="int"    LENGTH="10"  NOTNULL="true"/>
    <FIELD NAME="amount"       TYPE="number" LENGTH="10"  DECIMALS="2" NOTNULL="true" DEFAULT="0"/>
    <FIELD NAME="currency"     TYPE="char"   LENGTH="3"   NOTNULL="true" DEFAULT="EUR"/>
    <FIELD NAME="timecreated"  TYPE="int"    LENGTH="10"  NOTNULL="true" DEFAULT="0"/>
    <FIELD NAME="timemodified" TYPE="int"    LENGTH="10"  NOTNULL="true" DEFAULT="0"/>
  </FIELDS>
  <KEYS>
    <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
  </KEYS>
  <INDEXES>
    <INDEX NAME="courseid_unique" UNIQUE="true" FIELDS="courseid"/>
  </INDEXES>
</TABLE>
```

**Key rules:**
- Table names must start with `local_smgp_` (your plugin's frankenstyle prefix, abbreviated).
- `courseid` with a unique index = one row per course.
- Always include `timecreated` / `timemodified` — Moodle convention.

If the plugin is already installed and you add a new table, you also need
`db/upgrade.php` (see section 8.5).

---

### 8.2 Layer 2 — Hook into the course form (`db/hooks.php` + callback class)

Moodle fires three hooks when rendering the course edit form:

| Hook | When it fires | What you do |
|------|--------------|-------------|
| `after_form_definition` | Form is being built | **Add your fields** |
| `after_form_definition_after_data` | Course data loaded into the form | **Load saved values** into your fields |
| `after_form_validation` | User clicks Save | **Validate** your fields |

**Step A — Register the hooks** in `db/hooks.php`:

```php
// db/hooks.php
$callbacks = [
    [
        'hook'     => core_course\hook\after_form_definition::class,
        'callback' => local_sm_graphics_plugin\hook\course_form_handler::class . '::add_pricing_fields',
    ],
    [
        'hook'     => core_course\hook\after_form_definition_after_data::class,
        'callback' => local_sm_graphics_plugin\hook\course_form_handler::class . '::load_pricing_data',
    ],
    [
        'hook'     => core_course\hook\after_form_validation::class,
        'callback' => local_sm_graphics_plugin\hook\course_form_handler::class . '::validate_pricing',
    ],
];
```

**Step B — Write the callback class** `classes/hook/course_form_handler.php`:

```php
namespace local_sm_graphics_plugin\hook;

class course_form_handler {

    // --- HOOK 1: Add the fields -------------------------------------------
    public static function add_pricing_fields(
        \core_course\hook\after_form_definition $hook
    ): void {
        $mform = $hook->mform;

        // Section header.
        $mform->addElement('header', 'smgp_pricing_header',
            get_string('pricing_header', 'local_sm_graphics_plugin'));

        // Text input for price.
        $mform->addElement('text', 'smgp_price',
            get_string('pricing_amount', 'local_sm_graphics_plugin'));
        $mform->setType('smgp_price', PARAM_FLOAT);
        $mform->setDefault('smgp_price', '0.00');
        $mform->addHelpButton('smgp_price', 'pricing_amount', 'local_sm_graphics_plugin');

        // Dropdown for currency.
        $currencies = ['EUR' => 'EUR (€)', 'USD' => 'USD ($)', 'GBP' => 'GBP (£)'];
        $mform->addElement('select', 'smgp_currency',
            get_string('pricing_currency', 'local_sm_graphics_plugin'), $currencies);
        $mform->setDefault('smgp_currency', 'EUR');
    }

    // --- HOOK 2: Load existing data when editing --------------------------
    public static function load_pricing_data(
        \core_course\hook\after_form_definition_after_data $hook
    ): void {
        global $DB;
        $mform    = $hook->mform;
        $courseid = $mform->getElementValue('id');

        if (empty($courseid)) {
            return; // New course — nothing to load.
        }

        $pricing = $DB->get_record('local_smgp_course_pricing', ['courseid' => $courseid]);
        if ($pricing) {
            $mform->setDefault('smgp_price', format_float($pricing->amount, 2));
            $mform->setDefault('smgp_currency', $pricing->currency);
        }
    }

    // --- HOOK 3: Validate before saving -----------------------------------
    public static function validate_pricing(
        \core_course\hook\after_form_validation $hook
    ): void {
        $data = $hook->get_data();
        if (!isset($data['smgp_price'])) {
            return;
        }
        $price = unformat_float($data['smgp_price']);
        if ($price !== null && $price < 0) {
            $hook->add_errors([
                'smgp_price' => get_string('pricing_error_negative', 'local_sm_graphics_plugin'),
            ]);
        }
    }
}
```

**Autoloading:** Moodle autoloads classes by namespace → path.
`local_sm_graphics_plugin\hook\course_form_handler` maps to
`classes/hook/course_form_handler.php`. No `require` needed.

---

### 8.3 Layer 3 — Save the value via an event observer

The course form calls `create_course()` / `update_course()` which only save
standard `mdl_course` fields. Your custom fields are ignored. You catch them
with an **event observer** that fires right after the course is saved.

**Step A — Register the observer** in `db/events.php`:

```php
// db/events.php
$observers = [
    [
        'eventname' => '\core\event\course_created',
        'callback'  => 'local_sm_graphics_plugin\observer::course_saved',
    ],
    [
        'eventname' => '\core\event\course_updated',
        'callback'  => 'local_sm_graphics_plugin\observer::course_saved',
    ],
];
```

**Step B — Write the observer** `classes/observer.php`:

```php
namespace local_sm_graphics_plugin;

class observer {

    public static function course_saved(\core\event\base $event): void {
        global $DB;

        $courseid = $event->courseid;

        // Read submitted pricing values from the form POST.
        $price    = optional_param('smgp_price', null, PARAM_RAW);
        $currency = optional_param('smgp_currency', null, PARAM_ALPHA);

        // If pricing fields were not in the POST, bail out.
        // This prevents interference with CLI, restore, or API course creation.
        if ($price === null) {
            return;
        }

        $amount   = unformat_float($price) ?? 0.0;
        $currency = in_array($currency, ['EUR', 'USD', 'GBP']) ? $currency : 'EUR';
        $now      = time();

        $existing = $DB->get_record('local_smgp_course_pricing', ['courseid' => $courseid]);

        if ($existing) {
            $existing->amount       = $amount;
            $existing->currency     = $currency;
            $existing->timemodified = $now;
            $DB->update_record('local_smgp_course_pricing', $existing);
        } else {
            $record = new \stdClass();
            $record->courseid     = $courseid;
            $record->amount       = $amount;
            $record->currency     = $currency;
            $record->timecreated  = $now;
            $record->timemodified = $now;
            $DB->insert_record('local_smgp_course_pricing', $record);
        }
    }
}
```

**Why `optional_param` instead of the event data?**
Moodle events carry the course object, not the full form submission.
`optional_param()` reads from `$_POST`, which still contains our custom
fields at the moment the event fires (same HTTP request).

---

### 8.4 Layer 4 — Language strings

Every user-visible label must be a lang string. Add them to both language files:

```php
// lang/en/local_sm_graphics_plugin.php
$string['pricing_header']         = 'Pricing';
$string['pricing_amount']         = 'Price';
$string['pricing_amount_help']    = 'Set the course price. Use 0 for free courses.';
$string['pricing_currency']       = 'Currency';
$string['pricing_error_negative'] = 'The price cannot be negative.';

// lang/es/local_sm_graphics_plugin.php
$string['pricing_header']         = 'Precio';
$string['pricing_amount']         = 'Precio';
$string['pricing_amount_help']    = 'Establece el precio del curso. Usa 0 para cursos gratuitos.';
$string['pricing_currency']       = 'Moneda';
$string['pricing_error_negative'] = 'El precio no puede ser negativo.';
```

---

### 8.5 Activating the feature

**On first install:** Moodle reads `db/install.xml` and creates the table.
It reads `db/hooks.php` and `db/events.php` and registers everything. Done.

**On an existing install (upgrade):** You must:

1. **Bump the version** in `version.php`:
   ```php
   $plugin->version = 2026031700; // New version number.
   ```

2. **Add the table creation to `db/upgrade.php`:**
   ```php
   function xmldb_local_sm_graphics_plugin_upgrade($oldversion) {
       global $DB;
       $dbman = $DB->get_manager();

       if ($oldversion < 2026031700) {
           // Create pricing table.
           $table = new xmldb_table('local_smgp_course_pricing');
           $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
           $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
           $table->add_field('amount', XMLDB_TYPE_NUMBER, '10,2', null, XMLDB_NOTNULL, null, '0');
           $table->add_field('currency', XMLDB_TYPE_CHAR, '3', null, XMLDB_NOTNULL, null, 'EUR');
           $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
           $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
           $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
           $table->add_index('courseid_unique', XMLDB_INDEX_UNIQUE, ['courseid']);

           if (!$dbman->table_exists($table)) {
               $dbman->create_table($table);
           }

           upgrade_plugin_savepoint(true, 2026031700, 'local', 'sm_graphics_plugin');
       }

       // ... existing upgrade steps ...
       return true;
   }
   ```

3. **Visit** `/admin/index.php` — Moodle detects the version bump and runs the upgrade.

---

### 8.6 Reading the price from the theme (catalogue card)

The theme can read pricing data to display it in the course catalogue.
In the layout PHP (e.g. `layout/drawers.php`), when building the template context:

```php
// Inside the foreach ($courses as $course) loop:
$pricing = $DB->get_record('local_smgp_course_pricing', ['courseid' => $course->id]);
$allcourses[] = [
    'fullname'  => format_string($course->fullname),
    'price'     => $pricing ? format_float($pricing->amount, 2) : null,
    'currency'  => $pricing ? $pricing->currency : null,
    'hasprice'  => $pricing && $pricing->amount > 0,
    'isfree'    => !$pricing || $pricing->amount == 0,
    // ... other fields ...
];
```

Then in the Mustache template (`catalogue_card.mustache`):

```mustache
{{#hasprice}}
    <span class="course-card__price">{{currency}} {{price}}</span>
{{/hasprice}}
{{#isfree}}
    <span class="course-card__price course-card__price--free">{{#str}}free, local_sm_graphics_plugin{{/str}}</span>
{{/isfree}}
```

---

### 8.7 Summary: files involved per feature

| What | File | Purpose |
|------|------|---------|
| Table definition | `db/install.xml` | Columns, keys, indexes |
| Table creation on upgrade | `db/upgrade.php` | Runs for existing installs |
| Hook registration | `db/hooks.php` | Tells Moodle which callbacks to fire |
| Event registration | `db/events.php` | Tells Moodle which events to observe |
| Form injection + loading | `classes/hook/course_form_handler.php` | Adds fields, loads values, validates |
| Saving after submit | `classes/observer.php` | Reads POST, writes to DB |
| Labels | `lang/{en,es}/local_sm_graphics_plugin.php` | User-visible text |
| Version bump | `version.php` | Triggers upgrade |
| Display in theme | Theme's layout `.php` + `.mustache` | Reads DB, renders in HTML |

---

### 8.8 Replicating this pattern for a new field

To add a completely new custom field to courses (e.g. "difficulty level"):

1. Add a column to the table in `install.xml` (or create a new table)
2. Add the upgrade step in `upgrade.php`
3. Add `addElement()` in hook 1, `setDefault()` in hook 2, validation in hook 3
4. Add `optional_param()` + `$DB->update_record()` in the observer
5. Add lang strings
6. Bump version
7. Deploy + upgrade

The structure is always the same. Copy the pricing implementation and rename.

---

## 9. Deployment

### Deploy to Docker (development)

```bash
cd ~/SM_Moodle_Graphic_Layer_Plugin && \
sudo docker cp . iomad_app:/var/www/html/local/sm_graphics_plugin/ && \
sudo docker exec iomad_app php /var/www/html/admin/cli/upgrade.php --non-interactive && \
sudo docker exec iomad_app php /var/www/html/admin/cli/purge_caches.php
```

The `upgrade.php` step triggers `db/upgrade.php`, which automatically copies `theme_smartmind/` to `/theme/smartmind/` and activates it. No need to copy the theme separately.

### Production (ZIP upload)

1. Upload `sm_graphics_plugin.zip` via Site Administration → Plugins → Install plugins
2. Follow the upgrade wizard — the theme is deployed and activated automatically

### When to run upgrade vs purge caches

- **Version bump / new files:** Run `upgrade.php` + `purge_caches.php`
- **Template or SCSS only:** Run `purge_caches.php` only

---

## 10. Development Workflow

```
Change a page template     → purge caches → refresh browser
Change a page PHP file     → purge caches → refresh browser
Change theme SCSS          → purge caches → refresh browser (Moodle recompiles)
Change sidebar template    → purge caches → refresh browser
Add a new page             → deploy + upgrade → purge caches → refresh
```

**Purge caches:**
```bash
sudo docker exec iomad_app php /var/www/html/admin/cli/purge_caches.php
```
Or via UI: Site Administration → Development → Purge all caches

---

## 11. Releases and Auto-Update

The plugin uses a GitHub-hosted `update.xml` to notify Moodle admins of new versions.

### When releasing a new version:

1. Edit `version.php` — increment `$plugin->version` (YYYYMMDDXX) and `$plugin->release`
2. Edit `update.xml` — update `<version>`, `<release>`, `<download>`, and `<releasenotes>`
3. Create a GitHub Release at the matching tag with the plugin ZIP attached
4. Push `version.php` and `update.xml` to the `devPaulo` branch

Moodle periodically fetches `update.xml` from the GitHub raw URL. If the version there is higher than what is installed, Moodle shows "Update available" in Site Administration.
