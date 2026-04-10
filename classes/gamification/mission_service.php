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
 * Mission service: static catalogs of daily and weekly missions, progress
 * computation, and manual claim flow.
 *
 * Missions are intentionally NOT auto-awarded — the user must press a
 * "Reclamar" button in the SPA so they get visual feedback as the XP bar
 * grows. Idempotency is enforced via local_smgp_xp_log: each mission is
 * stored with `source = 'mission_<code>'` and `sourceid = <period_epoch>`,
 * so a user can only claim each mission once per its calendar period
 * (today's local midnight for daily, this week's Monday local midnight for
 * weekly).
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mission_service {

    public const PERIOD_DAILY  = 'daily';
    public const PERIOD_WEEKLY = 'weekly';

    public const COND_ACTIVITIES_TODAY = 'activities_today';
    public const COND_LOGIN_TODAY      = 'login_today';
    public const COND_ACTIVITIES_WEEK  = 'activities_week';
    public const COND_LOGIN_STREAK     = 'login_streak';
    public const COND_COURSES_WEEK     = 'courses_week';

    /** Supported SPA languages. Anything else falls back to 'es'. */
    public const SUPPORTED_LANGS = ['es', 'en', 'pt_br'];

    /**
     * Hardcoded translations for every mission. Living next to the catalog
     * means we don't depend on Moodle's lang packs being installed and the
     * SPA selector controls the output reliably.
     *
     * Format: [code => ['es' => ['name' => ..., 'desc' => ...], 'en' => ..., 'pt_br' => ...]]
     */
    private static function strings(): array {
        return [
            'daily_visit' => [
                'es'    => ['name' => 'Pasarse por aquí',  'desc' => 'Entra hoy en la plataforma.'],
                'en'    => ['name' => 'Drop by',           'desc' => 'Visit the platform today.'],
                'pt_br' => ['name' => 'Passe por aqui',    'desc' => 'Acesse a plataforma hoje.'],
            ],
            'daily_complete_1' => [
                'es'    => ['name' => 'Pequeño avance',    'desc' => 'Completa 1 actividad hoy.'],
                'en'    => ['name' => 'Small step',        'desc' => 'Complete 1 activity today.'],
                'pt_br' => ['name' => 'Pequeno avanço',    'desc' => 'Conclua 1 atividade hoje.'],
            ],
            'daily_complete_3' => [
                'es'    => ['name' => 'En racha',          'desc' => 'Completa 3 actividades hoy.'],
                'en'    => ['name' => 'On a roll',         'desc' => 'Complete 3 activities today.'],
                'pt_br' => ['name' => 'Em sequência',      'desc' => 'Conclua 3 atividades hoje.'],
            ],
            'weekly_complete_5' => [
                'es'    => ['name' => 'Aprendiz constante','desc' => 'Completa 5 actividades esta semana.'],
                'en'    => ['name' => 'Steady learner',    'desc' => 'Complete 5 activities this week.'],
                'pt_br' => ['name' => 'Aprendiz constante','desc' => 'Conclua 5 atividades esta semana.'],
            ],
            'weekly_streak_5' => [
                'es'    => ['name' => 'Cinco días seguidos','desc' => 'Mantén una racha de inicio de sesión de 5 días.'],
                'en'    => ['name' => 'Five in a row',     'desc' => 'Maintain a 5-day login streak.'],
                'pt_br' => ['name' => 'Cinco dias seguidos','desc' => 'Mantenha uma sequência de login de 5 dias.'],
            ],
            'weekly_complete_course' => [
                'es'    => ['name' => 'Cierra un curso',   'desc' => 'Termina 1 curso esta semana.'],
                'en'    => ['name' => 'Wrap a course',     'desc' => 'Finish 1 course this week.'],
                'pt_br' => ['name' => 'Feche um curso',    'desc' => 'Termine 1 curso esta semana.'],
            ],
        ];
    }

    /**
     * Normalize an arbitrary language code into one of SUPPORTED_LANGS.
     * Anything we don't know about (or empty) falls back to 'es'.
     */
    public static function normalize_lang(string $lang): string {
        $lang = strtolower($lang);
        if (in_array($lang, self::SUPPORTED_LANGS, true)) {
            return $lang;
        }
        // Map common Moodle locale variants (e.g. es_mx, pt_PT, en_us).
        if (strpos($lang, 'es') === 0) return 'es';
        if (strpos($lang, 'pt') === 0) return 'pt_br';
        if (strpos($lang, 'en') === 0) return 'en';
        return 'es';
    }

    /**
     * Translate a (mission_code, field) pair into the requested language.
     * Falls back to es if the language map is missing for some reason.
     */
    private static function translate(string $code, string $field, string $lang): string {
        $lang = self::normalize_lang($lang);
        $strings = self::strings();
        if (isset($strings[$code][$lang][$field])) {
            return $strings[$code][$lang][$field];
        }
        return $strings[$code]['es'][$field] ?? $code;
    }

    /**
     * Public shortcut: localized mission name by code, with no DB access.
     * Used by the recent XP feed to render mission_<code> entries.
     */
    public static function name_for(string $code, string $lang = 'es'): string {
        return self::translate($code, 'name', $lang);
    }

    /**
     * Static catalog of daily missions.
     * Format: [code, condition, target, xp_reward, icon]. Translations for
     * name/description live in self::strings().
     */
    public static function daily_catalog(): array {
        return [
            ['daily_visit',      self::COND_LOGIN_TODAY,      1, 10, 'log-in'],
            ['daily_complete_1', self::COND_ACTIVITIES_TODAY, 1, 25, 'check-circle'],
            ['daily_complete_3', self::COND_ACTIVITIES_TODAY, 3, 50, 'zap'],
        ];
    }

    /**
     * Static catalog of weekly missions.
     */
    public static function weekly_catalog(): array {
        return [
            ['weekly_complete_5',      self::COND_ACTIVITIES_WEEK, 5, 100, 'check-circle'],
            ['weekly_streak_5',        self::COND_LOGIN_STREAK,    5, 150, 'zap'],
            ['weekly_complete_course', self::COND_COURSES_WEEK,    1, 250, 'graduation'],
        ];
    }

    /**
     * Local-midnight epoch for today (in user's TZ).
     */
    public static function today_epoch(): int {
        $d = new \DateTime('now', \core_date::get_user_timezone_object());
        $d->setTime(0, 0, 0);
        return (int) $d->getTimestamp();
    }

    /**
     * Local-midnight epoch for the Monday of the current ISO week.
     */
    public static function week_monday_epoch(): int {
        $d = new \DateTime('now', \core_date::get_user_timezone_object());
        // ISO 8601: Monday = 1.
        $dayofweek = (int) $d->format('N');
        if ($dayofweek > 1) {
            $d->modify('-' . ($dayofweek - 1) . ' days');
        }
        $d->setTime(0, 0, 0);
        return (int) $d->getTimestamp();
    }

    /**
     * Period epoch for a given mission period: today's midnight for daily,
     * Monday's midnight for weekly.
     */
    public static function period_epoch(string $period): int {
        return $period === self::PERIOD_WEEKLY
            ? self::week_monday_epoch()
            : self::today_epoch();
    }

    /**
     * Compute the user's current progress (integer count) for a condition.
     * MUST NOT depend on lazy-loaded Moodle libraries (same rationale as
     * achievement_service::compute_metrics).
     */
    public static function compute_progress(int $userid, string $condition): int {
        global $DB;

        // Shared filter that excludes module types not shown in the player
        // (forum, label) so mission progress matches what the user actually
        // does on screen.
        $cf = completion_filter::build('cmc');
        $countTrackable = function (int $start, int $end) use ($DB, $userid, $cf): int {
            $sql = "SELECT COUNT(cmc.id)
                      FROM {course_modules_completion} cmc
                      {$cf['join']}
                     WHERE cmc.userid = :uid AND cmc.completionstate >= 1
                       AND cmc.timemodified BETWEEN :start AND :end
                       AND {$cf['where']}";
            return (int) $DB->count_records_sql(
                $sql,
                ['uid' => $userid, 'start' => $start, 'end' => $end] + $cf['params']
            );
        };

        switch ($condition) {
            case self::COND_ACTIVITIES_TODAY: {
                $start = self::today_epoch();
                $end = $start + DAYSECS - 1;
                return $countTrackable($start, $end);
            }

            case self::COND_LOGIN_TODAY: {
                $exists = $DB->record_exists('local_smgp_xp_log', [
                    'userid'   => $userid,
                    'source'   => xp_service::SOURCE_LOGIN_DAILY,
                    'sourceid' => self::today_epoch(),
                ]);
                return $exists ? 1 : 0;
            }

            case self::COND_ACTIVITIES_WEEK: {
                $start = self::week_monday_epoch();
                $end = $start + (7 * DAYSECS) - 1;
                return $countTrackable($start, $end);
            }

            case self::COND_LOGIN_STREAK: {
                return self::current_login_streak($userid);
            }

            case self::COND_COURSES_WEEK: {
                $start = self::week_monday_epoch();
                $end = $start + (7 * DAYSECS) - 1;
                return (int) $DB->count_records_select(
                    'course_completions',
                    'userid = :uid AND timecompleted BETWEEN :start AND :end',
                    ['uid' => $userid, 'start' => $start, 'end' => $end]
                );
            }
        }

        return 0;
    }

    /**
     * Same Duolingo-style streak as get_dashboard_data, but kept here so the
     * mission service has zero coupling to other modules.
     */
    private static function current_login_streak(int $userid): int {
        global $DB;

        $checkdate = new \DateTime('now', \core_date::get_user_timezone_object());

        $hasentryfor = function (\DateTime $d) use ($DB, $userid): bool {
            $epoch = (int) (clone $d)->setTime(0, 0, 0)->getTimestamp();
            return $DB->record_exists('local_smgp_xp_log', [
                'userid'   => $userid,
                'source'   => xp_service::SOURCE_LOGIN_DAILY,
                'sourceid' => $epoch,
            ]);
        };

        if (!$hasentryfor($checkdate)) {
            $checkdate->modify('-1 day');
            if (!$hasentryfor($checkdate)) {
                return 0;
            }
        }

        $streak = 0;
        for ($i = 0; $i < 365; $i++) {
            if ($hasentryfor($checkdate)) {
                $streak++;
                $checkdate->modify('-1 day');
            } else {
                break;
            }
        }
        return $streak;
    }

    /**
     * Whether a mission has already been claimed for its current period.
     */
    public static function is_claimed(int $userid, string $code, string $period): bool {
        global $DB;
        return $DB->record_exists('local_smgp_xp_log', [
            'userid'   => $userid,
            'source'   => 'mission_' . $code,
            'sourceid' => self::period_epoch($period),
        ]);
    }

    /**
     * Find a mission by code in either catalog. Returns null if unknown.
     */
    public static function find_mission(string $code): ?array {
        foreach (self::daily_catalog() as $m) {
            if ($m[0] === $code) {
                return self::decorate($m, self::PERIOD_DAILY);
            }
        }
        foreach (self::weekly_catalog() as $m) {
            if ($m[0] === $code) {
                return self::decorate($m, self::PERIOD_WEEKLY);
            }
        }
        return null;
    }

    /**
     * Turn a tuple from the catalog into an associative array.
     */
    private static function decorate(array $row, string $period): array {
        return [
            'code'      => $row[0],
            'condition' => $row[1],
            'target'    => (int) $row[2],
            'xp_reward' => (int) $row[3],
            'icon'      => $row[4],
            'period'    => $period,
        ];
    }

    /**
     * Build the full payload for one mission: progress, flags, localized text.
     */
    public static function payload_for(int $userid, array $mission, string $lang = 'es'): array {
        $progress  = self::compute_progress($userid, $mission['condition']);
        $claimed   = self::is_claimed($userid, $mission['code'], $mission['period']);
        $claimable = !$claimed && $progress >= $mission['target'];
        $pct       = $mission['target'] > 0
            ? (int) round(min(100, ($progress / $mission['target']) * 100))
            : 100;

        return [
            'code'         => $mission['code'],
            'name'         => self::translate($mission['code'], 'name', $lang),
            'description'  => self::translate($mission['code'], 'desc', $lang),
            'icon'         => $mission['icon'],
            'period'       => $mission['period'],
            'progress'     => $progress,
            'target'       => $mission['target'],
            'progress_pct' => $pct,
            'xp_reward'    => $mission['xp_reward'],
            'claimable'    => $claimable,
            'claimed'      => $claimed,
        ];
    }

    /**
     * Build the full mission list (daily + weekly) for a user.
     * Returned shape: ['daily' => [...], 'weekly' => [...]]
     */
    public static function user_missions(int $userid, string $lang = 'es'): array {
        $daily = [];
        foreach (self::daily_catalog() as $row) {
            $daily[] = self::payload_for($userid, self::decorate($row, self::PERIOD_DAILY), $lang);
        }
        $weekly = [];
        foreach (self::weekly_catalog() as $row) {
            $weekly[] = self::payload_for($userid, self::decorate($row, self::PERIOD_WEEKLY), $lang);
        }
        return ['daily' => $daily, 'weekly' => $weekly];
    }

    /**
     * Validate and award a mission claim.
     *
     * Returns ['success' => bool, 'reason' => string, 'xp_awarded' => int].
     * Reasons on failure: 'unknown', 'not_completed', 'already_claimed'.
     */
    public static function claim(int $userid, string $code): array {
        $mission = self::find_mission($code);
        if (!$mission) {
            return ['success' => false, 'reason' => 'unknown', 'xp_awarded' => 0];
        }

        if (self::is_claimed($userid, $code, $mission['period'])) {
            return ['success' => false, 'reason' => 'already_claimed', 'xp_awarded' => 0];
        }

        $progress = self::compute_progress($userid, $mission['condition']);
        if ($progress < $mission['target']) {
            return ['success' => false, 'reason' => 'not_completed', 'xp_awarded' => 0];
        }

        // Award via xp_service so the audit trail and idempotency stay
        // consistent with the rest of the gamification code.
        $awarded = xp_service::award_xp(
            $userid,
            'mission_' . $code,
            self::period_epoch($mission['period']),
            $mission['xp_reward'],
            'Mission claimed: ' . $code
        );

        return [
            'success'    => $awarded,
            'reason'     => $awarded ? 'ok' : 'already_claimed',
            'xp_awarded' => $awarded ? $mission['xp_reward'] : 0,
        ];
    }
}
