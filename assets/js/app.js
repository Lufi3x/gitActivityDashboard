document.addEventListener('DOMContentLoaded', () => {
    fetchActivity();
});

async function fetchActivity() {
    const timelineContainer = document.getElementById('activityTimeline');
    const userNameEl = document.getElementById('userName');
    const userBioEl = document.getElementById('userBio');
    const avatarContainer = document.querySelector('.avatar-container');

    try {
        const response = await fetch('api/fetch_activity.php');
        const result = await response.json();

        timelineContainer.innerHTML = ''; // Temizle

        if (result.error) {
            timelineContainer.innerHTML = `
                <div class="glass-panel activity-content" style="text-align:center; padding: 30px; color: #ff7b72;">
                    <p>⚠️ Hata: ${result.error}</p>
                    <p style="font-size: 0.8rem; margin-top:10px; color: var(--text-secondary);">
                        api/config.php dosyasındaki GITHUB_TOKEN değerini girdiniz mi?
                    </p>
                </div>`;
            userNameEl.textContent = 'Kurulum Bekleniyor';
            userBioEl.textContent = 'Lütfen ayarları yapın.';
            return;
        }

        if (result.success && result.data) {
            
            // İstatistikleri ekrana bas
            if (result.stats) {
                // Uzun Dönem İstatistikleri
                if (result.stats.weekly_commits !== undefined) {
                    document.getElementById('longTermStatsSection').style.display = 'flex';
                    document.getElementById('statWeekly').textContent = result.stats.weekly_commits;
                    document.getElementById('statMonthly').textContent = result.stats.monthly_commits;
                    document.getElementById('statYearly').textContent = result.stats.yearly_commits;
                }
                
                // Katkı Takvimi (Contribution Graph) Çizimi
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
                            
                            // Renk Seviyesi (Level 0-4)
                            let level = 0;
                            if (day.contributionCount > 0 && day.contributionCount <= 3) level = 1;
                            else if (day.contributionCount > 3 && day.contributionCount <= 9) level = 2;
                            else if (day.contributionCount > 9 && day.contributionCount <= 19) level = 3;
                            else if (day.contributionCount >= 20) level = 4;
                            
                            dayDiv.setAttribute('data-level', level);
                            
                            // Global Tooltip İçin Olay Dinleyicileri
                            const dateObj = new Date(day.date);
                            const dateStr = dateObj.toLocaleDateString('tr-TR', { day: 'numeric', month: 'long', year: 'numeric' });
                            
                            dayDiv.addEventListener('mouseenter', (e) => {
                                const globalTooltip = document.getElementById('globalTooltip');
                                globalTooltip.innerHTML = `<strong>${day.contributionCount} katkı</strong><br>${dateStr}`;
                                
                                const rect = dayDiv.getBoundingClientRect();
                                // Başlangıçta görünmeden önce içeriği ekleyip yüksekliğini ölçmemiz lazım
                                globalTooltip.style.display = 'block';
                                
                                globalTooltip.style.left = rect.left + (rect.width / 2) + window.scrollX + 'px';
                                globalTooltip.style.top = rect.top + window.scrollY - globalTooltip.offsetHeight - 8 + 'px';
                                globalTooltip.classList.add('show');
                            });
                            
                            dayDiv.addEventListener('mouseleave', () => {
                                document.getElementById('globalTooltip').classList.remove('show');
                            });
                            
                            weekDiv.appendChild(dayDiv);
                        });
                        
                        graphContainer.appendChild(weekDiv);
                    });
                    
                    // Takvimi en sağa kaydır (En güncel güne odaklanması için)
                    requestAnimationFrame(() => {
                        const wrapper = graphContainer.parentElement;
                        wrapper.scrollLeft = wrapper.scrollWidth;
                    });
                }

                const statsSec = document.getElementById('statsSection');
                statsSec.style.display = 'flex';
                
                document.getElementById('statCommits').textContent = result.stats.commits;
                document.getElementById('statAdditions').textContent = result.stats.additions.toLocaleString('tr-TR');
                document.getElementById('statDeletions').textContent = result.stats.deletions.toLocaleString('tr-TR');
                document.getElementById('statRepos').textContent = result.stats.repos;
                if (result.stats.work_time) {
                    document.getElementById('statWorkTime').textContent = result.stats.work_time;
                }
                
                const projectsList = document.getElementById('activeProjectsList');
                projectsList.innerHTML = '';
                if (result.stats.active_projects && result.stats.active_projects.length > 0) {
                    result.stats.active_projects.forEach(project => {
                        projectsList.insertAdjacentHTML('beforeend', `<li>${project}</li>`);
                    });
                } else {
                    projectsList.innerHTML = '<li style="color: var(--text-secondary); padding-left:0;">Bugün aktif proje yok</li>';
                }
            }

            // Kullanıcı bilgilerini güncelle (şimdilik statik username gösteriliyor, ileride github user api'den çekilebilir)
            userNameEl.textContent = 'GitHub Geliştiricisi'; 
            userBioEl.textContent = 'Son Aktiviteler';
            
            // Eğer avatar verisini eklemek istersek, fetch_activity'den avatar_url de dönmeliyiz.
            // Şimdilik GitHub'ın standart avatar URLsinden kullanıcının idsine göre çekebiliriz veya statik bırakabiliriz.
            // Gerçek username'i events'ten alalım (ilk eventin aktöründen)
            
            // API'den gelen verileri listele
            if (result.data.length === 0) {
                 timelineContainer.innerHTML = `
                    <div class="glass-panel activity-content" style="text-align:center; padding: 30px;">
                        <p>Yakın zamanda bir etkinlik bulunamadı.</p>
                    </div>`;
                return;
            }

            result.data.forEach((activity, index) => {
                const date = new Date(activity.date);
                const formattedDate = date.toLocaleDateString('tr-TR', {
                    day: 'numeric', month: 'short', hour: '2-digit', minute:'2-digit'
                });

                // Tip ismine göre kısa kod
                let typeCode = 'git';
                if(activity.type === 'PushEvent') typeCode = 'Push';
                else if(activity.type === 'CreateEvent') typeCode = 'New';
                else if(activity.type === 'IssuesEvent') typeCode = 'Issue';
                else if(activity.type === 'PullRequestEvent') typeCode = 'PR';

                const cardHTML = `
                    <div class="activity-card" style="animation-delay: ${index * 0.1}s">
                        <div class="activity-icon">${typeCode}</div>
                        <div class="glass-panel activity-content">
                            <div class="activity-header">
                                <span class="repo-name">${activity.repo}</span>
                                <span class="activity-time">${formattedDate}</span>
                            </div>
                            <div class="activity-desc">
                                ${activity.details}
                            </div>
                        </div>
                    </div>
                `;
                
                timelineContainer.insertAdjacentHTML('beforeend', cardHTML);
            });
            
            // Profil resmini güncellemek için ekstra bir fetch eklenebilir veya config'den okunabilir.
            // Bu örnekte avatar container'ı temizleyip bir emoji veya varsayılan bir ikon ekliyoruz.
            avatarContainer.innerHTML = '<span style="font-size: 2rem;">🧑‍💻</span>';

        }
    } catch (error) {
        timelineContainer.innerHTML = `
            <div class="glass-panel activity-content" style="text-align:center; padding: 30px; color: #ff7b72;">
                <p>⚠️ API ile bağlantı kurulamadı.</p>
                <p style="font-size: 0.8rem; margin-top:10px; color: var(--text-secondary);">${error.message}</p>
            </div>`;
    }
}
