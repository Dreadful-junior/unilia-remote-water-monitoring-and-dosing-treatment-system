<?php
session_start();

/**
 * UniLi Water Monitoring System - Database Export Utility
 * Provides a full SQL dump (schema and data) for backup purposes.
 */

// Basic authentication check (optional, but recommended)
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require 'db_connect.php';

// Set filenames and headers for download
$filename = 'water_system_backup_' . date('Ymd_His') . '.sql';

header('Content-Type: application/sql');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Open PHP output stream for efficient writing
$output = fopen('php://output', 'w');

// Header comments
fwrite($output, "-- UniLi Water Monitoring System - Full Database Dump\n");
fwrite($output, "-- Generated on: " . date('Y-m-d H:i:s') . "\n");
fwrite($output, "-- Host: " . $servername . "\n");
fwrite($output, "-- Database: " . $dbname . "\n\n");

fwrite($output, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
fwrite($output, "START TRANSACTION;\n");
fwrite($output, "SET time_zone = \"+00:00\";\n\n");

// Get all tables from the database
$tables = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

foreach ($tables as $table) {
    // 1. Table Structure
    fwrite($output, "-- --------------------------------------------------------\n");
    fwrite($output, "-- Table structure for table `$table`\n");
    fwrite($output, "-- --------------------------------------------------------\n\n");
    
    fwrite($output, "DROP TABLE IF EXISTS `$table`;\n");
    $create_res = $conn->query("SHOW CREATE TABLE `$table`")->fetch_row();
    fwrite($output, $create_res[1] . ";\n\n");

    // 2. Table Data
    $data_res = $conn->query("SELECT * FROM `$table`");
    $num_fields = $data_res->field_count;

    if ($data_res->num_rows > 0) {
        fwrite($output, "-- Dumping data for table `$table`\n\n");
        
        while ($row = $data_res->fetch_row()) {
            $insert_query = "INSERT INTO `$table` VALUES(";
            for ($j = 0; $j < $num_fields; $j++) {
                if (is_null($row[$j])) {
                    $insert_query .= "NULL";
                } elseif (is_numeric($row[$j])) {
                    $insert_query .= $row[$j];
                } else {
                    // Escape special characters
                    $escaped = $conn->real_escape_string($row[$j]);
                    $insert_query .= "'" . $escaped . "'";
                }
                
                if ($j < ($num_fields - 1)) {
                    $insert_query .= ", ";
                }
            }
            $insert_query .= ");\n";
            fwrite($output, $insert_query);
        }
        fwrite($output, "\n");
    }
}

fwrite($output, "COMMIT;\n");
fclose($output);
exit;
