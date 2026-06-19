<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nitro HUD - GitHub Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Teko:wght@400;600;700&family=Rajdhani:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="themes/nitro_hud/assets/css/style.css">

    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="icon" type="image/svg+xml" href="icon0.svg">
    <link rel="apple-touch-icon" href="apple-icon.png">
    <link rel="manifest" href="manifest.json">
</head>
<body>
    
    <div class="carbon-bg"></div>

    <!-- Dashboard Instrument Cluster -->
    <div class="cluster-container">
        
        <!-- LEFT DIAL: Speedometer (Additions) -->
        <div class="dial-box">
            <div class="dial">
                <svg viewBox="0 0 200 200" class="dial-svg">
                    <!-- Outer Ring -->
                    <circle cx="100" cy="100" r="95" class="outer-ring"></circle>
                    <!-- Ticks -->
                    <circle cx="100" cy="100" r="85" class="ticks"></circle>
                    <!-- Blue Inner Glow Track -->
                    <path class="progress-track" d="M 40 160 A 85 85 0 1 1 160 160" fill="none" />
                    <path class="progress-value" id="speedProgress" d="M 40 160 A 85 85 0 1 1 160 160" fill="none" stroke-dasharray="400" stroke-dashoffset="400" />
                </svg>
                <!-- Needle -->
                <div class="needle" id="speedNeedle">
                    <div class="needle-tip"></div>
                </div>
                <!-- Center Display -->
                <div class="dial-center">
                    <span class="value" id="valAdditions">0</span>
                    <span class="unit">LINES / H</span>
                </div>
            </div>
            <div class="dial-label">CODE VELOCITY</div>
        </div>

        <!-- CENTER DISPLAY: Trip Computer / Telemetry -->
        <div class="center-display">
            <div class="center-header">
                <span class="indicator green"></span>
                <span>TELEMETRY ON</span>
            </div>
            <div class="center-stats">
                <div class="c-row">
                    <span class="c-label">DELETIONS</span>
                    <span class="c-val text-danger" id="valDeletions">0</span>
                </div>
                <div class="c-row">
                    <span class="c-label">ACTIVE REPOS</span>
                    <span class="c-val" id="valRepos">0</span>
                </div>
                <div class="c-row">
                    <span class="c-label">SESSION TIME</span>
                    <span class="c-val" id="valWorkTime">0m</span>
                </div>
                <div class="c-divider"></div>
                <div class="c-row">
                    <span class="c-label">WEEKLY LAPS</span>
                    <span class="c-val text-accent" id="valWeekly">0</span>
                </div>
                <div class="c-row">
                    <span class="c-label">MONTHLY LAPS</span>
                    <span class="c-val text-accent" id="valMonthly">0</span>
                </div>
            </div>
            <!-- Live Logs scrolling ticker -->
            <div class="live-ticker" id="logsContainer">
                <ul class="ticker-list" id="activityList">
                    <li>Initializing engine tracking...</li>
                </ul>
            </div>
        </div>

        <!-- RIGHT DIAL: Tachometer (RPM / Commits) -->
        <div class="dial-box">
            <div class="dial">
                <svg viewBox="0 0 200 200" class="dial-svg">
                    <circle cx="100" cy="100" r="95" class="outer-ring"></circle>
                    <circle cx="100" cy="100" r="85" class="ticks"></circle>
                    <path class="progress-track" d="M 40 160 A 85 85 0 1 1 160 160" fill="none" />
                    <!-- Redline track -->
                    <path class="redline-track" d="M 160 160 A 85 85 0 0 0 185 100" fill="none" />
                    <path class="progress-value" id="rpmProgress" d="M 40 160 A 85 85 0 1 1 160 160" fill="none" stroke-dasharray="400" stroke-dashoffset="400" />
                </svg>
                <div class="needle" id="rpmNeedle">
                    <div class="needle-tip"></div>
                </div>
                <div class="dial-center">
                    <span class="value" id="valCommits">0</span>
                    <span class="unit">COMMITS</span>
                </div>
            </div>
            <div class="dial-label">ENGINE RPM</div>
        </div>

    </div>

    <script src="themes/nitro_hud/assets/js/app.js"></script>
</body>
</html>
