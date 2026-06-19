document.addEventListener('DOMContentLoaded', () => {
    fetchActivity();
});

async function fetchActivity() {
    try {
        const response = await fetch('api/fetch_activity.php');
        const result = await response.json();

        if (result.error) {
            document.getElementById('activityList').innerHTML = `<li class="text-red">Error: ${result.error}</li>`;
            return;
        }

        if (result.success && result.stats) {
            
            // 1. Populate Metric Cards with animation
            animateValue('valCommits', result.stats.commits || 0);
            animateValue('valAdditions', result.stats.additions || 0);
            animateValue('valDeletions', result.stats.deletions || 0);
            
            if (result.stats.work_time) {
                document.getElementById('valWorkTime').textContent = result.stats.work_time.replace(' Saat', 'h').replace(' Dakika', 'm');
            }
            
            document.getElementById('valRepos').textContent = result.stats.repos || 0;

            if (result.stats.weekly_commits !== undefined) {
                document.getElementById('valWeekly').textContent = result.stats.weekly_commits;
                document.getElementById('valMonthly').textContent = result.stats.monthly_commits;
            }

            // Fade in cards sequentially
            const cards = document.querySelectorAll('.metric-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // 2. Populate Active Projects
            const projectsList = document.getElementById('activeProjectsList');
            projectsList.innerHTML = '';
            if (result.stats.active_projects && result.stats.active_projects.length > 0) {
                result.stats.active_projects.forEach(project => {
                    projectsList.innerHTML += `<li class="project-item">${project}</li>`;
                });
            } else {
                projectsList.innerHTML = '<li class="text-muted">No active projects</li>';
            }

            // 3. Render Timeline
            const logList = document.getElementById('activityList');
            logList.innerHTML = '';
            
            if (result.config && result.config.show_system_logs === false) {
                logList.innerHTML = `<li class="text-muted">Activity timeline is hidden due to privacy settings.</li>`;
                return;
            }

            if (result.data.length === 0) {
                 logList.innerHTML = `<li class="text-muted">No recent activity to display.</li>`;
                return;
            }

            // Staggered fade-in for timeline items
            result.data.forEach((activity, index) => {
                const date = new Date(activity.date);
                const timeStr = date.toLocaleTimeString('en-US', { hour: '2-digit', minute:'2-digit' });
                
                let iconClass = '';
                let iconHtml = '';
                
                if(activity.type === 'PushEvent') {
                    iconClass = 'create'; // Green
                    iconHtml = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--accent-green)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
                } else if(activity.type === 'DeleteEvent') {
                    iconClass = 'delete'; // Red
                    iconHtml = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--accent-red)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
                } else {
                    // Blue (default)
                    iconHtml = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--accent-blue)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>';
                }

                const li = document.createElement('li');
                li.className = 'timeline-item';
                li.innerHTML = `
                    <div class="timeline-icon ${iconClass}">${iconHtml}</div>
                    <span class="timeline-time">${timeStr}</span>
                    <div class="timeline-content">${activity.details}</div>
                    <div class="timeline-repo">${activity.repo}</div>
                `;
                
                logList.appendChild(li);
                
                // Trigger CSS transition after append
                setTimeout(() => {
                    li.classList.add('show');
                }, 100 + (index * 80));
            });
        }
    } catch (error) {
        document.getElementById('activityList').innerHTML = `<li class="text-red">System Error: ${error.message}</li>`;
    }
}

function animateValue(id, end) {
    const obj = document.getElementById(id);
    if(!obj) return;
    
    let startTimestamp = null;
    const duration = 1000;
    
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        
        const easeProgress = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
        
        obj.innerHTML = Math.floor(easeProgress * end);
        if (progress < 1) {
            window.requestAnimationFrame(step);
        }
    };
    window.requestAnimationFrame(step);
}
