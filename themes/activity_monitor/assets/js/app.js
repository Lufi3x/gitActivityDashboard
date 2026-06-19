document.addEventListener('DOMContentLoaded', () => {
    initTabs();
    fetchActivity();
});

function initTabs() {
    const tabs = document.querySelectorAll('.tab');
    const contents = document.querySelectorAll('.tab-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            // Remove active class from all tabs and contents
            tabs.forEach(t => t.classList.remove('active'));
            contents.forEach(c => c.style.display = 'none');

            // Add active class to clicked tab
            tab.classList.add('active');
            
            // Show corresponding content
            const targetId = tab.getAttribute('data-target');
            const targetContent = document.getElementById(targetId);
            if (targetContent) {
                targetContent.style.display = 'block';
            }
        });
    });
}

async function fetchActivity() {
    const tableBody = document.getElementById('activityTableBody');

    try {
        const response = await fetch('api/fetch_activity.php');
        const result = await response.json();

        tableBody.innerHTML = ''; // Temizle

        if (result.error) {
            tableBody.innerHTML = `
                <tr>
                    <td colspan="4" class="loading-state" style="color: var(--danger-color)">
                        Error connecting to daemon: ${result.error}
                    </td>
                </tr>`;
            return;
        }

        if (result.success && result.data) {
            
            // Gizlilik ve Modül Ayarları (Config)
            if (result.config) {
                if (result.config.show_system_logs === false) {
                    document.getElementById('activityTableContainer').style.display = 'none';
                }
                
                if (result.config.show_active_projects === false) {
                    document.getElementById('activeProjectsSection').style.display = 'none';
                }
            }

            // İstatistikleri ekrana bas
            if (result.stats) {
                // Sidebar Stats
                document.getElementById('statCommits').textContent = result.stats.commits;
                document.getElementById('statAdditions').textContent = result.stats.additions.toLocaleString('tr-TR');
                document.getElementById('statDeletions').textContent = result.stats.deletions.toLocaleString('tr-TR');
                document.getElementById('statRepos').textContent = result.stats.repos;
                if (result.stats.work_time) {
                    document.getElementById('statWorkTime').textContent = result.stats.work_time.replace(' Saat ', 'h ').replace(' Dakika', 'm');
                }
                
                // Footer Stats (Long Term)
                if (result.stats.weekly_commits !== undefined) {
                    document.getElementById('statWeekly').textContent = result.stats.weekly_commits;
                    document.getElementById('statMonthly').textContent = result.stats.monthly_commits;
                    document.getElementById('statYearly').textContent = result.stats.yearly_commits;
                }
                
                // Active Projects
                const projectsList = document.getElementById('activeProjectsList');
                projectsList.innerHTML = '';
                if (result.stats.active_projects && result.stats.active_projects.length > 0) {
                    result.stats.active_projects.forEach(project => {
                        projectsList.insertAdjacentHTML('beforeend', `<li>${project}</li>`);
                    });
                } else {
                    projectsList.innerHTML = '<li style="color: var(--text-secondary); background: transparent;">No active processes</li>';
                }

                // Network Graph (Calendar)
                if (result.stats.calendar && result.stats.calendar.length > 0) {
                    const graphContainer = document.getElementById('calendarGraph');
                    graphContainer.innerHTML = '';
                    
                    result.stats.calendar.forEach(week => {
                        const weekDiv = document.createElement('div');
                        weekDiv.style.display = 'flex';
                        weekDiv.style.flexDirection = 'column';
                        weekDiv.style.gap = '4px';
                        
                        week.contributionDays.forEach(day => {
                            const dayDiv = document.createElement('div');
                            dayDiv.style.width = '10px';
                            dayDiv.style.height = '10px';
                            dayDiv.style.borderRadius = '2px';
                            
                            // Renk Seviyesi (Level 0-4)
                            let color = 'var(--table-header-bg)';
                            if (day.contributionCount > 0 && day.contributionCount <= 3) color = 'rgba(76, 175, 80, 0.4)';
                            else if (day.contributionCount > 3 && day.contributionCount <= 9) color = 'rgba(76, 175, 80, 0.6)';
                            else if (day.contributionCount > 9 && day.contributionCount <= 19) color = 'rgba(76, 175, 80, 0.8)';
                            else if (day.contributionCount >= 20) color = 'var(--success-color)';
                            
                            dayDiv.style.backgroundColor = color;
                            dayDiv.title = `${day.contributionCount} commits on ${day.date}`;
                            
                            weekDiv.appendChild(dayDiv);
                        });
                        
                        graphContainer.appendChild(weekDiv);
                    });
                    
                    // Scroll to end
                    requestAnimationFrame(() => {
                        const wrapper = graphContainer.parentElement;
                        wrapper.scrollLeft = wrapper.scrollWidth;
                    });
                }
            }

            // Tablo verileri
            if (result.data.length === 0) {
                 tableBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="loading-state">No recent activity found in the system.</td>
                    </tr>`;
                return;
            }

            result.data.forEach(activity => {
                const date = new Date(activity.date);
                const formattedDate = date.toLocaleDateString('tr-TR', {
                    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute:'2-digit', second: '2-digit'
                });

                let typeCode = activity.type;
                if(activity.type === 'PushEvent') typeCode = 'git-push';
                else if(activity.type === 'CreateEvent') typeCode = 'git-create';
                else if(activity.type === 'IssuesEvent') typeCode = 'git-issue';
                else if(activity.type === 'PullRequestEvent') typeCode = 'git-pr';

                const rowHTML = `
                    <tr>
                        <td class="cell-time">${formattedDate}</td>
                        <td class="cell-type">${typeCode}</td>
                        <td class="cell-process">${activity.repo}</td>
                        <td class="cell-details">${activity.details}</td>
                    </tr>
                `;
                
                tableBody.insertAdjacentHTML('beforeend', rowHTML);
            });

        }
    } catch (error) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="4" class="loading-state" style="color: var(--danger-color)">
                    System Error: ${error.message}
                </td>
            </tr>`;
    }
}
