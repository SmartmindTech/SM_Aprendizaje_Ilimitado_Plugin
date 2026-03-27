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
 * Statistics data provider for company managers.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_sm_graphics_plugin;

defined('MOODLE_INTERNAL') || die();

/**
 * Provides statistics queries scoped to an IOMAD company.
 */
class statistics {

    /** @var int The IOMAD company ID. */
    private int $companyid;

    /**
     * Constructor.
     *
     * @param int $companyid The IOMAD company ID.
     */
    public function __construct(int $companyid) {
        $this->companyid = $companyid;
    }

    /**
     * Students who accessed the platform within the last 5 days.
     *
     * @return int
     */
    public function get_active_last_5_days(): int {
        global $DB;

        $since = time() - (5 * DAYSECS);

        return (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT u.id)
               FROM {user} u
               JOIN {company_users} cu ON cu.userid = u.id
              WHERE cu.companyid = :cid
                AND cu.managertype = 0
                AND u.deleted = 0
                AND u.lastaccess >= :since",
            ['cid' => $this->companyid, 'since' => $since]
        );
    }

    /**
     * Total course enrolments across all company students.
     *
     * @return int
     */
    public function get_courses_started(): int {
        global $DB;

        return (int) $DB->count_records_sql(
            "SELECT COUNT(ue.id)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
               JOIN {company_users} cu ON cu.userid = ue.userid
              WHERE cu.companyid = :cid
                AND cu.managertype = 0",
            ['cid' => $this->companyid]
        );
    }

    /**
     * Total course completions across all company students.
     *
     * @return int
     */
    public function get_courses_completed(): int {
        global $DB;

        return (int) $DB->count_records_sql(
            "SELECT COUNT(cc.id)
               FROM {course_completions} cc
               JOIN {company_users} cu ON cu.userid = cc.userid
              WHERE cu.companyid = :cid
                AND cu.managertype = 0
                AND cc.timecompleted IS NOT NULL",
            ['cid' => $this->companyid]
        );
    }

    /**
     * Completion rate: courses completed / courses started × 100.
     *
     * @return float
     */
    public function get_completion_rate(): float {
        $started   = $this->get_courses_started();
        $completed = $this->get_courses_completed();

        return ($started > 0)
            ? round(($completed / $started) * 100, 1)
            : 0;
    }

    /**
     * Visible courses assigned to the company.
     *
     * @return int
     */
    public function get_courses_available(): int {
        global $DB;

        return (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT c.id)
               FROM {course} c
               JOIN {company_course} cc ON cc.courseid = c.id
              WHERE cc.companyid = :cid
                AND c.visible = 1",
            ['cid' => $this->companyid]
        );
    }

    /**
     * Courses completed per week for the last N weeks.
     *
     * @param int $weeks Number of weeks to look back.
     * @return array Array of ['label' => 'dd/mm', 'value' => int] per week.
     */
    public function get_weekly_completions(int $weeks = 10): array {
        global $DB;

        $now = time();
        $results = [];

        for ($i = $weeks - 1; $i >= 0; $i--) {
            $weekstart = strtotime("monday this week -$i weeks", $now);
            $weekend   = $weekstart + (7 * DAYSECS);

            $count = (int) $DB->count_records_sql(
                "SELECT COUNT(cc.id)
                   FROM {course_completions} cc
                   JOIN {company_users} cu ON cu.userid = cc.userid
                  WHERE cu.companyid = :cid
                    AND cu.managertype = 0
                    AND cc.timecompleted >= :wstart
                    AND cc.timecompleted < :wend",
                ['cid' => $this->companyid, 'wstart' => $weekstart, 'wend' => $weekend]
            );

            $results[] = [
                'label' => userdate($weekstart, '%d/%m'),
                'value' => $count,
            ];
        }

        return $results;
    }

    /**
     * Unique users connected per week for the last N weeks.
     *
     * Uses the {logstore_standard_log} table to count distinct users
     * with at least one event per week.
     *
     * @param int $weeks Number of weeks to look back.
     * @return array Array of ['label' => 'dd/mm', 'value' => int] per week.
     */
    public function get_weekly_active_users(int $weeks = 10): array {
        global $DB;

        $now = time();
        $results = [];

        for ($i = $weeks - 1; $i >= 0; $i--) {
            $weekstart = strtotime("monday this week -$i weeks", $now);
            $weekend   = $weekstart + (7 * DAYSECS);

            $count = (int) $DB->count_records_sql(
                "SELECT COUNT(DISTINCT l.userid)
                   FROM {logstore_standard_log} l
                   JOIN {company_users} cu ON cu.userid = l.userid
                  WHERE cu.companyid = :cid
                    AND cu.managertype = 0
                    AND l.timecreated >= :wstart
                    AND l.timecreated < :wend",
                ['cid' => $this->companyid, 'wstart' => $weekstart, 'wend' => $weekend]
            );

            $results[] = [
                'label' => userdate($weekstart, '%d/%m'),
                'value' => $count,
            ];
        }

        return $results;
    }

    /**
     * Return all stats as an associative array (for template rendering).
     *
     * @return array
     */
    public function get_all(): array {
        $started   = $this->get_courses_started();
        $completed = $this->get_courses_completed();

        $completionrate = ($started > 0)
            ? round(($completed / $started) * 100, 1)
            : 0;

        return [
            'active_last_5_days' => $this->get_active_last_5_days(),
            'courses_started'    => $started,
            'courses_completed'  => $completed,
            'completion_rate'    => $completionrate,
            'courses_available'  => $this->get_courses_available(),
        ];
    }
}
