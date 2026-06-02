<?php
$start = microtime(true);
$_POST['action'] = 'pump_control';
$_POST['state'] = 'off';
ob_start();
include 'api/toggle_pump.php';
$out = ob_get_clean();
$end = microtime(true);
echo "Time: " . ($end - $start) . "s\nOutput: $out";
