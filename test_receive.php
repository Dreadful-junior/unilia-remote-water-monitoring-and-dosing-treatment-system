<?php
$data = json_encode([
    'api_key' => 'your-secret-api-key-123',
    'turbidity' => 10,
    'tds' => 50,
    'temperature' => 25,
    'pump' => 0
]);
$ch = curl_init('http://127.0.0.1/water%20system/api/receive.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
if(curl_errno($ch)){
    echo 'Curl error: ' . curl_error($ch);
}
echo "Response: " . $response;
curl_close($ch);
?>
