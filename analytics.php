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
    <title>Analytics | UNILIA Water Monitoring</title>
    <link rel="stylesheet" href="assets/vendor/fonts/fonts.css">
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Custom Design -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard_new.css">
    <script src="assets/js/common.js"></script>

    <style>
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .chart-container-large {
            grid-column: span 4;
            min-height: 400px;
            padding: 1.5rem;
        }

        .chart-container-small {
            grid-column: span 2;
            min-height: 300px;
            padding: 1.5rem;
        }

        .period-selector {
            display: flex;
            background: rgba(255, 255, 255, 0.05);
            padding: 4px;
            border-radius: 12px;
            gap: 4px;
        }

        .period-btn {
            padding: 0.5rem 1.25rem;
            border-radius: 10px;
            border: none;
            background: transparent;
            color: var(--text-muted);
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .period-btn.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 12px rgba(14, 165, 233, 0.3);
        }

        .stat-card-mini {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 1rem;
            border-radius: 16px;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .stat-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .stat-value {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text-main);
        }

        .trend-badge {
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }

        .trend-up { color: #ef4444; background: rgba(239, 68, 68, 0.1); }
        .trend-down { color: #10b981; background: rgba(16, 185, 129, 0.1); }

        /* KPI Card Refinements */
        .kpi-card {
            background: white;
            border: 1px solid rgba(0, 0, 0, 0.05);
            border-radius: 20px;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.02);
            transition: all 0.3s ease;
        }

        .kpi-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.06);
            border-color: var(--primary);
        }

        .kpi-accent {
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }

        .kpi-label {
            font-size: 0.7rem;
            font-weight: 800;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .kpi-value-row {
            display: flex;
            align-items: baseline;
            gap: 0.4rem;
        }

        .kpi-value {
            font-size: 1.75rem;
            font-weight: 900;
            color: var(--text-main);
            letter-spacing: -0.02em;
        }

        .kpi-unit {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-muted);
        }

        /* Risk Gauge Styles */
        .risk-card {
            grid-column: span 1;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .risk-gauge-item {
            display: flex;
            align-items: center;
            gap: 1.25rem;
            padding: 1rem;
            background: rgba(0, 0, 0, 0.02);
            border-radius: 16px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }

        .risk-gauge-item:hover {
            background: white;
            transform: scale(1.02);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        }

        .circle-gauge {
            position: relative;
            width: 60px;
            height: 60px;
        }

        .circle-gauge svg {
            transform: rotate(-90deg);
            width: 60px;
            height: 60px;
        }

        .circle-gauge circle {
            fill: none;
            stroke-width: 6;
            stroke-linecap: round;
        }

        .circle-bg { stroke: rgba(0, 0, 0, 0.05); }
        .circle-progress {
            stroke-dasharray: 170;
            stroke-dashoffset: 170;
            transition: stroke-dashoffset 1s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .gauge-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 0.8rem;
            font-weight: 800;
            color: var(--text-main);
        }

        .risk-meta {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .risk-title {
            font-size: 0.85rem;
            font-weight: 800;
            color: var(--text-main);
        }

        .risk-status {
            font-size: 0.65rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
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
                        <h1 class="welcome-title" style="margin-bottom: 0;">Performance Analytics</h1>
                    </div>
                    <p class="welcome-subtitle">Insights and historical trends for your water system.</p>
                </div>

                <div style="display: flex; align-items: center; gap: 1.5rem;">
                    <div class="period-selector">
                        <button class="period-btn" onclick="setPeriod('24h', this)">24H</button>
                        <button class="period-btn active" onclick="setPeriod('7d', this)">7D</button>
                        <button class="period-btn" onclick="setPeriod('30d', this)">30D</button>
                    </div>
                    <?php include 'includes/header_user.php'; ?>
                </div>
            </header>

            <div class="dashboard-grid">
                <!-- Row 1: Key Performance Indicators -->
                <div class="analytics-grid" style="grid-column: 1 / -1; margin-bottom: 2rem;">
                    <div class="kpi-card">
                        <div class="kpi-accent" style="background: var(--primary);"></div>
                        <span class="kpi-label">Quality Score</span>
                        <div class="kpi-value-row">
                            <span class="kpi-value" id="quality-score">--</span>
                            <span class="kpi-unit">%</span>
                        </div>
                        <div id="quality-risk" class="trend-badge">Analyzing...</div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-accent" style="background: var(--success);"></div>
                        <span class="kpi-label">Avg Turbidity</span>
                        <div class="kpi-value-row">
                            <span class="kpi-value" id="avg-turbidity">--</span>
                            <span class="kpi-unit">NTU</span>
                        </div>
                        <div id="turbidity-trend" class="trend-badge">--</div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-accent" style="background: #f59e0b;"></div>
                        <span class="kpi-label">Avg TDS Level</span>
                        <div class="kpi-value-row">
                            <span class="kpi-value" id="avg-tds">--</span>
                            <span class="kpi-unit">PPM</span>
                        </div>
                        <div id="tds-trend" class="trend-badge">--</div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-accent" style="background: var(--danger);"></div>
                        <span class="kpi-label">Total Breaches</span>
                        <div class="kpi-value-row">
                            <span class="kpi-value" id="total-breaches">--</span>
                            <span class="kpi-unit">Events</span>
                        </div>
                        <div id="breach-rate" class="trend-badge">--</div>
                    </div>
                </div>

                <!-- Row 2: Large Trend Chart -->
                <div class="chart-container-large glass" style="grid-column: span 3;">
                    <div class="logs-header" style="margin-bottom: 1.5rem;">
                        <h3 class="card-label">Water Quality Trends</h3>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">Turbidity vs TDS Over Time</div>
                    </div>
                    <div style="height: 350px;">
                        <canvas id="qualityChart"></canvas>
                    </div>
                </div>

                <!-- Row 2 Sidebar: Water Quality Risk Matrix -->
                <div class="glass risk-card" style="grid-column: span 1;">
                    <h3 class="card-label">Water Quality Risk Matrix</h3>
                    
                    <div class="risk-gauge-item" id="risk-turbidity">
                        <div class="circle-gauge">
                            <svg>
                                <circle class="circle-bg" cx="30" cy="30" r="27"></circle>
                                <circle class="circle-progress" cx="30" cy="30" r="27" style="stroke: var(--primary);"></circle>
                            </svg>
                            <span class="gauge-text" id="turb-risk-val">0%</span>
                        </div>
                        <div class="risk-meta">
                            <span class="risk-title">Turbidity Hazard</span>
                            <span class="risk-status" id="turb-risk-status">Low</span>
                        </div>
                    </div>

                    <div class="risk-gauge-item" id="risk-tds">
                        <div class="circle-gauge">
                            <svg>
                                <circle class="circle-bg" cx="30" cy="30" r="27"></circle>
                                <circle class="circle-progress" cx="30" cy="30" r="27" style="stroke: #f59e0b;"></circle>
                            </svg>
                            <span class="gauge-text" id="tds-risk-val">0%</span>
                        </div>
                        <div class="risk-meta">
                            <span class="risk-title">Chemical Risk (TDS)</span>
                            <span class="risk-status" id="tds-risk-status">Low</span>
                        </div>
                    </div>

                    <div class="risk-gauge-item" id="risk-temp">
                        <div class="circle-gauge">
                            <svg>
                                <circle class="circle-bg" cx="30" cy="30" r="27"></circle>
                                <circle class="circle-progress" cx="30" cy="30" r="27" style="stroke: #ef4444;"></circle>
                            </svg>
                            <span class="gauge-text" id="temp-risk-val">0%</span>
                        </div>
                        <div class="risk-meta">
                            <span class="risk-title">Thermal Stability</span>
                            <span class="risk-status" id="temp-risk-status">Normal</span>
                        </div>
                    </div>

                    <div style="margin-top: auto; padding-top: 1rem; border-top: 1px solid rgba(0,0,0,0.05);">
                        <div class="stat-label" style="margin-bottom: 0.5rem;">Data Reliability</div>
                        <div style="font-size: 1.2rem; font-weight: 900; color: var(--success);" id="reliability">--%</div>
                    </div>
                </div>

                <!-- Row 3: Temperature Chart -->
                <div class="chart-container-small glass">
                    <h3 class="card-label" style="margin-bottom: 1.25rem;">Temperature Stability</h3>
                    <div style="height: 250px;">
                        <canvas id="tempChart"></canvas>
                    </div>
                </div>

                <!-- Row 3: Breach Distribution -->
                <div class="chart-container-small glass">
                    <h3 class="card-label" style="margin-bottom: 1.25rem;">Breach Analysis</h3>
                    <div style="height: 250px;">
                        <canvas id="breachChart"></canvas>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        let currentPeriod = '7d';
        let qualityChart, tempChart, breachChart;

        async function fetchAnalytics() {
            try {
                const response = await fetch(`api/analytics.php?period=${currentPeriod}`);
                const data = await response.json();

                if (data.success) {
                    updateUI(data);
                    renderCharts(data);
                }
            } catch (error) {
                console.error("Failed to fetch analytics:", error);
            }
        }

        function updateUI(data) {
            document.getElementById('quality-score').textContent = data.quality_score;
            document.getElementById('avg-turbidity').textContent = data.stats.turbidity.avg || '0';
            document.getElementById('avg-tds').textContent = data.stats.tds.avg || '0';
            document.getElementById('total-breaches').textContent = data.breaches.turbidity_exceed + data.breaches.tds_exceed;
            
            document.getElementById('reliability').textContent = (data.breach_rate > 0 ? (100 - data.breach_rate).toFixed(1) : '100') + '%';

            // Update Risk Gauges
            const turbRisk = 100 - data.parameter_scores.turbidity;
            const tdsRisk = 100 - data.parameter_scores.tds;
            
            // Calc temp risk (simplified: hazard if temp > 28 or < 18)
            const maxT = data.stats.temperature.max || 25;
            const minT = data.stats.temperature.min || 25;
            let tempRisk = 0;
            if (maxT > 30 || minT < 15) tempRisk = 80;
            else if (maxT > 27 || minT < 18) tempRisk = 40;
            else tempRisk = 5;

            updateRiskGauge('risk-turbidity', 'turb-risk-val', 'turb-risk-status', turbRisk);
            updateRiskGauge('risk-tds', 'tds-risk-val', 'tds-risk-status', tdsRisk);
            updateRiskGauge('risk-temp', 'temp-risk-val', 'temp-risk-status', tempRisk);

            // Risk Label
            const risk = document.getElementById('quality-risk');
            risk.textContent = data.risk_label;
            risk.className = 'trend-badge ' + (data.quality_score >= 70 ? 'trend-down' : 'trend-up');

            // Trend Deltas
            updateTrend('turbidity-trend', data.deltas.turbidity);
            updateTrend('tds-trend', data.deltas.tds);
            
            const breachRate = document.getElementById('breach-rate');
            breachRate.textContent = data.breach_rate + '% Breach Rate';
            breachRate.className = 'trend-badge ' + (data.breach_rate > 10 ? 'trend-up' : 'trend-down');
        }

        function updateRiskGauge(containerId, valId, statusId, percentage) {
            const valEl = document.getElementById(valId);
            const statusEl = document.getElementById(statusId);
            const circle = document.querySelector(`#${containerId} .circle-progress`);
            
            valEl.textContent = Math.round(percentage) + '%';
            
            // SVG dashoffset calculation (circumference is ~170)
            const offset = 170 - (percentage / 100) * 170;
            circle.style.strokeDashoffset = offset;

            if (percentage > 70) {
                statusEl.textContent = 'High Hazard';
                statusEl.style.color = '#ef4444';
            } else if (percentage > 30) {
                statusEl.textContent = 'Moderate Risk';
                statusEl.style.color = '#f59e0b';
            } else {
                statusEl.textContent = 'Low Risk';
                statusEl.style.color = '#22c55e';
            }
        }

        function updateTrend(id, value) {
            const el = document.getElementById(id);
            if (value === null) {
                el.textContent = 'No change';
                el.className = 'trend-badge';
                return;
            }
            const isUp = value > 0;
            el.innerHTML = `<i class="fas fa-arrow-${isUp ? 'up' : 'down'}"></i> ${Math.abs(value)}%`;
            el.className = 'trend-badge ' + (isUp ? 'trend-up' : 'trend-down');
        }

        function renderCharts(data) {
            const labels = data.hourly_data.map(d => {
                const date = new Date(d.hour);
                return currentPeriod === '24h' ? date.getHours() + ':00' : date.toLocaleDateString([], {month: 'short', day: 'numeric'});
            });

            const ctx = document.getElementById('qualityChart').getContext('2d');
            const skyGradient = ctx.createLinearGradient(0, 0, 0, 400);
            skyGradient.addColorStop(0, 'rgba(14, 165, 233, 0.4)');
            skyGradient.addColorStop(1, 'rgba(14, 165, 233, 0)');

            // Quality Chart
            if (qualityChart) qualityChart.destroy();
            qualityChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Turbidity (NTU)',
                            data: data.hourly_data.map(d => d.turbidity),
                            borderColor: '#0ea5e9',
                            borderWidth: 4,
                            pointBackgroundColor: '#0ea5e9',
                            pointBorderColor: '#fff',
                            pointHoverRadius: 8,
                            backgroundColor: skyGradient,
                            fill: true,
                            tension: 0.4,
                            yAxisID: 'y'
                        },
                        {
                            label: 'TDS (PPM)',
                            data: data.hourly_data.map(d => d.tds),
                            borderColor: '#f59e0b',
                            borderWidth: 2,
                            borderDash: [5, 5],
                            pointRadius: 0,
                            backgroundColor: 'transparent',
                            tension: 0.4,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        y: { 
                            type: 'linear', 
                            position: 'left', 
                            grid: { color: 'rgba(0,0,0,0.03)', drawBorder: false },
                            ticks: { color: '#64748b', font: { weight: '600' } }
                        },
                        y1: { 
                            type: 'linear', 
                            position: 'right', 
                            grid: { display: false },
                            ticks: { color: '#64748b', font: { weight: '600' } }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: '#64748b', font: { weight: '600' } }
                        }
                    },
                    plugins: { 
                        legend: { 
                            position: 'top',
                            align: 'end',
                            labels: { color: '#64748b', usePointStyle: true, boxWidth: 6, font: { weight: '700', family: 'Outfit' } } 
                        },
                        tooltip: {
                            backgroundColor: '#fff',
                            titleColor: '#0f172a',
                            bodyColor: '#64748b',
                            titleFont: { size: 14, weight: '800' },
                            padding: 16,
                            cornerRadius: 12,
                            displayColors: true,
                            borderColor: 'rgba(0,0,0,0.05)',
                            borderWidth: 1,
                            boxPadding: 6
                        }
                    }
                }
            });

            // Temp Chart
            if (tempChart) tempChart.destroy();
            tempChart = new Chart(document.getElementById('tempChart'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Temperature (°C)',
                        data: data.hourly_data.map(d => d.temperature),
                        borderColor: '#ef4444',
                        tension: 0.4,
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { grid: { color: 'rgba(255,255,255,0.05)' } } }
                }
            });

            // Breach Percentage Donut
            if (breachChart) breachChart.destroy();
            
            const totalBreaches = data.breaches.turbidity_exceed + data.breaches.tds_exceed;
            const safeCount = Math.max(0, data.record_count - totalBreaches);
            const successRate = data.record_count > 0 ? ((safeCount / data.record_count) * 100).toFixed(1) : '100';

            breachChart = new Chart(document.getElementById('breachChart'), {
                type: 'doughnut',
                data: {
                    labels: ['Turbidity', 'TDS', 'Safe'],
                    datasets: [{
                        data: [data.breaches.turbidity_exceed, data.breaches.tds_exceed, safeCount],
                        backgroundColor: ['#0ea5e9', '#f59e0b', '#22c55e'],
                        hoverOffset: 10,
                        borderRadius: 8,
                        spacing: 4,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '82%',
                    plugins: {
                        legend: { position: 'bottom', labels: { color: '#64748b', usePointStyle: true, font: { weight: '700', family: 'Outfit' } } },
                        tooltip: { cornerRadius: 8, padding: 12 }
                    }
                },
                plugins: [{
                    id: 'centerText',
                    afterDraw: (chart) => {
                        const { ctx, width, height } = chart;
                        ctx.save();
                        ctx.textAlign = 'center';
                        ctx.textBaseline = 'middle';
                        
                        // Percentage
                        ctx.font = '800 2rem Outfit';
                        ctx.fillStyle = '#0f172a';
                        ctx.fillText(successRate + '%', width / 2, height / 2 - 5);
                        
                        // Label
                        ctx.font = '700 0.7rem Outfit';
                        ctx.fillStyle = '#64748b';
                        ctx.fillText('SAFE WATER', width / 2, height / 2 + 25);
                        ctx.restore();
                    }
                }]
            });
        }

        function setPeriod(p, btn) {
            currentPeriod = p;
            document.querySelectorAll('.period-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            fetchAnalytics();
        }

        // Init
        document.addEventListener('DOMContentLoaded', fetchAnalytics);
    </script>
</body>

</html>
