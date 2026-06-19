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
        "active_projects" => []
    ];
    
    $unique_repos_today = [];

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
    }
    
    foreach ($unique_repos_today as $repo => $val) {
        $stats['repos']++;
        $parts = explode('/', $repo);
        $projectName = count($parts) > 1 ? $parts[1] : $repo;
        $stats['active_projects'][] = ucfirst($projectName);
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
