<?php
require("config/config.php");
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
$loading_file = (preg_match('/^([-\.\w]+)$/', $loading_file) > 0) ? $loading_file : "loading.txt"; // safe filename
if(is_file($loading_file)) {
    $loaded = file($loading_file);
    $percent = (int)$loaded[0];
}else{
    $percent = 100;
}
echo "retry: 2000\ndata: {$percent}\n\n"; // update client every 2 seconds
flush();
?>




