<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bosh sahifa — Avalan SmartPay Demo</title>
<link rel="stylesheet" href="assets/main.css">
<link rel="stylesheet" href="https://cdn-uicons.flaticon.com/4.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
</head>
<body>
<main class="app-container">
    <div class="topbar">
        <div>
            <h1>Xush kelibsiz, <span id="userName">...</span></h1>
            <div class="subtitle">Bugungi moliyaviy holat — SmartPay hisob-kitobi</div>
        </div>
        <span class="demo-badge">Demo</span>
    </div>

    <div id="crisisSlot"></div>

    <div class="balance-hero">
        <div class="label">Umumiy balans</div>
        <div class="amount"><span id="totalBalance">—</span></div>
        <div class="row">
            <div class="item"><div class="k">Zaxiraga ajratilgan</div><div class="v" id="reservedAmount">—</div></div>
            <div class="item"><div class="k">Bugun xavfsiz sarf</div><div class="v" id="dailyLimitAmount">—</div></div>
        </div>
    </div>

    <section class="card">
        <h2>Bugungi harakatlar (SmartPay tavsiyasi)</h2>
        <div id="todayActions"><div class="skeleton"></div></div>
    </section>

    <section class="card">
        <h2>Moliyaviy Reyting</h2>
        <div style="display:flex; align-items:center; gap:18px;">
            <div class="rank-ring" id="rankRing" style="--pct:0;"><div class="inner"><div class="rank" id="rankCode">—</div><div class="rating" id="rankRating">—</div></div></div>
            <div style="flex:1;">
                <div class="text-muted" style="font-size:0.82rem;">Stress darajasi</div>
                <div id="stressScore" style="font-weight:700; font-size:1.1rem;">—</div>
                <a href="financial-profile.php" class="text-accent" style="font-size:0.8rem; font-weight:600;">To'liq tahlilni ko'rish →</a>
            </div>
        </div>
    </section>

    <section class="card">
        <h2>Yaqin 30 kunlik to'lovlar</h2>
        <div id="paymentPlanPreview"><div class="skeleton"></div></div>
        <div style="margin-top:10px;"><a href="smartpay.php" class="text-accent" style="font-size:0.82rem; font-weight:600;">To'liq to'lov rejasi →</a></div>
    </section>

    <?php $activeNav = 'home'; require __DIR__ . '/assets/nav.php'; ?>
</main>

<script src="assets/api-client.js"></script>
<script src="assets/format.js"></script>
<script>
(async function () {
    if (!AvalanDemoApi.isAuthenticated()) { location.href = 'index.php'; return; }

    try {
        const [compute, profile] = await Promise.all([
            AvalanDemoApi.smartpayCompute(),
            AvalanDemoApi.profile(),
        ]);

        document.getElementById('userName').textContent = profile.user.full_name.split(' ')[0];
        document.getElementById('totalBalance').textContent = fmtSom(compute.balance.total_balance.minor_units);
        document.getElementById('reservedAmount').textContent = fmtSom(compute.reserved.minor_units);
        document.getElementById('dailyLimitAmount').textContent = fmtSom(compute.daily_limit.amount.minor_units);

        const crisisSlot = document.getElementById('crisisSlot');
        crisisSlot.innerHTML = compute.risk.crisis_mode
            ? `<div class="crisis-banner"><i class="fi fi-rr-triangle-warning"></i> Diqqat: xavf darajasi yuqori — majburiy to'lovlar joriy mablag'dan oshib ketmoqda.</div>`
            : `<div class="ok-banner"><i class="fi fi-rr-shield-check"></i> Moliyaviy holat barqaror — inqiroz rejimi faol emas.</div>`;

        const actionsEl = document.getElementById('todayActions');
        actionsEl.innerHTML = compute.today_actions.map(a => `
            <div class="list-row">
                <div class="meta"><div class="title">${a.label}</div><div class="sub">${a.reason}</div></div>
                <div class="amount">${fmtSom(a.amount.minor_units)}</div>
            </div>`).join('') || '<div class="empty-state">Bugun uchun harakat yo\'q</div>';

        document.getElementById('rankCode').textContent = profile.score.rank;
        document.getElementById('rankRating').textContent = profile.score.rating + '/1000';
        document.getElementById('rankRing').style.setProperty('--pct', Math.round(profile.score.rating / 10));
        document.getElementById('stressScore').textContent = profile.risk.stress_score + ' / 100';

        const planEl = document.getElementById('paymentPlanPreview');
        planEl.innerHTML = compute.payment_plan.slice(0, 4).map(p => `
            <div class="list-row">
                <div class="meta"><div class="title">${p.label}</div><div class="sub">${p.due_date} · ${p.days_away} kun qoldi</div></div>
                <div class="amount">${fmtSom(p.amount.minor_units)}<div><span class="badge ${p.status}">${statusLabel(p.status)}</span></div></div>
            </div>`).join('');
    } catch (e) {
        console.error(e);
        document.querySelector('.app-container').insertAdjacentHTML('afterbegin', `<div class="crisis-banner">${e.message}</div>`);
    }
})();
</script>
</body>
</html>
