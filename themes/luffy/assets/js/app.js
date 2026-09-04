let statsData = null;
let currentCalcMode = localStorage.getItem('git_dash_calc_mode') || 'session';

document.addEventListener('DOMContentLoaded', () => {
    fetchActivity();
    initFullscreen();
    initPeriodSelector();
    initCalcModeSelector();
    initTokenSimulator();
    initTabs();
});

async function fetchActivity(forceRefresh = false) {
    const timelineContainer = document.getElementById('activityTimeline');

    try {
        const url = forceRefresh ? 'api/fetch_activity.php?force_refresh=1' : 'api/fetch_activity.php';
        const response = await fetch(url);
        const result = await response.json();

        timelineContainer.innerHTML = ''; // Temizle.

        if (result.error) {
            timelineContainer.innerHTML = `
                <div class="activity-card" style="text-align:center; padding: 30px; color: #e62e2e;">
                    <p><i class="fa-solid fa-triangle-exclamation"></i> Hata: ${result.error}</p>
                </div>`;
            return;
        }

        if (result.success && result.data) {

            // Gizlilik ve Modül Ayarları
            if (result.config) {
                if (result.config.show_system_logs === false) {
                    document.querySelector('.luffy-sidebar-right').style.display = 'none';
                }
                if (result.config.show_active_projects === false) {
                    const projectsList = document.getElementById('activeProjectsList');
                    if (projectsList) {
                        projectsList.parentElement.style.display = 'none';
                    }
                }
            }

            // İstatistikleri ekrana bas
            if (result.stats) {
                // Uzun Dönem İstatistikleri
                if (result.stats.weekly_commits !== undefined) {
                    document.getElementById('longTermStatsSection').style.display = 'grid';
                    document.getElementById('statWeekly').textContent = result.stats.weekly_commits;
                    document.getElementById('statMonthly').textContent = result.stats.monthly_commits;
                    document.getElementById('statYearly').textContent = result.stats.yearly_commits;
                }

                // Detaylı İstatistikler (Ortalamalar ve Gerçek Toplamlar)
                if (result.stats.avg_daily_work_time_str !== undefined) {
                    document.getElementById('avgStatsSection').style.display = 'block';
                    
                    // Ortalamalar (Son 7 Gün & Son 30 Gün)
                    if (document.getElementById('avgDailyWorkTime7d')) {
                        document.getElementById('avgDailyWorkTime7d').textContent = result.stats.avg_daily_work_time_str_7d || "0dk";
                        document.getElementById('avgDailyWorkTime30d').textContent = result.stats.avg_daily_work_time_str_30d || "0dk";
                    }
                    if (document.getElementById('avgDailyCommits7d')) {
                        document.getElementById('avgDailyCommits7d').textContent = result.stats.avg_daily_commits_7d || 0;
                        document.getElementById('avgDailyCommits30d').textContent = result.stats.avg_daily_commits_30d || 0;
                    }

                    // Gerçek Toplam Süreler
                    if (document.getElementById('realTodayWorkTime')) {
                        document.getElementById('realTodayWorkTime').textContent = result.stats.real_today_work_time_str || "0dk";
                        document.getElementById('realWeeklyWorkTime').textContent = result.stats.real_weekly_work_time_str || "0dk";
                        document.getElementById('realMonthlyWorkTime').textContent = result.stats.real_monthly_work_time_str || "0dk";
                    }

                    // Gerçek Toplam Commitler
                    if (document.getElementById('realTodayCommits')) {
                        document.getElementById('realTodayCommits').textContent = result.stats.today ? (result.stats.today.commits || 0) : 0;
                        document.getElementById('realWeeklyCommits').textContent = result.stats.weekly_commits || 0;
                        document.getElementById('realMonthlyCommits').textContent = result.stats.monthly_commits || 0;
                    }

                    // Satır Bazlı Süre Kartı
                    if (document.getElementById('linesTodayWorkTime')) {
                        document.getElementById('linesTodayWorkTime').textContent = result.stats.today ? (result.stats.today.work_time_lines || "0dk") : "0dk";
                        document.getElementById('linesYesterdayWorkTime').textContent = result.stats.yesterday ? (result.stats.yesterday.work_time_lines || "0dk") : "0dk";
                        document.getElementById('lines24hWorkTime').textContent = result.stats.last_24h ? (result.stats.last_24h.work_time_lines || "0dk") : "0dk";
                    }

                    // Proje Bazlı Token & Analiz Tablosu Render (Tüm Zamanlar)
                    const projectsToRender = result.stats.all_time_project_stats || result.stats.project_stats;
                    if (projectsToRender && projectsToRender.length > 0) {
                        renderProjectAnalyticsTable(projectsToRender);
                    }
                    if (result.stats.all_time_cache_info && document.getElementById('tokenCacheInfoBadge')) {
                        const cInfo = result.stats.all_time_cache_info;
                        document.getElementById('tokenCacheInfoBadge').textContent = `TÜM ZAMANLAR (${projectsToRender.length} PROJE • CACHE: ${cInfo.cached_at || 'Aktif'})`;
                    }

                    // Günlük Çalışma Günlüğü (Daily Log) Render
                    if (result.stats.daily_log && result.stats.daily_log.length > 0) {
                        renderDailyLog(result.stats.daily_log);
                    }
                }

                // Katkı Takvimi
                if (result.stats.calendar && result.stats.calendar.length > 0) {
                    document.getElementById('calendarSection').style.display = 'block';
                    const graphContainer = document.getElementById('calendarGraph');
                    graphContainer.innerHTML = '';

                    result.stats.calendar.forEach(week => {
                        const weekDiv = document.createElement('div');
                        weekDiv.className = 'calendar-week';

                        week.contributionDays.forEach(day => {
                            const dayDiv = document.createElement('div');
                            dayDiv.className = 'calendar-day';

                            let level = 0;
                            if (day.contributionCount > 0 && day.contributionCount <= 3) level = 1;
                            else if (day.contributionCount > 3 && day.contributionCount <= 9) level = 2;
                            else if (day.contributionCount > 9 && day.contributionCount <= 19) level = 3;
                            else if (day.contributionCount >= 20) level = 4;

                            dayDiv.setAttribute('data-level', level);

                            const dateObj = new Date(day.date);
                            const dateStr = dateObj.toLocaleDateString('tr-TR', { day: 'numeric', month: 'long', year: 'numeric' });

                            dayDiv.addEventListener('mouseenter', (e) => {
                                const globalTooltip = document.getElementById('globalTooltip');
                                globalTooltip.innerHTML = `<strong>${day.contributionCount} Katkı</strong><br>${dateStr}`;
                                const rect = dayDiv.getBoundingClientRect();
                                globalTooltip.style.display = 'block';
                                globalTooltip.style.left = rect.left + (rect.width / 2) + window.scrollX + 'px';
                                globalTooltip.style.top = rect.top + window.scrollY - globalTooltip.offsetHeight - 8 + 'px';
                                globalTooltip.classList.add('show');
                            });

                            dayDiv.addEventListener('mouseleave', () => {
                                hideGlobalTooltip();
                            });

                            weekDiv.appendChild(dayDiv);
                        });
                        graphContainer.appendChild(weekDiv);
                    });

                    requestAnimationFrame(() => {
                        const wrapper = graphContainer.parentElement;
                        wrapper.scrollLeft = wrapper.scrollWidth;
                    });
                }

                // Günlük Rapor verilerini global değişkene kaydet ve UI'ı güncelle
                statsData = result.stats;
                document.getElementById('statsSection').style.display = 'block';
                
                // Aktif olarak hangi buton seçiliyse o periyoda göre güncelle (varsayılan: today)
                const activeBtn = document.querySelector('.period-btn.active');
                const currentPeriod = activeBtn ? activeBtn.getAttribute('data-period') : 'today';
                updateStatsUI(currentPeriod);
            }

            // Logları listele
            if (result.data.length === 0) {
                timelineContainer.innerHTML = `<p style="padding: 20px; text-align:center; color: var(--text-muted);">Hareket bulunamadı.</p>`;
                return;
            }

            result.data.forEach((activity, index) => {
                const date = new Date(activity.date);
                const timeStr = date.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });

                let typeCode = '<i class="fa-solid fa-arrows-up-down"></i>';
                if (activity.type === 'PushEvent') typeCode = '<i class="fa-solid fa-arrow-up"></i>';
                else if (activity.type === 'CreateEvent') typeCode = '<i class="fa-solid fa-star"></i>';
                else if (activity.type === 'PullRequestEvent') typeCode = '<i class="fa-solid fa-code-pull-request"></i>';

                let actionName = activity.type.replace('Event', '').toUpperCase();

                const cardHTML = `
                    <div class="activity-card" style="animation-delay: ${index * 0.1}s">
                        <div class="activity-icon">${typeCode}</div>
                        <div class="activity-content">
                            <span class="activity-time">${timeStr} &nbsp;&nbsp; <span class="activity-action">${actionName}</span></span>
                            <div class="activity-desc">
                                ${activity.details} <br>
                                <small style="color: var(--text-muted);">${activity.repo}</small>
                            </div>
                        </div>
                    </div>
                `;
                timelineContainer.insertAdjacentHTML('beforeend', cardHTML);
            });
        }
    } catch (error) {
        timelineContainer.innerHTML = `<p style="color:red; text-align:center;"><i class="fa-solid fa-triangle-exclamation"></i> API Bağlantı Hatası</p>`;
    }
}

