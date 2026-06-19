<?php
// HTML sayfası olarak yorumlanması için Content-Type belirle
header("Content-Type: text/html; charset=UTF-8");

$envFile = __DIR__ . '/.env';
$theme = 'jarvis'; // Varsayılan

if (file_exists($envFile)) {
    $envVariables = parse_ini_file($envFile);
    if ($envVariables !== false && isset($envVariables['UI_THEME'])) {
        $theme = trim($envVariables['UI_THEME']);
    }
}

// Güvenlik: Sadece mevcut olan klasör adlarına izin ver (Directory Traversal engellemesi)
$allowedThemes = ['jarvis', 'activity_monitor', 'nitro_hud'];
if (!in_array($theme, $allowedThemes)) {
    $theme = 'jarvis';
}

$themeFile = __DIR__ . '/themes/' . $theme . '/index.php';

if (file_exists($themeFile)) {
    include $themeFile;
} else {
    echo "<h1>Tema dosyası bulunamadı: " . htmlspecialchars($theme) . "</h1>";
    echo "<p>Lütfen 'themes' klasöründeki yapıları kontrol edin veya .env dosyasındaki UI_THEME ayarını düzeltin.</p>";
}
