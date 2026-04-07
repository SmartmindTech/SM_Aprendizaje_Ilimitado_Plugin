<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Upgrade script — deploys and activates the SmartMind theme on every version bump.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Runs when the plugin version is bumped.
 *
 * @param int $oldversion The previous version of the plugin.
 * @return bool
 */
function xmldb_local_sm_graphics_plugin_upgrade($oldversion) {
    global $DB;

    require_once(__DIR__ . '/install.php');

    // Fail fast if the pre-built SPA is missing (e.g. someone cloned the repo
    // instead of installing from the release zip).
    local_sm_graphics_plugin_verify_frontend();

    // Theme redeploy runs on every version bump.
    local_sm_graphics_plugin_deploy_theme();
    local_sm_graphics_plugin_activate_theme();
    local_sm_graphics_plugin_deploy_lang_overrides();
    local_sm_graphics_plugin_force_theme_for_all();
    local_sm_graphics_plugin_deploy_certificate_type();
    local_sm_graphics_plugin_enable_activity_modules();

    // Safety net: ensure course_meta table exists regardless of the starting version.
    // install.xml creates it on fresh installs, but upgrades from early versions may
    // lack both course_pricing (original name) and course_meta (final name) because
    // no upgrade block ever had a CREATE TABLE for this table.
    $dbman = $DB->get_manager();
    if (!$dbman->table_exists(new xmldb_table('local_smgp_course_meta'))
            && !$dbman->table_exists(new xmldb_table('local_smgp_course_pricing'))) {
        $metatable = new xmldb_table('local_smgp_course_meta');
        $metatable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $metatable->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $metatable->add_field('amount', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, '0');
        $metatable->add_field('currency', XMLDB_TYPE_CHAR, '3', null, XMLDB_NOTNULL, null, 'EUR');
        $metatable->add_field('duration_hours', XMLDB_TYPE_NUMBER, '6, 1', null, XMLDB_NOTNULL, null, '0');
        $metatable->add_field('description', XMLDB_TYPE_TEXT);
        $metatable->add_field('course_category', XMLDB_TYPE_CHAR, '255');
        $metatable->add_field('smartmind_code', XMLDB_TYPE_CHAR, '50');
        $metatable->add_field('sepe_code', XMLDB_TYPE_CHAR, '255');
        $metatable->add_field('level', XMLDB_TYPE_CHAR, '20', null, null, null, 'beginner');
        $metatable->add_field('completion_percentage', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '100');
        $metatable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $metatable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $metatable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $metatable->add_index('courseid_unique', XMLDB_INDEX_UNIQUE, ['courseid']);
        $dbman->create_table($metatable);
    }

    if ($oldversion < 2026031303) {
        get_string_manager()->reset_caches();
    }

    if ($oldversion < 2026031800) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_smgp_comments');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('parentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('content', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
            $table->add_field('contentformat', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('positionindex', XMLDB_TYPE_INTEGER, '10');
            $table->add_field('positiontimestamp', XMLDB_TYPE_INTEGER, '10');
            $table->add_field('activityname', XMLDB_TYPE_CHAR, '255');
            $table->add_field('activitytype', XMLDB_TYPE_CHAR, '50');
            $table->add_field('replycount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

            $table->add_index('ix_courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
            $table->add_index('ix_parentid', XMLDB_INDEX_NOTUNIQUE, ['parentid']);
            $table->add_index('ix_userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
            $table->add_index('ix_cmid', XMLDB_INDEX_NOTUNIQUE, ['cmid']);
            $table->add_index('ix_course_time', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'timecreated']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026031800, 'local', 'sm_graphics_plugin');
    }

    if ($oldversion < 2026032000) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_smgp_company_limits');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('companyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('maxstudents', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('companyid_unique', XMLDB_INDEX_UNIQUE, ['companyid']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026032000, 'local', 'sm_graphics_plugin');
    }

    if ($oldversion < 2026032001) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_smgp_course_pricing');

        // Skip if table was created directly as course_meta by install.xml.
        if ($dbman->table_exists($table)) {
            $field = new xmldb_field('duration_hours', XMLDB_TYPE_NUMBER, '6, 1', null, XMLDB_NOTNULL, null, '0', 'currency');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            $field2 = new xmldb_field('sepe_category', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'duration_hours');
            if (!$dbman->field_exists($table, $field2)) {
                $dbman->add_field($table, $field2);
            }
        }

        upgrade_plugin_savepoint(true, 2026032001, 'local', 'sm_graphics_plugin');
    }

    if ($oldversion < 2026032002) {
        // Grades & Certificates page + SmartMind certificate template.
        // Certificate type deployment handled above (runs every upgrade).
        upgrade_plugin_savepoint(true, 2026032002, 'local', 'sm_graphics_plugin');
    }

    if ($oldversion < 2026032300) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_smgp_cert_codes');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('code', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('code_unique', XMLDB_INDEX_UNIQUE, ['code']);
            $table->add_index('userid_courseid_unique', XMLDB_INDEX_UNIQUE, ['userid', 'courseid']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026032300, 'local', 'sm_graphics_plugin');
    }

    if ($oldversion < 2026032400) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_smgp_course_pricing');

        // Skip if table was created directly as course_meta by install.xml.
        if ($dbman->table_exists($table)) {
            // Add new fields.
            $descfield = new xmldb_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null, 'duration_hours');
            if (!$dbman->field_exists($table, $descfield)) {
                $dbman->add_field($table, $descfield);
            }

            $catfield = new xmldb_field('course_category', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'description');
            if (!$dbman->field_exists($table, $catfield)) {
                $dbman->add_field($table, $catfield);
            }

            $smcodefield = new xmldb_field('smartmind_code', XMLDB_TYPE_CHAR, '50', null, null, null, null, 'course_category');
            if (!$dbman->field_exists($table, $smcodefield)) {
                $dbman->add_field($table, $smcodefield);
            }

            // Rename sepe_category → sepe_code.
            $sepeold = new xmldb_field('sepe_category', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'smartmind_code');
            if ($dbman->field_exists($table, $sepeold)) {
                $dbman->rename_field($table, $sepeold, 'sepe_code');
            } else {
                $sepenew = new xmldb_field('sepe_code', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'smartmind_code');
                if (!$dbman->field_exists($table, $sepenew)) {
                    $dbman->add_field($table, $sepenew);
                }
            }
        }

        upgrade_plugin_savepoint(true, 2026032400, 'local', 'sm_graphics_plugin');
    }

    if ($oldversion < 2026032401) {
        $dbman = $DB->get_manager();

        // Create categories table.
        $table = new xmldb_table('local_smgp_categories');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
            $table->add_field('image_url', XMLDB_TYPE_CHAR, '1024');
            $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('ix_sortorder', XMLDB_INDEX_NOTUNIQUE, ['sortorder']);

            $dbman->create_table($table);
        }

        // Create course↔category link table.
        $table2 = new xmldb_table('local_smgp_course_category');
        if (!$dbman->table_exists($table2)) {
            $table2->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table2->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table2->add_field('categoryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);

            $table2->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table2->add_key('fk_categoryid', XMLDB_KEY_FOREIGN, ['categoryid'], 'local_smgp_categories', ['id']);
            $table2->add_index('courseid_categoryid_unique', XMLDB_INDEX_UNIQUE, ['courseid', 'categoryid']);

            $dbman->create_table($table2);
        }

        // Seed the 17 default categories.
        $now = time();
        $categories = [
            ['name' => 'Atención e intervención social',                      'image' => 'social'],
            ['name' => 'Bienestar profesional y empresa saludable',           'image' => 'bienestar'],
            ['name' => 'Calidad y prevención de riesgos laborales',           'image' => 'calidad'],
            ['name' => 'Clientes y ventas',                                   'image' => 'ventas'],
            ['name' => 'Compliance y otras normativas aplicables a la empresa','image' => 'compliance'],
            ['name' => 'Estrategia, proyectos e innovación',                  'image' => 'estrategia'],
            ['name' => 'Finanzas y gestión empresarial',                      'image' => 'finanzas'],
            ['name' => 'Formación y educación: Life Long Learning',           'image' => 'educacion'],
            ['name' => 'IA y ciencia de datos',                               'image' => 'ia'],
            ['name' => 'Igualdad, diversidad e inclusión',                    'image' => 'igualdad'],
            ['name' => 'Inglés',                                              'image' => 'ingles'],
            ['name' => 'Microsoft',                                           'image' => 'microsoft'],
            ['name' => 'Nutrición y seguridad alimentaria',                   'image' => 'nutricion'],
            ['name' => 'Softskills',                                          'image' => 'softskills'],
            ['name' => 'Sostenibilidad y economía verde',                     'image' => 'sostenibilidad'],
            ['name' => 'Tecnología y software',                               'image' => 'tecnologia'],
            ['name' => 'Turismo, hostelería y restauración',                  'image' => 'turismo'],
        ];
        foreach ($categories as $i => $cat) {
            if (!$DB->record_exists('local_smgp_categories', ['name' => $cat['name']])) {
                $DB->insert_record('local_smgp_categories', (object) [
                    'name'         => $cat['name'],
                    'image_url'    => $cat['image'],
                    'sortorder'    => $i + 1,
                    'timecreated'  => $now,
                    'timemodified' => $now,
                ]);
            }
        }

        upgrade_plugin_savepoint(true, 2026032401, 'local', 'sm_graphics_plugin');
    }

    if ($oldversion < 2026032402) {
        // Fix: update image_url for categories that were inserted with empty image_url.
        $imagemap = [
            'Atención e intervención social'                       => 'social',
            'Bienestar profesional y empresa saludable'            => 'bienestar',
            'Calidad y prevención de riesgos laborales'            => 'calidad',
            'Clientes y ventas'                                    => 'ventas',
            'Compliance y otras normativas aplicables a la empresa'=> 'compliance',
            'Estrategia, proyectos e innovación'                   => 'estrategia',
            'Finanzas y gestión empresarial'                       => 'finanzas',
            'Formación y educación: Life Long Learning'            => 'educacion',
            'IA y ciencia de datos'                                => 'ia',
            'Igualdad, diversidad e inclusión'                     => 'igualdad',
            'Inglés'                                               => 'ingles',
            'Microsoft'                                            => 'microsoft',
            'Nutrición y seguridad alimentaria'                    => 'nutricion',
            'Softskills'                                           => 'softskills',
            'Sostenibilidad y economía verde'                      => 'sostenibilidad',
            'Tecnología y software'                                => 'tecnologia',
            'Turismo, hostelería y restauración'                   => 'turismo',
        ];
        foreach ($imagemap as $name => $image) {
            $rec = $DB->get_record('local_smgp_categories', ['name' => $name]);
            if ($rec && empty($rec->image_url)) {
                $rec->image_url = $image;
                $rec->timemodified = time();
                $DB->update_record('local_smgp_categories', $rec);
            }
        }
        upgrade_plugin_savepoint(true, 2026032402, 'local', 'sm_graphics_plugin');
    }

    if ($oldversion < 2026032404) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_smgp_course_pricing');

        // Skip if table was created directly as course_meta by install.xml.
        if ($dbman->table_exists($table)) {
            $levelfield = new xmldb_field('level', XMLDB_TYPE_CHAR, '20', null, null, null, 'beginner', 'sepe_code');
            if (!$dbman->field_exists($table, $levelfield)) {
                $dbman->add_field($table, $levelfield);
            }

            $completionfield = new xmldb_field('completion_percentage', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, '100', 'level');
            if (!$dbman->field_exists($table, $completionfield)) {
                $dbman->add_field($table, $completionfield);
            }
        }

        upgrade_plugin_savepoint(true, 2026032404, 'local', 'sm_graphics_plugin');
    }

    if ($oldversion < 2026032500) {
        $dbman = $DB->get_manager();

        $table = new xmldb_table('local_smgp_course_browsing');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('timeaccess', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('userid_courseid_unique', XMLDB_INDEX_UNIQUE, ['userid', 'courseid']);
            $table->add_index('ix_userid_time', XMLDB_INDEX_NOTUNIQUE, ['userid', 'timeaccess']);
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026032500, 'local', 'sm_graphics_plugin');
    }

    if ($oldversion < 2026032502) {
        $dbman = $DB->get_manager();

        // Rename local_smgp_course_pricing → local_smgp_course_meta.
        $oldtable = new xmldb_table('local_smgp_course_pricing');
        if ($dbman->table_exists($oldtable)) {
            $dbman->rename_table($oldtable, 'local_smgp_course_meta');
        }

        upgrade_plugin_savepoint(true, 2026032502, 'local', 'sm_graphics_plugin');
    }

    if ($oldversion < 2026032505) {
        $dbman = $DB->get_manager();

        // Ensure local_smgp_company_limits exists (may be missing on instances
        // that were installed after version 2026032000 was already set).
        $table = new xmldb_table('local_smgp_company_limits');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('companyid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('maxstudents', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('companyid_unique', XMLDB_INDEX_UNIQUE, ['companyid']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026032505, 'local', 'sm_graphics_plugin');
    }

    if ($oldversion < 2026032600) {
        $dbman = $DB->get_manager();

        // Create activity duration cache table.
        $table = new xmldb_table('local_smgp_activity_duration');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('duration_minutes', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('estimation_source', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, 'fallback');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('cmid_unique', XMLDB_INDEX_UNIQUE, ['cmid']);
            $table->add_index('ix_courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026032600, 'local', 'sm_graphics_plugin');
    }

    if ($oldversion < 2026033000) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_smgp_learning_objectives');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('objective', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
            $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_field('lang', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'es');
            $table->add_index('ix_courseid_lang_sort', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'lang', 'sortorder']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026033000, 'local', 'sm_graphics_plugin');
    }

    if ($oldversion < 2026033001) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_smgp_learning_objectives');

        // Add lang column if table was created in 2026033000 without it.
        $field = new xmldb_field('lang', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'es', 'sortorder');
        if ($dbman->table_exists($table) && !$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);

            // Replace old index with new one including lang.
            $oldindex = new xmldb_index('ix_courseid_sort', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'sortorder']);
            if ($dbman->index_exists($table, $oldindex)) {
                $dbman->drop_index($table, $oldindex);
            }
            $newindex = new xmldb_index('ix_courseid_lang_sort', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'lang', 'sortorder']);
            if (!$dbman->index_exists($table, $newindex)) {
                $dbman->add_index($table, $newindex);
            }
        }

        upgrade_plugin_savepoint(true, 2026033001, 'local', 'sm_graphics_plugin');
    }

    if ($oldversion < 2026033003) {
        // Safety net: ensure all course_meta columns and activity_duration table exist.
        $dbman = $DB->get_manager();

        $metatable = new xmldb_table('local_smgp_course_meta');
        if ($dbman->table_exists($metatable)) {
            $cols = [
                ['description',            XMLDB_TYPE_TEXT,    null,  null, null, null, null,       'duration_hours'],
                ['course_category',        XMLDB_TYPE_CHAR,    '255', null, null, null, null,       'description'],
                ['smartmind_code',         XMLDB_TYPE_CHAR,    '50',  null, null, null, null,       'course_category'],
                ['sepe_code',              XMLDB_TYPE_CHAR,    '255', null, null, null, null,       'smartmind_code'],
                ['level',                  XMLDB_TYPE_CHAR,    '20',  null, null, null, 'beginner', 'sepe_code'],
                ['completion_percentage',  XMLDB_TYPE_INTEGER, '3',   null, XMLDB_NOTNULL, null, '100', 'level'],
            ];
            foreach ($cols as $c) {
                $field = new xmldb_field($c[0], $c[1], $c[2], $c[3], $c[4], $c[5], $c[6], $c[7]);
                if (!$dbman->field_exists($metatable, $field)) {
                    $dbman->add_field($metatable, $field);
                }
            }
            // Drop old sepe_category if it still exists.
            $oldfield = new xmldb_field('sepe_category');
            if ($dbman->field_exists($metatable, $oldfield)) {
                $dbman->drop_field($metatable, $oldfield);
            }
        }

        // Ensure activity_duration table exists.
        $durtable = new xmldb_table('local_smgp_activity_duration');
        if (!$dbman->table_exists($durtable)) {
            $durtable->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $durtable->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $durtable->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $durtable->add_field('duration_minutes', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $durtable->add_field('estimation_source', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, 'fallback');
            $durtable->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $durtable->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $durtable->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $durtable->add_index('cmid_unique', XMLDB_INDEX_UNIQUE, ['cmid']);
            $durtable->add_index('ix_courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
            $dbman->create_table($durtable);
        }

        upgrade_plugin_savepoint(true, 2026033003, 'local', 'sm_graphics_plugin');
    }

    if ($oldversion < 2026033002) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_smgp_course_translations');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
            $table->add_field('lang', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL);
            $table->add_field('summary', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('courseid_lang_unique', XMLDB_INDEX_UNIQUE, ['courseid', 'lang']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026033002, 'local', 'sm_graphics_plugin');
    }

    if ($oldversion < 2026040109) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('local_smgp_sp_courses');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
            $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
            $table->add_field('web_url', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
            $table->add_field('parent_folder', XMLDB_TYPE_CHAR, '255', null, null, null, '');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('ix_name', XMLDB_INDEX_NOTUNIQUE, ['name']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026040109, 'local', 'sm_graphics_plugin');
    }

    if ($oldversion < 2026040726) {
        // Backfill default SmartMind catalogue categories for instances
        // that were first installed at a version > 2026032401 and therefore
        // skipped the original seed block above.
        if ($DB->get_manager()->table_exists('local_smgp_categories')
                && $DB->count_records('local_smgp_categories') === 0) {
            $now = time();
            $defaults = [
                ['Atención e intervención social', 'social'],
                ['Bienestar profesional y empresa saludable', 'bienestar'],
                ['Calidad y prevención de riesgos laborales', 'calidad'],
                ['Clientes y ventas', 'ventas'],
                ['Compliance y otras normativas aplicables a la empresa', 'compliance'],
                ['Estrategia, proyectos e innovación', 'estrategia'],
                ['Finanzas y gestión empresarial', 'finanzas'],
                ['Formación y educación: Life Long Learning', 'educacion'],
                ['IA y ciencia de datos', 'ia'],
                ['Igualdad, diversidad e inclusión', 'igualdad'],
                ['Inglés', 'ingles'],
                ['Microsoft', 'microsoft'],
                ['Nutrición y seguridad alimentaria', 'nutricion'],
                ['Softskills', 'softskills'],
                ['Sostenibilidad y economía verde', 'sostenibilidad'],
                ['Tecnología y software', 'tecnologia'],
                ['Turismo, hostelería y restauración', 'turismo'],
            ];
            foreach ($defaults as $i => $c) {
                $DB->insert_record('local_smgp_categories', (object) [
                    'name'         => $c[0],
                    'image_url'    => $c[1],
                    'sortorder'    => $i + 1,
                    'timecreated'  => $now,
                    'timemodified' => $now,
                ]);
            }
        }

        upgrade_plugin_savepoint(true, 2026040726, 'local', 'sm_graphics_plugin');
    }

    return true;
}
