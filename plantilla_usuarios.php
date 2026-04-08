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
 * Styled Excel template for bulk user upload.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/excellib.class.php');

require_login();
require_capability('moodle/site:uploadusers', context_system::instance());

$workbook = new MoodleExcelWorkbook('plantilla_usuarios');
$ws = $workbook->add_worksheet('Usuarios');

// Formats.
$fmtHeader = $workbook->add_format([
    'bold'     => 1,
    'bg_color' => '#6A0DAD',
    'color'    => 'white',
    'border'   => 1,
    'align'    => 'center',
]);
$fmtCell = $workbook->add_format(['border' => 1]);
$fmtBold = $workbook->add_format(['bold' => 1]);
$fmtNote = $workbook->add_format(['color' => '#333333']);

// Headers.
$headers = ['username', 'firstname', 'lastname', 'email'];

foreach ($headers as $col => $h) {
    $ws->write_string(0, $col, $h, $fmtHeader);
}

// Example rows.
$examples = [
    ['juan.garcia', 'Juan', 'Garcia Lopez', 'juan.garcia@ejemplo.com'],
    ['maria.rodriguez', 'Maria', 'Rodriguez Perez', 'maria.rodriguez@ejemplo.com'],
];

$row = 1;
foreach ($examples as $ex) {
    foreach ($ex as $col => $val) {
        $ws->write_string($row, $col, $val, $fmtCell);
    }
    $row++;
}

// Empty row.
$row++;

// Notes.
$notes = [
    ['Notas:', true],
    ['- Borre las filas de ejemplo antes de rellenar con sus datos', false],
];

foreach ($notes as $note) {
    $fmt = $note[1] ? $fmtBold : $fmtNote;
    $ws->write_string($row, 0, $note[0], $fmt);
    $row++;
}

// Column widths.
$ws->set_column(0, 0, 20);  // username
$ws->set_column(1, 1, 18);  // firstname
$ws->set_column(2, 2, 22);  // lastname
$ws->set_column(3, 3, 32);  // email

$workbook->close();
