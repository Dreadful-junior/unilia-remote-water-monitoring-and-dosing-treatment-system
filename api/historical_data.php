<?php
/**
 * Historical Data API - Vanilla PHP
 * Returns filtered historical sensor data with pagination.
 * Supports: period (24h|7d|30d), start_date, end_date, page, per_page
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache');

require '../db_connect.php';

// ----------------------------------------------------------------
// Period resolution  (mirrors analytics.php logic)
// ----------------------------------------------------------------
$period   = isset($_GET['period']) ? trim($_GET['period']) : '7d';
$page     = max(1, intval($_GET['page']    ?? 1));
$per_page = max(1, min(100, intval($_GET['per_page'] ?? 10)));
$offset   = ($page - 1) * $per_page;

// Allow explicit overrides
if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
    $start_date = $conn->real_escape_string($_GET['start_date']);
    $end_date   = $conn->real_escape_string($_GET['end_date']);
} else {
    switch ($period) {
        case '24h':
            $start_date = date('Y-m-d H:i:s', strtotime('-24 hours'));
            $end_date   = date('Y-m-d H:i:s');
            break;
        case '30d':
            $start_date = date('Y-m-d 00:00:00', strtotime('-30 days'));
            $end_date   = date('Y-m-d 23:59:59');
            break;
        default: // 7d
            $start_date = date('Y-m-d 00:00:00', strtotime('-7 days'));
            $end_date   = date('Y-m-d 23:59:59');
    }
}

try {
    // ----------------------------------------------------------------
    // Detect optional columns (ph, chlorine)
    // ----------------------------------------------------------------
    $has_ph       = $conn->query("SHOW COLUMNS FROM sensor_data LIKE 'ph'")->num_rows       > 0;
    $has_chlorine = $conn->query("SHOW COLUMNS FROM sensor_data LIKE 'chlorine'")->num_rows > 0;

    $ph_field       = $has_ph       ? 'COALESCE(ph, 7.0)'      : '7.0';
    $chlorine_field = $has_chlorine ? 'COALESCE(chlorine, 0.0)' : '0.0';

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
    // Total count (for pagination)
    // ----------------------------------------------------------------
    $count_result  = $conn->query("SELECT COUNT(*) AS total FROM sensor_data WHERE recorded_at BETWEEN '$start_date' AND '$end_date'");
    $total_records = $count_result ? (int)$count_result->fetch_assoc()['total'] : 0;
    $total_pages   = max(1, (int)ceil($total_records / $per_page));

    // ----------------------------------------------------------------
    // Period-wide summary stats (always full range, not just current page)
    // ----------------------------------------------------------------
    $summary_result = $conn->query("
        SELECT
            AVG(turbidity)   AS avg_turb,
            AVG(tds)         AS avg_tds,
            AVG(temperature) AS avg_temp,
            SUM(turbidity > $max_turbidity OR tds > $max_tds) AS breach_count
        FROM sensor_data
        WHERE recorded_at BETWEEN '$start_date' AND '$end_date'
    ");
    $sum = $summary_result ? $summary_result->fetch_assoc() : [];
    $period_summary = [
        'total_records' => $total_records,
        'breach_count'  => (int)($sum['breach_count'] ?? 0),
        'avg_turbidity' => $sum['avg_turb'] !== null ? round((float)$sum['avg_turb'], 2) : null,
        'avg_tds'       => $sum['avg_tds']  !== null ? (int)round((float)$sum['avg_tds'])  : null,
        'avg_temp'      => $sum['avg_temp'] !== null ? round((float)$sum['avg_temp'], 1)   : null,
        'breach_rate'   => $total_records > 0 ? round(((int)($sum['breach_count'] ?? 0) / $total_records) * 100, 1) : 0,
    ];

    // ----------------------------------------------------------------
    // Fetch page of data
    // ----------------------------------------------------------------
    $query = "
        SELECT
            id,
            turbidity,
            tds,
            temperature,
            $chlorine_field AS chlorine,
            recorded_at
        FROM sensor_data
        WHERE recorded_at BETWEEN '$start_date' AND '$end_date'
        ORDER BY recorded_at DESC
        LIMIT $per_page OFFSET $offset
    ";
    $result = $conn->query($query);

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $turb = (float)$row['turbidity'];
        $tds  = (int)$row['tds'];

        if ($turb > $max_turbidity * 1.5 || $tds > $max_tds * 1.5) {
            $status = 'critical';
            $status_text = 'Treatment Required';
        } elseif ($turb > $max_turbidity || $tds > $max_tds) {
            $which = $turb > $max_turbidity ? 'Turbidity' : 'TDS';
            $status = 'warning';
            $status_text = "High $which";
        } else {
            $status = 'normal';
            $status_text = 'Normal';
        }

        $data[] = [
            'id'              => (int)$row['id'],
            'timestamp'       => $row['recorded_at'],
            'turbidity'       => $turb,
            'tds'             => $tds,
            'temperature'     => round((float)$row['temperature'], 1),
            'chlorine'        => round((float)$row['chlorine'], 2),
            'status'          => $status,
            'status_text'     => $status_text,
            'treatment_needed'=> $status !== 'normal',
        ];
    }

    echo json_encode([
        'success' => true,
        'period'  => $period,
        'data'    => $data,
        'thresholds' => [
            'max_turbidity' => $max_turbidity,
            'max_tds'       => $max_tds,
        ],
        'summary' => $period_summary,
        'pagination' => [
            'current_page'  => $page,
            'per_page'      => $per_page,
            'total_records' => $total_records,
            'total_pages'   => $total_pages,
            'has_next'      => $page < $total_pages,
            'has_prev'      => $page > 1,
        ],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Database error: ' . $e->getMessage(),
    ]);
    error_log('Historical data fetch error: ' . $e->getMessage());
}

$conn->close();
?>
