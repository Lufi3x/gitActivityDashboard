<?php
require_once 'config.php';

if (GITHUB_TOKEN === 'BURAYA_GITHUB_TOKEN_GELECEK' || empty(GITHUB_TOKEN)) {
    echo json_encode(["error" => "Lütfen api/config.php dosyasına GitHub Token'ınızı girin."]);
    exit;
}

$cacheFile = 'cache.json';
$cacheTime = 600; // 10 dakika

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
    
    $stats = [
        "commits" => 0,
        "additions" => 0,
        "deletions" => 0,
        "repos" => 0,
        "work_time" => "0 Dakika",
        "weekly_commits" => 0,
        "monthly_commits" => 0,
        "yearly_commits" => 0,
        "active_projects" => [],
        "calendar" => []
    ];
    
    $unique_repos_today = [];
    $today_timestamps = [];

    foreach ($events as $event) {
        $type = $event['type'];
        $repoName = $event['repo']['name'];
        $createdAt = $event['created_at'];
        $eventDate = date('Y-m-d', strtotime($createdAt));
        $isToday = ($eventDate === $today);
        
        $actionDetails = "";
        
        if ($type === "PushEvent") {
            $commits = isset($event['payload']['commits']) ? $event['payload']['commits'] : [];
            $commitCount = count($commits);
            
            $pushAdditions = 0;
            $pushDeletions = 0;
            
            // Eğer bugünse, Compare API'den hem doğru commit sayısını hem de satır değişimlerini çekelim
            if ($isToday && isset($event['payload']['before']) && isset($event['payload']['head'])) {
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
                $actionDetails = $commitCount . " commit pushlandı: " . implode(" | ", $commitMessages);
            } else {
                $ref = isset($event['payload']['ref']) ? str_replace('refs/heads/', '', $event['payload']['ref']) : 'bir';
                if ($commitCount === 0) $commitCount = 1; // Fallback
                $actionDetails = "{$commitCount} commit pushlandı ({$ref} dalına).";
            }
            
            // İstatistiklere ekle
            if ($isToday) {
                $stats['commits'] += $commitCount;
                $stats['additions'] += $pushAdditions;
                $stats['deletions'] += $pushDeletions;
                $unique_repos_today[$repoName] = true;
            }

        } elseif ($type === "CreateEvent") {
            $actionDetails = $event['payload']['ref_type'] . " oluşturuldu.";
            if ($isToday) $unique_repos_today[$repoName] = true;
        } elseif ($type === "WatchEvent") {
            $actionDetails = "Depo yıldızlandı.";
        } elseif ($type === "IssuesEvent") {
            $actionDetails = "Issue " . $event['payload']['action'];
            if ($isToday) $unique_repos_today[$repoName] = true;
        } elseif ($type === "PullRequestEvent") {
            $actionDetails = "Pull Request " . $event['payload']['action'];
            if ($isToday) $unique_repos_today[$repoName] = true;
        } else {
             $actionDetails = $type . " etkinliği";
        }
        
        $filtered_events[] = [
            "type" => $type,
            "repo" => $repoName,
            "date" => $createdAt,
            "details" => $actionDetails
        ];
        
        if ($isToday) {
            $today_timestamps[] = strtotime($createdAt);
        }
    }
    
    foreach ($unique_repos_today as $repo => $val) {
        $stats['repos']++;
        $parts = explode('/', $repo);
        $projectName = count($parts) > 1 ? $parts[1] : $repo;
        $stats['active_projects'][] = ucfirst($projectName);
    }
    
    // Çalışma süresi hesaplama (Clustering Algorithm)
    if (count($today_timestamps) > 0) {
        sort($today_timestamps); // Eskiden yeniye sırala
        $totalMinutes = 0;
        $sessionStart = null;
        $lastTime = null;
        
        foreach ($today_timestamps as $time) {
            if ($lastTime === null) {
                $sessionStart = $time;
                $lastTime = $time;
            } elseif (($time - $lastTime) <= 3600) { // 60 dk boşluk kuralı
                $lastTime = $time;
            } else {
                // Önceki oturumu kapat
                $sessionDuration = ($lastTime - $sessionStart) / 60;
                $totalMinutes += $sessionDuration + 30; // 30 dk buffer
                $sessionStart = $time;
                $lastTime = $time;
            }
        }
        
        // Son açık kalan oturumu kapat
        if ($sessionStart !== null) {
            $sessionDuration = ($lastTime - $sessionStart) / 60;
            $totalMinutes += $sessionDuration + 30;
        }
        
        $totalMinutes = round($totalMinutes);
        if ($totalMinutes >= 60) {
            $hours = floor($totalMinutes / 60);
            $mins = $totalMinutes % 60;
            $stats['work_time'] = "{$hours} Saat " . ($mins > 0 ? "{$mins} Dakika" : "");
        } else {
            $stats['work_time'] = "{$totalMinutes} Dakika";
        }
    }
    
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
    
    $output = json_encode(["success" => true, "data" => $filtered_events, "stats" => $stats]);
    
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
