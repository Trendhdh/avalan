<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Qarzlar — Avalan SmartPay Demo</title>
<link rel="stylesheet" href="assets/main.css">
<link rel="stylesheet" href="https://cdn-uicons.flaticon.com/4.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
</head>
<body>
<main class="app-container">
    <div class="topbar"><div><h1>Kreditlar</h1><div class="subtitle">Faol qarzlar — LiabilityEngine manbasi</div></div><span class="demo-badge">Demo</span></div>

    <div id="loansList"><div class="skeleton"></div><div style="height:10px"></div><div class="skeleton"></div></div>

    <?php $activeNav = 'loans'; require __DIR__ . '/assets/nav.php'; ?>
</main>

<script src="assets/api-client.js"></script>
<script src="assets/format.js"></script>
<script>
(async function () {
    if (!AvalanDemoApi.isAuthenticated()) { location.href = 'index.php'; return; }
    try {
        const { loans } = await AvalanDemoApi.loans();
        document.getElementById('loansList').innerHTML = loans.map(l => `
            <section class="card">
                <h2>${l.lender_name}</h2>
                <div class="stat-grid">
                    <div class="stat-tile"><div class="k">Qolgan qarz</div><div class="v">${fmtSom(l.remaining.minor_units)}</div></div>
                    <div class="stat-tile"><div class="k">Oylik to'lov</div><div class="v">${fmtSom(l.monthly_payment.minor_units)}</div></div>
                    <div class="stat-tile"><div class="k">Foiz stavkasi</div><div class="v">${l.interest_rate}%</div></div>
                    <div class="stat-tile"><div class="k">Muddat</div><div class="v">${l.months_paid}/${l.term_months} oy</div></div>
                </div>
                <div class="progress-bar"><div class="fill" style="width:${l.progress_pct}%"></div></div>
                <div class="text-muted" style="font-size:0.76rem; margin-top:6px;">${l.progress_pct}% to'langan</div>
            </section>`).join('') || '<div class="empty-state">Faol kredit yo\'q</div>';
    } catch (e) {
        document.querySelector('.app-container').insertAdjacentHTML('afterbegin', `<div class="crisis-banner">${e.message}</div>`);
    }
})();
</script>
</body>
</html>
