<?php
require("config/config.php");
header('Content-Type: text/event-stream');
header('connection: keep-alive');
header('Cache-Control: no-cache');
$loading_file = (preg_match('/^([-\.\w]+)$/', $loading_file) > 0) ? $loading_file : "loading.txt"; // safe filename
if (is_file($loading_file)) {
    $loaded = file($loading_file);
    list($percent, $task) = explode("|", $loaded[0]);
    $percent = (int)$percent;
}
$data = $percent . '|' . $task;
echo "id:1\nretry: 2000\ndata: {$data}\n\n";
flush();