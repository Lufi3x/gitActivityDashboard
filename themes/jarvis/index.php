<?php
$defaultTheme = (isset($envVariables) && isset($envVariables['DEFAULT_THEME'])) ? $envVariables['DEFAULT_THEME'] : 'theme-blue';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>J.A.R.V.I.S. OS - System Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;500;700;900&family=Rajdhani:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="themes/jarvis/assets/css/style.css?v=3">
</head>
<body class="<?= htmlspecialchars($defaultTheme) ?>">

    <!-- Main Background HUD Overlay -->
    <div class="hud-bg-overlay"></div>

    <!-- Fullscreen Toggle Button -->
    <button id="fullscreenBtn" class="fullscreen-btn" title="Toggle Fullscreen">
        <i class="fa-solid fa-expand"></i>
    </button>

    <div class="ironman-hud-wrapper">
        
        <!-- =================== LEFT COLUMN =================== -->
        <aside class="hud-col-left">
            <!-- Shield Logo Area -->
            <div class="shield-logo-block">
                <div class="shield-icon">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div class="shield-text">
                    <strong>J.A.R.V.I.S.</strong><br>
                    <span>S.H.I.E.L.D. OS</span><br>
                    <small>...Created by Stark Ind...</small>
                </div>
            </div>

            <!-- Bridge Control Decoration -->
            <div class="bridge-control-deco">
                <div class="bridge-line"></div>
                <div class="bridge-label">BRIDGE CONTROL</div>
            </div>

            <!-- Skewed Navigation Menu -->
            <nav class="skewed-menu">
                <a href="#" class="skew-btn active"><div class="skew-bg"></div><span class="skew-text">MAIN SYSTEM</span></a>
                <a href="#" class="skew-btn"><div class="skew-bg"></div><span class="skew-text">REPOSITORIES</span></a>
                <a href="#" class="skew-btn"><div class="skew-bg"></div><span class="skew-text">STATISTICS</span></a>
                <a href="#" class="skew-btn"><div class="skew-bg"></div><span class="skew-text">ACTIVITY</span></a>
                <a href="#" class="skew-btn"><div class="skew-bg"></div><span class="skew-text">SETTINGS</span></a>
            </nav>

            <!-- Hologram Projection Stats -->
            <div class="hologram-stats-area">
                <div class="hologram-emitter">
                    <div class="emitter-base"></div>
                    <div class="holo-beam"></div>
                </div>
                <div class="holo-content" id="statsSection" style="display: none;">
                    <div class="holo-title">UpTime: <span id="statWorkTime">0m</span></div>
                    <div class="holo-grid">
                        <div class="holo-row header">
                            <span>TYPE</span>
                            <span>COUNT</span>
                        </div>
                        <div class="holo-row">
                            <span>Commits</span>
                            <strong id="statCommits">0</strong>
                        </div>
                        <div class="holo-row">
                            <span>Files</span>
                            <strong id="statChangedFiles">0</strong>
                        </div>
                        <div class="holo-row">
                            <span>Added</span>
                            <strong id="statAdditions" class="text-success">+0</strong>
                        </div>
                        <div class="holo-row">
                            <span>Deleted</span>
                            <strong id="statDeletions" class="text-danger">-0</strong>
                        </div>
                        <div class="holo-row">
                            <span>Modules</span>
                            <strong id="statRepos">0</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Left Radar -->
            <div class="left-radar-widget">
                <div class="radar-crosshair"></div>
                <div class="radar-ring-1"></div>
                <div class="radar-ring-2"></div>
                <div class="radar-ring-3"></div>
                <div class="radar-sweep"></div>
                <div class="radar-center-text">J.A.R.V.I.S</div>
                <div class="radar-dots"></div>
            </div>
        </aside>

        <!-- =================== CENTER COLUMN =================== -->
        <main class="hud-col-center">
            
            <!-- Iron Man Bust Placeholder -->
            <div class="ironman-bust-placeholder">
                <!-- Tasarımcınız buraya dev arka planı ekleyebilir: 'themes/jarvis/assets/images/ironman-bg.png' -->
            </div>

            <!-- Center Top Title -->
            <div class="center-top-text">
                <h2>J.A.R.V.I.S.</h2>
                <p>What Can I Compile For You, Sir?</p>
            </div>

            <!-- Center Side Menu (Like Google, Gmail in image) -->
            <ul class="center-side-menu">
                <li>GITHUB</li>
                <li>COMMITS</li>
                <li>PULL REQ</li>
                <li>ISSUES</li>
                <li>ACTIONS</li>
                <li>MODULES</li>
            </ul>

            <!-- Central Holographic Content Panels -->
            <div class="hologram-panels-wrapper" id="hologramPanelsWrapper" style="display: none;">
                <div class="holo-panel" id="panel-REPOSITORIES">
                    <h3>ACTIVE REPOSITORIES</h3>
                    <div class="panel-content" id="bigReposList"></div>
                </div>

                <div class="holo-panel" id="panel-STATISTICS">
                    <h3>SYSTEM STATISTICS</h3>
                    <div class="panel-content">
                        <p class="stat-highlight">Total Commits: <span id="bigStatCommits" class="text-success">0</span></p>
                        <div style="text-align:center; color: var(--text-muted); margin-bottom:10px;">Contribution Map (Last 5 Weeks)</div>
                        <div class="big-calendar-wrapper" id="bigCalendarGraph" style="display:flex; flex-wrap:wrap; gap:4px; justify-content:center;">
                            <!-- JS injected calendar -->
                        </div>
                    </div>
                </div>

                <div class="holo-panel" id="panel-ACTIVITY">
                    <h3>TIMELINE EVENTS</h3>
                    <div class="panel-content" id="bigActivityList"></div>
                </div>

                <div class="holo-panel" id="panel-SETTINGS">
                    <h3>SYSTEM PREFERENCES</h3>
                    <div class="panel-content">
                        <div class="setting-item">
                            <span>HUD Color Scheme</span>
                            <strong class="text-success">J.A.R.V.I.S BLUE</strong>
                        </div>
                        <div class="setting-item">
                            <span>Voice Interface</span>
                            <strong class="text-danger">OFFLINE</strong>
                        </div>
                        <div class="setting-item">
                            <span>Data Source</span>
                            <strong>GITHUB API</strong>
                        </div>
                        <div class="setting-item">
                            <span>Auto Refresh</span>
                            <strong class="text-success">ENABLED</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Arc Reactor -->
            <div class="chest-arc-reactor">
                <div class="arc-reactor">
                    <div class="ring ring-outer"></div>
                    <div class="ring ring-middle"></div>
                    <div class="ring ring-inner">
                        <div class="reactor-core"></div>
                    </div>
                </div>
            </div>

            <!-- Center Bottom Status -->
            <div class="center-bottom-status">
                <p>Currently compilation level is at <strong id="powerLevel">100</strong> percent and holding steady.</p>
                <div class="timeline-ticker-wrapper">
                    <div class="timeline-ticker" id="activityTimeline">
                        <!-- JS logs -->
                        <div class="loading-state">SYSTEM BOOTING...</div>
                    </div>
                </div>
            </div>

        </main>

        <!-- =================== RIGHT COLUMN =================== -->
        <aside class="hud-col-right">
            
            <!-- Date/Time Block -->
            <div class="date-time-block">
                <div class="date-large" id="dayLarge">00</div>
                <div class="date-details">
                    <div id="monthText">MONTH</div>
                    <div id="dayText">DAY</div>
                </div>
            </div>

            <!-- System Monitor (Long term stats) -->
            <div class="system-monitor" id="longTermStatsSection" style="display: none;">
                <h3 class="monitor-title">SYSTEM PROTOCOLS</h3>
                
                <div class="progress-bar-group">
                    <div class="prog-label"><span>Weekly Commits</span> <span id="statWeekly">0</span></div>
                    <div class="prog-track"><div class="prog-fill" id="barWeekly" style="width: 0%"></div></div>
                </div>
                
                <div class="progress-bar-group">
                    <div class="prog-label"><span>Monthly Commits</span> <span id="statMonthly">0</span></div>
                    <div class="prog-track"><div class="prog-fill" id="barMonthly" style="width: 0%"></div></div>
                </div>
                
                <div class="progress-bar-group">
                    <div class="prog-label"><span>Yearly Commits</span> <span id="statYearly">0</span></div>
                    <div class="prog-track"><div class="prog-fill" id="barYearly" style="width: 0%"></div></div>
                </div>
            </div>

            <!-- Active Modules (Disk usage equivalent) -->
            <div class="disk-monitor">
                <h3 class="monitor-title">ACTIVE MODULES</h3>
                <ul class="disk-list" id="activeProjectsList">
                    <!-- JS list -->
                </ul>
            </div>

            <!-- Standing Armor Wireframe -->
            <div class="standing-armor-placeholder">
                <!-- Tasarımcı buraya ayakta duran Iron Man png'sini atabilir: 'images/armor-wireframe.png' -->
            </div>

            <!-- Right Bottom Dial (Calendar equivalent) -->
            <div class="right-dial-widget" id="calendarSection" style="display: none;">
                <!-- Dairesel Takvim veya Normal Takvim Wrapper -->
                <div class="calendar-wrapper">
                    <div class="calendar-graph" id="calendarGraph">
                        <!-- JS takvim -->
                    </div>
                </div>
            </div>

        </aside>

    </div>

    <script src="themes/jarvis/assets/js/app.js"></script>
</body>
</html>