function initFullscreen() {
    const fsBtn = document.getElementById('fullscreenBtn');
    if (!fsBtn) return;

    fsBtn.addEventListener('click', () => {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(err => {
                console.log(`Tam ekran moduna geçilirken hata oluştu: ${err.message}`);
            });
            fsBtn.innerHTML = '<i class="fa-solid fa-compress"></i>';
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
                fsBtn.innerHTML = '<i class="fa-solid fa-expand"></i>';
            }
        }
    });

    document.addEventListener('fullscreenchange', () => {
        if (document.fullscreenElement) {
            fsBtn.innerHTML = '<i class="fa-solid fa-compress"></i>';
        } else {
            fsBtn.innerHTML = '<i class="fa-solid fa-expand"></i>';
        }
    });
}

function updateStatsUI(period) {
    if (!statsData || !statsData[period]) return;
    
    const pStats = statsData[period];
    
    document.getElementById('statCommits').textContent = pStats.commits;
    if (document.getElementById('statChangedFiles')) {
        document.getElementById('statChangedFiles').textContent = pStats.changed_files || 0;
    }
    document.getElementById('statAdditions').textContent = '+' + pStats.additions.toLocaleString('tr-TR');
    document.getElementById('statDeletions').textContent = '-' + pStats.deletions.toLocaleString('tr-TR');
    document.getElementById('statRepos').textContent = pStats.repos;
    
    if (document.getElementById('statWorkTime')) {
        let workTimeVal = pStats.work_time;
        let modeLabelText = "Kodlama Süresi";

        if (currentCalcMode === 'lines' && pStats.work_time_lines) {
            workTimeVal = pStats.work_time_lines;
            modeLabelText = "Kodlama Süresi (Satır Hızı)";
        } else if (currentCalcMode === 'hybrid' && pStats.work_time_hybrid) {
            workTimeVal = pStats.work_time_hybrid;
            modeLabelText = "Kodlama Süresi (Hibrit)";
        } else if (pStats.work_time_session) {
            workTimeVal = pStats.work_time_session;
            modeLabelText = "Kodlama Süresi (Push Saati)";
        }

        document.getElementById('statWorkTime').textContent = workTimeVal || "0dk";
        if (document.getElementById('calcModeLabel')) {
            document.getElementById('calcModeLabel').textContent = modeLabelText;
        }
    }
    
    // Aktif Modüller
    const projectsList = document.getElementById('activeProjectsList');
    if (projectsList) {
        projectsList.innerHTML = '';
        if (pStats.active_projects && pStats.active_projects.length > 0) {
            pStats.active_projects.forEach(project => {
                projectsList.insertAdjacentHTML('beforeend', `<li>${project}</li>`);
            });
        } else {
            projectsList.innerHTML = '<li style="color: var(--text-muted); font-size:0.9rem;">Aktif modül yok</li>';
        }
    }

    // 24 Saatlik Gün İçi Isı Haritasını Güncelle
    renderHourlyHeatmap(period);
}

