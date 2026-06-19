<?php
require_once 'config.php';

if (GITHUB_TOKEN === 'BURAYA_GITHUB_TOKEN_GELECEK' || empty(GITHUB_TOKEN)) {
    echo json_encode(["error" => "Lütfen api/config.php dosyasına GitHub Token'ınızı girin."]);
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
    
    // Gelen veriyi süzebiliriz (Örneğin sadece PushEvent, CreateEvent gibi etkinlikleri alabiliriz)
    $filtered_events = [];
    foreach ($events as $event) {
        // İsterseniz burada sadece belirli tip etkinlikleri filtreleyebilirsiniz.
        // Şimdilik formatlayıp hepsini gönderiyoruz.
        
        $type = $event['type'];
        $repoName = $event['repo']['name'];
        $createdAt = $event['created_at'];
        
        $actionDetails = "";
        
        if ($type === "PushEvent") {
            $commits = isset($event['payload']['commits']) ? $event['payload']['commits'] : [];
            if (count($commits) > 0) {
                $commitMessages = array_map(function($commit) { return $commit['message'] ?? ''; }, $commits);
                $actionDetails = count($commits) . " commit pushlandı: " . implode(" | ", $commitMessages);
            } else {
                $ref = isset($event['payload']['ref']) ? str_replace('refs/heads/', '', $event['payload']['ref']) : 'bir';
                
                $commitCount = 1;
                if (isset($event['payload']['before']) && isset($event['payload']['head'])) {
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
                        }
                    }
                }
                
                $actionDetails = "{$commitCount} commit pushlandı ({$ref} dalına).";
            }
        } elseif ($type === "CreateEvent") {
            $actionDetails = $event['payload']['ref_type'] . " oluşturuldu.";
        } elseif ($type === "WatchEvent") {
            $actionDetails = "Depo yıldızlandı.";
        } elseif ($type === "IssuesEvent") {
            $actionDetails = "Issue " . $event['payload']['action'];
        } elseif ($type === "PullRequestEvent") {
            $actionDetails = "Pull Request " . $event['payload']['action'];
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
    
    echo json_encode(["success" => true, "data" => $filtered_events]);
} else {
    echo json_encode([
        "error" => "GitHub API'sine bağlanırken bir sorun oluştu.", 
        "http_code" => $httpcode,
        "curl_error" => $error
    ]);
}
