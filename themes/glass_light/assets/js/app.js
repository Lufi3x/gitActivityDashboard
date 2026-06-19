document.addEventListener('DOMContentLoaded', () => {
    fetchActivity();
});

async function fetchActivity() {
    try {
        const response = await fetch('api/fetch_activity.php');
        const result = await response.json();

        if (result.error) {
            document.getElementById('activityList').innerHTML = `<li>Error: ${result.error}</li>`;
            return;
        }

        if (result.success && result.stats) {
            
            // 1. Populate Stats
            animateValue('valCommits', result.stats.commits || 0);
            animateValue('valAdditions', result.stats.additions || 0);
            
            if (result.stats.work_time) {
                document.getElementById('valWorkTime').textContent = result.stats.work_time.replace(' Saat', 'h').replace(' Dakika', 'm');
            }

            if (result.stats.weekly_commits !== undefined) {
                document.getElementById('valWeekly').textContent = result.stats.weekly_commits;
                document.getElementById('valMonthly').textContent = result.stats.monthly_commits;
            }

            // 2. Populate Active Projects
            const projectsList = document.getElementById('activeProjectsList');
            projectsList.innerHTML = '';
            if (result.stats.active_projects && result.stats.active_projects.length > 0) {
                result.stats.active_projects.forEach(project => {
                    projectsList.innerHTML += `<li>${project}</li>`;
                });
            } else {
                projectsList.innerHTML = '<li style="opacity: 0.6">No active projects</li>';
            }

            // 3. Render Timeline
            const logList = document.getElementById('activityList');
            logList.innerHTML = '';
            
            if (result.config && result.config.show_system_logs === false) {
                logList.innerHTML = `<li style="opacity: 0.6">Logs hidden by privacy settings.</li>`;
            } else if (result.data.length === 0) {
                 logList.innerHTML = `<li style="opacity: 0.6">No recent activity.</li>`;
            } else {
                result.data.forEach((activity) => {
                    const date = new Date(activity.date);
                    const timeStr = date.toLocaleTimeString('en-US', { hour: '2-digit', minute:'2-digit' });
                    
                    const li = document.createElement('li');
                    li.innerHTML = `
                        <span class="time-badge">${timeStr}</span>
                        <div class="log-detail">${activity.details}</div>
                        <span class="log-repo">${activity.repo}</span>
                    `;
                    logList.appendChild(li);
                });
            }

            // Trigger reveal animations for glass cards
            setTimeout(() => {
                const cards = document.querySelectorAll('.glass-card');
                cards.forEach((card, index) => {
                    setTimeout(() => {
                        card.classList.add('reveal');
                    }, index * 150); // Staggered delay
                });
            }, 100);

        }
    } catch (error) {
        document.getElementById('activityList').innerHTML = `<li>System Error: ${error.message}</li>`;
    }
}

function animateValue(id, end) {
    const obj = document.getElementById(id);
    if(!obj) return;
    
    let startTimestamp = null;
    const duration = 1500;
    
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        
        // easeOutQuart
        const easeProgress = 1 - Math.pow(1 - progress, 4);
        
        obj.innerHTML = Math.floor(easeProgress * end);
        if (progress < 1) {
            window.requestAnimationFrame(step);
        }
    };
    window.requestAnimationFrame(step);
}
