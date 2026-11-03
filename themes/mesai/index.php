<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mesai Defteri</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="themes/mesai/assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="icon" type="image/svg+xml" href="icon0.svg">
    <link rel="apple-touch-icon" href="apple-icon.png">
    <link rel="manifest" href="manifest.json">
</head>
<body>
    <div class="loadbar" id="loadbar" role="progressbar" aria-label="Veri yükleniyor" aria-valuemin="0" aria-valuemax="100" hidden><i id="loadbarFill"></i></div>
    <main class="sheet">
        <header class="top">
            <div>
                <h1>Mesai Defteri</h1>
                <p class="muted">Commit saatlerinden ölçülen çalışma süreleri</p>
            </div>
            <div class="mono muted" id="updatedAt">yükleniyor…</div>
        </header>

        <section class="hero">
            <div class="hero-label"><span class="dot" id="liveDot"></span><span id="sessionState">KESİNTİSİZ MESAİ</span></div>
            <div class="hero-num is-loading" id="sessionTime">…</div>
            <p class="muted" id="sessionSince">Gün 00:00'da sıfırlanır, mesai sıfırlanmaz.</p>
            <div class="timeline" id="timeline" aria-label="Mesai zaman çizelgesi"></div>
            <div class="timeline-axis mono" id="timelineAxis"></div>
            <ul class="gaps mono" id="gapList"></ul>
        </section>

        <section class="tiles">
            <div class="tile"><span class="tile-k">BUGÜN</span><span class="tile-v" id="tToday">—</span><span class="tile-s mono" id="tTodayC"></span></div>
            <div class="tile"><span class="tile-k">DÜN</span><span class="tile-v" id="tYesterday">—</span><span class="tile-s mono" id="tYesterdayC"></span></div>
            <div class="tile"><span class="tile-k">SON 24 SAAT</span><span class="tile-v" id="t24">—</span><span class="tile-s mono" id="t24C"></span></div>
            <div class="tile"><span class="tile-k">SON 7 GÜN</span><span class="tile-v" id="t7">—</span><span class="tile-s mono" id="t7C"></span></div>
        </section>

        <section class="split">
            <div class="panel">
                <div class="history-head"><h2>Bu mesaide projeler</h2><span class="mono muted" id="projCount"></span></div>
                <ul class="projects" id="projectList"><li class="muted">—</li></ul>
            </div>
            <div class="panel">
                <div class="history-head">
                    <h2>Commitler</h2>
                    <div class="seg" role="group" aria-label="Commit aralığı">
                        <button type="button" data-range="session" class="on">Mesai</button>
                        <button type="button" data-range="today">Bugün</button>
                        <button type="button" data-range="yesterday">Dün</button>
                    </div>
                </div>
                <ol class="commits" id="commitList"><li class="muted">—</li></ol>
            </div>
        </section>

        <section class="history">
            <div class="history-head">
                <h2>Geçmiş günler</h2>
                <div class="legend mono"><span class="sw measured"></span>ölçülen <span class="sw estimated"></span>tahmini</div>
            </div>
            <ol class="days" id="dayList"></ol>
            <button class="more" id="moreBtn" type="button" hidden>Tüm yılı göster</button>
        </section>
    </main>
    <script src="themes/mesai/assets/js/app.js"></script>
</body>
</html>
