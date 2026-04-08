<?php
// Quick test script for Graph API email sending.
// Visit: https://your-site/local/sm_graphics_plugin/test_email.php
// DELETE this file after testing.

define('CLI_SCRIPT', false);
define('NO_MOODLE_COOKIES', false);

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('moodle/site:config', \context_system::instance());

echo '<pre>';

// Test 1: Check config loading.
require_once(__DIR__ . '/lib.php');
$conf = local_sm_graphics_plugin_load_config();
echo "Config loaded: " . (empty($conf) ? 'EMPTY (config.php not found)' : 'OK') . "\n";
echo "azure_tenant_id: " . (!empty($conf['azure_tenant_id']) ? 'SET' : 'MISSING') . "\n";
echo "azure_client_id: " . (!empty($conf['azure_client_id']) ? 'SET' : 'MISSING') . "\n";
echo "azure_client_secret: " . (!empty($conf['azure_client_secret']) ? 'SET' : 'MISSING') . "\n";
echo "smtp_noreply (sender): " . ($conf['smtp_noreply'] ?? 'NOT SET') . "\n\n";

require_once(__DIR__ . '/classes/graph_mailer.php');

$to = optional_param('to', $USER->email, PARAM_EMAIL);

echo '<h2>SmartMind Graph API Email Test</h2>';
echo '<p>Sending test email to: <strong>' . s($to) . '</strong></p>';

$sent = \local_sm_graphics_plugin\graph_mailer::send(
    $to,
    'SmartMind Test Email',
    '<h1>Test</h1><p>If you see this, Graph API email works correctly.</p>',
    'Test - If you see this, Graph API email works correctly.'
);

if ($sent) {
    echo '<p style="color:green;font-size:20px"><strong>SUCCESS</strong> — email sent. Check your inbox.</p>';
} else {
    $error = \local_sm_graphics_plugin\graph_mailer::get_last_error();
    echo '<p style="color:red;font-size:20px"><strong>FAILED</strong></p>';
    echo '<pre>' . s($error) . '</pre>';
}
