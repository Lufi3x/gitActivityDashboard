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

$allowedThemes = ['jarvis', 'activity_monitor', 'nitro_hud', 'hacker_terminal', 'minimal_light', 'glass_light', 'luffy'];

// 1. Kullanıcıdan gelen Tema Değiştirme İsteği (GET parametresi)
if (isset($_GET['theme']) && in_array($_GET['theme'], $allowedThemes)) {
    $theme = $_GET['theme'];
    // Seçimi Cookie'ye kaydet (30 gün)
    setcookie('demo_theme', $theme, time() + (86400 * 30), '/');
} 
// 2. Cookie'de önceden seçilmiş bir tema var mı?
elseif (isset($_COOKIE['demo_theme']) && in_array($_COOKIE['demo_theme'], $allowedThemes)) {
    $theme = $_COOKIE['demo_theme'];
}

if (!in_array($theme, $allowedThemes)) {
    $theme = 'jarvis';
}

$themeFile = __DIR__ . '/themes/' . $theme . '/index.php';

// Temayı hemen yazdırma, hafızaya al (Output Buffering)
ob_start();

if (file_exists($themeFile)) {
    include $themeFile;
} else {
    echo "<h1>Tema dosyası bulunamadı: " . htmlspecialchars($theme) . "</h1>";
    echo "<p>Lütfen 'themes' klasöründeki yapıları kontrol edin veya .env dosyasındaki UI_THEME ayarını düzeltin.</p>";
}

$htmlOutput = ob_get_clean();

// Eğer ENABLE_THEME_SWITCHER açıksa, Widget'ı </body> etiketinden önce enjekte et
$enableSwitcher = isset($envVariables['ENABLE_THEME_SWITCHER']) && filter_var($envVariables['ENABLE_THEME_SWITCHER'], FILTER_VALIDATE_BOOLEAN);

if ($enableSwitcher && file_exists(__DIR__ . '/includes/theme_switcher.php')) {
    ob_start();
    include __DIR__ . '/includes/theme_switcher.php';
    $switcherHtml = ob_get_clean();
    
    // HTML'in sonundaki </body> etiketini bul ve öncesine Switcher'ı ekle
    $htmlOutput = str_replace('</body>', $switcherHtml . "\n</body>", $htmlOutput);
}

echo $htmlOutput;