function renderHourlyHeatmap(period) {
    const container = document.getElementById('hourlyBarsGraph');
    const section = document.getElementById('hourlyHeatmapSection');
    const subtitle = document.getElementById('heatmapSubtitle');
    if (!container || !section) return;

    const pStats = (statsData && statsData[period]) ? statsData[period] : (statsData || {});
    const hourlyData = pStats.hourly_activity || (statsData ? statsData.hourly_activity : null) || Array(24).fill(0);

    const periodNames = {
        'today': 'BUGÜN (00:00 - 23:59)',
        'yesterday': 'DÜN (00:00 - 23:59)',
        'last_24h': 'SON 24 SAAT'
    };

    if (subtitle) {
        subtitle.textContent = `${periodNames[period] || 'SAATLİK'} DAĞILIM`;
    }

    section.style.display = 'block';
    container.innerHTML = '';

    const maxCount = Math.max(...hourlyData, 1);

    hourlyData.forEach((count, hour) => {
        const col = document.createElement('div');
        col.className = 'hourly-bar-col';

        const track = document.createElement('div');
        track.className = 'hourly-bar-track';

        const fill = document.createElement('div');
        fill.className = 'hourly-bar-fill';

        let level = 0;
        let heightPercent = 0;

        if (count > 0) {
            heightPercent = Math.max(15, Math.round((count / maxCount) * 100));
            if (count <= 2) level = 1;
            else if (count <= 5) level = 2;
            else if (count <= 10) level = 3;
            else level = 4;
        } else {
            heightPercent = 0;
            level = 0;
        }

        fill.style.height = `${heightPercent}%`;
        fill.setAttribute('data-level', level);

        track.appendChild(fill);
        col.appendChild(track);

        const hourStr = String(hour).padStart(2, '0') + ':00';
        const nextHourStr = String((hour + 1) % 24).padStart(2, '0') + ':00';

        col.addEventListener('mouseenter', (e) => {
            const globalTooltip = document.getElementById('globalTooltip');
            if (!globalTooltip) return;
            globalTooltip.innerHTML = `<strong>${hourStr} - ${nextHourStr}</strong><br>${count} İşlem / Aktivite`;
            const rect = col.getBoundingClientRect();
            globalTooltip.style.display = 'block';
            globalTooltip.style.left = rect.left + (rect.width / 2) + window.scrollX + 'px';
            globalTooltip.style.top = rect.top + window.scrollY - globalTooltip.offsetHeight - 8 + 'px';
            globalTooltip.classList.add('show');
        });

        col.addEventListener('mouseleave', () => {
            hideGlobalTooltip();
        });

        container.appendChild(col);
    });
}

