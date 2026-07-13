<?php
$defaultTheme = (isset($envVariables) && isset($envVariables['DEFAULT_THEME'])) ? $envVariables['DEFAULT_THEME'] : 'luffy-dark';
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Korsan Kralı - GitHub Paneli</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Pirata+One&family=Roboto+Condensed:wght@400;700&family=Permanent+Marker&display=swap"
        rel="stylesheet">
    <!-- FontAwesome Eklendi -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="themes/luffy/assets/css/style.css?v=2">
</head>

<body class="<?= htmlspecialchars($defaultTheme) ?>">
    <!-- Ana Arka Plan ve Efektler. -->
    <div class="luffy-bg-overlay"></div>

    <!-- Tam Ekran Tuşu -->
    <button id="fullscreenBtn" class="fullscreen-btn" title="Tam Ekran Modu">
        <i class="fa-solid fa-expand"></i>
    </button>

    <div class="luffy-dashboard">

        <!-- ÜST BÖLÜM (HEADER) -->
        <header class="luffy-header">
            <div class="header-left"></div>

            <div class="header-right">
                <div class="system-status-box">
                    <div class="status-top">SİSTEM DURUMU</div>
                    <div class="status-mid">
                        <div class="status-left">
                            <span class="status-text">AKTİF</span>
                            <div class="heartbeat-line">
                                <svg viewBox="0 0 100 30" preserveAspectRatio="none" style="width:100%; height:100%;">
                                    <path d="M0,15 L30,15 L35,5 L45,25 L55,0 L65,25 L70,15 L100,15"
                                        stroke="var(--danger-red)" stroke-width="2" fill="none"
                                        vector-effect="non-scaling-stroke" />
                                </svg>
                            </div>
                        </div>
                        <div class="radar-circle">
                            <div class="radar-grid"></div>
                            <div class="radar-cross"></div>
                            <div class="radar-sweep"></div>
                            <div class="radar-dot"></div>
                        </div>
                    </div>
                    <div class="status-bottom">
                        VERİLER CANLI OLARAK GÜNCELLENİYOR <span class="red-dot"></span>
                    </div>
                </div>
            </div>
        </header>

        <!-- ANA İÇERİK (ÜÇ KOLONLU YAPI) -->
        <main class="luffy-main">

            <!-- SOL KOLON -->
            <aside class="luffy-sidebar-left">
                <!-- Günlük Rapor Paneli -->
                <section class="luffy-panel" id="statsSection">
                    <h3 class="panel-header" style="justify-content: space-between; flex-wrap: wrap; gap: 10px;">
                        <span><i class="fa-solid fa-file-contract"></i> GÜNLÜK RAPOR</span>
                        <div class="period-selector">
                            <button class="period-btn active" data-period="today">BUGÜN</button>
                            <button class="period-btn" data-period="yesterday">DÜN</button>
                            <button class="period-btn" data-period="last_24h">24 SAAT</button>
                        </div>
                    </h3>
                    <ul class="daily-stats-list">
                        <li>
                            <div class="stat-icon anchor"><i class="fa-solid fa-anchor"></i></div>
                            <div class="stat-info">
                                <strong>İŞLEM KOMUTU</strong>
                                <span>Bugünkü Commit</span>
                            </div>
                            <div class="stat-num" id="statCommits">0</div>
                        </li>
                        <li>
                            <div class="stat-icon file"><i class="fa-solid fa-file-code"></i></div>
                            <div class="stat-info">
                                <strong>DOSYA DEĞİŞTİRİLDİ</strong>
                                <span>Dosya</span>
                            </div>
                            <div class="stat-num" id="statChangedFiles">0</div>
                        </li>
                        <li>
                            <div class="stat-icon plus"><i class="fa-solid fa-plus"></i></div>
                            <div class="stat-info">
                                <strong>VERİ EKLENDİ</strong>
                                <span>+ Satırlar</span>
                            </div>
                            <div class="stat-num success" id="statAdditions">+0</div>
                        </li>
                        <li>
                            <div class="stat-icon minus"><i class="fa-solid fa-minus"></i></div>
                            <div class="stat-info">
                                <strong>VERİ SİLİNDİ</strong>
                                <span>- Satırlar</span>
                            </div>
                            <div class="stat-num danger" id="statDeletions">-0</div>
                        </li>
                        <li>
                            <div class="stat-icon skull"><i class="fa-solid fa-code-branch"></i></div>
                            <div class="stat-info">
                                <strong>MODÜL GÜNCELLENDİ</strong>
                                <span>Aktif Repo</span>
                            </div>
                            <div class="stat-num" id="statRepos">0</div>
                        </li>
                        <li>
                            <div class="stat-icon hourglass"><i class="fa-solid fa-hourglass-half"></i></div>
                            <div class="stat-info">
                                <strong>SİSTEM SÜRESİ</strong>
                                <span>Kodlama Süresi</span>
                            </div>
                            <div class="stat-num" id="statWorkTime">0dk</div>
                        </li>
                    </ul>
                </section>

                <!-- Aktif Modüller Paneli -->
                <section class="luffy-panel mt-20">
                    <h3 class="panel-header"><i class="fa-solid fa-folder-open"></i> AKTİF MODÜLLER</h3>
                    <ul class="active-modules-list" id="activeProjectsList">
                        <!-- JS ile dolacak -->
                    </ul>
                </section>
            </aside>

            <!-- ORTA KOLON -->
            <section class="luffy-center-col">
                <!-- Üçlü Parşömen Kartları -->
                <div class="parchment-cards" id="longTermStatsSection" style="display: none;">
                    <div class="parchment-card">
                        <div class="card-inner">
                            <h4 class="card-title"><i class="fa-solid fa-skull-crossbones"></i> HAFTALIK KATKI</h4>
                            <div class="card-value" id="statWeekly">0</div>
                            <div class="card-desc">- BU HAFTA -</div>
                        </div>
                    </div>
                    <div class="parchment-card center-card">
                        <div class="card-inner">
                            <h4 class="card-title"><i class="fa-solid fa-skull-crossbones"></i> AYLIK KATKI</h4>
                            <div class="card-value" id="statMonthly">0</div>
                            <div class="card-desc">- BU AY -</div>
                        </div>
                    </div>
                    <div class="parchment-card">
                        <div class="card-inner">
                            <h4 class="card-title"><i class="fa-solid fa-skull-crossbones"></i> YILLIK KATKI</h4>
                            <div class="card-value" id="statYearly">0</div>
                            <div class="card-desc">- BU YIL -</div>
                        </div>
                    </div>
                </div>

                <!-- Takvim Paneli -->
                <section class="luffy-panel calendar-panel" id="calendarSection" style="display: none;">
                    <h3 class="panel-header"><i class="fa-solid fa-chart-line"></i> VERİ AKIŞI: 365 GÜN</h3>
                    <div class="calendar-wrapper">
                        <div class="calendar-graph" id="calendarGraph">
                            <!-- JS ile takvim -->
                        </div>
                    </div>
                    <div class="calendar-legend">
                        <span>MİN</span>
                        <ul class="legend-list">
                            <li style="background-color: var(--cal-level-0);"></li>
                            <li style="background-color: var(--cal-level-1);"></li>
                            <li style="background-color: var(--cal-level-2);"></li>
                            <li style="background-color: var(--cal-level-3);"></li>
                            <li style="background-color: var(--cal-level-4);"></li>
                        </ul>
                        <span>MAKS</span>
                    </div>
                </section>

                <!-- İstatistikler Paneli -->
                <section class="luffy-panel avg-stats-panel" id="avgStatsSection" style="display: none;">
                    <h3 class="panel-header"><i class="fa-solid fa-chart-pie"></i> DETAYLI İSTATİSTİKLER</h3>
                    <div class="avg-stats-container">
                        <!-- Ortalama Çalışma Süresi (Günlük) -->
                        <div class="parchment-card avg-card">
                            <div class="card-inner">
                                <h4 class="card-title"><i class="fa-solid fa-hourglass-half"></i> GÜNLÜK ORT. ÇALIŞMA</h4>
                                <div class="avg-stat-row single-row">
                                    <span class="avg-val big-val" id="avgDailyWorkTime">0dk</span>
                                </div>
                                <div class="card-desc">- SON 30 GÜN ORTALAMASI -</div>
                            </div>
                        </div>

                        <!-- Toplam Çalışma Süreleri -->
                        <div class="parchment-card avg-card">
                            <div class="card-inner">
                                <h4 class="card-title"><i class="fa-solid fa-clock"></i> TOPLAM ÇALIŞMA SÜRESİ</h4>
                                <div class="avg-stat-row">
                                    <span class="avg-label">BUGÜN:</span>
                                    <span class="avg-val" id="realTodayWorkTime">0dk</span>
                                </div>
                                <div class="avg-stat-row">
                                    <span class="avg-label">BU HAFTA:</span>
                                    <span class="avg-val" id="realWeeklyWorkTime">0dk</span>
                                </div>
                                <div class="avg-stat-row">
                                    <span class="avg-label">BU AY:</span>
                                    <span class="avg-val" id="realMonthlyWorkTime">0dk</span>
                                </div>
                                <div class="card-desc">- GERÇEKLEŞEN SÜRELER -</div>
                            </div>
                        </div>
                        
                        <!-- Ortalama Katkı (Günlük) -->
                        <div class="parchment-card avg-card">
                            <div class="card-inner">
                                <h4 class="card-title"><i class="fa-solid fa-calculator"></i> GÜNLÜK ORT. KATKI</h4>
                                <div class="avg-stat-row single-row">
                                    <span class="avg-val big-val" id="avgDailyCommits">0</span>
                                </div>
                                <div class="card-desc">- SON 30 GÜN ORTALAMASI -</div>
                            </div>
                        </div>

                        <!-- Gerçek Toplam Commit Parşömeni -->
                        <div class="parchment-card avg-card">
                            <div class="card-inner">
                                <h4 class="card-title"><i class="fa-solid fa-anchor"></i> TOPLAM KATKI (COMMIT)</h4>
                                <div class="avg-stat-row">
                                    <span class="avg-label">BUGÜN:</span>
                                    <span class="avg-val" id="realTodayCommits">0</span>
                                </div>
                                <div class="avg-stat-row">
                                    <span class="avg-label">BU HAFTA:</span>
                                    <span class="avg-val" id="realWeeklyCommits">0</span>
                                </div>
                                <div class="avg-stat-row">
                                    <span class="avg-label">BU AY:</span>
                                    <span class="avg-val" id="realMonthlyCommits">0</span>
                                </div>
                                <div class="card-desc">- GERÇEKLEŞEN RAPORLAR -</div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Ayarlar Paneli -->
                <section class="luffy-panel settings-panel" id="settingsSection" style="display: none;">
                    <h3 class="panel-header"><i class="fa-solid fa-gear"></i> PANEL AYARLARI</h3>
                    <div style="padding: 20px;">
                        <h4 style="color: var(--danger-red); margin-bottom: 10px; font-family: 'Permanent Marker', cursive;">KORSAN TAYFASI BİLGİ SİSTEMİ</h4>
                        <p style="color: var(--text-primary); line-height: 1.6; margin-bottom: 15px;">
                            Bu panel, Monkey D. Luffy ve Hasır Şapka Korsanları temasıyla tasarlanmış bir GitHub Aktivite Takip Sistemidir.
                        </p>
                        <div style="background: rgba(0, 0, 0, 0.4); padding: 15px; border-radius: 5px; border: 1px solid var(--panel-border);">
                            <p style="margin-bottom: 8px;"><strong>Aktif Tema:</strong> <span style="color: var(--danger-red);">Luffy (Korsan Kralı)</span></p>
                            <p style="margin-bottom: 8px;"><strong>Veri Kaynağı:</strong> GitHub REST & GraphQL API</p>
                            <p><strong>Durum:</strong> Tayfa hazır, yelkenler fora!</p>
                        </div>
                    </div>
                </section>
            </section>

            <!-- SAĞ KOLON -->
            <aside class="luffy-sidebar-right">
                <section class="luffy-panel log-panel">
                    <h3 class="panel-header"><i class="fa-solid fa-terminal"></i> SİSTEM LOGLARI</h3>
                    <div class="log-timeline" id="activityTimeline">
                        <!-- JS ile loglar yüklenecek -->
                        <div class="loading-state">
                            <div class="spinner"><i class="fa-solid fa-circle-notch fa-spin"></i></div>
                            <p>VERİLER ÇEKİLİYOR...<br><small>Nakama, biraz bekle!</small></p>
                        </div>
                    </div>
                </section>
            </aside>
        </main>

        <!-- ALT BAR (FOOTER SEKMELERİ) -->
        <footer class="luffy-footer">
            <nav class="footer-nav">
                <a href="#" class="nav-item active" data-tab="home"><i class="fa-solid fa-compass"></i> ANA SAYFA</a>
                <a href="#" class="nav-item" data-tab="repos"><i class="fa-solid fa-book-journal-whills"></i> REPOLAR</a>
                <a href="#" class="nav-item" data-tab="stats"><i class="fa-solid fa-chart-simple"></i> İSTATİSTİKLER</a>
                <a href="#" class="nav-item" data-tab="activity"><i class="fa-solid fa-anchor"></i> AKTİVİTE</a>
                <a href="#" class="nav-item" data-tab="settings"><i class="fa-solid fa-gear"></i> AYARLAR</a>
            </nav>
            <div class="footer-quote">
                "HAYALLERİ OLAN İNSANLAR,<br>ASLA GERÇEKLERDEN KAÇMAZLAR!"<br>
                <span>- MONKEY D. LUFFY</span>
            </div>
        </footer>

    </div>

    <div id="globalTooltip" class="global-tooltip"></div>
    <script src="themes/luffy/assets/js/app.js"></script>
</body>

</html>