const $ = id => document.getElementById(id);
const short = s => (s || '0 Dakika').replace(' Saat', 's').replace(' Dakika', 'dk').trim();
const fmtMins = m => { m = Math.round(m); const h = Math.floor(m / 60); return h ? `${h}s ${m % 60}dk` : `${m}dk`; };
const hhmm = ts => new Date(ts * 1000).toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

let allDays = [];
let showAll = false;
let commits = [];
let session = null;
let range = 'session';

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
    session = s.current_session;
    commits = s.recent_commits || [];
    renderProjects();
    renderCommits();

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

const localDate = offset => { const d = new Date(); d.setDate(d.getDate() - offset); return d.toLocaleDateString('sv-SE'); };

function commitsIn(r) {
    if (r === 'today') return commits.filter(c => c.date === localDate(0));
    if (r === 'yesterday') return commits.filter(c => c.date === localDate(1));
    return session ? commits.filter(c => c.ts >= session.start_ts) : [];
}

function renderProjects() {
    const groups = {};
    commitsIn('session').forEach(c => {
        const g = groups[c.repo] || (groups[c.repo] = { n: 0, first: c.ts, last: c.ts });
        g.n++; g.first = Math.min(g.first, c.ts); g.last = Math.max(g.last, c.ts);
    });
    const rows = Object.entries(groups).sort((a, b) => b[1].n - a[1].n);
    const max = rows.length ? rows[0][1].n : 1;
    $('projCount').textContent = rows.length ? rows.length + ' proje' : '';
    $('projectList').innerHTML = rows.length ? rows.map(([name, g]) => `<li class="proj">
        <div class="proj-top"><span title="${esc(name)}">${esc(name)}</span><span class="mono">${g.n} commit · ${hhmm(g.first)}–${hhmm(g.last)}</span></div>
        <div class="bar"><i style="width:${(g.n / max * 100).toFixed(1)}%"></i></div>
    </li>`).join('') : '<li class="muted">Bu mesaide commit yok</li>';
}

function renderCommits() {
    const list = commitsIn(range);
    document.querySelectorAll('.seg button').forEach(b => b.classList.toggle('on', b.dataset.range === range));
    $('commitList').innerHTML = list.length ? list.map(c => {
        const msg = esc(c.message || '(mesaj yok)');
        const link = /^https:\/\/github\.com\//.test(c.url) ? `<a class="commit-msg" href="${esc(c.url)}" target="_blank" rel="noopener">${msg}</a>` : `<span class="commit-msg">${msg}</span>`;
        return `<li class="commit"><time class="mono">${esc(c.time)}</time><div>${link}<span class="commit-repo mono">${esc(c.repo)} · ${esc(c.sha)}</span></div></li>`;
    }).join('') : '<li class="muted">Commit yok</li>';
}

document.querySelectorAll('.seg button').forEach(b => b.addEventListener('click', () => { range = b.dataset.range; renderCommits(); }));

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
            ${chips(d.projects)}
        </li>`;
    }).join('');
    $('moreBtn').hidden = allDays.length <= 30;
    $('moreBtn').textContent = showAll ? 'Son 30 günü göster' : 'Tüm yılı göster';
}

function chips(projects) {
    const p = Object.entries(projects || {});
    if (!p.length) return '';
    const shown = p.slice(0, 5).map(([n, c]) => `<span class="chip"><b>${esc(n)}</b> ${c}</span>`).join('');
    return `<div class="chips">${shown}${p.length > 5 ? `<span class="chip">+${p.length - 5}</span>` : ''}</div>`;
}

$('moreBtn').addEventListener('click', () => { showAll = !showAll; renderDays(); });
load();
setInterval(load, 5 * 60 * 1000);
