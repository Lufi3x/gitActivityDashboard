<?php
require_once 'config.php';

if (empty(GITHUB_TOKEN)) {
    echo json_encode(["error" => "Lütfen .env dosyasına GitHub Token'ınızı girin."]);
    exit;
}

$cacheFile = 'cache.json';
$cacheTime = CACHE_DURATION_MINUTES * 60; // Dakikayı saniyeye çevir
$forceRefresh = isset($_GET['force_refresh']) || isset($_GET['clear_cache']);

// Eğer zorla yenileme isteniyorsa cache dosyalarını temizle
if ($forceRefresh) {
    if (file_exists($cacheFile)) { @unlink($cacheFile); }
    $allTimeCacheTmp = __DIR__ . '/all_time_projects_cache.json';
    if (file_exists($allTimeCacheTmp)) { @unlink($allTimeCacheTmp); }
}

// Cache kontrolü
$servedStale = false;
if (!$forceRefresh && file_exists($cacheFile)) {
    $cached = file_get_contents($cacheFile);
    if ((time() - filemtime($cacheFile)) < $cacheTime) {
        echo $cached;
        exit;
    }
    // Bayat cache: eski veriyi hemen gönder, yenisini arka planda hesapla (hesap 30-60 sn sürüyor).
    // Aynı anda tek yenileme olsun diye kilit; kilit alınamazsa sadece eski veriyi ver.
    $refreshLock = fopen(__DIR__ . '/refresh.lock', 'c');
    if (!$refreshLock || !flock($refreshLock, LOCK_EX | LOCK_NB)) {
        echo $cached;
        exit;
    }
    ignore_user_abort(true);
    @set_time_limit(300);
    header('Content-Length: ' . strlen($cached));
    header('Connection: close');
    echo $cached;
    while (ob_get_level() > 0) { ob_end_flush(); }
    flush();
    if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); }
    elseif (function_exists('litespeed_finish_request')) { litespeed_finish_request(); }
    $servedStale = true;
}

