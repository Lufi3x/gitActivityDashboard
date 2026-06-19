<?php
// Geçerli temayı belirle (Zaten index.php içinde $theme değişkeniyle yakalanıyor)
$currentTheme = isset($theme) ? $theme : 'jarvis';

// Tüm temaların isimleri ve başlıkları
$allThemes = [
    'jarvis' => '🤖 Jarvis HUD',
    'activity_monitor' => '💻 Activity Monitor',
    'nitro_hud' => '🏎️ Nitro HUD',
    'hacker_terminal' => '🟢 Hacker Terminal',
    'minimal_light' => '☁️ Minimal Light',
    'glass_light' => '💎 Glass Light'
];
?>

<!-- Theme Switcher Widget -->
<div id="theme-switcher-widget">
    <button class="ts-toggle-btn" id="tsToggleBtn" title="Temayı Değiştir">
        🎨
    </button>
    <div class="ts-panel" id="tsPanel">
        <div class="ts-header">
            <h4>Canlı Önizleme</h4>
            <p>Bir tema seçin:</p>
        </div>
        <ul class="ts-list">
            <?php foreach ($allThemes as $key => $title): ?>
                <li>
                    <a href="?theme=<?= htmlspecialchars($key) ?>" class="<?= $currentTheme === $key ? 'active' : '' ?>">
                        <?= $title ?>
                        <?php if ($currentTheme === $key): ?>
                            <span class="ts-check">✓</span>
                        <?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<style>
/* Theme Switcher Styles - İzole edilmiştir, temaları bozmaz */
#theme-switcher-widget {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 999999;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}

.ts-toggle-btn {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: #000;
    color: #fff;
    border: 2px solid rgba(255,255,255,0.2);
    font-size: 24px;
    cursor: pointer;
    box-shadow: 0 10px 25px rgba(0,0,0,0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    outline: none;
}

.ts-toggle-btn:hover {
    transform: scale(1.1);
    background: #222;
}

.ts-panel {
    position: absolute;
    bottom: 80px;
    right: 0;
    width: 260px;
    background: rgba(15, 15, 20, 0.95);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.6);
    overflow: hidden;
    opacity: 0;
    visibility: hidden;
    transform: translateY(20px) scale(0.95);
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    transform-origin: bottom right;
}

#theme-switcher-widget.open .ts-panel {
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
}

.ts-header {
    padding: 16px;
    background: rgba(0,0,0,0.4);
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

.ts-header h4 {
    margin: 0 0 4px 0;
    color: #fff;
    font-size: 16px;
    font-weight: 600;
}

.ts-header p {
    margin: 0;
    color: #aaa;
    font-size: 12px;
}

.ts-list {
    list-style: none;
    margin: 0;
    padding: 8px;
}

.ts-list li {
    margin-bottom: 4px;
}

.ts-list li:last-child {
    margin-bottom: 0;
}

.ts-list a {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    color: #ddd;
    text-decoration: none;
    font-size: 14px;
    border-radius: 8px;
    transition: all 0.2s;
}

.ts-list a:hover {
    background: rgba(255,255,255,0.1);
    color: #fff;
}

.ts-list a.active {
    background: rgba(56, 189, 248, 0.15);
    color: #38bdf8;
    font-weight: 600;
}

.ts-check {
    font-size: 16px;
}

/* Mobil için ayarlamalar */
@media (max-width: 480px) {
    #theme-switcher-widget {
        bottom: 20px;
        right: 20px;
    }
    .ts-toggle-btn {
        width: 50px;
        height: 50px;
        font-size: 20px;
    }
    .ts-panel {
        bottom: 70px;
        width: 240px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tsBtn = document.getElementById('tsToggleBtn');
    const tsWidget = document.getElementById('theme-switcher-widget');

    tsBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        tsWidget.classList.toggle('open');
    });

    // Dışarıya tıklanırsa menüyü kapat
    document.addEventListener('click', function(e) {
        if (!tsWidget.contains(e.target)) {
            tsWidget.classList.remove('open');
        }
    });
});
</script>
