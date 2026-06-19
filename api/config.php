<?php
// Geliştirme aşamasında hataları görmek için (Canlıda kapatılmalıdır)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// CORS Ayarları (Gerekirse)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$envFile = __DIR__ . '/../.env';

if (!file_exists($envFile)) {
    echo json_encode(['error' => '.env dosyası bulunamadı. Lütfen .env.example dosyasını kopyalayarak .env dosyası oluşturun ve bilgilerinizi girin.']);
    exit;
}

// .env dosyasını parse et
$envVariables = parse_ini_file($envFile);

if ($envVariables === false) {
    echo json_encode(['error' => '.env dosyası okunamadı. Dosya formatının doğru olduğundan emin olun.']);
    exit;
}

// Değişkenleri tanımla
define('GITHUB_USERNAME', isset($envVariables['GITHUB_USERNAME']) ? trim($envVariables['GITHUB_USERNAME']) : '');
define('GITHUB_TOKEN', isset($envVariables['GITHUB_TOKEN']) ? trim($envVariables['GITHUB_TOKEN']) : '');
define('DEFAULT_THEME', isset($envVariables['DEFAULT_THEME']) ? trim($envVariables['DEFAULT_THEME']) : 'theme-cyan');

// UI Tema (jarvis veya activity_monitor)
define('UI_THEME', isset($envVariables['UI_THEME']) ? trim($envVariables['UI_THEME']) : 'jarvis');

// Görünürlük (Gizlilik) Modülleri
define('SHOW_SYSTEM_LOGS', isset($envVariables['SHOW_SYSTEM_LOGS']) ? filter_var($envVariables['SHOW_SYSTEM_LOGS'], FILTER_VALIDATE_BOOLEAN) : true);
define('SHOW_ACTIVE_PROJECTS', isset($envVariables['SHOW_ACTIVE_PROJECTS']) ? filter_var($envVariables['SHOW_ACTIVE_PROJECTS'], FILTER_VALIDATE_BOOLEAN) : true);

// Cache Süresi (Dakika cinsinden, varsayılan 10)
define('CACHE_DURATION_MINUTES', isset($envVariables['CACHE_DURATION_MINUTES']) ? (int)trim($envVariables['CACHE_DURATION_MINUTES']) : 10);


if (empty(GITHUB_USERNAME) || empty(GITHUB_TOKEN)) {
    echo json_encode(['error' => 'GITHUB_USERNAME veya GITHUB_TOKEN eksik. Lütfen .env dosyanızı kontrol edin.']);
    exit;
}