$url = "https://api.github.com/users/" . GITHUB_USERNAME . "/events?per_page=100";

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
            "commits" => 0, "additions" => 0, "deletions" => 0, "changed_files" => 0, "repos" => 0, "work_time" => "0 Dakika", "active_projects" => [], "unique_repos" => [], "timestamps" => [], "hourly_activity" => array_fill(0, 24, 0)
        ],
        'yesterday' => [
            "commits" => 0, "additions" => 0, "deletions" => 0, "changed_files" => 0, "repos" => 0, "work_time" => "0 Dakika", "active_projects" => [], "unique_repos" => [], "timestamps" => [], "hourly_activity" => array_fill(0, 24, 0)
        ],
        'last_24h' => [
            "commits" => 0, "additions" => 0, "deletions" => 0, "changed_files" => 0, "repos" => 0, "work_time" => "0 Dakika", "active_projects" => [], "unique_repos" => [], "timestamps" => [], "hourly_activity" => array_fill(0, 24, 0)
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
        "calendar" => [],
        "avg_daily_work_time_str" => "0 Dakika",
        "avg_weekly_work_time_str" => "0 Dakika",
        "avg_monthly_work_time_str" => "0 Dakika",
        "avg_daily_commits" => 0,
        "avg_weekly_commits" => 0,
        "avg_monthly_commits" => 0,
        "real_today_work_time_str" => "0 Dakika",
        "real_weekly_work_time_str" => "0 Dakika",
        "real_monthly_work_time_str" => "0 Dakika",
        "daily_log" => []
    ];

    $repoStatsMap = [];

    foreach ($events as $event) {
        $type = $event['type'];
        $repoName = $event['repo']['name'];
        $createdAt = $event['created_at'];
        $eventTimestamp = strtotime($createdAt);
        $eventDate = date('Y-m-d', $eventTimestamp);

        if (!isset($repoStatsMap[$repoName])) {
            $parts = explode('/', $repoName);
            $repoStatsMap[$repoName] = [
                'name' => $repoName,
                'short_name' => count($parts) > 1 ? $parts[1] : $repoName,
                'commits' => 0,
                'additions' => 0,
                'deletions' => 0,
                'changed_files' => 0,
                'timestamps' => []
            ];
        }
        $repoStatsMap[$repoName]['timestamps'][] = $eventTimestamp;
        
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
                        if (isset($compareData['commits']) && is_array($compareData['commits'])) {
                            foreach ($compareData['commits'] as $cItem) {
                                $cDateStr = $cItem['commit']['author']['date'] ?? ($cItem['commit']['committer']['date'] ?? null);
                                if ($cDateStr) {
                                    $cTimestamp = strtotime($cDateStr);
                                    $cDate = date('Y-m-d', $cTimestamp);
                                    if ($cDate === $today) $periods['today']['timestamps'][] = $cTimestamp;
                                    if ($cDate === $yesterday) $periods['yesterday']['timestamps'][] = $cTimestamp;
                                    if ($cTimestamp >= $twentyFourHoursAgo) $periods['last_24h']['timestamps'][] = $cTimestamp;
                                    $repoStatsMap[$repoName]['timestamps'][] = $cTimestamp;
                                }
                            }
                        }
                    }
                }
            }

            $repoStatsMap[$repoName]['commits'] += $commitCount;
            $repoStatsMap[$repoName]['additions'] += $pushAdditions;
            $repoStatsMap[$repoName]['deletions'] += $pushDeletions;
            $repoStatsMap[$repoName]['changed_files'] += $pushChangedFiles;

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

    // Bütün events listesini tarihlere göre gruplayıp günlük çalışma sürelerini hesaplayalım.
    $dailyTimestamps = [];
    foreach ($events as $event) {
        $createdAt = $event['created_at'];
        $eventTimestamp = strtotime($createdAt);
        $eventDate = date('Y-m-d', $eventTimestamp);
        $dailyTimestamps[$eventDate][] = $eventTimestamp;
    }

    $dailyWorkDurations = [];
    $maxGap = 3 * 3600; 
    $minSessionMinutes = 45;
    $postSessionBuffer = 30;

    foreach ($dailyTimestamps as $date => $times) {
        sort($times);
        $totalMinutes = 0;
        $sessionStart = null;
        $lastTime = null;
        
        foreach ($times as $time) {
            if ($lastTime === null) {
                $sessionStart = $time;
                $lastTime = $time;
            } elseif (($time - $lastTime) <= $maxGap) {
                $lastTime = $time;
            } else {
                $sessionDurationMinutes = ($lastTime - $sessionStart) / 60;
                if ($sessionDurationMinutes < $minSessionMinutes) {
                    $sessionDurationMinutes = $minSessionMinutes;
                } else {
                    $sessionDurationMinutes += $postSessionBuffer;
                }
                $totalMinutes += $sessionDurationMinutes;
                
                $sessionStart = $time;
                $lastTime = $time;
            }
        }
        if ($sessionStart !== null) {
            $sessionDurationMinutes = ($lastTime - $sessionStart) / 60;
            if ($sessionDurationMinutes < $minSessionMinutes) {
                $sessionDurationMinutes = $minSessionMinutes;
            } else {
                $sessionDurationMinutes += $postSessionBuffer;
            }
            $totalMinutes += $sessionDurationMinutes;
        }
        $dailyWorkDurations[$date] = round($totalMinutes);
    }

    $totalActiveDays = count($dailyWorkDurations);
    $avgDailyWorkMinutes = 0;
    if ($totalActiveDays > 0) {
        $eventDates = array_keys($dailyWorkDurations);
        sort($eventDates);
        $earliestDate = new DateTime(min($eventDates));
        $latestDate = new DateTime(max($eventDates));
        $daySpan = $earliestDate->diff($latestDate)->days + 1;
        if ($daySpan <= 0) $daySpan = 1;
        
        $avgDailyWorkMinutes = round(array_sum($dailyWorkDurations) / $daySpan);
    }
    
    $formatTime = function($totalMins) {
        $totalMins = round($totalMins);
        if ($totalMins <= 0) return "0 Dakika";
        if ($totalMins >= 60) {
            $hours = floor($totalMins / 60);
            $mins = $totalMins % 60;
            return "{$hours} Saat " . ($mins > 0 ? "{$mins} Dakika" : "");
        }
        return "{$totalMins} Dakika";
    };

    $linesPerHour = (defined('LINES_PER_HOUR') && LINES_PER_HOUR > 0) ? LINES_PER_HOUR : 40;
    $calcMode = defined('WORK_TIME_CALC_MODE') ? WORK_TIME_CALC_MODE : 'session';

    foreach (['today', 'yesterday', 'last_24h'] as $pKey) {
        foreach ($periods[$pKey]['unique_repos'] as $repo => $val) {
            $periods[$pKey]['repos']++;
            $parts = explode('/', $repo);
            $projectName = count($parts) > 1 ? $parts[1] : $repo;
            $periods[$pKey]['active_projects'][] = ucfirst($projectName);
        }
        
        $totalMinutes = 0;
        if (count($periods[$pKey]['timestamps']) > 0) {
            sort($periods[$pKey]['timestamps']);

            // Saatlik aktivite yoğunluğunu hesapla (24 Saat: 0..23)
            $hourlyCounts = array_fill(0, 24, 0);
            foreach ($periods[$pKey]['timestamps'] as $time) {
                $hourInt = (int)date('H', $time);
                $hourlyCounts[$hourInt]++;
            }
            $periods[$pKey]['hourly_activity'] = $hourlyCounts;

            $sessionStart = null;
            $lastTime = null;
            
            // Maksimum iki commit arası boşluk (oturumun devam etmesi için): 3 saat (10800 saniye)
            $maxGap = 3 * 3600; 
            // Her oturum için varsayılan asgari çalışma süresi (hazırlık süresi): 45 dakika
            $minSessionMinutes = 45;
            // Oturum sonuna eklenen geliştirme payı (post-session buffer): 30 dakika
            $postSessionBuffer = 30;
            
            foreach ($periods[$pKey]['timestamps'] as $time) {
                if ($lastTime === null) {
                    $sessionStart = $time;
                    $lastTime = $time;
                } elseif (($time - $lastTime) <= $maxGap) {
                    $lastTime = $time;
                } else {
                    $sessionDurationMinutes = ($lastTime - $sessionStart) / 60;
                    if ($sessionDurationMinutes < $minSessionMinutes) {
                        $sessionDurationMinutes = $minSessionMinutes;
                    } else {
                        $sessionDurationMinutes += $postSessionBuffer;
                    }
                    $totalMinutes += $sessionDurationMinutes;
                    
                    $sessionStart = $time;
                    $lastTime = $time;
                }
            }
            if ($sessionStart !== null) {
                $sessionDurationMinutes = ($lastTime - $sessionStart) / 60;
                if ($sessionDurationMinutes < $minSessionMinutes) {
                    $sessionDurationMinutes = $minSessionMinutes;
                } else {
                    $sessionDurationMinutes += $postSessionBuffer;
                }
                $totalMinutes += $sessionDurationMinutes;
            }
        }

        $sessionMins = round($totalMinutes);
        // Satır Sayısı & Yazım Hızına Dayalı Süre (Eklenenler tam, silinenler 0.5 çarpanla)
        $totalChangedLines = $periods[$pKey]['additions'] + round($periods[$pKey]['deletions'] * 0.5);
        $linesMins = round(($totalChangedLines / $linesPerHour) * 60);
        // Hibrit Süre (Ortalama)
        $hybridMins = ($sessionMins > 0 && $linesMins > 0) ? round(($sessionMins + $linesMins) / 2) : max($sessionMins, $linesMins);

        $periods[$pKey]['work_time_session'] = $formatTime($sessionMins);
        $periods[$pKey]['work_time_lines'] = $formatTime($linesMins);
        $periods[$pKey]['work_time_hybrid'] = $formatTime($hybridMins);

        if ($calcMode === 'lines') {
            $periods[$pKey]['work_time'] = $periods[$pKey]['work_time_lines'];
        } elseif ($calcMode === 'hybrid') {
            $periods[$pKey]['work_time'] = $periods[$pKey]['work_time_hybrid'];
        } else {
            $periods[$pKey]['work_time'] = $periods[$pKey]['work_time_session'];
        }
    }

    // --- PROJE BAZLI ANALİZ VE TOKEN HESAPLAMALARI ---
    $totalProjectTokens = 0;
    $projectStatsList = [];
    foreach ($repoStatsMap as $rName => $pData) {
        $pTimestamps = $pData['timestamps'];
        $pSessionMins = 0;
        if (count($pTimestamps) > 0) {
            sort($pTimestamps);
            $pStart = null;
            $pLast = null;
            foreach ($pTimestamps as $t) {
                if ($pLast === null) {
                    $pStart = $t;
                    $pLast = $t;
                } elseif (($t - $pLast) <= (3 * 3600)) {
                    $pLast = $t;
                } else {
                    $dur = ($pLast - $pStart) / 60;
                    $pSessionMins += ($dur < 45) ? 45 : ($dur + 30);
                    $pStart = $t;
                    $pLast = $t;
                }
            }
            if ($pStart !== null) {
                $dur = ($pLast - $pStart) / 60;
                $pSessionMins += ($dur < 45) ? 45 : ($dur + 30);
            }
        }
        $pSessionMins = round($pSessionMins);
        $pTotalLines = $pData['additions'] + round($pData['deletions'] * 0.5);
        $pLinesMins = round(($pTotalLines / $linesPerHour) * 60);
        $pHybridMins = ($pSessionMins > 0 && $pLinesMins > 0) ? round(($pSessionMins + $pLinesMins) / 2) : max($pSessionMins, $pLinesMins);

        // Gerçek Harcanan Token (Temel Kod + Reasoning / Düşünme Tokenları Dahil)
        // Temel Kod Tokenı: Satır başı 12 + Commit başı 250
        // Reasoning (Düşünme) Tokenı: Akıl yürütme modellerinde ek satır başı 18 + Commit başı 400
        $baseTokens = (($pData['additions'] + $pData['deletions']) * 12) + ($pData['commits'] * 250);
        $reasoningTokens = (($pData['additions'] + $pData['deletions']) * 18) + ($pData['commits'] * 400);
        $pTokens = $baseTokens + $reasoningTokens;
        $totalProjectTokens += $pTokens;

        $pCodeLines = max(10, $pData['additions'] - $pData['deletions']);

        $projectStatsList[] = [
            'name' => $pData['name'],
            'short_name' => ucfirst($pData['short_name']),
            'commits' => $pData['commits'],
            'additions' => $pData['additions'],
            'deletions' => $pData['deletions'],
            'changed_files' => $pData['changed_files'],
            'code_lines' => $pCodeLines,
            'formatted_code_lines' => number_format($pCodeLines, 0, ',', '.'),
            'primary_language' => 'Kod',
            'primary_language_color' => '#ffd700',
            'base_tokens' => $baseTokens,
            'reasoning_tokens' => $reasoningTokens,
            'estimated_tokens' => $pTokens,
            'formatted_tokens' => number_format($pTokens, 0, ',', '.'),
            'formatted_reasoning_tokens' => number_format($reasoningTokens, 0, ',', '.'),
            'work_time_session' => $formatTime($pSessionMins),
            'work_time_lines' => $formatTime($pLinesMins),
            'work_time_hybrid' => $formatTime($pHybridMins),
            'session_minutes' => $pSessionMins,
            'lines_minutes' => $pLinesMins
        ];
    }

    usort($projectStatsList, function($a, $b) {
        return $b['estimated_tokens'] <=> $a['estimated_tokens'];
    });

    // --- TÜM ZAMANLAR PROJE VE TOKEN ANALİZİ (UZUN SÜRELİ CACHE) ---
    $allTimeCacheFile = __DIR__ . '/all_time_projects_cache.json';
    $allTimeCacheDuration = 7200; // 2 saat (7200 saniye) önbellek süresi
    $allTimeProjectStats = [];

    if (file_exists($allTimeCacheFile) && (time() - filemtime($allTimeCacheFile)) < $allTimeCacheDuration) {
        $cachedAllTime = json_decode(file_get_contents($allTimeCacheFile), true);
        // Eğer önbellek geçerliyse ve yeni eklenen 'code_lines' alanı mevcutsa önbellekten yükle
        if (is_array($cachedAllTime) && !empty($cachedAllTime['project_stats']) && isset($cachedAllTime['project_stats'][0]['code_lines'])) {
            $allTimeProjectStats = $cachedAllTime;

            // Ön bellek süresi boyunca yeni commit atılırsa ön bellekteki verileri dinamik güncelle
            $updatedProjectStats = [];
            $totalAllTimeTokens = 0;
            $totalAllTimeCodeLines = 0;

            foreach ($allTimeProjectStats['project_stats'] as $pItem) {
                $rFullName = $pItem['name'];
                if (isset($repoStatsMap[$rFullName])) {
                    $rec = $repoStatsMap[$rFullName];
                    $currentRecentCommits = $rec['commits'] ?? 0;
                    $currentRecentAdditions = $rec['additions'] ?? 0;
                    $currentRecentDeletions = $rec['deletions'] ?? 0;
                    $currentRecentFiles = $rec['changed_files'] ?? 0;

                    $currentRecentSessionMins = 0;
                    if (!empty($rec['timestamps'])) {
                        $pTimestamps = $rec['timestamps'];
                        sort($pTimestamps);
                        $pStart = null;
                        $pLast = null;
                        foreach ($pTimestamps as $t) {
                            if ($pLast === null) { $pStart = $t; $pLast = $t; }
                            elseif (($t - $pLast) <= (3 * 3600)) { $pLast = $t; }
                            else {
                                $dur = ($pLast - $pStart) / 60;
                                $currentRecentSessionMins += ($dur < 45) ? 45 : ($dur + 30);
                                $pStart = $t; $pLast = $t;
                            }
                        }
                        if ($pStart !== null) {
                            $dur = ($pLast - $pStart) / 60;
                            $currentRecentSessionMins += ($dur < 45) ? 45 : ($dur + 30);
                        }
                        $currentRecentSessionMins = round($currentRecentSessionMins);
                    }

                    $prevRecentCommits = $pItem['recent_commits'] ?? 0;
                    $prevRecentAdditions = $pItem['recent_additions'] ?? 0;
                    $prevRecentDeletions = $pItem['recent_deletions'] ?? 0;
                    $prevRecentFiles = $pItem['recent_changed_files'] ?? 0;
                    $prevRecentSessionMins = $pItem['recent_session_minutes'] ?? 0;

                    $deltaCommits = max(0, $currentRecentCommits - $prevRecentCommits);
                    $deltaAdditions = max(0, $currentRecentAdditions - $prevRecentAdditions);
                    $deltaDeletions = max(0, $currentRecentDeletions - $prevRecentDeletions);
                    $deltaFiles = max(0, $currentRecentFiles - $prevRecentFiles);
                    $deltaSessionMins = max(0, $currentRecentSessionMins - $prevRecentSessionMins);

                    if ($deltaCommits > 0 || $deltaAdditions > 0 || $deltaDeletions > 0) {
                        $pItem['commits'] += $deltaCommits;
                        $pItem['additions'] += $deltaAdditions;
                        $pItem['deletions'] += $deltaDeletions;
                        $pItem['changed_files'] += $deltaFiles;
                        $pItem['session_minutes'] += $deltaSessionMins;

                        // Yeni satır değişimini kod satırına yansıt
                        $netLineChange = $deltaAdditions - $deltaDeletions;
                        if (isset($pItem['code_lines'])) {
                            $pItem['code_lines'] = max(10, $pItem['code_lines'] + $netLineChange);
                            $pItem['formatted_code_lines'] = number_format($pItem['code_lines'], 0, ',', '.');
                        }

                        $pItem['recent_commits'] = $currentRecentCommits;
                        $pItem['recent_additions'] = $currentRecentAdditions;
                        $pItem['recent_deletions'] = $currentRecentDeletions;
                        $pItem['recent_changed_files'] = $currentRecentFiles;
                        $pItem['recent_session_minutes'] = $currentRecentSessionMins;

                        $baseTokens = (($pItem['additions'] + $pItem['deletions']) * 12) + ($pItem['commits'] * 250);
                        $reasoningTokens = (($pItem['additions'] + $pItem['deletions']) * 18) + ($pItem['commits'] * 400);
                        $pTokens = $baseTokens + $reasoningTokens;

                        $pItem['base_tokens'] = $baseTokens;
                        $pItem['reasoning_tokens'] = $reasoningTokens;
                        $pItem['estimated_tokens'] = $pTokens;
                        $pItem['formatted_tokens'] = number_format($pTokens, 0, ',', '.');
                        $pItem['formatted_reasoning_tokens'] = number_format($reasoningTokens, 0, ',', '.');

                        $targetLines = (isset($pItem['code_lines']) && $pItem['code_lines'] > 0) 
                            ? $pItem['code_lines'] 
                            : ($pItem['additions'] + round($pItem['deletions'] * 0.5));
                        $pItem['lines_minutes'] = round(($targetLines / $linesPerHour) * 60);
                        $pItem['work_time_lines'] = $formatTime($pItem['lines_minutes']);
                        $pItem['work_time_session'] = $formatTime($pItem['session_minutes']);
                    }
                }
                $totalAllTimeTokens += $pItem['estimated_tokens'];
                $totalAllTimeCodeLines += ($pItem['code_lines'] ?? 0);
                $updatedProjectStats[] = $pItem;
            }

            usort($updatedProjectStats, function($a, $b) {
                return $b['estimated_tokens'] <=> $a['estimated_tokens'];
            });

            $allTimeProjectStats['project_stats'] = $updatedProjectStats;
            $allTimeProjectStats['total_estimated_tokens'] = $totalAllTimeTokens;
            $allTimeProjectStats['total_estimated_tokens_formatted'] = number_format($totalAllTimeTokens, 0, ',', '.');
            $allTimeProjectStats['total_code_lines'] = $totalAllTimeCodeLines;
            $allTimeProjectStats['total_code_lines_formatted'] = number_format($totalAllTimeCodeLines, 0, ',', '.');
        }
    }

    if (empty($allTimeProjectStats)) {
        // GraphQL üzerinden kullanıcının tüm depolarının dilleri ve boyutlarıyla birlikte çekilmesi
        $allReposQuery = '{"query": "query { user(login: \"' . GITHUB_USERNAME . '\") { repositories(first: 100, orderBy: {field: PUSHED_AT, direction: DESC}, ownerAffiliations: [OWNER, COLLABORATOR]) { nodes { name nameWithOwner isPrivate pushedAt defaultBranchRef { target { ... on Commit { history { totalCount } } } } languages(first: 5) { totalSize edges { size node { name color } } } } } } }"}';

        $chAllRepos = curl_init();
        curl_setopt($chAllRepos, CURLOPT_URL, "https://api.github.com/graphql");
        curl_setopt($chAllRepos, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chAllRepos, CURLOPT_POST, true);
        curl_setopt($chAllRepos, CURLOPT_POSTFIELDS, $allReposQuery);
        curl_setopt($chAllRepos, CURLOPT_TIMEOUT, 30);
        curl_setopt($chAllRepos, CURLOPT_HTTPHEADER, [
            "User-Agent: PHP-GitActivityDashboard",
            "Authorization: bearer " . GITHUB_TOKEN,
            "Content-Type: application/json"
        ]);

        $allReposResp = curl_exec($chAllRepos);
        $allReposData = json_decode($allReposResp, true);

        // Eğer ilk denemede timeout olduysa veya veri gelmediyse 50 repo ile tekrar dene
        if (!isset($allReposData['data']['user']['repositories']['nodes'])) {
            $fallbackQuery = '{"query": "query { user(login: \"' . GITHUB_USERNAME . '\") { repositories(first: 60, orderBy: {field: PUSHED_AT, direction: DESC}, ownerAffiliations: [OWNER, COLLABORATOR]) { nodes { name nameWithOwner isPrivate pushedAt defaultBranchRef { target { ... on Commit { history { totalCount } } } } languages(first: 3) { totalSize edges { size node { name color } } } } } } }"}';
            curl_setopt($chAllRepos, CURLOPT_POSTFIELDS, $fallbackQuery);
            $allReposResp = curl_exec($chAllRepos);
            $allReposData = json_decode($allReposResp, true);
        }

        $allProjectsList = [];
        $totalAllTimeTokens = 0;
        $totalAllTimeCodeLines = 0;

        if (isset($allReposData['data']['user']['repositories']['nodes'])) {
            $nodes = $allReposData['data']['user']['repositories']['nodes'];

            foreach ($nodes as $node) {
                $rFullName = $node['nameWithOwner'];
                $rShortName = ucfirst($node['name']);

                // Eğer bu repo son aktivitelerde yer alıyorsa detaylı additions/deletions verisini kullanalım
                $recentData = $repoStatsMap[$rFullName] ?? null;

                $commitCount = 0;
                if (isset($node['defaultBranchRef']['target']['history']['totalCount'])) {
                    $commitCount = (int)$node['defaultBranchRef']['target']['history']['totalCount'];
                }

                $recentCommits = ($recentData && isset($recentData['commits'])) ? $recentData['commits'] : 0;
                $recentAdditions = ($recentData && isset($recentData['additions'])) ? $recentData['additions'] : 0;
                $recentDeletions = ($recentData && isset($recentData['deletions'])) ? $recentData['deletions'] : 0;
                $recentChangedFiles = ($recentData && isset($recentData['changed_files'])) ? $recentData['changed_files'] : 0;

                if ($recentCommits > $commitCount) {
                    $commitCount = $recentCommits;
                }

                $olderCommits = max(0, $commitCount - $recentCommits);

                // Tüm Zamanlar Verileri: Gerçek Son Değişiklikler + Eski Commitler İçin Tahminler
                $additions = $recentAdditions + ($olderCommits * 45);
                $deletions = $recentDeletions + ($olderCommits * 15);
                $changedFiles = $recentChangedFiles + (int)round($olderCommits * 1.5);

                $pTotalLines = $additions + round($deletions * 0.5);
                $pLinesMins = round(($pTotalLines / $linesPerHour) * 60);

                // Push oturum süresi (Son aktivitelerdeki gerçek oturum + Eski commitler için tahmini süre)
                $recentSessionMins = 0;
                if ($recentData && !empty($recentData['timestamps'])) {
                    $pTimestamps = $recentData['timestamps'];
                    sort($pTimestamps);
                    $pStart = null;
                    $pLast = null;
                    foreach ($pTimestamps as $t) {
                        if ($pLast === null) { $pStart = $t; $pLast = $t; }
                        elseif (($t - $pLast) <= (3 * 3600)) { $pLast = $t; }
                        else {
                            $dur = ($pLast - $pStart) / 60;
                            $recentSessionMins += ($dur < 45) ? 45 : ($dur + 30);
                            $pStart = $t; $pLast = $t;
                        }
                    }
                    if ($pStart !== null) {
                        $dur = ($pLast - $pStart) / 60;
                        $recentSessionMins += ($dur < 45) ? 45 : ($dur + 30);
                    }
                    $recentSessionMins = round($recentSessionMins);
                }
                $olderSessionMins = round($olderCommits * 25);
                $pSessionMins = $recentSessionMins + $olderSessionMins;

                // Gerçek Harcanan Token (Temel Kod + Reasoning / Düşünme Tokenları Dahil)
                $baseTokens = (($additions + $deletions) * 12) + ($commitCount * 250);
                $reasoningTokens = (($additions + $deletions) * 18) + ($commitCount * 400);
                $pTokens = $baseTokens + $reasoningTokens;
                $totalAllTimeTokens += $pTokens;

                // --- Dil ve Kaynak Kod Satır Sayısı (LOC - Lines of Code) Analizi ---
                $totalCodeBytes = isset($node['languages']['totalSize']) ? (int)$node['languages']['totalSize'] : 0;
                $primaryLanguage = 'Kod';
                $primaryLanguageColor = '#ffd700';
                $languageList = [];

                if (isset($node['languages']['edges']) && is_array($node['languages']['edges'])) {
                    foreach ($node['languages']['edges'] as $lEdge) {
                        $lName = $lEdge['node']['name'] ?? '';
                        $lSize = $lEdge['size'] ?? 0;
                        $lColor = $lEdge['node']['color'] ?? '#ffd700';
                        if ($lName && $lSize > 0) {
                            $languageList[] = [
                                'name' => $lName,
                                'size' => $lSize,
                                'color' => $lColor
                            ];
                        }
                    }
                    if (!empty($languageList)) {
                        $primaryLanguage = $languageList[0]['name'];
                        $primaryLanguageColor = $languageList[0]['color'];
                    }
                }

                // Ortalama 1 kod satırı ~35 bayttır (Linguist sadece kod dosyalarını sayar)
                if ($totalCodeBytes > 0) {
                    $codeLines = (int)round($totalCodeBytes / 35);
                } else {
                    $codeLines = max(10, $additions - $deletions);
                }
                $totalAllTimeCodeLines += $codeLines;

                // İnsan yazımı süresi: Doğrudan projenin gerçek kaynak kod satırı (LOC) üzerinden hesaplanır
                $pLinesMins = round(($codeLines / $linesPerHour) * 60);

                $allProjectsList[] = [
                    'name' => $rFullName,
                    'short_name' => $rShortName,
                    'commits' => $commitCount,
                    'additions' => $additions,
                    'deletions' => $deletions,
                    'changed_files' => $changedFiles,
                    'code_bytes' => $totalCodeBytes,
                    'code_lines' => $codeLines,
                    'formatted_code_lines' => number_format($codeLines, 0, ',', '.'),
                    'primary_language' => $primaryLanguage,
                    'primary_language_color' => $primaryLanguageColor,
                    'languages' => $languageList,
                    'recent_commits' => $recentCommits,
                    'recent_additions' => $recentAdditions,
                    'recent_deletions' => $recentDeletions,
                    'recent_changed_files' => $recentChangedFiles,
                    'recent_session_minutes' => $recentSessionMins,
                    'base_tokens' => $baseTokens,
                    'reasoning_tokens' => $reasoningTokens,
                    'estimated_tokens' => $pTokens,
                    'formatted_tokens' => number_format($pTokens, 0, ',', '.'),
                    'formatted_reasoning_tokens' => number_format($reasoningTokens, 0, ',', '.'),
                    'work_time_session' => $formatTime($pSessionMins),
                    'work_time_lines' => $formatTime($pLinesMins),
                    'session_minutes' => $pSessionMins,
                    'lines_minutes' => $pLinesMins
                ];
            }

            usort($allProjectsList, function($a, $b) {
                return $b['estimated_tokens'] <=> $a['estimated_tokens'];
            });
        }

        if (empty($allProjectsList)) {
            $allProjectsList = $projectStatsList;
            $totalAllTimeTokens = $totalProjectTokens;
            $totalAllTimeCodeLines = 0;
            foreach ($allProjectsList as $p) {
                $totalAllTimeCodeLines += ($p['code_lines'] ?? 0);
            }
        }

        $allTimeProjectStats = [
            'project_stats' => $allProjectsList,
            'total_estimated_tokens' => $totalAllTimeTokens,
            'total_estimated_tokens_formatted' => number_format($totalAllTimeTokens, 0, ',', '.'),
            'total_code_lines' => $totalAllTimeCodeLines,
            'total_code_lines_formatted' => number_format($totalAllTimeCodeLines, 0, ',', '.'),
            'cached_at' => date('H:i - d.m.Y'),
            'cache_expires_minutes' => round($allTimeCacheDuration / 60)
        ];

        @file_put_contents($allTimeCacheFile, json_encode($allTimeProjectStats, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    $stats['project_stats'] = $projectStatsList;
    $stats['all_time_project_stats'] = $allTimeProjectStats['project_stats'];
    $stats['total_estimated_tokens'] = $allTimeProjectStats['total_estimated_tokens'];
    $stats['total_estimated_tokens_formatted'] = $allTimeProjectStats['total_estimated_tokens_formatted'];
    $stats['total_code_lines'] = $allTimeProjectStats['total_code_lines'] ?? 0;
    $stats['total_code_lines_formatted'] = $allTimeProjectStats['total_code_lines_formatted'] ?? '0';
    $stats['all_time_cache_info'] = [
        'cached_at' => $allTimeProjectStats['cached_at'] ?? null,
        'cache_expires_minutes' => $allTimeProjectStats['cache_expires_minutes'] ?? 120
    ];

    $stats['commits'] = $periods['today']['commits'];
    $stats['additions'] = $periods['today']['additions'];
    $stats['deletions'] = $periods['today']['deletions'];
    $stats['changed_files'] = $periods['today']['changed_files'];
    $stats['repos'] = $periods['today']['repos'];
    $stats['work_time'] = $periods['today']['work_time'];
    $stats['work_time_session'] = $periods['today']['work_time_session'];
    $stats['work_time_lines'] = $periods['today']['work_time_lines'];
    $stats['work_time_hybrid'] = $periods['today']['work_time_hybrid'];
    $stats['active_projects'] = $periods['today']['active_projects'];
    $stats['hourly_activity'] = $periods['today']['hourly_activity'];

    $stats['today'] = [
        "commits" => $periods['today']['commits'],
        "additions" => $periods['today']['additions'],
        "deletions" => $periods['today']['deletions'],
        "changed_files" => $periods['today']['changed_files'],
        "repos" => $periods['today']['repos'],
        "work_time" => $periods['today']['work_time'],
        "work_time_session" => $periods['today']['work_time_session'],
        "work_time_lines" => $periods['today']['work_time_lines'],
        "work_time_hybrid" => $periods['today']['work_time_hybrid'],
        "active_projects" => $periods['today']['active_projects'],
        "hourly_activity" => $periods['today']['hourly_activity']
    ];
    $stats['yesterday'] = [
        "commits" => $periods['yesterday']['commits'],
        "additions" => $periods['yesterday']['additions'],
        "deletions" => $periods['yesterday']['deletions'],
        "changed_files" => $periods['yesterday']['changed_files'],
        "repos" => $periods['yesterday']['repos'],
        "work_time" => $periods['yesterday']['work_time'],
        "work_time_session" => $periods['yesterday']['work_time_session'],
        "work_time_lines" => $periods['yesterday']['work_time_lines'],
        "work_time_hybrid" => $periods['yesterday']['work_time_hybrid'],
        "active_projects" => $periods['yesterday']['active_projects'],
        "hourly_activity" => $periods['yesterday']['hourly_activity']
    ];
    $stats['last_24h'] = [
        "commits" => $periods['last_24h']['commits'],
        "additions" => $periods['last_24h']['additions'],
        "deletions" => $periods['last_24h']['deletions'],
        "changed_files" => $periods['last_24h']['changed_files'],
        "repos" => $periods['last_24h']['repos'],
        "work_time" => $periods['last_24h']['work_time'],
        "work_time_session" => $periods['last_24h']['work_time_session'],
        "work_time_lines" => $periods['last_24h']['work_time_lines'],
        "work_time_hybrid" => $periods['last_24h']['work_time_hybrid'],
        "active_projects" => $periods['last_24h']['active_projects'],
        "hourly_activity" => $periods['last_24h']['hourly_activity']
    ];
    
    // --- GRAPHQL BÖLÜMÜ (KATKI TAKVİMİ VE SON REPOLAR) ---
    $graphqlUrl = "https://api.github.com/graphql";
    $query = '{"query": "query { user(login: \"' . GITHUB_USERNAME . '\") { contributionsCollection { contributionCalendar { totalContributions weeks { contributionDays { contributionCount date } } } } repositories(first: 30, orderBy: {field: PUSHED_AT, direction: DESC}, ownerAffiliations: [OWNER, COLLABORATOR]) { nodes { name nameWithOwner pushedAt } } } }"}';
    
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
            $todayDate = date('Y-m-d');
            $todayContribs = 0;
            
            for ($i = 1; $i <= 30; $i++) {
                if ($totalDays - $i >= 0) {
                    $dayCount = $allDays[$totalDays - $i]['contributionCount'];
                    $dayDate = $allDays[$totalDays - $i]['date'];
                    $stats['monthly_commits'] += $dayCount;
                    if ($i <= 7) {
                        $stats['weekly_commits'] += $dayCount;
                    }
                    if ($dayDate === $todayDate) {
                        $todayContribs = $dayCount;
                    }
                }
            }
            // Bugünün katkısı son 30 günde yoksa takvimin son gününe bak
            if ($todayContribs === 0 && $totalDays > 0) {
                $lastDay = $allDays[$totalDays - 1];
                if ($lastDay['date'] === $todayDate) {
                    $todayContribs = $lastDay['contributionCount'];
                }
            }

            // Dünün katkı sayısını GraphQL günlerinden bul
            $yesterdayDate = date('Y-m-d', strtotime('-1 day'));
            $yesterdayContribs = 0;
            foreach ($allDays as $calDay) {
                if ($calDay['date'] === $yesterdayDate) {
                    $yesterdayContribs = $calDay['contributionCount'];
                    break;
                }
            }

            // GraphQL'den gelen gerçek katkı/commit sayıları Events API'den yüksekse sol panel ve periyotları senkronize et
            if ($todayContribs > $stats['today']['commits']) {
                $stats['today']['commits'] = $todayContribs;
                $stats['commits'] = $todayContribs;
                if ($todayContribs > $stats['last_24h']['commits']) {
                    $stats['last_24h']['commits'] = $todayContribs;
                }
            }
            if ($yesterdayContribs > $stats['yesterday']['commits']) {
                $stats['yesterday']['commits'] = $yesterdayContribs;
            }

            // GraphQL üzerinden son pushlanan repoları kontrol edip aktif modüllere ekle ve gerçek diff verilerini çek
            $recentCommits = []; // dün 00:00'dan beri tüm repolardaki commitler (mesajlarıyla)
            $extraAdditionsToday = 0;
            $extraDeletionsToday = 0;
            $extraFilesToday = 0;
            
            $extraAdditionsYesterday = 0;
            $extraDeletionsYesterday = 0;
            $extraFilesYesterday = 0;

            $extraAdditionsLast24h = 0;
            $extraDeletionsLast24h = 0;
            $extraFilesLast24h = 0;

            $todayAllTimestamps = $periods['today']['timestamps'] ?? [];
            $yesterdayAllTimestamps = $periods['yesterday']['timestamps'] ?? [];
            $last24hAllTimestamps = $periods['last_24h']['timestamps'] ?? [];

            if (isset($graphData['data']['user']['repositories']['nodes'])) {
                $recentPushedNodes = $graphData['data']['user']['repositories']['nodes'];
                $yesterdayStartTs = strtotime($yesterdayDate . ' 00:00:00');
                $sinceGlobalTs = min($twentyFourHoursAgo, $yesterdayStartTs);
                $sinceGlobalIso = gmdate('Y-m-d\TH:i:s\Z', $sinceGlobalTs);

                foreach ($recentPushedNodes as $pNode) {
                    if (!empty($pNode['pushedAt'])) {
                        $pushedTs = strtotime($pNode['pushedAt']);
                        $pushedDate = date('Y-m-d', $pushedTs);
                        $rName = ucfirst($pNode['name']);
                        $rFullName = $pNode['nameWithOwner'] ?? (GITHUB_USERNAME . '/' . $pNode['name']);

                        $isRepoToday = ($pushedDate === $todayDate);
                        $isRepoYesterday = ($pushedDate === $yesterdayDate);
                        $isRepo24h = ($pushedTs >= $twentyFourHoursAgo);

                        if ($isRepoToday && !in_array($rName, $stats['today']['active_projects'])) {
                            $stats['today']['active_projects'][] = $rName;
                        }
                        if ($isRepoYesterday && !in_array($rName, $stats['yesterday']['active_projects'])) {
                            $stats['yesterday']['active_projects'][] = $rName;
                        }
                        if ($isRepo24h && !in_array($rName, $stats['last_24h']['active_projects'])) {
                            $stats['last_24h']['active_projects'][] = $rName;
                        }

                        // Eğer repo son 24 saat, dün veya bugün içinde hareket gördüyse commitlerini çek
                        if ($isRepoToday || $isRepoYesterday || $isRepo24h) {
                            $cUrl = "https://api.github.com/repos/{$rFullName}/commits?since={$sinceGlobalIso}&per_page=100";
                            $chCommits = curl_init();
                            curl_setopt($chCommits, CURLOPT_URL, $cUrl);
                            curl_setopt($chCommits, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($chCommits, CURLOPT_HTTPHEADER, [
                                "User-Agent: PHP-GitActivityDashboard",
                                "Authorization: token " . GITHUB_TOKEN,
                                "Accept: application/vnd.github.v3+json"
                            ]);
                            $cResp = curl_exec($chCommits);
                            if (curl_getinfo($chCommits, CURLINFO_HTTP_CODE) === 200) {
                                $cList = json_decode($cResp, true);
                                if (is_array($cList) && count($cList) > 0) {
                                    $repoTodayCommits = [];
                                    $repoYesterdayCommits = [];
                                    $repo24hCommits = [];

                                    foreach ($cList as $cItem) {
                                        $cDateStr = $cItem['commit']['author']['date'] ?? ($cItem['commit']['committer']['date'] ?? null);
                                        if ($cDateStr) {
                                            $cTs = strtotime($cDateStr);
                                            $recentCommits[] = [
                                                'repo' => $rName,
                                                'ts' => $cTs,
                                                'time' => date('H:i', $cTs),
                                                'date' => date('Y-m-d', $cTs),
                                                'message' => mb_substr(strtok($cItem['commit']['message'] ?? '', "\n"), 0, 140),
                                                'sha' => substr($cItem['sha'] ?? '', 0, 7),
                                                'url' => $cItem['html_url'] ?? '',
                                            ];
                                            $cDate = date('Y-m-d', $cTs);

                                            if ($cDate === $todayDate) {
                                                $todayAllTimestamps[] = $cTs;
                                                $repoTodayCommits[] = $cItem;
                                            }
                                            if ($cDate === $yesterdayDate) {
                                                $yesterdayAllTimestamps[] = $cTs;
                                                $repoYesterdayCommits[] = $cItem;
                                            }
                                            if ($cTs >= $twentyFourHoursAgo) {
                                                $last24hAllTimestamps[] = $cTs;
                                                $repo24hCommits[] = $cItem;
                                            }
                                        }
                                    }

                                    // Repo diff'lerini çek (compare)
                                    $headSha = $cList[0]['sha'];
                                    $oldestSha = end($cList);
                                    $beforeSha = $oldestSha['parents'][0]['sha'] ?? $oldestSha['sha'];

                                    $compareUrl = "https://api.github.com/repos/{$rFullName}/compare/{$beforeSha}...{$headSha}";
                                    $chComp = curl_init();
                                    curl_setopt($chComp, CURLOPT_URL, $compareUrl);
                                    curl_setopt($chComp, CURLOPT_RETURNTRANSFER, true);
                                    curl_setopt($chComp, CURLOPT_HTTPHEADER, [
                                        "User-Agent: PHP-GitActivityDashboard",
                                        "Authorization: token " . GITHUB_TOKEN,
                                        "Accept: application/vnd.github.v3+json"
                                    ]);
                                    $compRes = curl_exec($chComp);
                                    if (curl_getinfo($chComp, CURLINFO_HTTP_CODE) === 200) {
                                        $compData = json_decode($compRes, true);
                                        if (isset($compData['files']) && is_array($compData['files'])) {
                                            $cFilesCount = count($compData['files']);
                                            $cAdds = 0;
                                            $cDels = 0;
                                            foreach ($compData['files'] as $file) {
                                                $cAdds += $file['additions'] ?? 0;
                                                $cDels += $file['deletions'] ?? 0;
                                            }

                                            if (count($repoTodayCommits) > 0) {
                                                $extraFilesToday += $cFilesCount;
                                                $extraAdditionsToday += $cAdds;
                                                $extraDeletionsToday += $cDels;
                                            }
                                            if (count($repoYesterdayCommits) > 0) {
                                                $extraFilesYesterday += $cFilesCount;
                                                $extraAdditionsYesterday += $cAdds;
                                                $extraDeletionsYesterday += $cDels;
                                            }
                                            if (count($repo24hCommits) > 0) {
                                                $extraFilesLast24h += $cFilesCount;
                                                $extraAdditionsLast24h += $cAdds;
                                                $extraDeletionsLast24h += $cDels;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                $stats['today']['repos'] = count($stats['today']['active_projects']);
                $stats['yesterday']['repos'] = count($stats['yesterday']['active_projects']);
                $stats['last_24h']['repos'] = count($stats['last_24h']['active_projects']);
                $stats['repos'] = $stats['today']['repos'];
                $stats['active_projects'] = $stats['today']['active_projects'];
            }

            // Diff istatistiklerini güncelle (Today)
            if ($extraFilesToday > $stats['today']['changed_files']) {
                $stats['today']['changed_files'] = $extraFilesToday;
                $stats['changed_files'] = $extraFilesToday;
            }
            if ($extraAdditionsToday > $stats['today']['additions']) {
                $stats['today']['additions'] = $extraAdditionsToday;
                $stats['additions'] = $extraAdditionsToday;
            }
            if ($extraDeletionsToday > $stats['today']['deletions']) {
                $stats['today']['deletions'] = $extraDeletionsToday;
                $stats['deletions'] = $extraDeletionsToday;
            }

            // Diff istatistiklerini güncelle (Yesterday)
            if ($extraFilesYesterday > $stats['yesterday']['changed_files']) {
                $stats['yesterday']['changed_files'] = $extraFilesYesterday;
            }
            if ($extraAdditionsYesterday > $stats['yesterday']['additions']) {
                $stats['yesterday']['additions'] = $extraAdditionsYesterday;
            }
            if ($extraDeletionsYesterday > $stats['yesterday']['deletions']) {
                $stats['yesterday']['deletions'] = $extraDeletionsYesterday;
            }

            // Diff istatistiklerini güncelle (Last 24h)
            if ($extraFilesLast24h > $stats['last_24h']['changed_files']) {
                $stats['last_24h']['changed_files'] = $extraFilesLast24h;
            }
            if ($extraAdditionsLast24h > $stats['last_24h']['additions']) {
                $stats['last_24h']['additions'] = $extraAdditionsLast24h;
            }
            if ($extraDeletionsLast24h > $stats['last_24h']['deletions']) {
                $stats['last_24h']['deletions'] = $extraDeletionsLast24h;
            }

            // 24 Saatlik istatistikleri senkronize et (Bugün tamamen son 24 saatin içindedir)
            $stats['last_24h']['commits'] = max($stats['last_24h']['commits'], $stats['today']['commits']);
            $stats['last_24h']['changed_files'] = max($stats['last_24h']['changed_files'], $stats['today']['changed_files']);
            $stats['last_24h']['additions'] = max($stats['last_24h']['additions'], $stats['today']['additions']);
            $stats['last_24h']['deletions'] = max($stats['last_24h']['deletions'], $stats['today']['deletions']);
            $stats['last_24h']['repos'] = max($stats['last_24h']['repos'], $stats['today']['repos']);

            // Çalışma süresi hesaplama fonksiyonu (Tüm oturumlar için ortak mantık)
            $calcSessionDuration = function(array $timestamps) {
                if (empty($timestamps)) return 0;
                $tsList = array_values(array_unique($timestamps));
                sort($tsList);
                
                $maxGap = 3 * 3600; 
                $minSessionMinutes = 45;
                $postSessionBuffer = 30;
                $totalMins = 0;
                $sessionStart = null;
                $lastTime = null;

                foreach ($tsList as $time) {
                    if ($lastTime === null) {
                        $sessionStart = $time;
                        $lastTime = $time;
                    } elseif (($time - $lastTime) <= $maxGap) {
                        $lastTime = $time;
                    } else {
                        $dur = ($lastTime - $sessionStart) / 60;
                        $totalMins += ($dur < $minSessionMinutes) ? $minSessionMinutes : ($dur + $postSessionBuffer);
                        $sessionStart = $time;
                        $lastTime = $time;
                    }
                }
                if ($sessionStart !== null) {
                    $dur = ($lastTime - $sessionStart) / 60;
                    $totalMins += ($dur < $minSessionMinutes) ? $minSessionMinutes : ($dur + $postSessionBuffer);
                }
                return round($totalMins);
            };

            // --- 1. BUGÜN (TODAY) ÇALIŞMA SÜRESİ HESAPLAMASI ---
            $todayAllTimestamps = array_unique($todayAllTimestamps);
            if (count($todayAllTimestamps) > 0) {
                $sessionMinsToday = $calcSessionDuration($todayAllTimestamps);
                $totalChangedLinesToday = $stats['today']['additions'] + round($stats['today']['deletions'] * 0.5);
                $linesMinsToday = round(($totalChangedLinesToday / $linesPerHour) * 60);
                $hybridMinsToday = ($sessionMinsToday > 0 && $linesMinsToday > 0) ? round(($sessionMinsToday + $linesMinsToday) / 2) : max($sessionMinsToday, $linesMinsToday);

                $stats['today']['work_time_session'] = $formatTime($sessionMinsToday);
                $stats['today']['work_time_lines'] = $formatTime($linesMinsToday);
                $stats['today']['work_time_hybrid'] = $formatTime($hybridMinsToday);

                if ($calcMode === 'lines') {
                    $stats['today']['work_time'] = $stats['today']['work_time_lines'];
                } elseif ($calcMode === 'hybrid') {
                    $stats['today']['work_time'] = $stats['today']['work_time_hybrid'];
                } else {
                    $stats['today']['work_time'] = $stats['today']['work_time_session'];
                }
                $stats['work_time'] = $stats['today']['work_time'];
                $stats['work_time_session'] = $stats['today']['work_time_session'];
                $stats['work_time_lines'] = $stats['today']['work_time_lines'];
                $stats['work_time_hybrid'] = $stats['today']['work_time_hybrid'];
                $dailyWorkDurations[$todayDate] = $sessionMinsToday;
            }

            // --- 2. DÜN (YESTERDAY) ÇALIŞMA SÜRESİ HESAPLAMASI ---
            $yesterdayAllTimestamps = array_unique($yesterdayAllTimestamps);
            if (count($yesterdayAllTimestamps) > 0) {
                $sessionMinsY = $calcSessionDuration($yesterdayAllTimestamps);
                $totalChangedLinesY = $stats['yesterday']['additions'] + round($stats['yesterday']['deletions'] * 0.5);
                $linesMinsY = round(($totalChangedLinesY / $linesPerHour) * 60);
                $hybridMinsY = ($sessionMinsY > 0 && $linesMinsY > 0) ? round(($sessionMinsY + $linesMinsY) / 2) : max($sessionMinsY, $linesMinsY);

                $stats['yesterday']['work_time_session'] = $formatTime($sessionMinsY);
                $stats['yesterday']['work_time_lines'] = $formatTime($linesMinsY);
                $stats['yesterday']['work_time_hybrid'] = $formatTime($hybridMinsY);

                if ($calcMode === 'lines') {
                    $stats['yesterday']['work_time'] = $stats['yesterday']['work_time_lines'];
                } elseif ($calcMode === 'hybrid') {
                    $stats['yesterday']['work_time'] = $stats['yesterday']['work_time_hybrid'];
                } else {
                    $stats['yesterday']['work_time'] = $stats['yesterday']['work_time_session'];
                }
                $dailyWorkDurations[$yesterdayDate] = $sessionMinsY;
            }

            // --- 3. SON 24 SAAT (LAST_24H) ÇALIŞMA SÜRESİ (TAMAMEN BAĞIMSIZ!) ---
            $last24hAllTimestamps = array_unique($last24hAllTimestamps);
            if (count($last24hAllTimestamps) > 0) {
                $sessionMins24 = $calcSessionDuration($last24hAllTimestamps);
                $totalChangedLines24 = $stats['last_24h']['additions'] + round($stats['last_24h']['deletions'] * 0.5);
                $linesMins24 = round(($totalChangedLines24 / $linesPerHour) * 60);
                $hybridMins24 = ($sessionMins24 > 0 && $linesMins24 > 0) ? round(($sessionMins24 + $linesMins24) / 2) : max($sessionMins24, $linesMins24);

                $stats['last_24h']['work_time_session'] = $formatTime($sessionMins24);
                $stats['last_24h']['work_time_lines'] = $formatTime($linesMins24);
                $stats['last_24h']['work_time_hybrid'] = $formatTime($hybridMins24);

                if ($calcMode === 'lines') {
                    $stats['last_24h']['work_time'] = $stats['last_24h']['work_time_lines'];
                } elseif ($calcMode === 'hybrid') {
                    $stats['last_24h']['work_time'] = $stats['last_24h']['work_time_hybrid'];
                } else {
                    $stats['last_24h']['work_time'] = $stats['last_24h']['work_time_session'];
                }

                // Saatlik aktivite yoğunluğunu son 24 saat için güncelle (Isı haritası)
                $hourlyCounts24 = array_fill(0, 24, 0);
                foreach ($last24hAllTimestamps as $ts) {
                    $h = (int)date('H', $ts);
                    $hourlyCounts24[$h]++;
                }
                $stats['last_24h']['hourly_activity'] = $hourlyCounts24;
            }

            // ========================================================
            // KATKIYA DAYALI SÜRE TAHMİN ORANI (minutesPerContribution)
            // ========================================================
            // Events API'den gelen çalışma sürelerini, aynı günlerdeki
            // GraphQL katkı sayılarına bölerek "1 katkı = kaç dakika"
            // oranını buluyoruz. Böylece tüm hesaplamalar tutarlı olur.
            $totalEventsWorkMinutes = isset($dailyWorkDurations) ? array_sum($dailyWorkDurations) : 0;
            $totalContribsInEventDays = 0;
            if (isset($dailyWorkDurations) && is_array($dailyWorkDurations)) {
                foreach ($dailyWorkDurations as $evDate => $evMins) {
                    foreach ($allDays as $calDay) {
                        if ($calDay['date'] === $evDate) {
                            $totalContribsInEventDays += $calDay['contributionCount'];
                            break;
                        }
                    }
                }
            }
            // Katkı başına dakika (fallback: 8 dakika)
            $minutesPerContrib = ($totalContribsInEventDays > 0) 
                ? ($totalEventsWorkMinutes / $totalContribsInEventDays) 
                : 8;
            // Minimum 3, maksimum 20 dakika arasında sınırla (aşırı sapmaları önle)
            $minutesPerContrib = max(3, min(20, $minutesPerContrib));

            // ========================================================
            // FORMATLAMA FONKSİYONU
            // ========================================================
            $formatTime = function($totalMins) {
                $totalMins = round($totalMins);
                if ($totalMins <= 0) return "0 Dakika";
                if ($totalMins >= 60) {
                    $hours = floor($totalMins / 60);
                    $mins = $totalMins % 60;
                    return "{$hours} Saat " . ($mins > 0 ? "{$mins} Dakika" : "");
                }
                return "{$totalMins} Dakika";
            };
            // ========================================================
            // ORTALAMA KATKI (COMMIT) - Son 7 ve 30 Güne Göre
            // ========================================================
            $stats['avg_daily_commits_7d'] = round($stats['weekly_commits'] / 7, 1);
            $stats['avg_daily_commits_30d'] = round($stats['monthly_commits'] / 30, 1);
            // Geriye dönük uyumluluk için eski key'i de 30 günlük olarak tutalım
            $stats['avg_daily_commits'] = $stats['avg_daily_commits_30d'];

            // ========================================================
            // ORTALAMA ÇALIŞMA SÜRESİ - Son 7 ve 30 Güne Göre
            // ========================================================
            $avgDailyCommits7d = $stats['weekly_commits'] / 7;
            $avgDailyMinutes7d = round($avgDailyCommits7d * $minutesPerContrib);
            $stats['avg_daily_work_time_str_7d'] = $formatTime($avgDailyMinutes7d);

            $avgDailyCommits30d = $stats['monthly_commits'] / 30;
            $avgDailyMinutes30d = round($avgDailyCommits30d * $minutesPerContrib);
            $stats['avg_daily_work_time_str_30d'] = $formatTime($avgDailyMinutes30d);
            // Geriye dönük uyumluluk için eski key'i de 30 günlük olarak tutalım
            $stats['avg_daily_work_time_str'] = $stats['avg_daily_work_time_str_30d'];

            // ========================================================
            // TOPLAM ÇALIŞMA SÜRESİ (Gerçekleşen)
            // ========================================================
            // Bugün için events-based gerçek session süresini kullan
            // (sol paneldeki SİSTEM SÜRESİ ile tutarlı olması için)
            $realTodayMins = isset($dailyWorkDurations[$todayDate]) 
                ? $dailyWorkDurations[$todayDate] 
                : ($todayContribs * $minutesPerContrib);
            $stats['real_today_work_time_str'] = $formatTime($realTodayMins);
            // Hafta ve ay için GraphQL x oran (events bu kadar geriye gitmiyor)
            $stats['real_weekly_work_time_str'] = $formatTime($stats['weekly_commits'] * $minutesPerContrib);
            $stats['real_monthly_work_time_str'] = $formatTime($stats['monthly_commits'] * $minutesPerContrib);

            // ========================================================
            // KESİNTİSİZ OTURUM (gece yarısını aşan çalışma)
            // ========================================================
            // Gün 00:00'da sıfırlanır ama mesai sıfırlanmaz: son commit'ten geriye gidip
            // 6 saatten uzun ilk boşluğa (uyku) kadar olan zincir tek mesai sayılır.
            // Commit atmadan çalışılan 3-4 saatlik aralar mesaiyi bölmez, "mola" olarak listelenir.
            $restGap = 6 * 3600;
            $allTs = array_merge($todayAllTimestamps, $yesterdayAllTimestamps, $last24hAllTimestamps);
            foreach ($dailyTimestamps as $dTimes) { $allTs = array_merge($allTs, $dTimes); }
            $allTs = array_values(array_unique($allTs));
            rsort($allTs);
            $stats['current_session'] = null;
            if (count($allTs) > 0) {
                $sessEnd = $allTs[0];
                $sessStart = $sessEnd;
                $gaps = [];
                foreach ($allTs as $t) {
                    $gap = $sessStart - $t;
                    if ($gap > $restGap) break;
                    if ($gap >= 90 * 60) $gaps[] = ['from' => date('H:i', $t), 'to' => date('H:i', $sessStart), 'from_ts' => $t, 'to_ts' => $sessStart, 'minutes' => round($gap / 60)];
                    $sessStart = $t;
                }
                $isActive = ($nowTime - $sessEnd) <= 3 * 3600;
                $endTs = $isActive ? $nowTime : $sessEnd + $postSessionBuffer * 60;
                $sessMins = max($minSessionMinutes, round(($endTs - $sessStart) / 60));
                $stats['current_session'] = [
                    'active' => $isActive,
                    'start' => date('c', $sessStart),
                    'start_ts' => $sessStart,
                    'end_ts' => $endTs,
                    'start_label' => date('d.m H:i', $sessStart),
                    'last_commit' => date('d.m H:i', $sessEnd),
                    'minutes' => $sessMins,
                    'work_time_str' => $formatTime($sessMins),
                    'crosses_midnight' => date('Y-m-d', $sessStart) !== $todayDate,
                    'gaps' => array_reverse($gaps), // commit atılmayan 90dk+ aralar
                ];
            }

            // ========================================================
            // KALICI GÜN GEÇMİŞİ (work_history.json)
            // ========================================================
            // Events API ~100 olaya kadar geriye gider; hesaplanan gerçek süreleri
            // dosyada saklayarak eski günlerin push-saati süresini kaybetmiyoruz.
            $historyFile = __DIR__ . '/work_history.json';
            $history = file_exists($historyFile) ? (json_decode(@file_get_contents($historyFile), true) ?: []) : [];
            foreach ($dailyWorkDurations as $hDate => $hMins) {
                // Bugün/dün tam veriyle hesaplanır → üzerine yaz; eski günlerde events penceresi kısalır → büyük olanı tut
                $history[$hDate] = ($hDate === $todayDate || $hDate === $yesterdayDate)
                    ? (int)$hMins
                    : max((int)($history[$hDate] ?? 0), (int)$hMins);
            }
            ksort($history);
            @file_put_contents($historyFile, json_encode($history), LOCK_EX);

            // Commit listesi (yeniden eskiye) + günlük proje dökümü (projects_history.json'da kalıcı)
            usort($recentCommits, fn($a, $b) => $b['ts'] <=> $a['ts']);
            $stats['recent_commits'] = $recentCommits;
            $dayProjects = [];
            foreach ($recentCommits as $rc) {
                $dayProjects[$rc['date']][$rc['repo']] = ($dayProjects[$rc['date']][$rc['repo']] ?? 0) + 1;
            }
            $projHistFile = __DIR__ . '/projects_history.json';
            $projHistory = file_exists($projHistFile) ? (json_decode(@file_get_contents($projHistFile), true) ?: []) : [];
            foreach ($dayProjects as $pDate => $pRepos) {
                if ($pDate === $todayDate || $pDate === $yesterdayDate) { arsort($pRepos); $projHistory[$pDate] = $pRepos; }
            }
            ksort($projHistory);
            @file_put_contents($projHistFile, json_encode($projHistory), LOCK_EX);

            // ========================================================
            // GÜNLÜK ÇALIŞMA DÖKÜMÜ (daily_log)
            // ========================================================
            $dailyLog = [];
            $turkishDays = [
                'Monday' => 'Pazartesi', 'Tuesday' => 'Salı', 'Wednesday' => 'Çarşamba',
                'Thursday' => 'Perşembe', 'Friday' => 'Cuma', 'Saturday' => 'Cumartesi', 'Sunday' => 'Pazar'
            ];
            $turkishMonths = [
                '01' => 'Ocak', '02' => 'Şubat', '03' => 'Mart', '04' => 'Nisan',
                '05' => 'Mayıs', '06' => 'Haziran', '07' => 'Temmuz', '08' => 'Ağustos',
                '09' => 'Eylül', '10' => 'Ekim', '11' => 'Kasım', '12' => 'Aralık'
            ];

            $reversedDays = array_reverse($allDays);
            foreach ($reversedDays as $dayItem) {
                $dDate = $dayItem['date'];
                $dContribs = $dayItem['contributionCount'];
                
                $isMeasured = isset($history[$dDate]);
                $dMinutes = $isMeasured ? $history[$dDate] : round($dContribs * $minutesPerContrib);
                
                $ts = strtotime($dDate);
                $dayName = $turkishDays[date('l', $ts)] ?? date('l', $ts);
                $monthName = $turkishMonths[date('m', $ts)] ?? date('m', $ts);
                $formattedDate = date('j', $ts) . ' ' . $monthName . ' ' . date('Y', $ts) . ', ' . $dayName;

                $dailyLog[] = [
                    'date' => $dDate,
                    'formatted_date' => $formattedDate,
                    'contributions' => $dContribs,
                    'work_minutes' => $dMinutes,
                    'work_time_str' => $formatTime($dMinutes),
                    'measured' => $isMeasured, // true: push saatinden ölçüldü, false: katkı sayısından tahmin
                    'projects' => $projHistory[$dDate] ?? new stdClass(), // {repo: commit sayısı}
                    'is_today' => ($dDate === $todayDate)
                ];
            }
            $stats['daily_log'] = $dailyLog;
        }
    }
    
    // GİZLİLİK VE MODÜL KONTROLLERİ
    $showLogs = defined('SHOW_SYSTEM_LOGS') ? SHOW_SYSTEM_LOGS : true;
    $showProjects = defined('SHOW_ACTIVE_PROJECTS') ? SHOW_ACTIVE_PROJECTS : true;
    
    if (!$showLogs) {
        $filtered_events = []; // İfşayı engellemek için logları API'den bile siliyoruz
        $stats['recent_commits'] = [];
    }
    
    if (!$showProjects) {
        $stats['active_projects'] = [];
        $stats['today']['active_projects'] = [];
        $stats['yesterday']['active_projects'] = [];
        $stats['last_24h']['active_projects'] = [];
        $stats['recent_commits'] = [];
        foreach (($stats['daily_log'] ?? []) as $i => $_) { $stats['daily_log'][$i]['projects'] = new stdClass(); }
    }
    
    $output = json_encode([
        "success" => true, 
        "data" => $filtered_events, 
        "stats" => $stats,
        "config" => [
            "show_system_logs" => $showLogs,
            "show_active_projects" => $showProjects,
            "default_theme" => defined('DEFAULT_THEME') ? DEFAULT_THEME : 'theme-cyan',
            "calc_mode" => defined('WORK_TIME_CALC_MODE') ? WORK_TIME_CALC_MODE : 'session',
            "lines_per_hour" => defined('LINES_PER_HOUR') ? LINES_PER_HOUR : 40
        ]
    ]);
    
    // Cache'i kaydet (önce geçici dosya, sonra rename: okuyan istek yarım JSON görmesin)
    if (file_put_contents($cacheFile . '.tmp', $output) !== false) { rename($cacheFile . '.tmp', $cacheFile); }
    
    if (!$servedStale) echo $output;
} elseif (!$servedStale) {
    echo json_encode([
        "error" => "GitHub API'sine bağlanırken bir sorun oluştu.", 
        "http_code" => $httpcode,
        "curl_error" => $error
    ]);
}
