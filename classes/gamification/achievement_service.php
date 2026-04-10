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
 * Achievement service: seeds the catalog, evaluates conditions, unlocks achievements.
 *
 * Conditions are evaluated against precomputed user metrics so the same call site
 * (the event observer) can fire all checks at once after any meaningful event.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class achievement_service {

    public const COND_COURSES_COMPLETED    = 'courses_completed';
    public const COND_ACTIVITIES_COMPLETED = 'activities_completed';
    public const COND_STREAK_DAYS          = 'streak_days';
    public const COND_TOTAL_HOURS          = 'total_hours';
    public const COND_TOTAL_XP             = 'total_xp';

    /** Supported SPA languages. Anything else falls back to 'es'. */
    public const SUPPORTED_LANGS = ['es', 'en', 'pt_br'];

    /**
     * Hardcoded translations for every achievement. Keyed by code, then by
     * language. Living next to the catalog means we don't depend on Moodle's
     * lang packs being installed and the SPA selector controls the output
     * reliably.
     */
    private static function strings(): array {
        return [
            'first_activity' => [
                'es'    => ['name' => 'Primer paso',         'desc' => 'Completa tu primera actividad.'],
                'en'    => ['name' => 'First step',          'desc' => 'Complete your first activity.'],
                'pt_br' => ['name' => 'Primeiro passo',      'desc' => 'Conclua sua primeira atividade.'],
            ],
            'first_course' => [
                'es'    => ['name' => 'Graduación',          'desc' => 'Completa tu primer curso.'],
                'en'    => ['name' => 'Graduation',          'desc' => 'Complete your first course.'],
                'pt_br' => ['name' => 'Formatura',           'desc' => 'Conclua seu primeiro curso.'],
            ],
            'activities_10' => [
                'es'    => ['name' => 'En marcha',           'desc' => 'Completa 10 actividades.'],
                'en'    => ['name' => 'Getting started',     'desc' => 'Complete 10 activities.'],
                'pt_br' => ['name' => 'Em movimento',        'desc' => 'Conclua 10 atividades.'],
            ],
            'activities_50' => [
                'es'    => ['name' => 'Maratón de aprendizaje', 'desc' => 'Completa 50 actividades.'],
                'en'    => ['name' => 'Learning marathon',      'desc' => 'Complete 50 activities.'],
                'pt_br' => ['name' => 'Maratona de aprendizado','desc' => 'Conclua 50 atividades.'],
            ],
            'activities_100' => [
                'es'    => ['name' => 'Centurión',           'desc' => 'Completa 100 actividades.'],
                'en'    => ['name' => 'Centurion',           'desc' => 'Complete 100 activities.'],
                'pt_br' => ['name' => 'Centurião',           'desc' => 'Conclua 100 atividades.'],
            ],
            'courses_3' => [
                'es'    => ['name' => 'Coleccionista',       'desc' => 'Completa 3 cursos.'],
                'en'    => ['name' => 'Collector',           'desc' => 'Complete 3 courses.'],
                'pt_br' => ['name' => 'Colecionador',        'desc' => 'Conclua 3 cursos.'],
            ],
            'courses_10' => [
                'es'    => ['name' => 'Estudiante experto',  'desc' => 'Completa 10 cursos.'],
                'en'    => ['name' => 'Expert student',      'desc' => 'Complete 10 courses.'],
                'pt_br' => ['name' => 'Estudante experiente','desc' => 'Conclua 10 cursos.'],
            ],
            'courses_25' => [
                'es'    => ['name' => 'Maestro del catálogo','desc' => 'Completa 25 cursos.'],
                'en'    => ['name' => 'Catalog master',      'desc' => 'Complete 25 courses.'],
                'pt_br' => ['name' => 'Mestre do catálogo',  'desc' => 'Conclua 25 cursos.'],
            ],
            'streak_3' => [
                'es'    => ['name' => 'En racha',            'desc' => 'Mantén una racha de 3 días.'],
                'en'    => ['name' => 'On a roll',           'desc' => 'Maintain a 3-day streak.'],
                'pt_br' => ['name' => 'Em sequência',        'desc' => 'Mantenha uma sequência de 3 dias.'],
            ],
            'streak_7' => [
                'es'    => ['name' => 'Constancia semanal',  'desc' => 'Mantén una racha de 7 días.'],
                'en'    => ['name' => 'Weekly warrior',      'desc' => 'Maintain a 7-day streak.'],
                'pt_br' => ['name' => 'Constância semanal',  'desc' => 'Mantenha uma sequência de 7 dias.'],
            ],
            'streak_30' => [
                'es'    => ['name' => 'Imparable',           'desc' => 'Mantén una racha de 30 días.'],
                'en'    => ['name' => 'Unstoppable',         'desc' => 'Maintain a 30-day streak.'],
                'pt_br' => ['name' => 'Imparável',           'desc' => 'Mantenha uma sequência de 30 dias.'],
            ],
            'hours_10' => [
                'es'    => ['name' => 'Diez horas de vuelo', 'desc' => 'Acumula 10 horas de aprendizaje.'],
                'en'    => ['name' => 'Ten flight hours',    'desc' => 'Accumulate 10 hours of learning.'],
                'pt_br' => ['name' => 'Dez horas de voo',    'desc' => 'Acumule 10 horas de aprendizado.'],
            ],
            'hours_50' => [
                'es'    => ['name' => 'Veterano',            'desc' => 'Acumula 50 horas de aprendizaje.'],
                'en'    => ['name' => 'Veteran',             'desc' => 'Accumulate 50 hours of learning.'],
                'pt_br' => ['name' => 'Veterano',            'desc' => 'Acumule 50 horas de aprendizado.'],
            ],
            'hours_100' => [
                'es'    => ['name' => 'Erudito',             'desc' => 'Acumula 100 horas de aprendizaje.'],
                'en'    => ['name' => 'Scholar',             'desc' => 'Accumulate 100 hours of learning.'],
                'pt_br' => ['name' => 'Erudito',             'desc' => 'Acumule 100 horas de aprendizado.'],
            ],
            'xp_1000' => [
                'es'    => ['name' => 'Mil puntos',          'desc' => 'Alcanza 1.000 XP.'],
                'en'    => ['name' => 'A thousand points',   'desc' => 'Reach 1,000 XP.'],
                'pt_br' => ['name' => 'Mil pontos',          'desc' => 'Alcance 1.000 XP.'],
            ],
            'xp_5000' => [
                'es'    => ['name' => 'Leyenda viva',        'desc' => 'Alcanza 5.000 XP.'],
                'en'    => ['name' => 'Living legend',       'desc' => 'Reach 5,000 XP.'],
                'pt_br' => ['name' => 'Lenda viva',          'desc' => 'Alcance 5.000 XP.'],
            ],
        ];
    }

    /**
     * Normalize an arbitrary language code into one of SUPPORTED_LANGS.
     */
    public static function normalize_lang(string $lang): string {
        $lang = strtolower($lang);
        if (in_array($lang, self::SUPPORTED_LANGS, true)) {
            return $lang;
        }
        if (strpos($lang, 'es') === 0) return 'es';
        if (strpos($lang, 'pt') === 0) return 'pt_br';
        if (strpos($lang, 'en') === 0) return 'en';
        return 'es';
    }

    /**
     * Translate a (achievement_code, field) pair into the requested language.
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
     * Default catalog. Each row: code, name_key, description_key, icon, condition, value, xp.
     * Lang keys live in lang/{en,es,pt_br}/local_sm_graphics_plugin.php under the
     * "achievement_*" namespace.
     */
    public static function default_catalog(): array {
        return [
            // First steps.
            ['first_activity',   'achievement_first_activity_name',   'achievement_first_activity_desc',   'play',         self::COND_ACTIVITIES_COMPLETED, 1,    25,  10],
            ['first_course',     'achievement_first_course_name',     'achievement_first_course_desc',     'graduation',   self::COND_COURSES_COMPLETED,    1,    100, 20],

            // Activity grinder.
            ['activities_10',    'achievement_activities_10_name',    'achievement_activities_10_desc',    'check-circle', self::COND_ACTIVITIES_COMPLETED, 10,   75,  30],
            ['activities_50',    'achievement_activities_50_name',    'achievement_activities_50_desc',    'check-circle', self::COND_ACTIVITIES_COMPLETED, 50,   200, 40],
            ['activities_100',   'achievement_activities_100_name',   'achievement_activities_100_desc',   'check-circle', self::COND_ACTIVITIES_COMPLETED, 100,  400, 50],

            // Course collector.
            ['courses_3',        'achievement_courses_3_name',        'achievement_courses_3_desc',        'book',         self::COND_COURSES_COMPLETED,    3,    150, 60],
            ['courses_10',       'achievement_courses_10_name',       'achievement_courses_10_desc',       'book',         self::COND_COURSES_COMPLETED,    10,   500, 70],
            ['courses_25',       'achievement_courses_25_name',       'achievement_courses_25_desc',       'book',         self::COND_COURSES_COMPLETED,    25,   1000, 80],

            // Streak.
            ['streak_3',         'achievement_streak_3_name',         'achievement_streak_3_desc',         'fire',         self::COND_STREAK_DAYS,          3,    50,  90],
            ['streak_7',         'achievement_streak_7_name',         'achievement_streak_7_desc',         'fire',         self::COND_STREAK_DAYS,          7,    150, 100],
            ['streak_30',        'achievement_streak_30_name',        'achievement_streak_30_desc',        'fire',         self::COND_STREAK_DAYS,          30,   500, 110],

            // Hours.
            ['hours_10',         'achievement_hours_10_name',         'achievement_hours_10_desc',         'clock',        self::COND_TOTAL_HOURS,          10,   100, 120],
            ['hours_50',         'achievement_hours_50_name',         'achievement_hours_50_desc',         'clock',        self::COND_TOTAL_HOURS,          50,   400, 130],
            ['hours_100',        'achievement_hours_100_name',        'achievement_hours_100_desc',        'clock',        self::COND_TOTAL_HOURS,          100,  800, 140],

            // XP milestones (for late-game players).
            ['xp_1000',          'achievement_xp_1000_name',          'achievement_xp_1000_desc',          'star',         self::COND_TOTAL_XP,             1000, 0,   150],
            ['xp_5000',          'achievement_xp_5000_name',          'achievement_xp_5000_desc',          'star',         self::COND_TOTAL_XP,             5000, 0,   160],
        ];
    }

    /**
     * Insert any missing catalog rows. Idempotent — safe to call on every install/upgrade.
     */
    public static function seed_defaults(): void {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_smgp_achievement')) {
            return;
        }

        foreach (self::default_catalog() as [$code, $namekey, $desckey, $icon, $cond, $value, $xp, $sortorder]) {
            $existing = $DB->get_record('local_smgp_achievement', ['code' => $code]);
            if ($existing) {
                // Refresh metadata in case the catalog evolved (keep id).
                $existing->name_key        = $namekey;
                $existing->description_key = $desckey;
                $existing->icon            = $icon;
                $existing->condition_type  = $cond;
                $existing->condition_value = $value;
                $existing->xp_reward       = $xp;
                $existing->sortorder       = $sortorder;
                $existing->enabled         = 1;
                $DB->update_record('local_smgp_achievement', $existing);
                continue;
            }
            $DB->insert_record('local_smgp_achievement', (object) [
                'code'            => $code,
                'name_key'        => $namekey,
                'description_key' => $desckey,
                'icon'            => $icon,
                'condition_type'  => $cond,
                'condition_value' => $value,
                'xp_reward'       => $xp,
                'sortorder'       => $sortorder,
                'enabled'         => 1,
            ]);
        }
    }

    /**
     * Compute the metrics needed to evaluate every achievement condition.
     * Centralized so we hit the DB once per check_and_unlock() call.
     *
     * All metrics here are based on course_modules_completion transitions
     * (state >= 1), NOT on raw logstore visits. This way an achievement only
     * unlocks when the user actually completes work, not when they revisit
     * something they had already done.
     */
    public static function compute_metrics(int $userid): array {
        global $DB;

        // IMPORTANT: this method MUST NOT use \completion_info,
        // enrol_get_users_courses() or any other helper that lives in
        // lazy-loaded Moodle libraries. The user_loggedin observer dispatches
        // here from /login/index.php before completionlib/enrollib have been
        // required, and a missing class would lock users out. We query the
        // raw tables directly so we depend on nothing but $DB.

        // Filter out activity types the SmartMind player doesn't show (forum,
        // label) so the metrics match what the user actually does in the player.
        $cf = completion_filter::build('cmc');

        // Activities completed (trackable types only).
        $countsql = "SELECT COUNT(cmc.id)
                       FROM {course_modules_completion} cmc
                       {$cf['join']}
                      WHERE cmc.userid = :uid AND cmc.completionstate >= 1
                        AND {$cf['where']}";
        $activitiescompleted = (int) $DB->count_records_sql(
            $countsql,
            ['uid' => $userid] + $cf['params']
        );

        // Courses completed: read straight from {course_completions}.
        // Moodle marks a course as completed by setting timecompleted on
        // this table — exactly what is_course_complete() checks under the
        // hood. Counting non-zero timecompleted is equivalent and library-free.
        $coursescompleted = (int) $DB->count_records_select(
            'course_completions',
            'userid = :uid AND timecompleted IS NOT NULL AND timecompleted > 0',
            ['uid' => $userid]
        );

        // Streak: consecutive days with at least one completion transition.
        $streak = self::compute_streak($userid);

        // Total hours: sum AI-estimated durations (or default 5 min) for all
        // completed trackable activities. Same source as get_profile_data so
        // the value shown to the user matches the value gating the achievement.
        $hourssql = "SELECT COALESCE(SUM(COALESCE(d.duration_minutes, 5)), 0) AS minutes
                       FROM {course_modules_completion} cmc
                  LEFT JOIN {local_smgp_activity_duration} d ON d.cmid = cmc.coursemoduleid
                       {$cf['join']}
                      WHERE cmc.userid = :uid AND cmc.completionstate >= 1
                        AND {$cf['where']}";
        $totalminutes = (int) $DB->get_field_sql($hourssql, ['uid' => $userid] + $cf['params']);
        $totalhours = (int) round($totalminutes / 60);

        // Total XP.
        $xprow = $DB->get_record('local_smgp_xp', ['userid' => $userid]);
        $totalxp = $xprow ? (int) $xprow->xp_total : 0;

        return [
            self::COND_ACTIVITIES_COMPLETED => $activitiescompleted,
            self::COND_COURSES_COMPLETED    => $coursescompleted,
            self::COND_STREAK_DAYS          => $streak,
            self::COND_TOTAL_HOURS          => $totalhours,
            self::COND_TOTAL_XP             => $totalxp,
        ];
    }

    /**
     * Days of consecutive completion activity. Caps at 365.
     *
     * Streak semantics match get_profile_data: if today has no completion
     * yet but yesterday does, the streak is alive (counts from yesterday).
     * Only breaks if neither today nor yesterday has any completion.
     */
    private static function compute_streak(int $userid): int {
        global $DB;

        $cf = completion_filter::build('cmc');
        $hascompletionon = function (\DateTime $d) use ($DB, $userid, $cf): bool {
            $daystart = (clone $d)->setTime(0, 0, 0)->getTimestamp();
            $dayend   = (clone $d)->setTime(23, 59, 59)->getTimestamp();
            $sql = "SELECT 1
                      FROM {course_modules_completion} cmc
                      {$cf['join']}
                     WHERE cmc.userid = :uid AND cmc.completionstate >= 1
                       AND cmc.timemodified BETWEEN :start AND :end
                       AND {$cf['where']}";
            return $DB->record_exists_sql(
                $sql,
                ['uid' => $userid, 'start' => $daystart, 'end' => $dayend] + $cf['params']
            );
        };

        $checkdate = new \DateTime('now', \core_date::get_user_timezone_object());
        if (!$hascompletionon($checkdate)) {
            $checkdate->modify('-1 day');
            if (!$hascompletionon($checkdate)) {
                return 0;
            }
        }

        $streak = 0;
        for ($i = 0; $i < 365; $i++) {
            if ($hascompletionon($checkdate)) {
                $streak++;
                $checkdate->modify('-1 day');
            } else {
                break;
            }
        }
        return $streak;
    }

    /**
     * Check every enabled achievement against the user's current metrics and
     * unlock those that are met but not yet recorded. Returns the list of
     * newly unlocked achievement codes.
     */
    public static function check_and_unlock(int $userid): array {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_smgp_achievement')) {
            return [];
        }

        $catalog = $DB->get_records('local_smgp_achievement', ['enabled' => 1], 'sortorder ASC');
        if (empty($catalog)) {
            return [];
        }

        $alreadyunlocked = $DB->get_records_menu('local_smgp_user_achievement',
            ['userid' => $userid], '', 'achievementid, id');

        $metrics = self::compute_metrics($userid);
        $now = time();
        $newunlocks = [];

        foreach ($catalog as $ach) {
            if (isset($alreadyunlocked[$ach->id])) {
                continue;
            }
            $current = $metrics[$ach->condition_type] ?? 0;
            if ($current < $ach->condition_value) {
                continue;
            }

            // Unlock.
            $DB->insert_record('local_smgp_user_achievement', (object) [
                'userid'        => $userid,
                'achievementid' => $ach->id,
                'unlocked_at'   => $now,
            ]);

            // Award the achievement's XP reward (if any).
            if ((int) $ach->xp_reward > 0) {
                xp_service::award_xp(
                    $userid,
                    xp_service::SOURCE_ACHIEVEMENT,
                    (int) $ach->id,
                    (int) $ach->xp_reward,
                    'Achievement: ' . $ach->code
                );
            }

            $newunlocks[] = $ach->code;
        }

        return $newunlocks;
    }

    /**
     * Return all achievements with unlock state and progress for a user.
     * Designed for the profile page.
     *
     * @param int    $userid
     * @param string $lang Target language for name/description (es | en | pt_br).
     */
    public static function user_achievements(int $userid, string $lang = 'es'): array {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('local_smgp_achievement')) {
            return [];
        }

        $catalog = $DB->get_records('local_smgp_achievement', ['enabled' => 1], 'sortorder ASC');
        if (empty($catalog)) {
            return [];
        }

        $unlocks = $DB->get_records('local_smgp_user_achievement', ['userid' => $userid], '', 'achievementid, unlocked_at');
        $metrics = self::compute_metrics($userid);

        $result = [];
        foreach ($catalog as $ach) {
            $current = $metrics[$ach->condition_type] ?? 0;
            $unlocked = isset($unlocks[$ach->id]);
            $progresspct = $ach->condition_value > 0
                ? (int) round(min(100, ($current / $ach->condition_value) * 100))
                : 100;
            $result[] = [
                'code'            => $ach->code,
                'name_key'        => $ach->name_key,
                'description_key' => $ach->description_key,
                'name'            => self::translate($ach->code, 'name', $lang),
                'description'     => self::translate($ach->code, 'desc', $lang),
                'icon'            => $ach->icon,
                'condition_type'  => $ach->condition_type,
                'condition_value' => (int) $ach->condition_value,
                'xp_reward'       => (int) $ach->xp_reward,
                'unlocked'        => $unlocked,
                'unlocked_at'     => $unlocked ? (int) $unlocks[$ach->id]->unlocked_at : 0,
                'current_value'   => (int) $current,
                'progress_pct'    => $progresspct,
            ];
        }
        return $result;
    }
}
