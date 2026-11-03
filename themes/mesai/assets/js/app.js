const $ = id => document.getElementById(id);
const short = s => (s || '0 Dakika').replace(' Saat', 's').replace(' Dakika', 'dk').trim();
const fmtMins = m => { m = Math.round(m); const h = Math.floor(m / 60); return h ? `${h}s ${m % 60}dk` : `${m}dk`; };
const hhmm = ts => new Date(ts * 1000).toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

let allDays = [];
let showAll = false;

async function load() {
    try {
        const res = await fetch('api/fetch_activity.php');
        const r = await res.json();
        if (r.error) throw new Error(r.error);
        render(r.stats);
        $('updatedAt').textContent = 'güncellendi ' + new Date().toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
    } catch (e) {
        $('updatedAt').textContent = 'hata: ' + e.message;
    }
}

function render(s) {
    renderSession(s.current_session);

    $('tToday').textContent = short(s.today && s.today.work_time_session);
    $('tTodayC').textContent = (s.today ? s.today.commits : 0) + ' commit';
    $('tYesterday').textContent = short(s.yesterday && s.yesterday.work_time_session);
    $('tYesterdayC').textContent = (s.yesterday ? s.yesterday.commits : 0) + ' commit';
    $('t24').textContent = short(s.last_24h && s.last_24h.work_time_session);
    $('t24C').textContent = (s.last_24h ? s.last_24h.commits : 0) + ' commit';

    allDays = s.daily_log || [];
    const week = allDays.slice(0, 7).reduce((a, d) => a + d.work_minutes, 0);
    $('t7').textContent = fmtMins(week);
    $('t7C').textContent = 'ort. ' + fmtMins(week / 7) + '/gün';
    renderDays();
}

function renderSession(cs) {
    if (!cs) { $('sessionTime').textContent = '0dk'; return; }
    $('sessionTime').textContent = short(cs.work_time_str);
    $('liveDot').classList.toggle('live', cs.active);
    $('sessionState').textContent = cs.active ? 'KESİNTİSİZ MESAİ • DEVAM EDİYOR' : 'SON MESAİ • BİTTİ';
    $('sessionSince').textContent = `${cs.start_label}'den beri` + (cs.crosses_midnight ? ' (gece yarısını geçti)' : '') + ` · son commit ${cs.last_commit}`;

    // Zaman çizelgesi: yeşil = commit akışı, açık = commit atılmayan 90dk+ ara, siyah çizgi = 00:00
    const span = cs.end_ts - cs.start_ts;
    const pct = ts => ((ts - cs.start_ts) / span * 100).toFixed(2) + '%';
    let html = cs.gaps.map(g => `<span class="g" style="left:${pct(g.from_ts)};width:${(g.minutes * 60 / span * 100).toFixed(2)}%" title="${g.from}–${g.to} commit yok"></span>`).join('');
    const midnight = new Date(cs.end_ts * 1000); midnight.setHours(0, 0, 0, 0);
    const mTs = midnight / 1000;
    if (mTs > cs.start_ts && mTs < cs.end_ts) html += `<span class="mid" style="left:${pct(mTs)}" title="00:00 gün sıfırlandı"></span>`;
    $('timeline').innerHTML = html;
    $('timelineAxis').innerHTML = `<span>${hhmm(cs.start_ts)}</span><span>${cs.active ? 'şimdi' : hhmm(cs.end_ts)}</span>`;
    $('gapList').innerHTML = cs.gaps.map(g => `<li>ara ${g.from}–${g.to} · ${fmtMins(g.minutes)}</li>`).join('');
}

function renderDays() {
    const list = showAll ? allDays : allDays.slice(0, 30);
    const max = Math.max(60, ...list.map(d => d.work_minutes));
    $('dayList').innerHTML = list.map(d => {
        const [date, dayName] = d.formatted_date.split(', ');
        const cls = ['day', d.measured ? '' : 'est', d.work_minutes ? '' : 'zero', d.is_today ? 'today' : '', dayName === 'Pazar' ? 'week-start' : ''].join(' ');
        return `<li class="${cls}" title="${d.contributions} katkı · ${d.measured ? 'commit saatlerinden ölçüldü' : 'katkı sayısından tahmin'}">
            <span class="day-date">${esc(date)}<small>${esc(dayName || '')}</small></span>
            <span class="bar"><i style="width:${(d.work_minutes / max * 100).toFixed(1)}%"></i></span>
            <span class="day-time mono">${d.work_minutes ? short(d.work_time_str) : '—'}</span>
        </li>`;
    }).join('');
    $('moreBtn').hidden = allDays.length <= 30;
    $('moreBtn').textContent = showAll ? 'Son 30 günü göster' : 'Tüm yılı göster';
}

$('moreBtn').addEventListener('click', () => { showAll = !showAll; renderDays(); });
load();
setInterval(load, 5 * 60 * 1000);
