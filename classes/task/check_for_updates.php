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

namespace local_sm_graphics_plugin\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task to check for plugin updates and notify site administrators.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_for_updates extends \core\task\scheduled_task {

    /**
     * Get the name of the task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task:checkforupdates', 'local_sm_graphics_plugin');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $CFG;

        mtrace('Checking for SmartMind Graphics Plugin updates...');

        $plugin = \core_plugin_manager::instance()->get_plugin_info('local_sm_graphics_plugin');
        if (!$plugin) {
            mtrace('Could not get plugin info.');
            return;
        }

        $currentversion = $plugin->versiondisk;
        $currentrelease = $plugin->release ?? 'unknown';

        mtrace("Current installed version: {$currentversion} ({$currentrelease})");

        $updateinfo = \local_sm_graphics_plugin\update_checker::fetch_update_info();
        if (!$updateinfo) {
            mtrace('Could not fetch or parse update information.');
            return;
        }

        mtrace("Latest available version: {$updateinfo['version']} ({$updateinfo['release']})");

        if ($updateinfo['version'] <= $currentversion) {
            mtrace('Plugin is up to date.');
            return;
        }

        // Check if already notified about this version.
        $lastnotified = get_config('local_sm_graphics_plugin', 'last_notified_version');
        if ($lastnotified == $updateinfo['version']) {
            mtrace('Already notified about this version.');
            return;
        }

        // Notify all site administrators.
        $this->notify_admins($updateinfo, $currentrelease);

        set_config('last_notified_version', $updateinfo['version'], 'local_sm_graphics_plugin');
        mtrace('Notifications sent to all site administrators.');
    }

    /**
     * Send notifications to all site administrators.
     *
     * @param array $updateinfo Update information.
     * @param string $currentrelease Current installed release string.
     */
    private function notify_admins(array $updateinfo, string $currentrelease): void {
        $admins = get_admins();
        if (empty($admins)) {
            return;
        }

        $subject = get_string('updateavailable_subject', 'local_sm_graphics_plugin', $updateinfo['release']);

        $messagedata = new \stdClass();
        $messagedata->currentversion = $currentrelease;
        $messagedata->newversion = $updateinfo['release'];

        $fullmessage = get_string('updateavailable_message', 'local_sm_graphics_plugin', $messagedata);
        $htmlmessage = get_string('updateavailable_message_html', 'local_sm_graphics_plugin', $messagedata);

        $noreplyuser = \core_user::get_noreply_user();

        foreach ($admins as $admin) {
            $message = new \core\message\message();
            $message->component = 'local_sm_graphics_plugin';
            $message->name = 'updatenotification';
            $message->userfrom = $noreplyuser;
            $message->userto = $admin;
            $message->subject = $subject;
            $message->fullmessage = $fullmessage;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = $htmlmessage;
            $message->smallmessage = $subject;
            $message->notification = 1;

            try {
                message_send($message);
                mtrace("Notified admin: {$admin->username}");
            } catch (\Exception $e) {
                mtrace("Failed to notify {$admin->username}: " . $e->getMessage());
            }
        }
    }
}
