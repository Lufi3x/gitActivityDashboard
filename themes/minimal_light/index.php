<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Overview - Minimal Light</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="themes/minimal_light/assets/css/style.css">

    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="icon" type="image/svg+xml" href="icon0.svg">
    <link rel="apple-touch-icon" href="apple-icon.png">
    <link rel="manifest" href="manifest.json">
</head>
<body>

    <div class="app-container">
        <!-- Header -->
        <header class="app-header">
            <div class="header-content">
                <h1>Overview</h1>
                <p class="subtitle">Your daily development metrics</p>
            </div>
            <div class="status-badge">
                <span class="status-dot"></span>
                System Online
            </div>
        </header>

        <!-- Metric Cards -->
        <div class="metrics-grid">
            <div class="metric-card">
                <h3 class="metric-title">Commits</h3>
                <div class="metric-value" id="valCommits">0</div>
                <div class="metric-trend positive">Active today</div>
            </div>
            <div class="metric-card">
                <h3 class="metric-title">Changed Files</h3>
                <div class="metric-value" id="valChangedFiles">0</div>
                <div class="metric-trend">Files touched</div>
            </div>
            <div class="metric-card">
                <h3 class="metric-title">Lines Added</h3>
                <div class="metric-value text-blue" id="valAdditions">0</div>
                <div class="metric-trend">Code injected</div>
            </div>
            <div class="metric-card">
                <h3 class="metric-title">Lines Deleted</h3>
                <div class="metric-value text-red" id="valDeletions">0</div>
                <div class="metric-trend">Technical debt removed</div>
            </div>
            <div class="metric-card">
                <h3 class="metric-title">Work Time</h3>
                <div class="metric-value" id="valWorkTime">0m</div>
                <div class="metric-trend">Session duration</div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="content-grid">
            
            <!-- Recent Activity Timeline -->
            <div class="card timeline-card">
                <div class="card-header">
                    <h2>Recent Activity</h2>
                </div>
                <div class="card-body">
                    <ul class="timeline" id="activityList">
                        <li class="timeline-loading">Loading activity data...</li>
                    </ul>
                </div>
            </div>

            <!-- Side Panel (Projects & Stats) -->
            <div class="side-panel">
                
                <div class="card">
                    <div class="card-header">
                        <h2>Active Repositories</h2>
                        <span class="badge" id="valRepos">0</span>
                    </div>
                    <div class="card-body">
                        <ul class="project-list" id="activeProjectsList">
                            <li class="text-muted">Loading repositories...</li>
                        </ul>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h2>Historical Data</h2>
                    </div>
                    <div class="card-body">
                        <div class="history-stat">
                            <span class="history-label">This Week</span>
                            <span class="history-value" id="valWeekly">0</span>
                        </div>
                        <div class="history-stat">
                            <span class="history-label">This Month</span>
                            <span class="history-value" id="valMonthly">0</span>
                        </div>
                        <div class="history-stat">
                            <span class="history-label">This Year</span>
                            <span class="history-value" id="valYearly">0</span>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>

    <script src="themes/minimal_light/assets/js/app.js"></script>
</body>
</html>