function initPeriodSelector() {
    const buttons = document.querySelectorAll('.period-btn');
    buttons.forEach(btn => {
        btn.addEventListener('click', () => {
            buttons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            
            const period = btn.getAttribute('data-period');
            updateStatsUI(period);
        });
    });
}

function hideGlobalTooltip() {
    const globalTooltip = document.getElementById('globalTooltip');
    if (globalTooltip) {
        globalTooltip.classList.remove('show');
        globalTooltip.style.display = 'none';
    }
}

function initTabs() {
    const navItems = document.querySelectorAll('.footer-nav .nav-item');
    const dashboard = document.querySelector('.luffy-dashboard');
    
    // Varsayılan olarak home tab sınıfını ekleyelim
    if (dashboard && !dashboard.classList.contains('tab-home')) {
        dashboard.classList.add('tab-home');
    }

    // Sayfa kaydırıldığında tooltip'i kapat
    window.addEventListener('scroll', hideGlobalTooltip, { passive: true });
    
    navItems.forEach(item => {
        item.addEventListener('click', (e) => {
            e.preventDefault();
            
            // Sekme değiştirildiğinde açık kalan tooltip'leri mutlaka kapat
            hideGlobalTooltip();
            
            const tab = item.getAttribute('data-tab');
            if (!tab) return;
            
            // Navigasyon butonlarının aktifliğini güncelle
            navItems.forEach(nav => nav.classList.remove('active'));
            item.classList.add('active');
            
            // Dashboard üzerindeki tab sınıflarını temizle ve yenisini ekle
            if (dashboard) {
                dashboard.className = 'luffy-dashboard';
                dashboard.classList.add(`tab-${tab}`);
            }
        });
    });
}

let globalDailyLogData = [];

