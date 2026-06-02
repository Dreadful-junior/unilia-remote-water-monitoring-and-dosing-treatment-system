<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require '../config/db.php';

// Accept GET or POST
$input = json_decode(file_get_contents("php://input"), true) ?: $_GET;

$start_date = $input['start_date'] ?? date('Y-m-d 00:00:00', strtotime('-7 days'));
$end_date   = $input['end_date']   ?? date('Y-m-d 23:59:59');
$type       = $input['type']       ?? 'water_quality';
$format     = $input['format']     ?? 'preview';
$log_only   = !empty($input['log_only']); // If true, only log the generation, don't return data (used when user actually hits export after previewing)

$user_id = $_SESSION['user_id'];

try {
    // 1. Fetch Monitoring Settings
    $mon_result = $pdo->query("SELECT max_turbidity, max_tds FROM monitoring_settings WHERE id = 1");
    $mon = $mon_result->fetch(PDO::FETCH_ASSOC);
    $max_turbidity = $mon ? (float)$mon['max_turbidity'] : 5.0;
    $max_tds       = $mon ? (int)$mon['max_tds'] : 500;

    // 2. Fetch Data
    $stmt = $pdo->prepare("
        SELECT turbidity, tds, temperature, water_level, pump_status, recorded_at
        FROM sensor_data
        WHERE recorded_at BETWEEN ? AND ?
        ORDER BY recorded_at DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Compute Metrics
    $totalSamples = count($rows);
    $activeSamples = 0;
    $safeSamples = 0;
    $reliabilitySamples = 0;
    
    $sumTurb = 0;
    $sumTds = 0;
    $sumTemp = 0;
    $sumLevel = 0;

    $minTemp = 999;
    $maxTemp = -999;
    $minLevel = 999;
    $maxLevel = -999;

    foreach ($rows as &$r) {
        $r['pump_status'] = (int)$r['pump_status'];
        if ($r['pump_status'] === 1) $activeSamples++;
        
        $isSafe = ($r['turbidity'] <= $max_turbidity && $r['tds'] <= $max_tds);
        if ($isSafe) $safeSamples++;
        
        if ($r['turbidity'] > 0 || $r['tds'] > 0) $reliabilitySamples++;

        $t = (float)$r['turbidity'];
        $td = (float)$r['tds'];
        $tmp = (float)$r['temperature'];
        $lvl = (float)($r['water_level'] ?? 0);

        $sumTurb += $t;
        $sumTds  += $td;
        $sumTemp += $tmp;
        $sumLevel += $lvl;

        if ($tmp < $minTemp && $tmp > -50) $minTemp = $tmp;
        if ($tmp > $maxTemp) $maxTemp = $tmp;
        
        if ($lvl < $minLevel && $lvl > 0) $minLevel = $lvl;
        if ($lvl > $maxLevel) $maxLevel = $lvl;
    }

    if ($minTemp == 999) $minTemp = 0;
    if ($maxTemp == -999) $maxTemp = 0;
    if ($minLevel == 999) $minLevel = 0;
    if ($maxLevel == -999) $maxLevel = 0;

    $summary = [
        'pump_uptime'   => $totalSamples > 0 ? round(($activeSamples / $totalSamples) * 100, 1) : 0,
        'water_safety'  => $totalSamples > 0 ? round(($safeSamples / $totalSamples) * 100, 1) : 0,
        'reliability'   => $totalSamples > 0 ? round(($reliabilitySamples / $totalSamples) * 100, 1) : 0,
        'avg_turbidity' => $totalSamples > 0 ? round($sumTurb / $totalSamples, 2) : 0,
        'avg_tds'       => $totalSamples > 0 ? round($sumTds / $totalSamples) : 0,
        'avg_temp'      => $totalSamples > 0 ? round($sumTemp / $totalSamples, 1) : 0,
        'avg_level'     => $totalSamples > 0 ? round($sumLevel / $totalSamples, 1) : 0,
        'min_temp'      => round($minTemp, 1),
        'max_temp'      => round($maxTemp, 1),
        'min_level'     => round($minLevel, 1),
        'max_level'     => round($maxLevel, 1),
        'active_count'  => $activeSamples,
        'total_records' => $totalSamples,
        'safe_records'  => $safeSamples,
        'dosing_sessions' => $activeSamples // We can improve this logic later if needed
    ];

    // 4. Log the report generation (if format is pdf or csv, meaning they actually exported it)
    if ($format === 'pdf' || $format === 'csv' || $log_only) {
        $filename = "{$type}_report_" . date('Ymd_His') . ".{$format}";
        $log_stmt = $pdo->prepare("
            INSERT INTO generated_reports (filename, type, format, start_date, end_date, generated_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $log_stmt->execute([$filename, $type, $format, $start_date, $end_date, $user_id]);

        if ($log_only) {
            echo json_encode(['success' => true, 'filename' => $filename]);
            exit;
        }
    }

    echo json_encode([
        'success'    => true,
        'start_date' => $start_date,
        'end_date'   => $end_date,
        'summary'    => $summary,
        'data'       => $rows,
        'thresholds' => ['max_turbidity' => $max_turbidity, 'max_tds' => $max_tds]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
