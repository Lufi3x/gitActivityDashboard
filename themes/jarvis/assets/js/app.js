document.addEventListener('DOMContentLoaded', () => {
    updateDateTime();
    setInterval(updateDateTime, 1000); // Saat ve tarih canlı aksın
    
    fetchActivity();
    initDraggableReactor();
    initFullscreen();
    initNavMenu();
});

function updateDateTime() {
    const now = new Date();
    
    // Day Number
    const day = now.getDate().toString().padStart(2, '0');
    document.getElementById('dayLarge').textContent = day;
    
    // Month Name
    const months = ["JANUARY", "FEBRUARY", "MARCH", "APRIL", "MAY", "JUNE", "JULY", "AUGUST", "SEPTEMBER", "OCTOBER", "NOVEMBER", "DECEMBER"];
    document.getElementById('monthText').textContent = months[now.getMonth()];
    
    // Day Name
    const days = ["SUNDAY", "MONDAY", "TUESDAY", "WEDNESDAY", "THURSDAY", "FRIDAY", "SATURDAY"];
    document.getElementById('dayText').textContent = days[now.getDay()];
}

function applyReactorPosition(isFullscreen) {
    const reactor = document.querySelector('.chest-arc-reactor');
    if (!reactor) return;
    
    const key = isFullscreen ? 'jarvis_reactor_pos_fullscreen' : 'jarvis_reactor_pos_normal';
    const savedPos = localStorage.getItem(key);
    
    if (savedPos) {
        const pos = JSON.parse(savedPos);
        reactor.style.left = pos.left;
        reactor.style.top = pos.top;
        reactor.style.bottom = 'auto'; // Bottom'u iptal et ki top çalışsın
        reactor.style.transform = 'translate(-50%, -50%)';
    }
}

function initDraggableReactor() {
    const reactor = document.querySelector('.chest-arc-reactor');
    if (!reactor) return;

    // Yüklendiğinde ekran moduna göre pozisyonu çek
    applyReactorPosition(!!document.fullscreenElement);

    let isDragging = false;

    reactor.addEventListener('mousedown', (e) => {
        isDragging = true;
        reactor.style.cursor = 'grabbing';
        reactor.classList.add('is-dragging');
    });

    document.addEventListener('mousemove', (e) => {
        if (!isDragging) return;
        
        // Ekranın neresinde olduğunu yüzde (%) olarak hesapla (Responsive için)
        const xPercent = (e.clientX / window.innerWidth) * 100;
        const yPercent = (e.clientY / window.innerHeight) * 100;

        reactor.style.bottom = 'auto'; // Eski bottom değerini ez
        reactor.style.left = xPercent + '%';
        reactor.style.top = yPercent + '%';
        reactor.style.transform = 'translate(-50%, -50%)'; // Merkeze hizala
    });

    document.addEventListener('mouseup', () => {
        if (isDragging) {
            isDragging = false;
            reactor.style.cursor = 'grab';
            reactor.classList.remove('is-dragging');

            // Hangi ekranda olduğumuzu bul ve ona göre kaydet
            const isFs = !!document.fullscreenElement;
            const key = isFs ? 'jarvis_reactor_pos_fullscreen' : 'jarvis_reactor_pos_normal';

            localStorage.setItem(key, JSON.stringify({
                left: reactor.style.left,
                top: reactor.style.top
            }));
            
            showTooltip(reactor, isFs ? "Fullscreen Calibrated." : "Normal Calibrated.");
        }
    });

    // Tam ekrana girip çıkıldığında otomatik olarak doğru konumu yükle
    document.addEventListener('fullscreenchange', () => {
        applyReactorPosition(!!document.fullscreenElement);
    });
}

function showTooltip(element, text) {
    let tooltip = document.getElementById('calibrationTooltip');
    if (!tooltip) {
        tooltip = document.createElement('div');
        tooltip.id = 'calibrationTooltip';
        tooltip.className = 'global-tooltip show';
        document.body.appendChild(tooltip);
    }
    tooltip.textContent = text;
    
    const rect = element.getBoundingClientRect();
    tooltip.style.left = (rect.left + rect.width / 2) + 'px';
    tooltip.style.top = (rect.top - 40) + 'px';
    tooltip.style.visibility = 'visible';
    tooltip.style.opacity = '1';
    
    setTimeout(() => {
        tooltip.style.opacity = '0';
        tooltip.style.visibility = 'hidden';
    }, 2000);
}