function renderDailyLog(dailyLog) {
    globalDailyLogData = dailyLog;
    const container = document.getElementById('dailyLogList');
    if (!container) return;

    // Özet Hesaplamaları
    let totalActiveDays = 0;
    let totalWorkMinutes = 0;
    let maxWorkDay = null;
    let maxWorkMinutes = 0;

    dailyLog.forEach(day => {
        if (day.contributions > 0 || day.work_minutes > 0) {
            totalActiveDays++;
        }
        totalWorkMinutes += day.work_minutes;

        if (day.work_minutes > maxWorkMinutes) {
            maxWorkMinutes = day.work_minutes;
            maxWorkDay = day;
        }
    });

    // Özet DOM Elemanlarını Doldur
    const activeDaysElem = document.getElementById('dailyLogTotalActiveDays');
    if (activeDaysElem) activeDaysElem.textContent = `${totalActiveDays} Gün`;

    const totalTimeElem = document.getElementById('dailyLogTotalWorkTime');
    if (totalTimeElem) {
        const hours = Math.floor(totalWorkMinutes / 60);
        const mins = totalWorkMinutes % 60;
        totalTimeElem.textContent = hours > 0 ? `${hours} Saat ${mins}dk` : `${mins}dk`;
    }

    const maxDayElem = document.getElementById('dailyLogMaxWorkDay');
    if (maxDayElem) {
        if (maxWorkDay) {
            maxDayElem.textContent = `${maxWorkDay.formatted_date.split(',')[0]} (${maxWorkDay.work_time_str})`;
        } else {
            maxDayElem.textContent = '-';
        }
    }

    // Liste Render Etme
    displayDailyLogItems(dailyLog, maxWorkMinutes);

    // Arama Event Listener
    const searchInput = document.getElementById('dailyLogSearchInput');
    if (searchInput && !searchInput.dataset.initialized) {
        searchInput.dataset.initialized = 'true';
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            const filtered = globalDailyLogData.filter(day => 
                day.formatted_date.toLowerCase().includes(query) || 
                day.date.includes(query) ||
                day.work_time_str.toLowerCase().includes(query)
            );
            displayDailyLogItems(filtered, maxWorkMinutes);
        });
    }
}

