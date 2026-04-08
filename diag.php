<?php
define("CLI_SCRIPT", true);
require("/var/www/html/config.php");
\core\session\manager::set_user($DB->get_record("user", ["id" => 107]));
$result = \local_sm_graphics_plugin\external\get_browsed_courses::execute(4);
echo "Rows returned: " . count($result) . "\n\n";
foreach ($result as $r) {
    echo "  id={$r['id']} fullname={$r['fullname']} categoryname={$r['categoryname']} image=" . (empty($r['image']) ? '(none)' : '(set)') . "\n";
}
