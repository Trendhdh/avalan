<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Moliyaviy tahlil — Avalan SmartPay Demo</title>
<link rel="stylesheet" href="assets/main.css">
<link rel="stylesheet" href="https://cdn-uicons.flaticon.com/4.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
</head>
<body>
<main class="app-container">
    <div class="topbar"><div><h1>Moliyaviy tahlil</h1><div class="subtitle">RiskEngine + ScoreEngine natijalari</div></div><span class="demo-badge">Demo</span></div>

    <section class="card" style="text-align:center;">
        <div class="rank-ring" id="rankRing" style="--pct:0;"><div class="inner"><div class="rank" id="rankCode">—</div><div class="rating" id="rankRating">—</div></div></div>
        <div id="rankLabel" style="font-weight:700; margin-top:4px;">—</div>
        <div class="text-muted" style="font-size:0.78rem;">Ma'lumot ishonchliligi: <span id="dataConfidence">—</span>%</div>
    </section>

    <section class="card">
        <h2>Reyting tarkibi</h2>
        <div id="components"></div>
    </section>

    <section class="card">
        <h2>Xavf ko'rsatkichlari</h2>
        <div class="stat-grid">
            <div class="stat-tile"><div class="k">Stress darajasi</div><div class="v" id="stressScore">—</div></div>
            <div class="stat-tile"><div class="k">Qarz nisbati</div><div class="v" id="debtRatio">—</div></div>
            <div class="stat-tile"><div class="k">Likvidlik nisbati</div><div class="v" id="liquidityRatio">—</div></div>
            <div class="stat-tile"><div class="k">Zaxira kunlari</div><div class="v" id="emergencyDays">—</div></div>
            <div class="stat-tile"><div class="k">Qarz/Daromad</div><div class="v" id="dti">—</div></div>
            <div class="stat-tile"><div class="k">Ishonch darajasi</div><div class="v" id="confidenceScore">—</div></div>
        </div>
        <div id="crisisNote" style="margin-top:12px;"></div>
    </section>

    <?php $activeNav = 'profile'; require __DIR__ . '/assets/nav.php'; ?>
</main>

<script src="assets/api-client.js"></script>
<script src="assets/format.js"></script>
<script>
(async function () {
    if (!AvalanDemoApi.isAuthenticated()) { location.href = 'index.php'; return; }
    try {
        const p = await AvalanDemoApi.profile();
        document.getElementById('rankCode').textContent = p.score.rank;
        document.getElementById('rankRating').textContent = p.score.rating + '/1000';
        document.getElementById('rankRing').style.setProperty('--pct', Math.round(p.score.rating / 10));
        document.getElementById('rankLabel').textContent = p.score.rank_label;
        document.getElementById('dataConfidence').textContent = p.score.data_confidence;

        const labels = { income_stability: 'Daromad barqarorligi', financial_health: 'Moliyaviy salomatlik', payment_discipline: "To'lov intizomi", resilience: 'Chidamlilik' };
        document.getElementById('components').innerHTML = Object.entries(p.score.components).map(([k, v]) => `
            <div class="component-bar">
                <div class="row"><span>${labels[k] || k}</span><span>${v}</span></div>
                <div class="progress-bar"><div class="fill" style="width:${v}%"></div></div>
            </div>`).join('');

        document.getElementById('stressScore').textContent = p.risk.stress_score + '/100';
        document.getElementById('debtRatio').textContent = p.risk.debt_ratio;
        document.getElementById('liquidityRatio').textContent = p.risk.liquidity_ratio;
        document.getElementById('emergencyDays').textContent = p.risk.emergency_days + ' kun';
        document.getElementById('dti').textContent = p.risk.debt_to_income_ratio !== null ? p.risk.debt_to_income_ratio : "Ma'lumot yo'q";
        document.getElementById('confidenceScore').textContent = p.risk.confidence_score + '/100';

        document.getElementById('crisisNote').innerHTML = p.risk.crisis_mode
            ? `<div class="crisis-banner"><i class="fi fi-rr-triangle-warning"></i> Inqiroz rejimi faol — majburiy to'lovlar mavjud mablag'dan oshib ketmoqda.</div>`
            : `<div class="ok-banner"><i class="fi fi-rr-shield-check"></i> Inqiroz rejimi faol emas.</div>`;
    } catch (e) {
        document.querySelector('.app-container').insertAdjacentHTML('afterbegin', `<div class="crisis-banner">${e.message}</div>`);
    }
})();
</script>
</body>
</html>
