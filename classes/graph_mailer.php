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
 * Send emails via Microsoft Graph API using OAuth2 client credentials.
 *
 * Requires an Azure AD app registration with the "Mail.Send" application
 * permission (not delegated). The sender address must be a real mailbox
 * in the tenant (e.g. noreply-smartlearning@smartmind.net).
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class graph_mailer {

    /** @var string|null Cached access token. */
    private static ?string $token = null;

    /** @var int Token expiry timestamp. */
    private static int $token_expires = 0;

    /** @var string|null Last error message for debugging. */
    private static ?string $last_error = null;

    /**
     * Get the last error message.
     */
    public static function get_last_error(): ?string {
        return self::$last_error;
    }

    /**
     * Obtain an OAuth2 access token via client_credentials grant.
     *
     * @return string|null Access token or null on failure.
     */
    private static function get_access_token(): ?string {
        if (self::$token !== null && time() < self::$token_expires) {
            return self::$token;
        }

        require_once(dirname(__DIR__) . '/lib.php');
        $conf = \local_sm_graphics_plugin_load_config();
        $tenantid = $conf['azure_tenant_id'] ?? '';
        $clientid = $conf['azure_client_id'] ?? '';
        $secret   = $conf['azure_client_secret'] ?? '';

        if (empty($tenantid) || empty($clientid) || empty($secret)) {
            self::$last_error = 'Graph mailer: missing Azure AD credentials in config.php';
            return null;
        }

        $url = "https://login.microsoftonline.com/{$tenantid}/oauth2/v2.0/token";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type'    => 'client_credentials',
                'client_id'     => $clientid,
                'client_secret' => $secret,
                'scope'         => 'https://graph.microsoft.com/.default',
            ], '', '&'),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlerr  = curl_error($ch);
        curl_close($ch);

        if ($curlerr || $httpcode !== 200) {
            self::$last_error = "Graph OAuth error (HTTP {$httpcode}): {$curlerr} — " . substr($response, 0, 300);
            return null;
        }

        $data = json_decode($response, true);
        if (empty($data['access_token'])) {
            self::$last_error = 'Graph OAuth: no access_token in response.';
            return null;
        }

        self::$token = $data['access_token'];
        self::$token_expires = time() + ($data['expires_in'] ?? 3500) - 60;
        return self::$token;
    }

    /**
     * Send an email via Microsoft Graph API.
     *
     * @param string $to        Recipient email address.
     * @param string $subject   Email subject.
     * @param string $htmlbody  HTML body content.
     * @param string $textbody  Plain-text body (fallback).
     * @return bool True on success, false on failure.
     */
    public static function send(string $to, string $subject, string $htmlbody, string $textbody = ''): bool {
        $token = self::get_access_token();
        if (!$token) {
            return false;
        }

        $conf   = \local_sm_graphics_plugin_load_config();
        $sender = $conf['smtp_noreply'] ?? 'noreply-smartlearning@smartmind.net';

        $payload = [
            'message' => [
                'subject' => $subject,
                'body'    => [
                    'contentType' => 'HTML',
                    'content'     => $htmlbody,
                ],
                'toRecipients' => [
                    [
                        'emailAddress' => [
                            'address' => $to,
                        ],
                    ],
                ],
            ],
            'saveToSentItems' => false,
        ];

        $url = "https://graph.microsoft.com/v1.0/users/{$sender}/sendMail";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlerr  = curl_error($ch);
        curl_close($ch);

        // Graph returns 202 Accepted on success.
        if ($httpcode === 202) {
            self::$last_error = null;
            return true;
        }

        self::$last_error = "Graph sendMail error (HTTP {$httpcode}): {$curlerr} — " . substr($response, 0, 500);
        return false;
    }
}