function displayDailyLogItems(items, maxWorkMinutes) {
    const container = document.getElementById('dailyLogList');
    if (!container) return;

    if (!items || items.length === 0) {
        container.innerHTML = `
            <div style="text-align: center; padding: 30px; color: var(--text-muted);">
                <i class="fa-solid fa-folder-open" style="font-size: 2rem; margin-bottom: 10px; color: var(--danger-red);"></i>
                <p>Aradığınız kriterlere uygun günlük çalışma kaydı bulunamadı.</p>
            </div>
        `;
        return;
    }

    const maxCap = maxWorkMinutes > 0 ? maxWorkMinutes : 300;

    let html = '';
    items.forEach(day => {
        const progressPercent = Math.min(100, Math.round((day.work_minutes / maxCap) * 100));
        const isTodayClass = day.is_today ? 'is-today' : '';
        
        html += `
            <div class="daily-item-card ${isTodayClass}">
                <div class="daily-info-left">
                    <div class="daily-date">${day.formatted_date}</div>
                    <div class="daily-sub">
                        <span class="daily-badge"><i class="fa-solid fa-code-commit"></i> ${day.contributions} Katkı</span>
                        ${day.work_minutes > 0 ? `<span style="opacity: 0.8;"><i class="fa-solid fa-stopwatch"></i> ${day.work_minutes} Dk</span>` : '<span style="opacity: 0.5;">İşlem Yok</span>'}
                    </div>
                </div>
                <div class="daily-info-right">
                    <div class="daily-time-str">${day.work_time_str}</div>
                    <div class="daily-progress-bg">
                        <div class="daily-progress-bar" style="width: ${progressPercent}%;"></div>
                    </div>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

function initCalcModeSelector() {
    const buttons = document.querySelectorAll('.calc-mode-btn');
    if (!buttons.length) return;

    buttons.forEach(btn => {
        if (btn.getAttribute('data-mode') === currentCalcMode) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            buttons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            currentCalcMode = btn.getAttribute('data-mode');
            localStorage.setItem('git_dash_calc_mode', currentCalcMode);

            const activeBtn = document.querySelector('.period-btn.active');
            const currentPeriod = activeBtn ? activeBtn.getAttribute('data-period') : 'today';
            updateStatsUI(currentPeriod);
        });
    });
}

function renderProjectAnalyticsTable(projects) {
    const tableBody = document.getElementById('projectAnalyticsTableBody');
    if (!tableBody) return;

    if (!projects || projects.length === 0) {
        tableBody.innerHTML = `<tr><td colspan="7" style="text-align:center; padding: 20px; color: var(--text-muted);">Proje analitik verisi bulunamadı.</td></tr>`;
        return;
    }

    let totalTokensSum = 0;
    let totalHumanMinsSum = 0;
    let totalPushMinsSum = 0;
    let totalCodeLinesSum = 0;

    let rowsHtml = '';
    projects.forEach(p => {
        totalTokensSum += (p.estimated_tokens || 0);
        totalHumanMinsSum += (p.lines_minutes || 0);
        totalPushMinsSum += (p.session_minutes || 0);
        totalCodeLinesSum += (p.code_lines || 0);

        const formattedTokens = p.formatted_tokens || (p.estimated_tokens ? p.estimated_tokens.toLocaleString('tr-TR') : '0');
        const formattedCodeLines = p.formatted_code_lines || (p.code_lines ? p.code_lines.toLocaleString('tr-TR') : '0');
        const changeStr = `+${p.additions.toLocaleString('tr-TR')} / -${p.deletions.toLocaleString('tr-TR')}`;

        const langColor = p.primary_language_color || '#ffd700';
        const langBadge = p.primary_language ? `<span class="lang-tag" style="border-color: ${langColor}77; color: ${langColor}; background: ${langColor}18;">${p.primary_language}</span>` : '';

        rowsHtml += `
            <tr>
                <td>
                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                        <strong>${p.short_name}</strong>
                        ${langBadge}
                    </div>
                    <small style="color: var(--text-muted); font-size:0.75rem;">${p.name}</small>
                </td>
                <td style="text-align:center;"><span class="badge" style="background:rgba(255,255,255,0.05); padding:2px 6px; border-radius:4px;">${p.commits} Commit</span></td>
                <td style="text-align:center;">
                    <button type="button" class="loc-badge-btn" onclick="setSimulatorLines(${p.code_lines || 0}, '${p.short_name}')" title="Bu projenin satır sayısını canlı simülatöre aktar">
                        <i class="fa-solid fa-code"></i> ${formattedCodeLines} Satır
                        <i class="fa-solid fa-calculator" style="font-size: 0.7rem; opacity: 0.6; margin-left: 3px;"></i>
                    </button>
                </td>
                <td style="text-align:center; color: var(--text-muted); font-size:0.85rem;">${changeStr}</td>
                <td style="text-align:center; font-weight:bold; color: #00aa00;">${p.work_time_lines}</td>
                <td style="text-align:center; font-weight:bold; color: var(--danger-red);">${p.work_time_session}</td>
                <td style="text-align:right;"><span class="token-badge">${formattedTokens} Token</span></td>
            </tr>
        `;
    });

    tableBody.innerHTML = rowsHtml;

    // Özet Kartlarını Güncelle
    if (document.getElementById('statTotalTokens')) {
        document.getElementById('statTotalTokens').textContent = totalTokensSum > 1000000 
            ? (totalTokensSum / 1000000).toFixed(2) + 'M' 
            : totalTokensSum.toLocaleString('tr-TR');
    }
    if (document.getElementById('statTotalCodeLines')) {
        document.getElementById('statTotalCodeLines').textContent = totalCodeLinesSum > 1000000
            ? (totalCodeLinesSum / 1000000).toFixed(2) + 'M'
            : totalCodeLinesSum.toLocaleString('tr-TR');
    }
    if (document.getElementById('statTotalHumanTime')) {
        const h = Math.floor(totalHumanMinsSum / 60);
        const m = totalHumanMinsSum % 60;
        document.getElementById('statTotalHumanTime').textContent = h >= 1 ? `${h}s ${m}dk` : `${m}dk`;
    }
    if (document.getElementById('statTotalPushTime')) {
        const h = Math.floor(totalPushMinsSum / 60);
        const m = totalPushMinsSum % 60;
        document.getElementById('statTotalPushTime').textContent = h >= 1 ? `${h}s ${m}dk` : `${m}dk`;
    }
}

function initTokenSimulator() {
    const lineInput = document.getElementById('simLineInput');
    const ratioSelect = document.getElementById('simRatioSelect');
    if (!lineInput || !ratioSelect) return;

    const calculateSim = () => {
        const lines = parseInt(lineInput.value) || 0;
        const ratio = parseInt(ratioSelect.value) || 12;

        const tokens = lines * ratio;
        const humanMins = Math.round((lines / 40) * 60);
        const h = Math.floor(humanMins / 60);
        const m = humanMins % 60;
        const humanTimeStr = h >= 1 ? `${h} Saat ${m} Dk` : `${m} Dk`;
        const prompts = Math.ceil(lines / 35);

        if (document.getElementById('simResultTokens')) {
            document.getElementById('simResultTokens').textContent = tokens.toLocaleString('tr-TR') + ' Token';
        }
        if (document.getElementById('simResultHumanTime')) {
            document.getElementById('simResultHumanTime').textContent = humanTimeStr;
        }
        if (document.getElementById('simResultPrompts')) {
            document.getElementById('simResultPrompts').textContent = `~${prompts} İstek`;
        }
    };

    lineInput.addEventListener('input', calculateSim);
    ratioSelect.addEventListener('change', calculateSim);
    calculateSim();
}

window.setSimulatorLines = function(lines, projectName) {
    const lineInput = document.getElementById('simLineInput');
    if (!lineInput) return;
    lineInput.value = lines;
    lineInput.dispatchEvent(new Event('input'));

    const simBox = document.querySelector('.token-simulator-box');
    if (simBox) {
        simBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        simBox.classList.add('sim-pulse-highlight');
        setTimeout(() => simBox.classList.remove('sim-pulse-highlight'), 1600);
    }
};

window.triggerClearCache = async function(btn) {
    const allCacheBtns = document.querySelectorAll('.btn-clear-cache, .header-cache-btn');
    
    allCacheBtns.forEach(b => {
        b.disabled = true;
        b.innerHTML = '<i class="fa-solid fa-arrows-rotate fa-spin"></i> TEMİZLENİYOR...';
    });

    try {
        // 1. Önbellek dosyalarını temizle
        await fetch('api/clear_cache.php');
        
        // 2. Verileri doğrudan GitHub API üzerinden taze olarak yeniden çek
        await fetchActivity(true);

        allCacheBtns.forEach(b => {
            b.innerHTML = '<i class="fa-solid fa-check"></i> GÜNCELLENDİ!';
            b.style.borderColor = '#00aa00';
            b.style.color = '#00aa00';
        });

        setTimeout(() => {
            allCacheBtns.forEach(b => {
                b.disabled = false;
                b.style.borderColor = '';
                b.style.color = '';
            });
            const headerBtn = document.querySelector('.header-cache-btn');
            if (headerBtn) headerBtn.innerHTML = '<i class="fa-solid fa-arrows-rotate"></i> ÖNBELLEĞİ TEMİZLE';
            const tokenBtn = document.querySelector('.btn-clear-cache:not(.full-btn)');
            if (tokenBtn) tokenBtn.innerHTML = '<i class="fa-solid fa-arrows-rotate"></i> ÖNBELLEĞİ TEMİZLE';
            const fullBtn = document.querySelector('.btn-clear-cache.full-btn');
            if (fullBtn) fullBtn.innerHTML = '<i class="fa-solid fa-arrows-rotate"></i> TÜM ÖNBELLEĞİ TEMİZLE VE VERİLERİ YENİDEN HESAPLA';
        }, 2000);

    } catch (e) {
        console.error('Önbellek temizleme hatası:', e);
        allCacheBtns.forEach(b => {
            b.disabled = false;
            b.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> HATA!';
            b.style.borderColor = 'var(--danger-red)';
            b.style.color = 'var(--danger-red)';
        });
        setTimeout(() => {
            allCacheBtns.forEach(b => {
                b.style.borderColor = '';
                b.style.color = '';
            });
            const headerBtn = document.querySelector('.header-cache-btn');
            if (headerBtn) headerBtn.innerHTML = '<i class="fa-solid fa-arrows-rotate"></i> ÖNBELLEĞİ TEMİZLE';
            const tokenBtn = document.querySelector('.btn-clear-cache:not(.full-btn)');
            if (tokenBtn) tokenBtn.innerHTML = '<i class="fa-solid fa-arrows-rotate"></i> ÖNBELLEĞİ TEMİZLE';
            const fullBtn = document.querySelector('.btn-clear-cache.full-btn');
            if (fullBtn) fullBtn.innerHTML = '<i class="fa-solid fa-arrows-rotate"></i> TÜM ÖNBELLEĞİ TEMİZLE VE VERİLERİ YENİDEN HESAPLA';
        }, 2500);
    }
};


