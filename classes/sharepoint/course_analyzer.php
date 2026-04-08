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

namespace local_sm_graphics_plugin\sharepoint;

/**
 * Scans a SharePoint course folder and classifies its contents.
 *
 * Expected folder structure:
 *   COURSE_CODE/
 *     MBZ/          -> *.mbz files
 *     SCORM/        -> *.zip SCORM packages
 *     PDF/          -> *.pdf documents
 *     Documentos*   -> supplementary PDFs/PPTXs
 *     Evaluaciones* -> AIKEN/GIFT question files (.txt)
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_analyzer {

    /** @var string|null Last error message for diagnostics. */
    private static ?string $last_error = null;

    /**
     * Get the last error message.
     * @return string|null
     */
    public static function get_last_error(): ?string {
        return self::$last_error;
    }

    /**
     * Subfolder classification rules: needle => type.
     *
     * Matched with stripos() so any subfolder whose name CONTAINS the
     * needle (case-insensitive) is classified — that catches real-world
     * variants like "MBZ anterior a v4.0", "SCORM (en castellano)",
     * "CONECTORES", "CONECTOR Articulate", "Documentos PDF", etc.
     * Order matters: first match wins, so put more specific keys before
     * generic ones.
     */
    private const FOLDER_RULES = [
        'mbz'         => 'mbz',
        'scorm'       => 'scorm',
        'conector'    => 'scorm',  // CONECTOR / CONECTORES → SCORM packages.
        'pdf'         => 'pdf',
        'documentos'  => 'documents',
    ];

    /**
     * Analyze a SharePoint folder and return a classified manifest.
     *
     * @param string $sharepointurl Browser URL of the SharePoint folder.
     * @return array|null Manifest array or null on failure.
     */
    public static function analyze(string $sharepointurl): ?array {
        // Check credentials first.
        if (!client::is_configured()) {
            self::$last_error = 'Credenciales de SharePoint no configuradas. Ve a Administracion del sitio > Plugins > SM Graphic Layer.';
            return null;
        }

        $location = client::parse_sharepoint_url($sharepointurl);
        if ($location === null) {
            $clienterr = client::get_last_error();
            self::$last_error = 'No se pudo parsear la URL. ' . ($clienterr ?? 'URL recibida: ' . $sharepointurl);
            return null;
        }

        $items = client::list_folder($location['site_id'], $location['drive_id'], $location['item_path']);
        if ($items === null) {
            $clienterr = client::get_last_error();
            self::$last_error = 'No se pudo listar la carpeta (ruta: ' . $location['item_path'] . '). '
                . ($clienterr ?? '');
            return null;
        }

        // Derive folder name from the path.
        $pathparts = explode('/', trim($location['item_path'], '/'));
        $foldername = end($pathparts) ?: 'Unknown';

        $manifest = [
            'folder_name'        => $foldername,
            'site_id'            => $location['site_id'],
            'drive_id'           => $location['drive_id'],
            'base_path'          => $location['item_path'],
            'mbz'                => [],
            'scorm'              => [],
            'pdf'                => [],
            'documents'          => [],
            'evaluations_aiken'  => [],
            'evaluations_gift'   => [],
            'warnings'           => [],
        ];

        foreach ($items as $item) {
            if ($item['is_folder']) {
                self::classify_subfolder($item, $location, $manifest);
            } else {
                // Real-world course folders sometimes drop the .mbz / .pdf
                // straight at the root with no MBZ/ wrapper. Catch those.
                self::classify_root_file($item, $manifest);
            }
        }

        // Validate the manifest.
        if (empty($manifest['mbz'])) {
            $manifest['warnings'][] = 'No se encontro ningun archivo MBZ en la carpeta.';
        } else if (count($manifest['mbz']) > 1) {
            $manifest['warnings'][] = 'Se encontraron multiples archivos MBZ. Se usara el primero.';
        }

        if (empty($manifest['scorm'])) {
            $manifest['warnings'][] = 'No se encontraron paquetes SCORM.';
        }

        return $manifest;
    }

    /**
     * Classify a subfolder and add its relevant files to the manifest.
     *
     * @param array $folder Folder item from list_folder.
     * @param array $location Parsed SharePoint location.
     * @param array &$manifest Manifest to populate.
     */
    private static function classify_subfolder(array $folder, array $location, array &$manifest): void {
        $name = $folder['name'];
        $type = null;

        // Match against known folder needles (substring, case-insensitive).
        foreach (self::FOLDER_RULES as $needle => $t) {
            if (stripos($name, $needle) !== false) {
                $type = $t;
                break;
            }
        }

        // Handle evaluation folders (may be combined AIKEN-GIFT).
        if ($type === null && stripos($name, 'evaluaciones') !== false) {
            $type = 'evaluations_mixed';
        }

        if ($type === null) {
            // Unknown subfolder - skip.
            return;
        }

        // List files inside this subfolder.
        $subpath = rtrim($location['item_path'], '/') . '/' . $folder['name'];
        $files = client::list_folder($location['site_id'], $location['drive_id'], $subpath);
        if ($files === null) {
            return;
        }

        foreach ($files as $file) {
            // Skip subfolders (e.g. "MBZ anterior a v4.0").
            if ($file['is_folder']) {
                // For MBZ folders, skip "anterior" subfolders.
                continue;
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filedata = [
                'name'    => $file['name'],
                'item_id' => $file['id'],
                'size'    => $file['size'],
            ];

            switch ($type) {
                case 'mbz':
                    if ($ext === 'mbz') {
                        $manifest['mbz'][] = $filedata;
                    }
                    break;

                case 'scorm':
                    if ($ext === 'zip') {
                        $manifest['scorm'][] = $filedata;
                    }
                    break;

                case 'pdf':
                    if ($ext === 'pdf') {
                        $manifest['pdf'][] = $filedata;
                    }
                    break;

                case 'documents':
                    if (in_array($ext, ['pdf', 'pptx', 'docx'])) {
                        $manifest['documents'][] = $filedata;
                    }
                    break;

                case 'evaluations_mixed':
                    if ($ext === 'txt') {
                        $evaltype = self::classify_evaluation_file($file['name']);
                        $manifest[$evaltype][] = $filedata;
                    }
                    break;
            }
        }
    }

    /**
     * Classify a file that lives at the ROOT of the course folder (no
     * MBZ/SCORM/PDF wrapper subfolder). Useful when a course author dumps
     * the .mbz straight at the root.
     *
     * @param array $file File item from list_folder.
     * @param array &$manifest Manifest to populate.
     */
    private static function classify_root_file(array $file, array &$manifest): void {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filedata = [
            'name'    => $file['name'],
            'item_id' => $file['id'],
            'size'    => $file['size'],
        ];
        switch ($ext) {
            case 'mbz':
                $manifest['mbz'][] = $filedata;
                break;
            case 'pdf':
                $manifest['pdf'][] = $filedata;
                $manifest['documents'][] = $filedata;
                break;
            case 'pptx':
            case 'docx':
                $manifest['documents'][] = $filedata;
                break;
            case 'zip':
                // SCORM packages at root are uncommon but accept them.
                $manifest['scorm'][] = $filedata;
                break;
            case 'txt':
                $evaltype = self::classify_evaluation_file($file['name']);
                $manifest[$evaltype][] = $filedata;
                break;
        }
    }

    /**
     * Classify an evaluation text file as AIKEN or GIFT based on its filename.
     *
     * Convention: filenames containing "AIKEN" go to aiken, those containing
     * "GIFT" or "DESARROLLO" go to gift.
     *
     * @param string $filename The filename to classify.
     * @return string 'evaluations_aiken' or 'evaluations_gift'.
     */
    private static function classify_evaluation_file(string $filename): string {
        $upper = strtoupper($filename);

        if (strpos($upper, 'AIKEN') !== false) {
            return 'evaluations_aiken';
        }

        if (strpos($upper, 'GIFT') !== false || strpos($upper, 'DESARROLLO') !== false) {
            return 'evaluations_gift';
        }

        // Default to AIKEN for unclassifiable .txt files.
        return 'evaluations_aiken';
    }
}
