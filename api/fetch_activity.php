<?php
require_once 'config.php';

if (empty(GITHUB_TOKEN)) {
    echo json_encode(["error" => "Lütfen .env dosyasına GitHub Token'ınızı girin."]);
    exit;
}

$cacheFile = 'cache.json';
$cacheTime = CACHE_DURATION_MINUTES * 60; // Dakikayı saniyeye çevir

// Cache kontrolü
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
    echo file_get_contents($cacheFile);
    exit;
}

$url = "https://api.github.com/users/" . GITHUB_USERNAME . "/events";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "User-Agent: PHP-GitActivityDashboard",
    "Authorization: token " . GITHUB_TOKEN,
    "Accept: application/vnd.github.v3+json"
]);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

if ($httpcode === 200) {
    $events = json_decode($response, true);
    
    $filtered_events = [];
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $nowTime = time();
    $twentyFourHoursAgo = $nowTime - (24 * 3600);

    $periods = [
        'today' => [
            "commits" => 0, "additions" => 0, "deletions" => 0, "changed_files" => 0, "repos" => 0, "work_time" => "0 Dakika", "active_projects" => [], "unique_repos" => [], "timestamps" => []
        ],
        'yesterday' => [
            "commits" => 0, "additions" => 0, "deletions" => 0, "changed_files" => 0, "repos" => 0, "work_time" => "0 Dakika", "active_projects" => [], "unique_repos" => [], "timestamps" => []
        ],
        'last_24h' => [
            "commits" => 0, "additions" => 0, "deletions" => 0, "changed_files" => 0, "repos" => 0, "work_time" => "0 Dakika", "active_projects" => [], "unique_repos" => [], "timestamps" => []
        ]
    ];
    
    $stats = [
        "commits" => 0,
        "additions" => 0,
        "deletions" => 0,
        "changed_files" => 0,
        "repos" => 0,
        "work_time" => "0 Dakika",
        "active_projects" => [],
        "today" => [],
        "yesterday" => [],
        "last_24h" => [],
        "weekly_commits" => 0,
        "monthly_commits" => 0,
        "yearly_commits" => 0,
        "calendar" => []
    ];

    foreach ($events as $event) {
        $type = $event['type'];
        $repoName = $event['repo']['name'];
        $createdAt = $event['created_at'];
        $eventTimestamp = strtotime($createdAt);
        $eventDate = date('Y-m-d', $eventTimestamp);
        
        $isToday = ($eventDate === $today);
        $isYesterday = ($eventDate === $yesterday);
        $isLast24h = ($eventTimestamp >= $twentyFourHoursAgo);
        
        $isRelevant = ($isToday || $isYesterday || $isLast24h);
        $actionDetails = "";
        
        if ($type === "PushEvent") {
            $commits = isset($event['payload']['commits']) ? $event['payload']['commits'] : [];
            $commitCount = count($commits);
            
            $pushAdditions = 0;
            $pushDeletions = 0;
            $pushChangedFiles = 0;
            
            if ($isRelevant && isset($event['payload']['before']) && isset($event['payload']['head'])) {
                $before = $event['payload']['before'];
                $head = $event['payload']['head'];
                
                if ($before !== '0000000000000000000000000000000000000000') {
                    $compareUrl = "https://api.github.com/repos/{$repoName}/compare/{$before}...{$head}";
                    $chCompare = curl_init();
                    curl_setopt($chCompare, CURLOPT_URL, $compareUrl);
                    curl_setopt($chCompare, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($chCompare, CURLOPT_HTTPHEADER, [
                        "User-Agent: PHP-GitActivityDashboard",
                        "Authorization: token " . GITHUB_TOKEN,
                        "Accept: application/vnd.github.v3+json"
                    ]);
                    $compareResponse = curl_exec($chCompare);
                    if (curl_getinfo($chCompare, CURLINFO_HTTP_CODE) === 200) {
                        $compareData = json_decode($compareResponse, true);
                        if (isset($compareData['total_commits'])) {
                            $commitCount = $compareData['total_commits']; 
                        }
                        if (isset($compareData['files'])) {
                            $pushChangedFiles += count($compareData['files']);
                            foreach ($compareData['files'] as $file) {
                                $pushAdditions += $file['additions'] ?? 0;
                                $pushDeletions += $file['deletions'] ?? 0;
                            }
                        }
                    }
                }
            }

            if (count($commits) > 0) {
                $commitMessages = array_map(function($commit) { return $commit['message'] ?? ''; }, $commits);
                $fileInfo = ($pushChangedFiles > 0) ? " ({$pushChangedFiles} dosya)" : "";
                $actionDetails = $commitCount . " commit{$fileInfo} pushlandı: " . implode(" | ", $commitMessages);
            } else {
                $ref = isset($event['payload']['ref']) ? str_replace('refs/heads/', '', $event['payload']['ref']) : 'bir';
                if ($commitCount === 0) $commitCount = 1; 
                $fileInfo = ($pushChangedFiles > 0) ? " ({$pushChangedFiles} dosya)" : "";
                $actionDetails = "{$commitCount} commit{$fileInfo} pushlandı ({$ref} dalına).";
            }
            
            if ($isToday) {
                $periods['today']['commits'] += $commitCount;
                $periods['today']['additions'] += $pushAdditions;
                $periods['today']['deletions'] += $pushDeletions;
                $periods['today']['changed_files'] += $pushChangedFiles;
                $periods['today']['unique_repos'][$repoName] = true;
            }
            if ($isYesterday) {
                $periods['yesterday']['commits'] += $commitCount;
                $periods['yesterday']['additions'] += $pushAdditions;
                $periods['yesterday']['deletions'] += $pushDeletions;
                $periods['yesterday']['changed_files'] += $pushChangedFiles;
                $periods['yesterday']['unique_repos'][$repoName] = true;
            }
            if ($isLast24h) {
                $periods['last_24h']['commits'] += $commitCount;
                $periods['last_24h']['additions'] += $pushAdditions;
                $periods['last_24h']['deletions'] += $pushDeletions;
                $periods['last_24h']['changed_files'] += $pushChangedFiles;
                $periods['last_24h']['unique_repos'][$repoName] = true;
            }

        } elseif ($type === "CreateEvent") {
            $actionDetails = $event['payload']['ref_type'] . " oluşturuldu.";
            if ($isToday) $periods['today']['unique_repos'][$repoName] = true;
            if ($isYesterday) $periods['yesterday']['unique_repos'][$repoName] = true;
            if ($isLast24h) $periods['last_24h']['unique_repos'][$repoName] = true;
        } elseif ($type === "WatchEvent") {
            $actionDetails = "Depo yıldızlandı.";
        } elseif ($type === "IssuesEvent") {
            $actionDetails = "Issue " . $event['payload']['action'];
            if ($isToday) $periods['today']['unique_repos'][$repoName] = true;
            if ($isYesterday) $periods['yesterday']['unique_repos'][$repoName] = true;
            if ($isLast24h) $periods['last_24h']['unique_repos'][$repoName] = true;
        } elseif ($type === "PullRequestEvent") {
            $actionDetails = "Pull Request " . $event['payload']['action'];
            if ($isToday) $periods['today']['unique_repos'][$repoName] = true;
            if ($isYesterday) $periods['yesterday']['unique_repos'][$repoName] = true;
            if ($isLast24h) $periods['last_24h']['unique_repos'][$repoName] = true;
        } else {
             $actionDetails = $type . " etkinliği";
        }
        
        $filtered_events[] = [
            "type" => $type,
            "repo" => $repoName,
            "date" => $createdAt,
            "details" => $actionDetails
        ];
        
        if ($isToday) $periods['today']['timestamps'][] = $eventTimestamp;
        if ($isYesterday) $periods['yesterday']['timestamps'][] = $eventTimestamp;
        if ($isLast24h) $periods['last_24h']['timestamps'][] = $eventTimestamp;
    }
    
    foreach (['today', 'yesterday', 'last_24h'] as $pKey) {
        foreach ($periods[$pKey]['unique_repos'] as $repo => $val) {
            $periods[$pKey]['repos']++;
            $parts = explode('/', $repo);
            $projectName = count($parts) > 1 ? $parts[1] : $repo;
            $periods[$pKey]['active_projects'][] = ucfirst($projectName);
        }
        
        if (count($periods[$pKey]['timestamps']) > 0) {
            sort($periods[$pKey]['timestamps']);
            $totalMinutes = 0;
            $sessionStart = null;
            $lastTime = null;
            
            foreach ($periods[$pKey]['timestamps'] as $time) {
                if ($lastTime === null) {
                    $sessionStart = $time;
                    $lastTime = $time;
                } elseif (($time - $lastTime) <= 3600) {
                    $lastTime = $time;
                } else {
                    $sessionDuration = ($lastTime - $sessionStart) / 60;
                    $totalMinutes += $sessionDuration + 30;
                    $sessionStart = $time;
                    $lastTime = $time;
                }
            }
            if ($sessionStart !== null) {
                $sessionDuration = ($lastTime - $sessionStart) / 60;
                $totalMinutes += $sessionDuration + 30;
            }
            
            $totalMinutes = round($totalMinutes);
            if ($totalMinutes >= 60) {
                $hours = floor($totalMinutes / 60);
                $mins = $totalMinutes % 60;
                $periods[$pKey]['work_time'] = "{$hours} Saat " . ($mins > 0 ? "{$mins} Dakika" : "");
            } else {
                $periods[$pKey]['work_time'] = "{$totalMinutes} Dakika";
            }
        }
    }

    $stats['commits'] = $periods['today']['commits'];
    $stats['additions'] = $periods['today']['additions'];
    $stats['deletions'] = $periods['today']['deletions'];
    $stats['changed_files'] = $periods['today']['changed_files'];
    $stats['repos'] = $periods['today']['repos'];
    $stats['work_time'] = $periods['today']['work_time'];
    $stats['active_projects'] = $periods['today']['active_projects'];

    $stats['today'] = [
        "commits" => $periods['today']['commits'],
        "additions" => $periods['today']['additions'],
        "deletions" => $periods['today']['deletions'],
        "changed_files" => $periods['today']['changed_files'],
        "repos" => $periods['today']['repos'],
        "work_time" => $periods['today']['work_time'],
        "active_projects" => $periods['today']['active_projects']
    ];
    $stats['yesterday'] = [
        "commits" => $periods['yesterday']['commits'],
        "additions" => $periods['yesterday']['additions'],
        "deletions" => $periods['yesterday']['deletions'],
        "changed_files" => $periods['yesterday']['changed_files'],
        "repos" => $periods['yesterday']['repos'],
        "work_time" => $periods['yesterday']['work_time'],
        "active_projects" => $periods['yesterday']['active_projects']
    ];
    $stats['last_24h'] = [
        "commits" => $periods['last_24h']['commits'],
        "additions" => $periods['last_24h']['additions'],
        "deletions" => $periods['last_24h']['deletions'],
        "changed_files" => $periods['last_24h']['changed_files'],
        "repos" => $periods['last_24h']['repos'],
        "work_time" => $periods['last_24h']['work_time'],
        "active_projects" => $periods['last_24h']['active_projects']
    ];
    
    // --- GRAPHQL BÖLÜMÜ (KATKI TAKVİMİ) ---
    $graphqlUrl = "https://api.github.com/graphql";
    $query = '{"query": "query { user(login: \"' . GITHUB_USERNAME . '\") { contributionsCollection { contributionCalendar { totalContributions weeks { contributionDays { contributionCount date } } } } } }"}';
    
    $chGraph = curl_init();
    curl_setopt($chGraph, CURLOPT_URL, $graphqlUrl);
    curl_setopt($chGraph, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chGraph, CURLOPT_POST, true);
    curl_setopt($chGraph, CURLOPT_POSTFIELDS, $query);
    curl_setopt($chGraph, CURLOPT_HTTPHEADER, [
        "User-Agent: PHP-GitActivityDashboard",
        "Authorization: bearer " . GITHUB_TOKEN,
        "Content-Type: application/json"
    ]);
    
    $graphResponse = curl_exec($chGraph);
    if (curl_getinfo($chGraph, CURLINFO_HTTP_CODE) === 200) {
        $graphData = json_decode($graphResponse, true);
        if (isset($graphData['data']['user']['contributionsCollection']['contributionCalendar'])) {
            $calendar = $graphData['data']['user']['contributionsCollection']['contributionCalendar'];
            
            $stats['yearly_commits'] = $calendar['totalContributions'] ?? 0;
            
            // Tüm günleri düz bir listeye al
            $allDays = [];
            foreach ($calendar['weeks'] as $week) {
                foreach ($week['contributionDays'] as $day) {
                    $allDays[] = $day;
                }
            }
            
            $stats['calendar'] = $calendar['weeks']; // Arayüz için haftalık yapı

            // Son 7 ve 30 günün hesaplanması (tersten)
            $totalDays = count($allDays);
            for ($i = 1; $i <= 30; $i++) {
                if ($totalDays - $i >= 0) {
                    $dayCount = $allDays[$totalDays - $i]['contributionCount'];
                    $stats['monthly_commits'] += $dayCount;
                    if ($i <= 7) {
                        $stats['weekly_commits'] += $dayCount;
                    }
                }
            }
        }
    }
    
    // GİZLİLİK VE MODÜL KONTROLLERİ
    $showLogs = defined('SHOW_SYSTEM_LOGS') ? SHOW_SYSTEM_LOGS : true;
    $showProjects = defined('SHOW_ACTIVE_PROJECTS') ? SHOW_ACTIVE_PROJECTS : true;
    
    if (!$showLogs) {
        $filtered_events = []; // İfşayı engellemek için logları API'den bile siliyoruz
    }
    
    if (!$showProjects) {
        $stats['active_projects'] = [];
        $stats['today']['active_projects'] = [];
        $stats['yesterday']['active_projects'] = [];
        $stats['last_24h']['active_projects'] = [];
    }
    
    $output = json_encode([
        "success" => true, 
        "data" => $filtered_events, 
        "stats" => $stats,
        "config" => [
            "show_system_logs" => $showLogs,
            "show_active_projects" => $showProjects,
            "default_theme" => defined('DEFAULT_THEME') ? DEFAULT_THEME : 'theme-cyan'
        ]
    ]);
    
    // Cache'i kaydet
    file_put_contents($cacheFile, $output);
    
    echo $output;
} else {
    echo json_encode([
        "error" => "GitHub API'sine bağlanırken bir sorun oluştu.", 
        "http_code" => $httpcode,
        "curl_error" => $error
    ]);
}
