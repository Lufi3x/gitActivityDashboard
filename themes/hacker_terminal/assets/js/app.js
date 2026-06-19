document.addEventListener('DOMContentLoaded', () => {
    fetchActivity();
});

async function fetchActivity() {
    try {
        const response = await fetch('api/fetch_activity.php');
        const result = await response.json();

        if (result.error) {
            typeText('typingLine', `FATAL ERROR: ${result.error}`, 50);
            return;
        }

        if (result.success && result.stats) {
            
            // 1. Instantly populate simple stats
            document.getElementById('valCommits').textContent = result.stats.commits || 0;
            document.getElementById('valAdditions').textContent = result.stats.additions || 0;
            document.getElementById('valDeletions').textContent = result.stats.deletions || 0;
            document.getElementById('valRepos').textContent = result.stats.repos || 0;
            
            if (result.stats.work_time) {
                document.getElementById('valWorkTime').textContent = result.stats.work_time;
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
                    projectsList.innerHTML += `<p>> [OK] ${project}</p>`;
                });
            } else {
                projectsList.innerHTML = '<p>> NO_ACTIVE_PROCESSES_FOUND</p>';
            }

            // 3. Render Logs with Typewriter Effect
            const logList = document.getElementById('activityList');
            
            if (result.config && result.config.show_system_logs === false) {
                logList.innerHTML += `<p class="alert-text">> ACCESS DENIED. LOGS ENCRYPTED.</p>`;
                return;
            }

            if (result.data.length === 0) {
                 logList.innerHTML += `<p>> NO TRAFFIC DETECTED.</p>`;
                return;
            }

            // Start typing the logs one by one
            await typeLogsArray(result.data, logList);
            
            // Finish
            const typingLine = document.getElementById('typingLine');
            typingLine.innerHTML = "SYSTEM STANDBY.";
        }
    } catch (error) {
        document.getElementById('typingLine').innerHTML = `<span class="alert-text">KERNEL PANIC: ${error.message}</span>`;
    }
}

/**
 * Typewriter effect for an array of logs
 */
async function typeLogsArray(dataArray, container) {
    const typingLine = document.getElementById('typingLine');
    
    for (let i = 0; i < dataArray.length; i++) {
        const activity = dataArray[i];
        const date = new Date(activity.date);
        const timeStr = date.toLocaleTimeString('tr-TR', { hour: '2-digit', minute:'2-digit' });
        
        let colorClass = '';
        if(activity.type === 'PushEvent') colorClass = 'alert-text';
        
        const fullString = `[${timeStr}] ${activity.repo} -> ${activity.details}`;
        
        // Type the string into the typing line
        await typeText('typingLine', fullString, 20);
        
        // Move it to the history
        const p = document.createElement('p');
        p.innerHTML = `> <span class="${colorClass}">${fullString}</span>`;
        container.appendChild(p);
        
        // Scroll down
        const logBox = document.getElementById('logsContainer');
        logBox.scrollTop = logBox.scrollHeight;
        
        // Small pause between logs
        await new Promise(r => setTimeout(r, 100));
    }
    
    typingLine.innerHTML = '';
}

/**
 * Helper to type text character by character
 */
function typeText(elementId, text, speed) {
    return new Promise((resolve) => {
        const el = document.getElementById(elementId);
        el.innerHTML = '';
        let i = 0;
        
        function type() {
            if (i < text.length) {
                el.innerHTML += text.charAt(i);
                i++;
                setTimeout(type, speed);
            } else {
                resolve();
            }
        }
        
        type();
    });
}