function initFullscreen() {
    const fsBtn = document.getElementById('fullscreenBtn');
    if (!fsBtn) return;
    
    fsBtn.addEventListener('click', () => {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(err => {
                console.log(`Error attempting to enable fullscreen: ${err.message}`);
            });
            fsBtn.innerHTML = '<i class="fa-solid fa-compress"></i>';
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
                fsBtn.innerHTML = '<i class="fa-solid fa-expand"></i>';
            }
        }
    });
}

function initNavMenu() {
    const navButtons = document.querySelectorAll('.skew-btn');
    const centerTitle = document.querySelector('.center-top-text h2');
    const centerDesc = document.querySelector('.center-top-text p');
    
    const panelsWrapper = document.getElementById('hologramPanelsWrapper');
    const allPanels = document.querySelectorAll('.holo-panel');
    const arcReactor = document.querySelector('.chest-arc-reactor');

    navButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            
            // Seçili olanı değiştir
            navButtons.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            
            const menuName = btn.querySelector('.skew-text').textContent;
            
            // Hologram Tab Mantığı
            if (menuName === 'MAIN SYSTEM') {
                panelsWrapper.style.display = 'none';
                if(arcReactor) arcReactor.style.opacity = '1';
                if(centerTitle) centerTitle.parentElement.style.display = 'block';
            } else {
                panelsWrapper.style.display = 'flex';
                if(arcReactor) arcReactor.style.opacity = '0.1'; // Panelin arkasında sönük kalsın
                if(centerTitle) centerTitle.parentElement.style.display = 'none'; // Üst yazıyı gizle ki çakışmasın
                
                allPanels.forEach(p => p.classList.remove('active'));
                const targetPanel = document.getElementById('panel-' + menuName);
                if (targetPanel) {
                    targetPanel.classList.add('active');
                }
            }
            
            showTooltip(btn, "Accessing " + menuName + "...");
        });
    });
}

