document.addEventListener('DOMContentLoaded', () => {
    updateDateTime();
    setInterval(updateDateTime, 1000); // Saat ve tarih canlı aksın
    
    fetchActivity();
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
                projectsList.innerHTML = '';
                if (result.stats.active_projects && result.stats.active_projects.length > 0) {
                    result.stats.active_projects.slice(0, 5).forEach(project => {
                        projectsList.insertAdjacentHTML('beforeend', `<li>> ${project}</li>`);
                    });
                } else {
                    projectsList.innerHTML = '<li>> System Idle</li>';
                }
            }

            // Timeline Ticker
            if (result.data.length === 0) {
                 timelineContainer.innerHTML = `<div class="ticker-item"><small>NO RECENT ACTIVITY DETECTED IN THE DATABASE.</small></div>`;
                return;
            }

            // Create a long string of activity for the ticker
            let tickerHTML = '';
            result.data.forEach((activity, index) => {
                const date = new Date(activity.date);
                const timeStr = date.toLocaleTimeString('en-US', { hour12: false });
                let actionName = activity.type.replace('Event', '').toUpperCase();

                tickerHTML += `
                    <div class="ticker-item">
                        [${timeStr}] <span>${actionName}</span> ON <small>${activity.repo}</small> // ${activity.details}
                    </div>
                `;
            });
            
            // Duplicate the content to make infinite scroll smooth
            timelineContainer.innerHTML = tickerHTML + tickerHTML;
        }
    } catch (error) {
        timelineContainer.innerHTML = `<div class="ticker-item"><span style="color:red">CONNECTION SEVERED.</span></div>`;
    }
}
