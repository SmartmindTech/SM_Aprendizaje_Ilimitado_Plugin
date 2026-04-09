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

namespace local_sm_graphics_plugin\gamification;

defined('MOODLE_INTERNAL') || die();

/**
 * XP service: awards XP, computes level and progress to next level.
 *
 * Level curve: xp_for_level(n) = round(100 * n^1.5).
 *   Lvl 1 →    0 XP   (everyone starts here)
 *   Lvl 2 →  100 XP
 *   Lvl 3 →  283 XP
 *   Lvl 5 →  559 XP
 *   Lvl 10 → 1581 XP
 *   Lvl 20 → 4472 XP
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class xp_service {

    /** Source identifiers used for the xp_log audit trail. */
    public const SOURCE_ACTIVITY      = 'activity_complete';
    public const SOURCE_COURSE        = 'course_complete';
    public const SOURCE_STREAK        = 'streak';
    public const SOURCE_LOGIN_DAILY   = 'login_daily';
    public const SOURCE_ACHIEVEMENT   = 'achievement';
    public const SOURCE_MANUAL        = 'manual';

    /** Default XP rewards by source (admins can later override via settings). */
    public const XP_PER_ACTIVITY      = 25;
    public const XP_PER_COURSE        = 250;
    public const XP_PER_LOGIN_DAILY   = 10;
    public const XP_STREAK_MILESTONE  = 50;

    /**
     * Calculate the cumulative XP needed to reach a given level.
     * Level 1 = 0 XP. Curve: 100 * n^1.5.
     */
    public static function xp_for_level(int $level): int {
        if ($level <= 1) {
            return 0;
        }
        return (int) round(100 * pow($level - 1, 1.5));
    }

    /**
     * Inverse of xp_for_level: given a total XP amount, return the level.
     * Iterates against xp_for_level() so the boundaries always agree (the
     * round() in xp_for_level otherwise causes off-by-one at exact thresholds).
     */
    public static function level_for_xp(int $xp): int {
        if ($xp <= 0) {
            return 1;
        }
        $level = 1;
        // Hard cap of 1000 for safety; far beyond any realistic player.
        while ($level < 1000 && self::xp_for_level($level + 1) <= $xp) {
            $level++;
        }
        return $level;
    }

    /**
     * Return progress data for the current XP total.
     *
     * @param int $xp Total XP earned.
     * @return array {
     *   level: int, xp_total: int,
     *   xp_into_level: int, xp_for_next: int,
     *   progress_pct: int, xp_to_next: int
     * }
     */
    public static function progress(int $xp): array {
        $level = self::level_for_xp($xp);
        $xpcurrentlevel = self::xp_for_level($level);
        $xpnextlevel = self::xp_for_level($level + 1);
        $span = max(1, $xpnextlevel - $xpcurrentlevel);
        $into = max(0, $xp - $xpcurrentlevel);
        $pct = (int) round(($into / $span) * 100);
        return [
            'level'         => $level,
            'xp_total'      => $xp,
            'xp_into_level' => $into,
            'xp_for_next'   => $span,
            'xp_to_next'    => max(0, $xpnextlevel - $xp),
            'progress_pct'  => max(0, min(100, $pct)),
        ];
    }

    /**
     * Get a user's XP record. Creates a zeroed row if it does not exist.
     */
    public static function get_user_xp(int $userid): \stdClass {
        global $DB;

        $row = $DB->get_record('local_smgp_xp', ['userid' => $userid]);
        if ($row) {
            return $row;
        }
        $row = (object) [
            'userid'       => $userid,
            'xp_total'     => 0,
            'level'        => 1,
            'last_updated' => time(),
        ];
        $row->id = $DB->insert_record('local_smgp_xp', $row);
        return $row;
    }

    /**
     * Award XP to a user. Idempotent on (userid, source, sourceid):
     * if a log row already exists for the same combination, no XP is added.
     * Returns true if XP was awarded, false if it was a duplicate.
     *
     * @param int    $userid
     * @param string $source One of the SOURCE_* constants.
     * @param int    $sourceid Related id (cmid, courseid, achievementid, 0).
     * @param int    $amount XP to add (must be > 0).
     * @param string $description Optional human-readable description.
     */
    public static function award_xp(
        int $userid,
        string $source,
        int $sourceid,
        int $amount,
        string $description = ''
    ): bool {
        global $DB;

        if ($userid <= 0 || $amount <= 0) {
            return false;
        }

        // Idempotency: same source + sourceid for the same user can only award once.
        // Sources that should be repeatable (e.g. SOURCE_LOGIN_DAILY) must use a
        // different sourceid each time (e.g. the day's epoch).
        $exists = $DB->record_exists('local_smgp_xp_log', [
            'userid'   => $userid,
            'source'   => $source,
            'sourceid' => $sourceid,
        ]);
        if ($exists) {
            return false;
        }

        $now = time();

        // Insert audit row.
        $DB->insert_record('local_smgp_xp_log', (object) [
            'userid'      => $userid,
            'source'      => $source,
            'sourceid'    => $sourceid,
            'xp_amount'   => $amount,
            'description' => $description,
            'timecreated' => $now,
        ]);

        // Update aggregate row.
        $row = self::get_user_xp($userid);
        $row->xp_total = (int) $row->xp_total + $amount;
        $row->level = self::level_for_xp($row->xp_total);
        $row->last_updated = $now;
        $DB->update_record('local_smgp_xp', $row);

        return true;
    }

    /**
     * Return the N most recent XP log entries for a user (newest first).
     */
    public static function recent_xp_log(int $userid, int $limit = 10): array {
        global $DB;

        $rows = $DB->get_records('local_smgp_xp_log',
            ['userid' => $userid],
            'timecreated DESC',
            'id, source, sourceid, xp_amount, description, timecreated',
            0,
            $limit
        );
        return array_values($rows);
    }

    /**
     * Hardcoded labels for the standard XP sources, in every supported SPA
     * language. Mission-specific sources are resolved separately because
     * the catalog already owns its own translation map.
     */
    private static function source_labels(): array {
        return [
            self::SOURCE_ACTIVITY => [
                'es'    => 'Actividad completada',
                'en'    => 'Activity completed',
                'pt_br' => 'Atividade concluída',
            ],
            self::SOURCE_COURSE => [
                'es'    => 'Curso completado',
                'en'    => 'Course completed',
                'pt_br' => 'Curso concluído',
            ],
            self::SOURCE_LOGIN_DAILY => [
                'es'    => 'Inicio de sesión diario',
                'en'    => 'Daily login',
                'pt_br' => 'Login diário',
            ],
            self::SOURCE_STREAK => [
                'es'    => 'Bonus de racha',
                'en'    => 'Streak bonus',
                'pt_br' => 'Bônus de sequência',
            ],
            self::SOURCE_ACHIEVEMENT => [
                'es'    => 'Logro desbloqueado',
                'en'    => 'Achievement unlocked',
                'pt_br' => 'Conquista desbloqueada',
            ],
            self::SOURCE_MANUAL => [
                'es'    => 'Ajuste manual',
                'en'    => 'Manual adjustment',
                'pt_br' => 'Ajuste manual',
            ],
        ];
    }

    /**
     * Translate an xp_log row's source identifier into a user-friendly,
     * localized label. mission_<code> entries are resolved against the
     * mission catalog so the user sees the actual mission name.
     */
    public static function localize_source(string $source, string $lang = 'es'): string {
        $lang = mission_service::normalize_lang($lang);

        // Mission claims: source format is "mission_<code>". Look up the
        // mission's localized name from the catalog so the recent feed
        // says e.g. "Pasarse por aquí" instead of "mission_daily_visit".
        if (strpos($source, 'mission_') === 0) {
            $code = substr($source, strlen('mission_'));
            return mission_service::name_for($code, $lang);
        }

        $labels = self::source_labels();
        if (isset($labels[$source][$lang])) {
            return $labels[$source][$lang];
        }
        if (isset($labels[$source]['es'])) {
            return $labels[$source]['es'];
        }
        return $source;
    }
}
