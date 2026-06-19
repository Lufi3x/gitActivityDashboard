<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Monitor - GitHub</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="themes/activity_monitor/assets/css/style.css">
</head>
<body>
    <div class="window-container">
        <!-- Mac Window Header -->
        <header class="window-header">
            <div class="window-controls">
                <span class="control close"></span>
                <span class="control minimize"></span>
                <span class="control maximize"></span>
            </div>
            <div class="window-title">Activity Monitor - GitHub Activity Data</div>
        </header>

        <!-- Main Content Layout -->
        <div class="main-layout">
            <!-- Sidebar for Active Projects & Quick Stats -->
            <aside class="sidebar">
                <div class="sidebar-section">
                    <h3>System Stats</h3>
                    <ul class="stat-list">
                        <li>
                            <span class="label">Commits Today</span>
                            <span class="value" id="statCommits">0</span>
                        </li>
                        <li>
                            <span class="label">Lines Added</span>
                            <span class="value success" id="statAdditions">0</span>
                        </li>
                        <li>
                            <span class="label">Lines Deleted</span>
                            <span class="value danger" id="statDeletions">0</span>
                        </li>
                        <li>
                            <span class="label">Active Repos</span>
                            <span class="value" id="statRepos">0</span>
                        </li>
                        <li>
                            <span class="label">Session Time</span>
                            <span class="value" id="statWorkTime">0m</span>
                        </li>
                    </ul>
                </div>

                <div class="sidebar-section" id="activeProjectsSection">
                    <h3>Active Modules</h3>
                    <ul class="project-list" id="activeProjectsList">
                        <!-- Projects loaded via JS -->
                        <li class="loading-text">Loading...</li>
                    </ul>
                </div>
            </aside>

            <!-- Main Data View -->
            <main class="data-view">
                <!-- Tab Bar -->
                <div class="tab-bar">
                    <button class="tab active">System Logs</button>
                    <button class="tab">Network Graph</button>
                    <button class="tab">Disk Usage</button>
                </div>
                
                <!-- Table Area -->
                <div class="table-container" id="activityTableContainer">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 15%">Time</th>
                                <th style="width: 10%">Type</th>
                                <th style="width: 25%">Process (Repo)</th>
                                <th style="width: 50%">Details</th>
                            </tr>
                        </thead>
                        <tbody id="activityTableBody">
                            <tr>
                                <td colspan="4" class="loading-state">Initializing tracking daemons...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Footer Stats -->
                <footer class="window-footer">
                    <div class="footer-stat">
                        <span class="label">Weekly:</span> <strong id="statWeekly">0</strong>
                    </div>
                    <div class="footer-stat">
                        <span class="label">Monthly:</span> <strong id="statMonthly">0</strong>
                    </div>
                    <div class="footer-stat">
                        <span class="label">Yearly:</span> <strong id="statYearly">0</strong>
                    </div>
                </footer>
            </main>
        </div>
    </div>

    <script src="themes/activity_monitor/assets/js/app.js"></script>
</body>
</html>
