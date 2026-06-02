<?php
/**
 * Unified Activities API
 * Combines alerts, treatment logs, and system reports into a single feed
 */

header('Content-Type: application/json');
require '../db_connect.php';

try {
    $activities = [];
    $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 15;

    // 1. Fetch Alerts (Sensor breaches, etc.)
    $alerts_res = $conn->query("
        SELECT 
            id, 
            'alert' as source, 
            severity as type, 
            message, 
            created_at 
        FROM alerts 
        ORDER BY created_at DESC 
        LIMIT $limit
    ");
    if ($alerts_res) {
        while ($row = $alerts_res->fetch_assoc()) {
            $activities[] = [
                'id' => $row['id'],
                'source' => $row['source'],
                'type' => $row['type'],
                'message' => $row['message'],
                'created_at' => $row['created_at'],
                'icon' => $row['type'] === 'critical' ? 'fa-exclamation-triangle' : 'fa-bell',
                'color' => $row['type'] === 'critical' ? '#ef4444' : '#f59e0b'
            ];
        }
    }

    // 2. Fetch Treatment Logs
    $treatment_res = $conn->query("
        SELECT 
            id, 
            'treatment' as source, 
            log_type as type, 
            volume,
            created_at 
        FROM treatment_logs 
        ORDER BY created_at DESC 
        LIMIT $limit
    ");
    if ($treatment_res) {
        while ($row = $treatment_res->fetch_assoc()) {
            $activities[] = [
                'id' => $row['id'],
                'source' => $row['source'],
                'type' => $row['type'],
                'message' => "Dosed " . $row['volume'] . "ml of chemical (" . ucfirst($row['type']) . ")",
                'created_at' => $row['created_at'],
                'icon' => 'fa-flask',
                'color' => '#10b981'
            ];
        }
    }

    // 3. Fetch Generated Reports
    $reports_res = $conn->query("
        SELECT 
            id, 
            'report' as source, 
            type, 
            format,
            created_at 
        FROM generated_reports 
        ORDER BY created_at DESC 
        LIMIT $limit
    ");
    if ($reports_res) {
        while ($row = $reports_res->fetch_assoc()) {
            $activities[] = [
                'id' => $row['id'],
                'source' => $row['source'],
                'type' => $row['type'],
                'message' => "Generated " . strtoupper($row['format']) . " report: " . ucfirst(str_replace('_', ' ', $row['type'])),
                'created_at' => $row['created_at'],
                'icon' => 'fa-file-alt',
                'color' => '#3b82f6'
            ];
        }
    }

    // Sort all combined activities by created_at DESC
    usort($activities, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    // Final Slice
    $activities = array_slice($activities, 0, $limit);

    echo json_encode([
        'success' => true,
        'activities' => $activities,
        'total' => count($activities)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>
