<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Reports | UniLi Water Monitoring</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Lexend:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=2.1">
    <link rel="stylesheet" href="assets/css/dashboard_new.css?v=<?php echo time(); ?>">
    <script src="assets/js/common.js"></script>

    <style>
        body { font-family: 'Lexend', sans-serif; }
        .main-content { padding: 0 2rem 2rem; }

        .reports-layout {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 2rem;
            align-items: start;
        }

        /* Controls Sidebar */
        .controls-card {
            background: rgba(255, 255, 255, 0.65);
            backdrop-filter: blur(12px);
            border-radius: 16px;
            border: 1px solid var(--surface-border);
            padding: 1.5rem;
            position: sticky;
            top: 2rem;
        }

        .form-group { margin-bottom: 1.2rem; }
        .form-group label {
            display: block; margin-bottom: 0.5rem;
            font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase;
        }
        .form-control {
            width: 100%; padding: 0.8rem 1rem; border: 1px solid var(--surface-border);
            border-radius: 10px; background: white; font-family: inherit;
            transition: all 0.2s; color: var(--text-main); font-size: 0.9rem;
        }
        .form-control:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(14,165,233,0.1); }

        .btn-generate {
            width: 100%; padding: 1rem; background: var(--primary); color: white;
            border: none; border-radius: 10px; font-weight: 700; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 0.5rem;
            transition: all 0.2s; margin-top: 1.5rem;
        }
        .btn-generate:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(14,165,233,0.25); }

        /* Preview Area */
        .preview-header {
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;
        }
        .export-actions { display: flex; gap: 0.75rem; }
        .btn-export {
            padding: 0.6rem 1.2rem; border-radius: 8px; border: 1px solid var(--surface-border);
            background: white; font-weight: 700; font-size: 0.85rem; cursor: pointer;
            display: flex; align-items: center; gap: 0.5rem; transition: all 0.2s;
        }
        .btn-export-pdf { color: var(--primary); border-color: var(--primary); }
        .btn-export-pdf:hover { background: var(--primary); color: white; }
        .btn-export-csv { color: var(--success); border-color: var(--success); }
        .btn-export-csv:hover { background: var(--success); color: white; }

        .report-paper {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            padding: 3rem;
            min-height: 800px;
            position: relative;
        }

        .paper-overlay {
            position: absolute; inset: 0; background: rgba(255,255,255,0.8);
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            border-radius: 12px; z-index: 10; backdrop-filter: blur(4px);
        }

        /* Document Styles */
        .doc-header { border-bottom: 2px solid var(--surface-border); padding-bottom: 1.5rem; margin-bottom: 2rem; }
        .doc-title { font-family: 'Outfit', sans-serif; font-size: 2.2rem; font-weight: 800; color: #0f172a; margin: 0 0 0.5rem 0; letter-spacing: -0.03em; }
        .doc-meta { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; font-size: 0.9rem; color: #475569; }

        .doc-section { margin-bottom: 2.5rem; }
        .doc-section-title { font-size: 1.1rem; font-weight: 700; color: #0f172a; margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 0.5rem; }
        .doc-section-title i { color: var(--primary); }

        .metrics-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
        .metric-box { border: 1px solid var(--surface-border); border-radius: 8px; padding: 1.25rem; text-align: center; background: #f8fafc; }
        .metric-val { font-size: 1.8rem; font-weight: 800; color: #0f172a; line-height: 1.2; }
        .metric-lbl { font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; }

        .doc-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .doc-table th { background: #f1f5f9; padding: 0.75rem; text-align: left; font-weight: 700; color: #334155; border-bottom: 2px solid #cbd5e1; }
        .doc-table td { padding: 0.75rem; border-bottom: 1px solid #e2e8f0; color: #1e293b; }
        .doc-table tr:nth-child(even) td { background: #f8fafc; }

        .footer { margin-top: 3rem; padding-top: 1rem; border-top: 1px solid #e2e8f0; font-size: 0.8rem; color: #94a3b8; text-align: center; }

        @media (max-width: 1024px) {
            .reports-layout { grid-template-columns: 1fr; }
            .controls-card { position: static; }
        }

        /* ── PRINT STYLES ── */
        @media print {
            body * { visibility: hidden; }
            .report-paper, .report-paper * { visibility: visible; }
            .report-paper {
                position: absolute; left: 0; top: 0; width: 100%; padding: 0;
                box-shadow: none; border-radius: 0; min-height: auto;
            }
            .paper-overlay { display: none !important; }
            /* Break pages correctly */
            .doc-table { page-break-inside: auto; }
            .doc-table tr { page-break-inside: avoid; page-break-after: auto; }
            .doc-table thead { display: table-header-group; }
            .doc-section { page-break-inside: avoid; }
            @page { margin: 1cm; }
        }
    </style>
</head>

<body class="web-dashboard-body">
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

        <main class="main-content">
            <header class="dashboard-header-wide">
                <div class="main-header-welcome">
                    <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.5rem;">
                        <a href="dashboard.php" class="btn-back" style="text-decoration: none; color: var(--gray-400); font-size: 1.2rem; transition: color 0.2s;">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                        <h1 class="welcome-title" style="margin-bottom: 0;">Report Generator</h1>
                    </div>
                    <p class="welcome-subtitle">
                        <a href="dashboard.php" style="text-decoration: none; color: inherit; opacity: 0.7;">Dashboard</a>
                        <i class="fas fa-chevron-right" style="font-size: 0.7rem; margin: 0 0.5rem; opacity: 0.5;"></i>
                        <span style="font-weight: 600;">Reports</span>
                    </p>
                </div>
                <?php include 'includes/header_user.php'; ?>
            </header>

            <div id="toast-container" class="toast-container"></div>

            <div class="reports-layout">
                <!-- Controls -->
                <div class="controls-card">
                    <h3 class="card-label" style="margin-bottom: 1.5rem;">Report Parameters</h3>
                    
                    <form id="reportForm">
                        <div class="form-group">
                            <label>Report Title / Type</label>
                            <select id="reportType" class="form-control" required>
                                <option value="Water Quality Report">Water Quality & Safety Report</option>
                                <option value="System Performance Summary">System Performance Summary</option>
                                <option value="Compliance Log">Institutional Compliance Log</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Start Date</label>
                            <input type="date" id="startDate" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>End Date</label>
                            <input type="date" id="endDate" class="form-control" required>
                        </div>

                        <button type="submit" class="btn-generate" id="generateBtn">
                            <i class="fas fa-sync"></i> Generate Preview
                        </button>
                    </form>
                </div>

                <!-- Preview Area -->
                <div>
                    <div class="preview-header">
                        <h3 class="card-label">Live Preview</h3>
                        <div class="export-actions">
                            <button class="btn-export btn-export-csv" onclick="exportCSV()" id="btn-csv" disabled>
                                <i class="fas fa-file-csv"></i> Download CSV
                            </button>
                            <button class="btn-export btn-export-pdf" onclick="exportPDF()" id="btn-pdf" disabled>
                                <i class="fas fa-print"></i> Print / Save PDF
                            </button>
                        </div>
                    </div>

                    <div class="report-paper" id="printable-report">
                        <div class="paper-overlay" id="paper-overlay">
                            <i class="fas fa-file-invoice" style="font-size:3rem; color:var(--text-muted); opacity:0.5; margin-bottom:1rem;"></i>
                            <p style="color:var(--text-muted); font-weight:600;">Select parameters and generate a preview.</p>
                        </div>

                        <!-- Document Content (Populated by JS) -->
                        <div class="doc-header">
                            <h1 class="doc-title" id="doc-title">Water Quality Report</h1>
                            <div class="doc-meta">
                                <div><strong>Period:</strong> <span id="doc-period">--</span></div>
                                <div><strong>Generated:</strong> <span id="doc-generated">--</span></div>
                                <div><strong>Generated By:</strong> <?php echo htmlspecialchars($_SESSION['username'] ?? 'System Admin'); ?></div>
                                <div><strong>Total Records:</strong> <span id="doc-records">--</span></div>
                            </div>
                        </div>

                        <div class="doc-section">
                            <div class="doc-section-title"><i class="fas fa-chart-pie"></i> System Performance summary</div>
                            <div class="metrics-grid">
                                <div class="metric-box">
                                    <div class="metric-val" id="doc-uptime">--</div>
                                    <div class="metric-lbl">Pump Uptime</div>
                                </div>
                                <div class="metric-box">
                                    <div class="metric-val" id="doc-safety">--</div>
                                    <div class="metric-lbl">Water Safety Score</div>
                                </div>
                                <div class="metric-box">
                                    <div class="metric-val" id="doc-rel">--</div>
                                    <div class="metric-lbl">Sensor Reliability</div>
                                </div>
                            </div>
                        </div>

                        <div class="doc-section" id="section-averages">
                            <div class="doc-section-title"><i class="fas fa-vial"></i> Sensor Averages</div>
                            <div class="metrics-grid">
                                <div class="metric-box">
                                    <div class="metric-val"><span id="doc-avg-turb">--</span> <span style="font-size:0.5em;">NTU</span></div>
                                    <div class="metric-lbl">Turbidity</div>
                                </div>
                                <div class="metric-box">
                                    <div class="metric-val"><span id="doc-avg-tds">--</span> <span style="font-size:0.5em;">PPM</span></div>
                                    <div class="metric-lbl">Total Dissolved Solids</div>
                                </div>
                                <div class="metric-box">
                                    <div class="metric-val"><span id="doc-avg-temp">--</span> <span style="font-size:0.5em;">°C</span></div>
                                    <div class="metric-lbl">Temperature</div>
                                </div>
                            </div>
                        </div>

                        <!-- Pump Specifics -->
                        <div class="doc-section" id="section-pump-events" style="display:none;">
                            <div class="doc-section-title"><i class="fas fa-plug"></i> Dosing Events Summary</div>
                            <div class="metrics-grid">
                                <div class="metric-box">
                                    <div class="metric-val" id="doc-pump-active-total">--</div>
                                    <div class="metric-lbl">Active Dosing Samples</div>
                                </div>
                                <div class="metric-box">
                                    <div class="metric-val" id="doc-pump-avg-dur">--</div>
                                    <div class="metric-lbl">Average Session (Est)</div>
                                </div>
                                <div class="metric-box">
                                    <div class="metric-val" id="doc-pump-last">--</div>
                                    <div class="metric-lbl">Last Dosing Event</div>
                                </div>
                            </div>
                        </div>

                        <!-- Hardware Specifics (Hidden by default, shown for System Performance) -->
                        <div class="doc-section" id="section-hardware" style="display:none;">
                            <div class="doc-section-title"><i class="fas fa-microchip"></i> Hardware Diagnostics</div>
                            <div class="metrics-grid">
                                <div class="metric-box">
                                    <div class="metric-val"><span id="doc-avg-level">--</span> <span style="font-size:0.5em;">cm</span></div>
                                    <div class="metric-lbl">Avg Tank Level</div>
                                </div>
                                <div class="metric-box">
                                    <div class="metric-val"><span id="doc-temp-range">--</span> <span style="font-size:0.5em;">°C</span></div>
                                    <div class="metric-lbl">Temp Extremes (Min - Max)</div>
                                </div>
                                <div class="metric-box">
                                    <div class="metric-val" id="doc-pump-cycles">--</div>
                                    <div class="metric-lbl">Pump Active Samples</div>
                                </div>
                            </div>
                        </div>

                        <div class="doc-section" id="section-table">
                            <div class="doc-section-title"><i class="fas fa-list"></i> Sensor Data Log</div>
                            <table class="doc-table">
                                <thead>
                                    <tr>
                                        <th>Date / Time</th>
                                        <th>Turbidity (NTU)</th>
                                        <th>TDS (PPM)</th>
                                        <th>Temp (°C)</th>
                                        <th>Pump State</th>
                                    </tr>
                                </thead>
                                <tbody id="doc-tbody">
                                    <!-- JS populated -->
                                </tbody>
                            </table>
                            <div style="font-size:0.75rem; color:#64748b; margin-top:0.5rem; font-style:italic;" id="table-note"></div>
                        </div>

                        <div class="footer">
                            Report generated automatically by the UniLi Remote Water Monitoring System.<br>
                            For institutional compliance and auditing purposes.
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        let reportData = null;
        let currentType = '';

        // Init dates
        const today = new Date();
        const lastWeek = new Date(today);
        lastWeek.setDate(today.getDate() - 7);
        document.getElementById('endDate').value = today.toISOString().slice(0, 10);
        document.getElementById('startDate').value = lastWeek.toISOString().slice(0, 10);

        function showToast(msg, sev = 'info') {
            const c = document.getElementById('toast-container');
            const t = document.createElement('div');
            t.className = `web-toast ${sev}`;
            t.innerHTML = `<i class="fas fa-info-circle"></i> <div style="font-weight:700;">${msg}</div>`;
            c.appendChild(t);
            setTimeout(() => { t.style.opacity='0'; setTimeout(()=>t.remove(),300); }, 3000);
        }

        document.getElementById('reportForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('generateBtn');
            const overlay = document.getElementById('paper-overlay');
            
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Fetching Data...';
            btn.disabled = true;

            const start = document.getElementById('startDate').value + ' 00:00:00';
            const end   = document.getElementById('endDate').value + ' 23:59:59';
            currentType = document.getElementById('reportType').value;

            try {
                const res = await fetch('api/reports_data.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ start_date: start, end_date: end, type: currentType, format: 'preview' })
                });
                const data = await res.json();
                
                if(!data.success) throw new Error(data.error || 'Failed to load');

                reportData = data;
                renderPreview(data);
                
                overlay.style.display = 'none';
                document.getElementById('btn-csv').disabled = false;
                document.getElementById('btn-pdf').disabled = false;
                showToast('Preview generated successfully', 'success');

            } catch (err) {
                showToast(err.message, 'critical');
                overlay.innerHTML = `<i class="fas fa-exclamation-triangle" style="color:var(--danger); font-size:3rem; margin-bottom:1rem;"></i><p>Error generating report</p>`;
            } finally {
                btn.innerHTML = '<i class="fas fa-sync"></i> Generate Preview';
                btn.disabled = false;
            }
        });

        function renderPreview(data) {
            document.getElementById('doc-title').textContent = currentType;
            document.getElementById('doc-period').textContent = `${data.start_date.slice(0,10)} to ${data.end_date.slice(0,10)}`;
            document.getElementById('doc-generated').textContent = new Date().toLocaleString();
            document.getElementById('doc-records').textContent = data.summary.total_records.toLocaleString();

            document.getElementById('doc-uptime').textContent = data.summary.pump_uptime + '%';
            document.getElementById('doc-safety').textContent = data.summary.water_safety + '%';
            document.getElementById('doc-rel').textContent    = data.summary.reliability + '%';

            document.getElementById('doc-avg-turb').textContent = data.summary.avg_turbidity;
            document.getElementById('doc-avg-tds').textContent  = data.summary.avg_tds;
            document.getElementById('doc-avg-temp').textContent = data.summary.avg_temp;

            const tbody = document.getElementById('doc-tbody');
            // If massive, only show first 100 in preview to not freeze the browser print dialog
            const maxRows = 200;
            const rowsToShow = data.data.slice(0, maxRows);
            
            tbody.innerHTML = rowsToShow.map(r => {
                const pumpOn = Number(r.pump_status) === 1;
                const pumpStyle = pumpOn 
                    ? 'background: #dcfce7; color: #166534; padding: 4px 8px; border-radius: 6px; font-weight: 800; font-size: 0.75rem;' 
                    : 'color: #94a3b8;';
                return `
                <tr>
                    <td>${r.recorded_at}</td>
                    <td>${Number(r.turbidity).toFixed(2)}</td>
                    <td>${r.tds}</td>
                    <td>${Number(r.temperature).toFixed(1)}</td>
                    <td><span style="${pumpStyle}">${pumpOn ? 'ON' : 'OFF'}</span></td>
                </tr>
            `;}).join('');

            if (data.data.length > maxRows) {
                document.getElementById('table-note').textContent = `Showing first ${maxRows} rows in preview. Full data available in CSV export.`;
            } else {
                document.getElementById('table-note').textContent = '';
            }

            // Dynamic layout switching
            if (currentType === 'System Performance Summary' || currentType === 'Water Quality Report') {
                document.getElementById('section-pump-events').style.display = 'block';
                document.getElementById('doc-pump-active-total').textContent = data.summary.active_count;
                
                const pumpEvents = data.data.filter(r => Number(r.pump_status) === 1);
                if (pumpEvents.length > 0) {
                    document.getElementById('doc-pump-last').textContent = pumpEvents[pumpEvents.length-1].recorded_at.split(' ')[1];
                    document.getElementById('doc-pump-avg-dur').textContent = '~' + Math.round(pumpEvents.length * 0.5) + ' min';
                } else {
                    document.getElementById('doc-pump-last').textContent = 'None';
                    document.getElementById('doc-pump-avg-dur').textContent = '0 min';
                }
            }

            if (currentType === 'System Performance Summary') {
                document.getElementById('section-table').style.display = 'none';
                document.getElementById('section-hardware').style.display = 'block';
                
                // Populate hardware stats
                document.getElementById('doc-avg-level').textContent = data.summary.avg_level;
                document.getElementById('doc-temp-range').textContent = `${data.summary.min_temp} - ${data.summary.max_temp}`;
                document.getElementById('doc-pump-cycles').textContent = data.summary.active_count;
            } else {
                document.getElementById('section-table').style.display = 'block';
                document.getElementById('section-hardware').style.display = 'none';
            }
        }

        async function logExport(format) {
            const start = document.getElementById('startDate').value + ' 00:00:00';
            const end   = document.getElementById('endDate').value + ' 23:59:59';
            try {
                await fetch('api/reports_data.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ start_date: start, end_date: end, type: currentType, format: format, log_only: true })
                });
            } catch(e) { console.error('Failed to log export'); }
        }

        function exportPDF() {
            if (!reportData) return;
            logExport('pdf');
            window.print();
        }

        function exportCSV() {
            if (!reportData || !reportData.data.length) {
                showToast('No data to export', 'warning');
                return;
            }
            logExport('csv');
            
            const h = ['Timestamp','Turbidity (NTU)','TDS (PPM)','Temperature (°C)','Pump Status'];
            const rows = reportData.data.map(r => 
                [r.recorded_at, r.turbidity, r.tds, r.temperature, r.pump_status == 1 ? 'ON' : 'OFF']
                .map(v => `"${String(v).replace(/"/g,'""')}"`).join(',')
            );
            
            const csv = [h.join(','), ...rows].join('\n');
            const blob = new Blob(['\uFEFF' + csv], {type:'text/csv;charset=utf-8;'});
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `${currentType.replace(/\s+/g, '_')}_${reportData.start_date.slice(0,10)}.csv`;
            a.click();
            URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
