document.addEventListener('DOMContentLoaded', () => {
    fetchActivity();
});

async function fetchActivity() {
    try {
        const response = await fetch('api/fetch_activity.php');
        const result = await response.json();

        if (result.error) {
            document.getElementById('activityList').innerHTML = `<li class="log-item" style="border-color: var(--nitro-red)"><span class="log-desc">ECU ERROR: ${result.error}</span></li>`;
            return;
        }

        if (result.success && result.stats) {
            
            // 1. Animate Gauges
            const commits = result.stats.commits || 0;
            const additions = result.stats.additions || 0;
            
            animateGauge('rpmNeedle', 'rpmProgress', 'valCommits', commits, 40, 400); // Max 40 commits for redline
            animateGauge('speedNeedle', 'speedProgress', 'valAdditions', additions, 2000, 450); // Max 2000 lines for top speed

            // 2. Populate Telemetry
            document.getElementById('valDeletions').textContent = result.stats.deletions.toLocaleString('tr-TR');
            document.getElementById('valRepos').textContent = result.stats.repos;
            if (result.stats.work_time) {
                document.getElementById('valWorkTime').textContent = result.stats.work_time.replace(' Saat ', 'H ').replace(' Dakika', 'M');
            }
            if (result.stats.weekly_commits !== undefined) {
                document.getElementById('valWeekly').textContent = result.stats.weekly_commits;
                document.getElementById('valMonthly').textContent = result.stats.monthly_commits;
            }

            // 3. Populate Live Logs
            const logList = document.getElementById('activityList');
            logList.innerHTML = '';
            
            if (result.config && result.config.show_system_logs === false) {
                logList.innerHTML = `<li class="log-item"><span class="log-desc">TELEMETRY ENCRYPTED. LOGS HIDDEN.</span></li>`;
                return;
            }

            if (result.data.length === 0) {
                 logList.innerHTML = `<li class="log-item"><span class="log-desc">NO RECENT ACTIVITY DETECTED. ENGINE IDLE.</span></li>`;
                return;
            }

            result.data.forEach((activity, index) => {
                const date = new Date(activity.date);
                const timeStr = date.toLocaleTimeString('tr-TR', { hour: '2-digit', minute:'2-digit' });

                setTimeout(() => {
                    const li = document.createElement('li');
                    
                    let color = '#8f929b';
                    if(activity.type === 'PushEvent') color = '#ff2a2a';
                    else if(activity.type === 'CreateEvent') color = '#00e676';
                    
                    li.innerHTML = `<span style="color: ${color}">[${timeStr}]</span> ${activity.repo}: ${activity.details}`;
                    
                    logList.appendChild(li);
                    
                    // Keep only last 2 items visible in the small ticker box
                    if (logList.children.length > 2) {
                        logList.removeChild(logList.firstElementChild);
                    }
                    
                    // Scroll to bottom
                    const container = document.getElementById('logsContainer');
                    container.scrollTop = container.scrollHeight;
                    
                }, index * 200); // Cascading animation effect
            });
        }
    } catch (error) {
        document.getElementById('activityList').innerHTML = `<li class="log-item" style="border-color: var(--nitro-red)"><span class="log-desc">CRITICAL FAILURE: ${error.message}</span></li>`;
    }
}

/**
 * Animate the needles and progress bars
 * Base angle: -45deg
 * Sweep angle: 270deg
 */
function animateGauge(needleId, progressId, textId, targetValue, maxValue, maxDashOffset) {
    const needle = document.getElementById(needleId);
    const progress = document.getElementById(progressId);
    const textEl = document.getElementById(textId);
    
    // Clamp value
    let val = targetValue > maxValue ? maxValue : targetValue;
    let ratio = val / maxValue;
    
    // Calculate rotation (-45 to 225)
    let rotation = -45 + (ratio * 270);
    
    // Calculate dash offset (maxDashOffset down to 0)
    let dashOffset = maxDashOffset - (ratio * maxDashOffset);
    
    // Trigger CSS transitions
    setTimeout(() => {
        needle.style.transform = `translateY(-50%) rotate(${rotation}deg)`;
        progress.style.strokeDashoffset = dashOffset;
        
        // Number counter animation
        animateValue(textEl, 0, targetValue, 2000);
    }, 500); // 500ms delay for visual drama
}

function animateValue(obj, start, end, duration) {
    let startTimestamp = null;
    const step = (timestamp) => {
        if (!startTimestamp) startTimestamp = timestamp;
        const progress = Math.min((timestamp - startTimestamp) / duration, 1);
        
        // Easing (easeOutExpo)
        const easeProgress = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
        
        obj.innerHTML = Math.floor(easeProgress * (end - start) + start);
        if (progress < 1) {
            window.requestAnimationFrame(step);
        }
    };
    window.requestAnimationFrame(step);
}