async function fetchActivity() {
    const timelineContainer = document.getElementById('activityTimeline');

    try {
        const response = await fetch('api/fetch_activity.php');
        const result = await response.json();

        timelineContainer.innerHTML = ''; // Temizle

        if (result.error) {
            timelineContainer.innerHTML = `<div class="ticker-item"><span style="color:red">SYSTEM ERROR:</span> ${result.error}</div>`;
            return;
        }

        if (result.success && result.data) {
            
            // Stats Panel
            if (result.stats) {
                // Long term stats (Progress Bars)
                if (result.stats.weekly_commits !== undefined) {
                    document.getElementById('longTermStatsSection').style.display = 'flex';
                    
                    document.getElementById('statWeekly').textContent = result.stats.weekly_commits;
                    document.getElementById('statMonthly').textContent = result.stats.monthly_commits;
                    document.getElementById('statYearly').textContent = result.stats.yearly_commits;

                    // Progress Bar width calculation (example targets: 50 weekly, 200 monthly, 2000 yearly)
                    let wPct = Math.min((result.stats.weekly_commits / 50) * 100, 100);
                    let mPct = Math.min((result.stats.monthly_commits / 200) * 100, 100);
                    let yPct = Math.min((result.stats.yearly_commits / 2000) * 100, 100);

                    // Delay for animation
                    setTimeout(() => {
                        document.getElementById('barWeekly').style.width = wPct + '%';
                        document.getElementById('barMonthly').style.width = mPct + '%';
                        document.getElementById('barYearly').style.width = yPct + '%';
                    }, 500);
                }
                
                // Calendar Map
                if (result.stats.calendar && result.stats.calendar.length > 0) {
                    document.getElementById('calendarSection').style.display = 'flex';
                    const graphContainer = document.getElementById('calendarGraph');
                    graphContainer.innerHTML = '';
                    
                    // We only take the last 5 weeks for the tiny dial space
                    const recentWeeks = result.stats.calendar.slice(-5);
                    
                    recentWeeks.forEach(week => {
                        week.contributionDays.forEach(day => {
                            const dayDiv = document.createElement('div');
                            dayDiv.className = 'calendar-day';
                            
                            let level = 0;
                            if (day.contributionCount > 0 && day.contributionCount <= 3) level = 1;
                            else if (day.contributionCount > 3 && day.contributionCount <= 9) level = 2;
                            else if (day.contributionCount > 9 && day.contributionCount <= 19) level = 3;
                            else if (day.contributionCount >= 20) level = 4;
                            
                            dayDiv.setAttribute('data-level', level);
                            graphContainer.appendChild(dayDiv);
                        });
                    });
                }

                // Daily Stats
                document.getElementById('statsSection').style.display = 'block';
                document.getElementById('statCommits').textContent = result.stats.commits;
                if (document.getElementById('statChangedFiles')) document.getElementById('statChangedFiles').textContent = result.stats.changed_files || 0;
                document.getElementById('statAdditions').textContent = '+' + result.stats.additions.toLocaleString('en-US');
                document.getElementById('statDeletions').textContent = '-' + result.stats.deletions.toLocaleString('en-US');
                document.getElementById('statRepos').textContent = result.stats.repos;
                if (result.stats.work_time) {
                    document.getElementById('statWorkTime').textContent = result.stats.work_time.replace('dk', 'm');
                }
                
                // Active Projects
                const projectsList = document.getElementById('activeProjectsList');
                const bigReposList = document.getElementById('bigReposList'); // YENİ
                projectsList.innerHTML = '';
                bigReposList.innerHTML = ''; // YENİ
                if (result.stats.active_projects && result.stats.active_projects.length > 0) {
                    result.stats.active_projects.slice(0, 5).forEach(project => {
                        projectsList.insertAdjacentHTML('beforeend', `<li>> ${project}</li>`);
                    });
                    // Hologram Panel için
                    result.stats.active_projects.forEach(project => {
                        bigReposList.insertAdjacentHTML('beforeend', `<div class="big-repo-item"><strong>${project}</strong> <span>Active</span></div>`);
                    });
                } else {
                    projectsList.innerHTML = '<li>> System Idle</li>';
                    bigReposList.innerHTML = '<div>No active modules detected.</div>';
                }
            }

            // Timeline Ticker
            if (result.data.length === 0) {
                 timelineContainer.innerHTML = `<div class="ticker-item"><small>NO RECENT ACTIVITY DETECTED IN THE DATABASE.</small></div>`;
                 document.getElementById('bigActivityList').innerHTML = '<div>No timeline events found.</div>';
                return;
            }

            // Create a long string of activity for the ticker
            let tickerHTML = '';
            let bigActivityHTML = ''; // Hologram panel için
            result.data.forEach((activity, index) => {
                const date = new Date(activity.date);
                const timeStr = date.toLocaleTimeString('en-US', { hour12: false });
                let actionName = activity.type.replace('Event', '').toUpperCase();

                tickerHTML += `
                    <div class="ticker-item">
                        [${timeStr}] <span>${actionName}</span> ON <small>${activity.repo}</small> // ${activity.details}
                    </div>
                `;
                
                // Hologram panel için daha şık versiyon
                const fullDateStr = date.toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'});
                bigActivityHTML += `<div class="big-activity-item"><div class="time">[${fullDateStr}]</div><div class="details"><strong>${actionName}</strong> on ${activity.repo}<br>${activity.details}</div></div>`;
            });
            
            // Duplicate the content to make infinite scroll smooth
            timelineContainer.innerHTML = tickerHTML + tickerHTML;
            document.getElementById('bigActivityList').innerHTML = bigActivityHTML;
            
            // Büyük istatistik panelini güncelle
            if (result.stats) {
                document.getElementById('bigStatCommits').textContent = result.stats.commits;
                // Minik takvimi kopyala
                const smallGraph = document.getElementById('calendarGraph');
                if (smallGraph) {
                    document.getElementById('bigCalendarGraph').innerHTML = smallGraph.innerHTML;
                }
            }
            
        }
    } catch (error) {
        timelineContainer.innerHTML = `<div class="ticker-item"><span style="color:red">CONNECTION SEVERED.</span></div>`;
    }
}
