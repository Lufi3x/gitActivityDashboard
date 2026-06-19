<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TERMINAL - ROOT ACCESS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=VT323&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="themes/hacker_terminal/assets/css/style.css">
</head>
<body>
    
    <!-- CRT Overlay Effects -->
    <div class="crt-scanlines"></div>
    <div class="crt-flicker"></div>

    <div class="terminal">
        <header class="term-header">
            <pre>
  ____  _ _     _           _     ___        __      
 / ___|(_) |__ | |__  _   _| |__ |_ _|_ __  / _| ___ 
| |  _ | | '_ \| '_ \| | | | '_ \ | || '_ \| |_ / _ \
| |_| || | | | | | | | |_| | |_) || || | | |  _| (_) |
 \____||_|_| |_|_| |_|\__,_|_.__/|___|_| |_|_|  \___/
                                                     
SYSTEM INITIALIZED. WELCOME, ROOT.
            </pre>
        </header>

        <div class="term-grid">
            
            <!-- Left Column: Stats -->
            <div class="term-col">
                <div class="ascii-box">
                    <div class="box-title">[ SYSTEM STATUS ]</div>
                    <div class="box-content">
                        <p>> COMMITS_TODAY : <span id="valCommits" class="glow-text">0</span></p>
                        <p>> LINES_ADDED   : <span id="valAdditions">0</span></p>
                        <p>> LINES_DELETED : <span id="valDeletions" class="alert-text">0</span></p>
                        <p>> ACTIVE_REPOS  : <span id="valRepos">0</span></p>
                        <br>
                        <p>> UPTIME        : <span id="valWorkTime">0m</span></p>
                        <p>> WEEKLY_LAPS   : <span id="valWeekly">0</span></p>
                        <p>> MONTHLY_LAPS  : <span id="valMonthly">0</span></p>
                    </div>
                </div>

                <div class="ascii-box" style="margin-top: 20px;">
                    <div class="box-title">[ PROCESS LIST ]</div>
                    <div class="box-content" id="activeProjectsList">
                        <p class="loading">Scanning processes<span class="dot-anim">...</span></p>
                    </div>
                </div>
            </div>

            <!-- Right Column: Logs -->
            <div class="term-col">
                <div class="ascii-box log-box">
                    <div class="box-title">[ NETWORK ACTIVITY LOG ]</div>
                    <div class="box-content" id="logsContainer">
                        <div id="activityList">
                            <p>> Establishing secure connection...</p>
                        </div>
                        <div class="cursor-line">
                            <span class="prompt">root@github:~#</span> <span class="typing" id="typingLine"></span><span class="cursor">_</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script src="themes/hacker_terminal/assets/js/app.js"></script>
</body>
</html>
