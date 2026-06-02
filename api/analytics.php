<?php
/**
 * Analytics API - Vanilla PHP
 * Returns statistical analysis of sensor data
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache');

require '../db_connect.php';

// --- Period resolution ---
$period = isset($_GET['period']) ? $conn->real_escape_string($_GET['period']) : '7d';

if ($period === '24h') {
    $start_date  = date('Y-m-d H:i:s', strtotime('-24 hours'));
    $end_date    = date('Y-m-d H:i:s');
    $period_label = 'Last 24 hours';
    // Previous window (the 24h before that)
    $prev_start  = date('Y-m-d H:i:s', strtotime('-48 hours'));
    $prev_end    = $start_date;
} elseif ($period === '30d') {
    $start_date  = date('Y-m-d 00:00:00', strtotime('-30 days'));
    $end_date    = date('Y-m-d 23:59:59');
    $period_label = 'Last 30 days';
    $prev_start  = date('Y-m-d 00:00:00', strtotime('-60 days'));
    $prev_end    = date('Y-m-d 23:59:59', strtotime('-30 days'));
} else {
    // Default: 7d
    $period      = '7d';
    $start_date  = date('Y-m-d 00:00:00', strtotime('-7 days'));
    $end_date    = date('Y-m-d 23:59:59');
    $period_label = 'Last 7 days';
    $prev_start  = date('Y-m-d 00:00:00', strtotime('-14 days'));
    $prev_end    = date('Y-m-d 23:59:59', strtotime('-7 days'));
}

try {
    // ----------------------------------------------------------------
    // Helper: run a stats query for a given date range
    // ----------------------------------------------------------------
    $getStats = function(string $col, string $s, string $e) use ($conn): array {
        $col = $conn->real_escape_string($col);
        $s   = $conn->real_escape_string($s);
        $e   = $conn->real_escape_string($e);
        $r   = $conn->query("
            SELECT
                AVG(`$col`)    AS avg,
                MIN(`$col`)    AS min,
                MAX(`$col`)    AS max,
                STDDEV(`$col`) AS stddev,
                COUNT(*)       AS cnt
            FROM sensor_data
            WHERE recorded_at BETWEEN '$s' AND '$e'
        ");
        $row = $r ? $r->fetch_assoc() : [];
        return [
            'avg'    => $row['avg']    !== null ? round((float)$row['avg'],    2) : null,
            'min'    => $row['min']    !== null ? round((float)$row['min'],    2) : null,
            'max'    => $row['max']    !== null ? round((float)$row['max'],    2) : null,
            'stddev' => $row['stddev'] !== null ? round((float)$row['stddev'], 2) : null,
            'count'  => (int)($row['cnt'] ?? 0),
        ];
    };

    // ----------------------------------------------------------------
    // Current period stats
    // ----------------------------------------------------------------
    $turbidity_stats = $getStats('turbidity', $start_date, $end_date);
    $tds_stats       = $getStats('tds',       $start_date, $end_date);
    $temp_stats      = $getStats('temperature', $start_date, $end_date);

    // Round TDS to integers for display
    foreach (['avg','min','max','stddev'] as $k) {
        if ($tds_stats[$k] !== null) $tds_stats[$k] = (int)$tds_stats[$k];
    }
    if ($temp_stats['avg'] !== null) $temp_stats['avg'] = round($temp_stats['avg'], 1);

    // ----------------------------------------------------------------
    // Previous period stats (for trend deltas)
    // ----------------------------------------------------------------
    $prev_turbidity = $getStats('turbidity',   $prev_start, $prev_end);
    $prev_tds       = $getStats('tds',         $prev_start, $prev_end);
    $prev_temp      = $getStats('temperature', $prev_start, $prev_end);

    $calcDelta = function($cur, $prev): ?float {
        if ($cur === null || $prev === null || $prev == 0) return null;
        return round((($cur - $prev) / $prev) * 100, 1);
    };

    // ----------------------------------------------------------------
    // Latest sensor reading timestamp
    // ----------------------------------------------------------------
    $latest_result = $conn->query("SELECT MAX(recorded_at) AS last_reading FROM sensor_data");
    $latest_row    = $latest_result ? $latest_result->fetch_assoc() : [];
    $latest_reading_at = $latest_row['last_reading'] ?? null;

    // ----------------------------------------------------------------
    // Total record count
    // ----------------------------------------------------------------
    $count_result  = $conn->query("SELECT COUNT(*) AS total FROM sensor_data WHERE recorded_at BETWEEN '$start_date' AND '$end_date'");
    $total_records = $count_result ? (int)$count_result->fetch_assoc()['total'] : 0;

    // ----------------------------------------------------------------
    // Monitoring thresholds
    // ----------------------------------------------------------------
    $mon_result = $conn->query("SELECT max_turbidity, max_tds FROM monitoring_settings WHERE id = 1");
    $mon = ($mon_result && $mon_result->num_rows > 0)
        ? $mon_result->fetch_assoc()
        : ['max_turbidity' => 5.0, 'max_tds' => 500];
    $max_turbidity = (float)$mon['max_turbidity'];
    $max_tds       = (int)$mon['max_tds'];

    // ----------------------------------------------------------------
    // Threshold breaches — use prepared statement for threshold values
    // ----------------------------------------------------------------
    $stmt = $conn->prepare("
        SELECT
            SUM(turbidity > ?)              AS turbidity_exceed,
            SUM(turbidity > ? * 1.5)        AS turbidity_critical,
            SUM(tds > ?)                    AS tds_exceed,
            SUM(tds > ? * 1.5)              AS tds_critical
        FROM sensor_data
        WHERE recorded_at BETWEEN ? AND ?
    ");
    $stmt->bind_param('ddddss', $max_turbidity, $max_turbidity, $max_tds, $max_tds, $start_date, $end_date);
    $stmt->execute();
    $br = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $breaches = [
        'turbidity_exceed'   => (int)($br['turbidity_exceed']   ?? 0),
        'turbidity_critical' => (int)($br['turbidity_critical'] ?? 0),
        'tds_exceed'         => (int)($br['tds_exceed']         ?? 0),
        'tds_critical'       => (int)($br['tds_critical']       ?? 0),
    ];
    $total_breaches  = $breaches['turbidity_exceed'] + $breaches['tds_exceed'];
    $breach_rate     = $total_records > 0 ? round(($total_breaches / $total_records) * 100, 1) : 0;

    // ----------------------------------------------------------------
    // Quality / risk score
    // ----------------------------------------------------------------
    $avg_turb = $turbidity_stats['avg'] ?? 0;
    $avg_tds  = $tds_stats['avg']       ?? 0;
    $turbidity_score = $avg_turb > 0 ? max(0, min(100, 100 - (($avg_turb / $max_turbidity) * 80))) : 100;
    $tds_score       = $avg_tds  > 0 ? max(0, min(100, 100 - (($avg_tds  / $max_tds)       * 80))) : 100;
    $quality_score   = round(($turbidity_score + $tds_score) / 2);

    if      ($quality_score >= 90) $risk_label = 'OPTIMAL';
    elseif  ($quality_score >= 70) $risk_label = 'STABLE';
    elseif  ($quality_score >= 50) $risk_label = 'CAUTION';
    else                           $risk_label = 'CRITICAL';

    // ----------------------------------------------------------------
    // Hourly trend data (chart)
    // ----------------------------------------------------------------
    $hourly_result = $conn->query("
        SELECT
            DATE_FORMAT(recorded_at, '%Y-%m-%d %H:00:00') AS hour,
            AVG(turbidity)   AS avg_turbidity,
            AVG(tds)         AS avg_tds,
            AVG(temperature) AS avg_temp,
            COUNT(*)         AS readings
        FROM sensor_data
        WHERE recorded_at BETWEEN '$start_date' AND '$end_date'
        GROUP BY DATE_FORMAT(recorded_at, '%Y-%m-%d %H:00:00')
        ORDER BY hour ASC
    ");

    $hourly_data = [];
    while ($row = $hourly_result->fetch_assoc()) {
        $hourly_data[] = [
            'hour'        => $row['hour'],
            'turbidity'   => round((float)$row['avg_turbidity'], 2),
            'tds'         => (int)round((float)$row['avg_tds']),
            'temperature' => round((float)$row['avg_temp'], 1),
            'readings'    => (int)$row['readings'],
        ];
    }

    // ----------------------------------------------------------------
    // Response
    // ----------------------------------------------------------------
    echo json_encode([
        'success'          => true,
        'period'           => $period,
        'period_label'     => $period_label,
        'start_date'       => $start_date,
        'end_date'         => $end_date,
        'latest_reading_at'=> $latest_reading_at,
        'record_count'     => $total_records,
        'stats' => [
            'turbidity'   => $turbidity_stats,
            'tds'         => $tds_stats,
            'temperature' => $temp_stats,
        ],
        'prev_stats' => [
            'turbidity'   => ['avg' => $prev_turbidity['avg']],
            'tds'         => ['avg' => $prev_tds['avg']],
            'temperature' => ['avg' => $prev_temp['avg']],
        ],
        'deltas' => [
            'turbidity'   => $calcDelta($turbidity_stats['avg'], $prev_turbidity['avg']),
            'tds'         => $calcDelta($tds_stats['avg'],       $prev_tds['avg']),
            'temperature' => $calcDelta($temp_stats['avg'],      $prev_temp['avg']),
        ],
        'hourly_data'      => $hourly_data,
        'quality_score'    => $quality_score,
        'risk_label'       => $risk_label,
        'parameter_scores' => [
            'turbidity'   => round($turbidity_score),
            'tds'         => round($tds_score),
        ],
        'thresholds' => [
            'max_turbidity' => $max_turbidity,
            'max_tds'       => $max_tds,
        ],
        'breaches'    => $breaches,
        'breach_rate' => $breach_rate,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Database error: ' . $e->getMessage(),
    ]);
    error_log('Analytics fetch error: ' . $e->getMessage());
}

$conn->close();
?>