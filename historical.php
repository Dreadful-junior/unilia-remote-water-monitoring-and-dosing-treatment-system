<?php
session_start();
include 'includes/session_sync.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historical Data | UNILIA Water Monitoring</title>
    <link rel="stylesheet" href="assets/vendor/fonts/fonts.css">
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    
    <!-- Custom Design -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard_new.css">
    <script src="assets/js/common.js"></script>

    <style>
        .premium-control-panel {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            flex-wrap: nowrap;
            overflow-x: auto;
            padding: 1.5rem;
        }

        .filter-section, .stats-section {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .control-group, .stat-group {
            background: rgba(255, 255, 255, 0.4);
            border: 1px solid rgba(0, 0, 0, 0.05);
            padding: 0.8rem 1.25rem;
            border-radius: 16px;
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            min-width: 140px;
            transition: all 0.3s ease;
        }

        .control-group:hover, .stat-group:hover {
            background: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.05);
            border-color: var(--primary);
        }

        .stat-label-mini {
            font-size: 0.7rem;
            font-weight: 800;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .stat-value-mini {
            font-size: 1.3rem;
            font-weight: 900;
            color: var(--text-main);
            line-height: 1;
        }

        .filter-section .control-group {
            border-left: 4px solid var(--primary);
        }

        .filter-input {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            padding: 0.6rem 0.8rem;
            color: var(--text-main);
            font-family: inherit;
            font-size: 0.9rem;
            outline: none;
        }

        .filter-input:focus {
            border-color: var(--primary);
            background: rgba(255, 255, 255, 0.08);
        }

        /* Premium Table Design */
        .data-table-wrapper {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.1);
        }

        .premium-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            text-align: left;
        }

        .premium-table th {
            padding: 1.5rem;
            background: rgba(255, 255, 255, 0.05);
            font-size: 0.75rem;
            font-weight: 800;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            border-bottom: 2px solid rgba(255, 255, 255, 0.05);
            border-right: 1px solid rgba(255, 255, 255, 0.03);
        }

        .premium-table th:last-child { border-right: none; }

        .premium-table td {
            padding: 1.25rem 1.5rem;
            font-size: 0.9rem;
            color: var(--text-main);
            border-bottom: 1px solid rgba(255, 255, 255, 0.03);
            border-right: 1px solid rgba(255, 255, 255, 0.02);
            transition: all 0.3s ease;
        }

        .premium-table td:last-child { border-right: none; }

        .premium-table tr:nth-child(even) td {
            background: rgba(255, 255, 255, 0.01);
        }

        .premium-table tr:hover td {
            background: rgba(14, 165, 233, 0.04) !important;
            color: var(--primary);
        }

        /* Value Pills */
        .value-pill {
            display: inline-flex;
            align-items: center;
            background: rgba(0, 0, 0, 0.03);
            padding: 0.4rem 0.8rem;
            border-radius: 12px;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 800;
            border: 1px solid rgba(0, 0, 0, 0.05);
            color: var(--text-main);
        }

        .timestamp-text {
            white-space: nowrap;
            font-weight: 700;
            color: var(--text-main);
            font-size: 0.9rem;
        }

        .timestamp-time-dim {
            color: var(--text-muted);
            font-weight: 500;
            margin-left: 0.5rem;
        }

        /* Status Badge */
        .status-badge-premium {
            padding: 6px 12px;
            border-radius: 10px;
            font-size: 0.7rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: inline-block;
        }

        .status-normal { background: rgba(34, 197, 94, 0.15); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.2); }
        .status-warning { background: rgba(245, 158, 11, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11, 0.2); }
        .status-critical { background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }

        /* Pagination Refined */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 2rem;
            margin-top: 3rem;
        }

        .pag-btn {
            background: white;
            border: 1px solid rgba(0, 0, 0, 0.08);
            color: var(--text-main);
            width: 48px;
            height: 48px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .pag-btn:not(:disabled):hover {
            background: var(--primary);
            box-shadow: 0 8px 24px rgba(14, 165, 233, 0.3);
            transform: translateY(-2px);
        }

        .pag-btn:disabled { opacity: 0.2; cursor: not-allowed; }
    </style>
    </style>
</head>

<body class="web-dashboard-body">

    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

        <main class="main-content">
            <!-- Header -->
            <header class="dashboard-header-wide">
                <div class="main-header-welcome">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                        <button class="mobile-toggle" onclick="toggleSidebar()">
                            <i class="fas fa-bars"></i>
                        </button>
                        <h1 class="welcome-title" style="margin-bottom: 0;">Historical Data</h1>
                    </div>
                    <p class="welcome-subtitle">Browse and filter historical sensor readings.</p>
                </div>

                <?php include 'includes/header_user.php'; ?>
            </header>

            <!-- Integrated Control & Stats Panel -->
            <div class="glass" style="margin-bottom: 2.5rem; padding: 0; background: rgba(255,255,255,0.2);">
                <div class="premium-control-panel">
                    <!-- Filters -->
                    <div class="filter-section">
                        <div class="control-group">
                            <span class="stat-label-mini" style="color: var(--primary);">Time Range Filter</span>
                            <select class="premium-input-box" id="period-select" onchange="handlePeriodChange(this.value)" style="padding: 0.4rem 0.5rem; font-size: 1rem; border: none; background: transparent; font-weight: 800; color: var(--text-main);">
                                <option value="24h">Last 24 Hours</option>
                                <option value="7d" selected>Last 7 Days</option>
                                <option value="30d">Last 30 Days</option>
                                <option value="custom">Custom Range</option>
                            </select>
                        </div>
                        
                        <div class="date-input" style="display:none; flex-direction: column;">
                            <div class="control-group">
                                <span class="stat-label-mini">Start Date</span>
                                <input type="date" class="premium-input-box" id="start-date" style="padding: 0; border: none; background: transparent; font-weight: 700;">
                            </div>
                        </div>
                        
                        <div class="date-input" style="display:none; flex-direction: column;">
                            <div class="control-group">
                                <span class="stat-label-mini">End Date</span>
                                <input type="date" class="premium-input-box" id="end-date" style="padding: 0; border: none; background: transparent; font-weight: 700;">
                            </div>
                        </div>

                        <button class="btn-premium-search" onclick="applyFilters()" style="padding: 0.8rem 1.5rem; border-radius: 16px; font-size: 0.9rem;">
                            <i class="fas fa-sync"></i> Update
                        </button>
                    </div>

                    <!-- Stats Row -->
                    <div class="stats-section">
                        <div class="stat-group">
                            <span class="stat-label-mini">Total Logs</span>
                            <span class="stat-value-mini" id="total-records">--</span>
                        </div>
                        <div class="stat-group" style="border-top: 3px solid var(--primary);">
                            <span class="stat-label-mini">Avg Turbidity</span>
                            <span class="stat-value-mini" id="avg-turb" style="color: var(--primary);">--</span>
                        </div>
                        <div class="stat-group" style="border-top: 3px solid var(--warning);">
                            <span class="stat-label-mini">Avg TDS</span>
                            <span class="stat-value-mini" id="avg-tds" style="color: var(--warning);">--</span>
                        </div>
                        <div class="stat-group" style="border-top: 3px solid var(--success);">
                            <span class="stat-label-mini">Health Score</span>
                            <span class="stat-value-mini" id="breach-rate" style="color: var(--success);">--</span>
                        </div>
                        
                        <a href="reports.php" class="btn-premium-search" style="background: white; color: var(--text-main); box-shadow: 0 4px 12px rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.1); text-decoration: none; padding: 1rem; border-radius: 16px;">
                            <i class="fas fa-download"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Enhanced Data Table -->
            <div class="data-table-wrapper">
                <table class="premium-table">
                    <thead>
                        <tr>
                            <th>Data Point Timestamp</th>
                            <th>Turbidity</th>
                            <th>TDS Level</th>
                            <th>Temperature</th>
                            <th>System Status</th>
                        </tr>
                    </thead>
                    <tbody id="data-body">
                        <!-- Populated by JS -->
                        <tr>
                            <td colspan="5" style="text-align:center; padding: 6rem;">
                                <i class="fas fa-sync fa-spin" style="font-size: 2rem; color: var(--primary); margin-bottom: 1.5rem; display: block;"></i>
                                <span style="font-weight: 600; color: var(--text-muted); letter-spacing: 0.05em;">SYNCHRONIZING SYSTEM LOGS...</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="pagination">
                <button class="pag-btn" id="prev-btn" onclick="changePage(-1)" disabled>
                    <i class="fas fa-chevron-left"></i>
                </button>
                <span style="font-weight: 800; color: var(--text-main); letter-spacing: 0.1em; font-size: 0.8rem;" id="page-info">PAGE 1 OF 1</span>
                <button class="pag-btn" id="next-btn" onclick="changePage(1)" disabled>
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>

        </main>
    </div>

    <script>
        let currentPage = 1;
        let totalPages = 1;

        async function fetchData() {
            const period = document.getElementById('period-select').value;
            const start = document.getElementById('start-date').value;
            const end = document.getElementById('end-date').value;
            
            let url = `api/historical_data.php?page=${currentPage}&per_page=15&t=${Date.now()}`;
            if (period === 'custom') {
                url += `&start_date=${start} 00:00:00&end_date=${end} 23:59:59`;
            } else {
                url += `&period=${period}`;
            }

            try {
                const response = await fetch(url);
                const data = await response.json();

                if (data.success) {
                    renderTable(data.data);
                    updatePagination(data.pagination);
                    updateSummary(data.summary);
                }
            } catch (error) {
                console.error("Failed to fetch historical data:", error);
            }
        }

        function renderTable(rows) {
            const body = document.getElementById('data-body');
            if (rows.length === 0) {
                body.innerHTML = '<tr><td colspan="5" style="text-align:center; padding: 6rem; color: var(--text-muted); font-weight: 800;">NO READINGS FOUND FOR THIS PERIOD</td></tr>';
                return;
            }

            body.innerHTML = rows.map(row => {
                const date = new Date(row.timestamp);
                const dateStr = date.toLocaleDateString([], {month: 'short', day: 'numeric', year: 'numeric'});
                const timeStr = date.toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
                
                return `
                    <tr style="animation: fadeIn 0.4s ease forwards;">
                        <td>
                            <div class="timestamp-text">
                                ${dateStr} <span class="timestamp-time-dim">${timeStr}</span>
                            </div>
                        </td>
                        <td>
                            <div class="value-pill">
                                ${parseFloat(row.turbidity).toFixed(2)}
                                <span style="font-size: 0.6rem; margin-left: 0.4rem; opacity: 0.5;">NTU</span>
                            </div>
                        </td>
                        <td>
                            <div class="value-pill">
                                ${Math.round(row.tds)}
                                <span style="font-size: 0.6rem; margin-left: 0.4rem; opacity: 0.5;">PPM</span>
                            </div>
                        </td>
                        <td>
                            <div class="value-pill" style="border-color: rgba(239, 68, 68, 0.2); color: #ef4444;">
                                ${parseFloat(row.temperature).toFixed(1)}°C
                            </div>
                        </td>
                        <td>
                            <span class="status-badge-premium status-${row.status}">${row.status_text}</span>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        function updatePagination(pag) {
            currentPage = pag.current_page;
            totalPages = pag.total_pages;
            document.getElementById('page-info').textContent = `Page ${currentPage} of ${totalPages}`;
            document.getElementById('prev-btn').disabled = !pag.has_prev;
            document.getElementById('next-btn').disabled = !pag.has_next;
        }

        function updateSummary(sum) {
            document.getElementById('total-records').textContent = sum.total_records.toLocaleString();
            document.getElementById('avg-turb').textContent = (sum.avg_turbidity || 0) + ' NTU';
            document.getElementById('avg-tds').textContent = (sum.avg_tds || 0) + ' PPM';
            document.getElementById('breach-rate').textContent = (sum.breach_rate || 0) + '%';
        }

        function changePage(delta) {
            currentPage += delta;
            fetchData();
        }

        function applyFilters() {
            currentPage = 1;
            fetchData();
        }

        function toggleDateInputs(val) {
            const inputs = document.querySelectorAll('.date-input');
            inputs.forEach(el => el.style.display = (val === 'custom' ? 'flex' : 'none'));
        }

        function handlePeriodChange(val) {
            toggleDateInputs(val);
            if (val !== 'custom') {
                applyFilters();
            }
        }

        // Init
        document.addEventListener('DOMContentLoaded', fetchData);
    </script>
</body>

</html>
