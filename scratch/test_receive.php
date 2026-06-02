<?php
$data = array(
    'api_key' => 'your-secret-api-key-123',
    'turbidity' => 10.0,
    'tds' => 100.0,
    'temperature' => 25.0,
    'water_level' => 50.0,
    'pump' => 0
);
$options = array(
    'http' => array(
        'header'  => "Content-type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($data),
        'ignore_errors' => true // To get body even on 500
    )
);
$context  = stream_context_create($options);
$result = file_get_contents('http://127.0.0.1/water%20system/api/receive.php', false, $context);
echo "Result: $result\n";
