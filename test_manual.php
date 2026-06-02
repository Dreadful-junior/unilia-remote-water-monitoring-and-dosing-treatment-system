<?php
$ch = curl_init('http://127.0.0.1/water%20system/api/toggle_pump.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['action' => 'pump_control', 'state' => 'on']));
$response = curl_exec($ch);
echo "Toggle Response: " . $response . "\n";
curl_close($ch);

$data = json_encode(['api_key' => 'your-secret-api-key-123']);
$ch2 = curl_init('http://127.0.0.1/water%20system/api/receive.php');
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_POST, true);
curl_setopt($ch2, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
echo "Receive Response: " . curl_exec($ch2) . "\n";
curl_close($ch2);
?>
