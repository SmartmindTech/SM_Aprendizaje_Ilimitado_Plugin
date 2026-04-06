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
 * Microsoft Graph API client for SharePoint integration.
 *
 * Uses OAuth2 client_credentials flow to authenticate against Azure AD
 * and interact with SharePoint Online via the Microsoft Graph API.
 *
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client {

    /** @var string|null Cached OAuth2 access token. */
    private static ?string $access_token = null;

    /** @var int Token expiry timestamp. */
    private static int $token_expires = 0;

    /** @var string|null Last error for diagnostics. */
    private static ?string $last_error = null;

    /**
     * Get the last error message.
     * @return string|null
     */
    public static function get_last_error(): ?string {
        return self::$last_error;
    }

    /**
     * Get plugin config for SharePoint.
     *
     * @return array{tenant_id: string, client_id: string, client_secret: string, site_url: string}
     */
    private static function get_config(): array {
        // Read from plugin config.php first, fall back to admin settings.
        $conf = \local_sm_graphics_plugin_load_config();
        return [
            'tenant_id'     => $conf['azure_tenant_id'] ?? get_config('local_sm_graphics_plugin', 'sp_tenant_id') ?: '',
            'client_id'     => $conf['azure_client_id'] ?? get_config('local_sm_graphics_plugin', 'sp_client_id') ?: '',
            'client_secret' => $conf['azure_client_secret'] ?? get_config('local_sm_graphics_plugin', 'sp_client_secret') ?: '',
            'site_url'      => $conf['sp_site_url'] ?? get_config('local_sm_graphics_plugin', 'sp_site_url') ?: '',
        ];
    }

    /**
     * Check whether SharePoint credentials are configured.
     *
     * @return bool
     */
    public static function is_configured(): bool {
        $config = self::get_config();
        return !empty($config['tenant_id']) && !empty($config['client_id'])
            && !empty($config['client_secret']);
    }

    /**
     * Obtain an OAuth2 access token via client_credentials grant.
     *
     * @return string|null Access token or null on failure.
     */
    public static function get_access_token(): ?string {
        if (self::$access_token !== null && time() < self::$token_expires) {
            return self::$access_token;
        }

        $config = self::get_config();
        if (empty($config['tenant_id']) || empty($config['client_id']) || empty($config['client_secret'])) {
            debugging('SharePoint client: missing Azure AD credentials.', DEBUG_DEVELOPER);
            return null;
        }

        $url = "https://login.microsoftonline.com/{$config['tenant_id']}/oauth2/v2.0/token";

        $secret = $config['client_secret'];
        $secretlen = strlen($secret);
        $secrethint = $secretlen > 4 ? substr($secret, 0, 4) . '***(' . $secretlen . ' chars)' : '(empty or too short: ' . $secretlen . ')';
        self::$last_error = "Debug: tenant={$config['tenant_id']}, client_id={$config['client_id']}, secret={$secrethint}";

        $postfields = http_build_query([
            'grant_type'    => 'client_credentials',
            'client_id'     => $config['client_id'],
            'client_secret' => $secret,
            'scope'         => 'https://graph.microsoft.com/.default',
        ], '', '&');

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postfields,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpcode !== 200) {
            self::$last_error = "OAuth error (HTTP {$httpcode}): {$error} - {$response} | Debug: secret={$secrethint}";
            debugging("SharePoint OAuth error (HTTP {$httpcode}): {$error} - {$response}", DEBUG_DEVELOPER);
            return null;
        }

        $data = json_decode($response, true);
        if (empty($data['access_token'])) {
            self::$last_error = 'OAuth: no access_token en la respuesta. Response: ' . substr($response, 0, 200);
            debugging('SharePoint OAuth: no access_token in response.', DEBUG_DEVELOPER);
            return null;
        }

        self::$access_token = $data['access_token'];
        self::$token_expires = time() + (int) ($data['expires_in'] ?? 3600) - 60;

        return self::$access_token;
    }

    /**
     * Parse a SharePoint browser URL into Graph API components.
     *
     * Accepts URLs like:
     *   https://org.sharepoint.com/sites/LMS/Shared Documents/Cursos/FOLDER
     *   https://org.sharepoint.com/:f:/s/LMS/EaBcDeFg...
     *
     * @param string $url SharePoint browser URL.
     * @return array|null {site_id, drive_id, item_path} or null on failure.
     */
    public static function parse_sharepoint_url(string $url): ?array {
        $parsed = parse_url($url);
        if (empty($parsed['host']) || empty($parsed['path'])) {
            return null;
        }

        $hostname = $parsed['host'];
        $path = urldecode($parsed['path']);

        // Browser URLs use AllItems.aspx with the real path in the "id" query param.
        // Example: /sites/producto/Documentos%20compartidos/Forms/AllItems.aspx?id=/sites/producto/Documentos%20compartidos/FOLDER
        if (preg_match('/AllItems\.aspx$/i', $path) && !empty($parsed['query'])) {
            parse_str($parsed['query'], $queryparams);
            if (!empty($queryparams['id'])) {
                $path = urldecode($queryparams['id']);
                debugging("SharePoint URL: extracted path from id param: {$path}", DEBUG_DEVELOPER);
            }
        }

        // Also handle the case where the id param is in the original URL but parse_url
        // didn't split the query (e.g. URL was partially encoded).
        if (preg_match('/AllItems\.aspx/i', $path) && preg_match('/[?&]id=([^&]+)/i', $url, $idmatch)) {
            $path = urldecode($idmatch[1]);
            debugging("SharePoint URL: extracted path from raw URL id param: {$path}", DEBUG_DEVELOPER);
        }

        // Extract site relative path: /sites/SITENAME or /teams/SITENAME.
        if (!preg_match('#^/(sites|teams)/([^/]+)#i', $path, $sitematch)) {
            debugging("SharePoint URL: cannot extract site path from {$path}", DEBUG_DEVELOPER);
            return null;
        }

        $siterelpath = "/{$sitematch[1]}/{$sitematch[2]}";

        // Resolve site ID via Graph API.
        $sitedata = self::api_request('GET', "/sites/{$hostname}:{$siterelpath}");
        if ($sitedata === null || empty($sitedata['id'])) {
            return null;
        }
        $siteid = $sitedata['id'];

        // Get the default document library drive.
        $drives = self::api_request('GET', "/sites/{$siteid}/drives");
        if ($drives === null || empty($drives['value'])) {
            return null;
        }

        // Find the drive that matches the path, or default to the first.
        $driveid = $drives['value'][0]['id'];

        // Extract the folder path after the document library name.
        // Path format: /sites/SITE/DocLibName/folder/subfolder
        $remainder = substr($path, strlen($siterelpath));
        $remainder = ltrim($remainder, '/');

        // The first segment after the site is the document library name.
        $segments = explode('/', $remainder);
        if (count($segments) < 2) {
            // No subfolder specified — root of the library.
            return ['site_id' => $siteid, 'drive_id' => $driveid, 'item_path' => '/'];
        }

        $doclibname = $segments[0];

        // Try to match drive by name.
        foreach ($drives['value'] as $drive) {
            if (strcasecmp($drive['name'], $doclibname) === 0) {
                $driveid = $drive['id'];
                break;
            }
        }

        // The remaining path after the doc lib name is the folder path.
        $folderpath = '/' . implode('/', array_slice($segments, 1));

        return [
            'site_id'   => $siteid,
            'drive_id'  => $driveid,
            'item_path' => $folderpath,
        ];
    }

    /**
     * List children of a folder in SharePoint.
     *
     * @param string $siteid Graph site ID.
     * @param string $driveid Graph drive ID.
     * @param string $path Folder path within the drive (e.g. /Cursos/FOLDER).
     * @return array|null Array of items or null on failure.
     */
    public static function list_folder(string $siteid, string $driveid, string $path): ?array {
        $path = rtrim($path, '/');
        if ($path === '' || $path === '/') {
            $endpoint = "/sites/{$siteid}/drives/{$driveid}/root/children";
        } else {
            // Encode each path segment individually for the Graph API colon notation.
            $segments = explode('/', trim($path, '/'));
            $encodedpath = '/' . implode('/', array_map('rawurlencode', $segments));
            $endpoint = "/sites/{$siteid}/drives/{$driveid}/root:{$encodedpath}:/children";
        }

        $data = self::api_request('GET', $endpoint . '?$top=200');
        if ($data === null || !isset($data['value'])) {
            return null;
        }

        $items = [];
        foreach ($data['value'] as $item) {
            $items[] = [
                'name'      => $item['name'],
                'id'        => $item['id'],
                'size'      => $item['size'] ?? 0,
                'is_folder' => isset($item['folder']),
                'mime_type' => $item['file']['mimeType'] ?? null,
                'web_url'   => $item['webUrl'] ?? '',
            ];
        }

        return $items;
    }

    /**
     * Create an anonymous sharing link for a file.
     *
     * @param string $siteid Graph site ID.
     * @param string $driveid Graph drive ID.
     * @param string $itemid Item ID in the drive.
     * @return string|null Sharing URL or null on failure.
     */
    public static function create_sharing_link(string $siteid, string $driveid, string $itemid): ?string {
        $body = [
            'type'  => 'view',
            'scope' => 'anonymous',
        ];

        $data = self::api_request('POST', "/sites/{$siteid}/drives/{$driveid}/items/{$itemid}/createLink", $body);
        if ($data === null || empty($data['link']['webUrl'])) {
            return null;
        }

        return $data['link']['webUrl'];
    }

    /**
     * Get a pre-authenticated download URL for a file (no sharing link needed).
     *
     * Uses the Graph API @microsoft.graph.downloadUrl property which provides
     * a short-lived pre-authenticated URL that works without further auth.
     *
     * @param string $siteid Graph site ID.
     * @param string $driveid Graph drive ID.
     * @param string $itemid Item ID in the drive.
     * @return string|null Download URL or null on failure.
     */
    public static function get_download_url(string $siteid, string $driveid, string $itemid): ?string {
        $data = self::api_request('GET', "/sites/{$siteid}/drives/{$driveid}/items/{$itemid}");
        if ($data === null) {
            return null;
        }

        // The @microsoft.graph.downloadUrl is a pre-authenticated temporary URL.
        if (!empty($data['@microsoft.graph.downloadUrl'])) {
            return $data['@microsoft.graph.downloadUrl'];
        }

        // Fallback: construct a content URL (requires auth but works for SCORM external packages).
        if (!empty($data['webUrl'])) {
            return $data['webUrl'];
        }

        return null;
    }

    /**
     * Download a file from SharePoint to a local temp path.
     *
     * @param string $siteid Graph site ID.
     * @param string $driveid Graph drive ID.
     * @param string $itemid Item ID.
     * @param string $filename Desired local filename.
     * @return string|null Absolute path to the downloaded file or null on failure.
     */
    public static function download_file(string $siteid, string $driveid, string $itemid, string $filename): ?string {
        global $CFG;

        $tempdir = make_temp_directory('local_sm_courseloader');
        $filepath = $tempdir . '/' . clean_filename($filename);

        $content = self::api_request(
            'GET',
            "/sites/{$siteid}/drives/{$driveid}/items/{$itemid}/content",
            null,
            true // raw response
        );

        if ($content === null) {
            return null;
        }

        if (file_put_contents($filepath, $content) === false) {
            debugging("SharePoint: failed to write file to {$filepath}", DEBUG_DEVELOPER);
            return null;
        }

        return $filepath;
    }

    /**
     * Make an authenticated request to the Microsoft Graph API.
     *
     * @param string $method HTTP method (GET, POST, etc.).
     * @param string $endpoint Graph API endpoint (relative to https://graph.microsoft.com/v1.0).
     * @param array|null $body Request body for POST/PATCH (will be JSON-encoded).
     * @param bool $raw If true, return raw response body instead of decoded JSON.
     * @return mixed Decoded JSON array, raw string, or null on error.
     */
    /**
     * Build a signed proxy URL for a SharePoint file item.
     *
     * The URL points to sp_download.php with an HMAC token so that
     * Moodle's SCORM cron fetcher can download without a browser session.
     *
     * @param string $itemid SharePoint item ID.
     * @return string Signed proxy URL.
     */
    public static function build_proxy_url(string $itemid): string {
        global $CFG;
        $secret = self::get_config()['client_secret'];
        $token = hash_hmac('sha256', $itemid, $secret);
        return $CFG->wwwroot . '/local/sm_graphics_plugin/pages/sp_download.php?'
            . 'item=' . urlencode($itemid) . '&token=' . $token;
    }

    /**
     * Public wrapper for api_request, used by sp_download.php proxy.
     */
    public static function api_request_public(string $method, string $endpoint, ?array $body = null, bool $raw = false) {
        return self::api_request($method, $endpoint, $body, $raw);
    }

    private static function api_request(string $method, string $endpoint, ?array $body = null, bool $raw = false) {
        $token = self::get_access_token();
        if ($token === null) {
            return null;
        }

        $url = 'https://graph.microsoft.com/v1.0' . $endpoint;

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
        ]);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            debugging("SharePoint Graph API curl error: {$error}", DEBUG_DEVELOPER);
            return null;
        }

        if ($raw) {
            return ($httpcode >= 200 && $httpcode < 400) ? $response : null;
        }

        if ($httpcode < 200 || $httpcode >= 300) {
            self::$last_error = "Graph API error (HTTP {$httpcode}) en {$method} {$endpoint}: " . substr($response, 0, 300);
            debugging("SharePoint Graph API error (HTTP {$httpcode}): {$response}", DEBUG_DEVELOPER);
            return null;
        }

        return json_decode($response, true);
    }
}
