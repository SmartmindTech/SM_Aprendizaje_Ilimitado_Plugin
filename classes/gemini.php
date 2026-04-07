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

/**
 * Gemini API client for translating learning objectives.
 *
 * Reads GEMINI_API_KEY and GEMINI_MODEL from the plugin's .env file.
 * Calls Google's Gemini REST API via curl.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class gemini {

    /** @var array|null Cached env config. */
    private static ?array $config = null;

    /** @var array Human-readable language names. */
    private const LANG_NAMES = [
        'en'    => 'English',
        'es'    => 'Spanish',
        'pt_br' => 'Brazilian Portuguese',
    ];

    /**
     * Load config from the plugin's .env file.
     *
     * @return array{api_key: string, model: string}
     */
    private static function get_config(): array {
        if (self::$config !== null) {
            return self::$config;
        }

        $envfile = __DIR__ . '/../.env';
        $apikey = '';
        $model = 'gemma-3-4b-it';

        if (file_exists($envfile)) {
            $lines = file($envfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') {
                    continue;
                }
                $eqpos = strpos($line, '=');
                if ($eqpos === false) {
                    continue;
                }
                $key = trim(substr($line, 0, $eqpos));
                $value = trim(substr($line, $eqpos + 1));
                if ($key === 'GEMINI_API_KEY') {
                    $apikey = $value;
                } else if ($key === 'GEMINI_MODEL') {
                    $model = $value;
                }
            }
        }

        self::$config = ['api_key' => $apikey, 'model' => $model];
        return self::$config;
    }

    /**
     * Translate a single text from one language to another.
     *
     * @param string $text Text to translate.
     * @param string $fromlang Source language code.
     * @param string $tolang Target language code.
     * @return string|null Translated text, or null on failure.
     */
    public static function translate(string $text, string $fromlang, string $tolang): ?string {
        $config = self::get_config();
        if (empty($config['api_key']) || trim($text) === '') {
            return null;
        }

        $fromname = self::LANG_NAMES[$fromlang] ?? $fromlang;
        $toname = self::LANG_NAMES[$tolang] ?? $tolang;

        $prompt = "You are a professional translator. "
            . "Translate the following text from {$fromname} to {$toname}. "
            . "Return ONLY the translation, preserving any HTML formatting. "
            . "Do not add explanations.\n\n"
            . $text;

        return self::call_api($prompt, $config, 0.3, 2000);
    }

    /**
     * Translate a batch of texts from one language to another in a single API call.
     *
     * @param array $texts Array of strings to translate.
     * @param string $fromlang Source language code (en, es, pt_br).
     * @param string $tolang Target language code.
     * @return array|null Array of translated strings (same order), or null on failure.
     */
    public static function translate_batch(array $texts, string $fromlang, string $tolang): ?array {
        $config = self::get_config();
        if (empty($config['api_key']) || empty($texts)) {
            return null;
        }

        $fromname = self::LANG_NAMES[$fromlang] ?? $fromlang;
        $toname = self::LANG_NAMES[$tolang] ?? $tolang;

        // Build numbered list for clear parsing.
        $numbered = '';
        foreach (array_values($texts) as $i => $text) {
            $numbered .= ($i + 1) . '. ' . trim($text) . "\n";
        }

        $prompt = "You are a professional translator. "
            . "Translate each numbered line below from {$fromname} to {$toname}. "
            . "Return ONLY the translations, one per line, keeping the same numbering. "
            . "Do not add explanations or extra text.\n\n"
            . $numbered;

        $response = self::call_api($prompt, $config, 0.3, 1000);
        if ($response === null) {
            return null;
        }

        // Parse numbered response lines.
        $lines = explode("\n", trim($response));
        $result = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            // Strip leading number and dot/dash: "1. text" or "1- text" or "1) text".
            $cleaned = preg_replace('/^\d+[\.\)\-]\s*/', '', $line);
            $result[] = $cleaned;
        }

        // Ensure we got the same count.
        if (count($result) !== count($texts)) {
            // Try to salvage: if we got more, trim; if fewer, pad with originals.
            if (count($result) > count($texts)) {
                $result = array_slice($result, 0, count($texts));
            } else {
                $textsvalues = array_values($texts);
                while (count($result) < count($texts)) {
                    $result[] = $textsvalues[count($result)] ?? '';
                }
            }
        }

        return $result;
    }

    /**
     * Call the Gemini REST API.
     *
     * @param string $prompt The full prompt (system + user combined for Gemma models).
     * @param array $config API config with 'api_key' and 'model'.
     * @param float $temperature Sampling temperature.
     * @param int $maxtokens Maximum output tokens.
     * @return string|null The response text, or null on error.
     */
    private static function call_api(string $prompt, array $config, float $temperature = 0.5, int $maxtokens = 500): ?string {
        $model = $config['model'];
        $apikey = $config['api_key'];

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apikey}";

        $body = [
            'contents' => [
                ['parts' => [['text' => $prompt]]],
            ],
            'generationConfig' => [
                'temperature' => $temperature,
                'maxOutputTokens' => $maxtokens,
            ],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpcode !== 200) {
            debugging("Gemini API error (HTTP {$httpcode}): {$error}", DEBUG_DEVELOPER);
            return null;
        }

        $data = json_decode($response, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        return $text ? trim($text) : null;
    }
}
