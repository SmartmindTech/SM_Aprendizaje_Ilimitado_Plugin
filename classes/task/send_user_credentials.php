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
 * Ad-hoc task to send login credentials to a newly created user.
 *
 * Queued from the user_created event observer. Runs asynchronously via cron
 * so bulk CSV uploads are not blocked by email delivery.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_user_credentials extends \core\task\adhoc_task {

    /**
     * Execute the task: generate a temporary password and email credentials.
     */
    public function execute() {
        global $DB, $CFG;

        $data = $this->get_custom_data();
        if (empty($data->userid)) {
            mtrace("SmartMind credentials: task fired but no userid in custom_data — skipping.");
            return;
        }

        mtrace("SmartMind credentials: processing userid={$data->userid}");

        $user = $DB->get_record('user', ['id' => $data->userid]);
        if (!$user || $user->deleted || $user->suspended) {
            mtrace("SmartMind credentials: user {$data->userid} not found, deleted or suspended — skipping.");
            return;
        }

        // Prevent sending credentials more than once (e.g. if task is retried).
        if (get_user_preferences('smgp_credentials_sent', false, $user->id)) {
            mtrace("SmartMind credentials: user {$user->email} already received credentials — skipping.");
            return;
        }

        // Only send to users belonging to an IOMAD company.
        // The task runs async, so company_users is already populated by now.
        $companyrec = $DB->get_record('company_users', ['userid' => $user->id]);
        if (!$companyrec) {
            mtrace("SmartMind credentials: user {$user->email} has no company_users record — skipping.");
            return;
        }

        // Skip managers (managertype > 0) — only send to students.
        if (!empty($companyrec->managertype)) {
            mtrace("SmartMind credentials: user {$user->email} is a manager (type={$companyrec->managertype}) — skipping.");
            return;
        }

        $companyname = $DB->get_field('company', 'name', ['id' => $companyrec->companyid]);

        // Generate a temporary password.
        $temppassword = generate_password(12);

        // Update the user's password (overrides whatever IOMAD may have set).
        update_internal_user_password($user, $temppassword);

        // Force password change on first login.
        set_user_preference('auth_forcepasswordchange', 1, $user->id);

        // Build email content.
        $loginurl = $CFG->wwwroot . '/login/index.php';
        $sitename = format_string(get_site()->fullname);

        $a = (object) [
            'firstname' => $user->firstname,
            'username'  => $user->username,
            'password'  => $temppassword,
            'loginurl'  => $loginurl,
            'sitename'  => $sitename,
            'company'   => $companyname ?? '',
        ];

        $subject  = get_string('newuser_email_subject', 'local_sm_graphics_plugin', $a);
        $htmlbody = get_string('newuser_email_body_html', 'local_sm_graphics_plugin', $a);
        $textbody = get_string('newuser_email_body', 'local_sm_graphics_plugin', $a);

        // Send via Microsoft Graph API.
        require_once(__DIR__ . '/../graph_mailer.php');
        mtrace("SmartMind credentials: sending email to {$user->email} (company: {$companyname})...");
        $sent = \local_sm_graphics_plugin\graph_mailer::send($user->email, $subject, $htmlbody, $textbody);

        if ($sent) {
            // Mark as sent so we never send twice.
            set_user_preference('smgp_credentials_sent', 1, $user->id);
            mtrace("SmartMind credentials: email sent successfully to {$user->email}.");
        } else {
            $error = \local_sm_graphics_plugin\graph_mailer::get_last_error();
            mtrace("SmartMind credentials: FAILED to send to {$user->email}: {$error}");
        }
    }
}
