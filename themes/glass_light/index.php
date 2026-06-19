<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Glassmorphism</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="themes/glass_light/assets/css/style.css">

    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="icon" type="image/svg+xml" href="icon0.svg">
    <link rel="apple-touch-icon" href="apple-icon.png">
    <link rel="manifest" href="manifest.json">
</head>
<body>

    <!-- Animated Mesh Background -->
    <div class="mesh-bg">
        <div class="color-blob blob-1"></div>
        <div class="color-blob blob-2"></div>
        <div class="color-blob blob-3"></div>
    </div>

    <div class="glass-container">
        
        <!-- Header -->
        <header class="glass-header">
            <div class="header-titles">
                <h1>Overview</h1>
                <p>Welcome to your creative space</p>
            </div>
            <div class="user-profile">
                <div class="avatar-ring">
                    <img src="https://github.com/identicons/lutfullahuygur.png" alt="User">
                </div>
            </div>
        </header>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="glass-card stat-card">
                <div class="stat-icon pink-gradient">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
                </div>
                <div class="stat-info">
                    <h3>Commits</h3>
                    <p class="value" id="valCommits">0</p>
                </div>
            </div>

            <div class="glass-card stat-card">
                <div class="stat-icon blue-gradient">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                </div>
                <div class="stat-info">
                    <h3>Lines Added</h3>
                    <p class="value" id="valAdditions">0</p>
                </div>
            </div>

            <div class="glass-card stat-card">
                <div class="stat-icon orange-gradient">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                </div>
                <div class="stat-info">
                    <h3>Work Time</h3>
                    <p class="value" id="valWorkTime">0m</p>
                </div>
            </div>
        </div>

        <div class="main-content">
            
            <!-- Left Side: Timeline -->
            <div class="glass-card large-card">
                <div class="card-title">Recent Activity</div>
                <div class="timeline-container">
                    <ul class="glass-timeline" id="activityList">
                        <li class="loading-text">Synchronizing data streams...</li>
                    </ul>
                </div>
            </div>

            <!-- Right Side: Repos & Info -->
            <div class="side-content">
                
                <div class="glass-card">
                    <div class="card-title">Active Repositories</div>
                    <ul class="repo-list" id="activeProjectsList">
                        <li class="loading-text">Loading...</li>
                    </ul>
                </div>

                <div class="glass-card metric-summary">
                    <div class="card-title">History</div>
                    <div class="summary-row">
                        <span>Weekly</span>
                        <span class="summary-val" id="valWeekly">0</span>
                    </div>
                    <div class="summary-row">
                        <span>Monthly</span>
                        <span class="summary-val" id="valMonthly">0</span>
                    </div>
                </div>

            </div>

        </div>

    </div>

    <script src="themes/glass_light/assets/js/app.js"></script>
</body>
</html>
