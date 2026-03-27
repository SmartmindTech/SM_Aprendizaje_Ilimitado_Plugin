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

namespace local_sm_graphics_plugin;

defined('MOODLE_INTERNAL') || die();

/**
 * Custom update checker for SmartMind Graphic Layer Plugin.
 *
 * Fetches update information directly from GitHub update.xml.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_checker {

    /** @var string URL to the update.xml file */
    const UPDATE_URL = 'https://raw.githubusercontent.com/SmartmindTech/SM_Moodle_Graphic_Layer_Plugin/dev/update.xml';

    /** @var string Config key for cached update info */
    const CONFIG_UPDATE_INFO = 'cached_update_info';

    /** @var string Config key for last check time */
    const CONFIG_LAST_CHECK = 'last_update_check';

    /**
     * Check for updates and return info if available.
     *
     * @param bool $force Force fetch even if recently checked.
     * @return object|null Update info object or null if up to date.
     */
    public static function check(bool $force = false): ?object {
        $plugin = \core_plugin_manager::instance()->get_plugin_info('local_sm_graphics_plugin');
        if (!$plugin) {
            return null;
        }
        $currentversion = $plugin->versiondisk;

        $interval = get_config('local_sm_graphics_plugin', 'update_check_interval');
        if ($interval === false) {
            $interval = 60;
        }

        $lastcheck = get_config('local_sm_graphics_plugin', self::CONFIG_LAST_CHECK);
        $needsfetch = $force || ($lastcheck === false) || (time() - $lastcheck >= $interval);

        $cached = get_config('local_sm_graphics_plugin', self::CONFIG_UPDATE_INFO);
        $updateinfo = $cached ? json_decode($cached, true) : null;

        if ($needsfetch) {
            $fetchedinfo = self::fetch_update_info();
            if ($fetchedinfo) {
                set_config(self::CONFIG_UPDATE_INFO, json_encode($fetchedinfo), 'local_sm_graphics_plugin');
                $updateinfo = $fetchedinfo;
            }
            set_config(self::CONFIG_LAST_CHECK, time(), 'local_sm_graphics_plugin');
        }

        if (!$updateinfo) {
            return null;
        }

        if ($updateinfo['version'] > $currentversion) {
            $result = new \stdClass();
            $result->version = $updateinfo['version'];
            $result->release = $updateinfo['release'];
            $result->download = $updateinfo['download'] ?? '';
            $result->url = $updateinfo['url'] ?? '';
            $result->currentversion = $currentversion;
            $result->currentrelease = $plugin->release ?? $currentversion;
            return $result;
        }

        return null;
    }

    /**
     * Fetch update information from GitHub.
     *
     * @return array|null Update info array or null on failure.
     */
    public static function fetch_update_info(): ?array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl(['cache' => false]);
        $curl->setopt([
            'CURLOPT_TIMEOUT' => 30,
            'CURLOPT_CONNECTTIMEOUT' => 10,
            'CURLOPT_FOLLOWLOCATION' => true,
            'CURLOPT_SSL_VERIFYPEER' => false,
            'CURLOPT_SSL_VERIFYHOST' => 0,
            'CURLOPT_HTTPHEADER' => [
                'Cache-Control: no-cache, no-store, must-revalidate',
                'Pragma: no-cache',
            ],
        ]);

        $url = self::UPDATE_URL . '?t=' . time();
        $content = $curl->get($url);

        if ($curl->get_errno() || empty($content)) {
            return null;
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        if ($xml === false || !isset($xml->update)) {
            return null;
        }

        $update = $xml->update;

        return [
            'version' => (int) $update->version,
            'release' => (string) $update->release,
            'download' => (string) $update->download,
            'url' => (string) $update->url,
            'maturity' => (int) $update->maturity,
        ];
    }

    /**
     * Get the current installed version info.
     *
     * @return object|null Plugin info or null.
     */
    public static function get_current_version(): ?object {
        $plugin = \core_plugin_manager::instance()->get_plugin_info('local_sm_graphics_plugin');
        if (!$plugin) {
            return null;
        }

        $result = new \stdClass();
        $result->version = $plugin->versiondisk;
        $result->release = $plugin->release ?? $plugin->versiondisk;
        return $result;
    }
}
