<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SmartPay — Avalan SmartPay Demo</title>
<link rel="stylesheet" href="assets/main.css">
<link rel="stylesheet" href="https://cdn-uicons.flaticon.com/4.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
</head>
<body>
<main class="app-container">
    <div class="topbar"><div><h1>SmartPay</h1><div class="subtitle">To'lov taqsimoti — PaymentAllocationEngine</div></div><span class="demo-badge">Demo</span></div>

    <section class="card">
        <div class="stat-grid">
            <div class="stat-tile"><div class="k">Bugun xavfsiz sarf</div><div class="v" id="safeToday">—</div></div>
            <div class="stat-tile"><div class="k">Zaxiradagi (sarflab bo'lmaydi)</div><div class="v" id="unsafe">—</div></div>
        </div>
    </section>

    <section class="card">
        <h2>Tavsiya etilgan tartib</h2>
        <div id="recommendedOrder"><div class="skeleton"></div></div>
    </section>

    <section class="card">
        <h2>30 kunlik to'lov rejasi</h2>
        <div id="fullPlan"><div class="skeleton"></div></div>
    </section>

    <?php $activeNav = 'smartpay'; require __DIR__ . '/assets/nav.php'; ?>
</main>

<script src="assets/api-client.js"></script>
<script src="assets/format.js"></script>
<script>
(async function () {
    if (!AvalanDemoApi.isAuthenticated()) { location.href = 'index.php'; return; }
    try {
        const c = await AvalanDemoApi.smartpayCompute();
        document.getElementById('safeToday').textContent = fmtSom(c.available.minor_units);
        document.getElementById('unsafe').textContent = fmtSom(c.reserved.minor_units);

        document.getElementById('recommendedOrder').innerHTML = c.recommended_order.map(o => `
            <div class="list-row">
                <div class="meta"><div class="title">${o.order}. ${o.label}</div><div class="sub">${o.due_date} · ${o.action === 'pay_now' ? "Hoziroq to'lash" : 'Zaxiraga ajratish'}</div></div>
                <div class="amount">${fmtSom(o.amount.minor_units)}</div>
            </div>`).join('');

        document.getElementById('fullPlan').innerHTML = c.payment_plan.map(p => `
            <div class="list-row">
                <div class="meta"><div class="title">${p.label}${p.lender_name ? ' · ' + p.lender_name : ''}</div><div class="sub">${p.due_date} · ${p.days_away} kun</div></div>
                <div class="amount">${fmtSom(p.amount.minor_units)}<div><span class="badge ${p.status}">${statusLabel(p.status)}</span></div></div>
            </div>`).join('');
    } catch (e) {
        document.querySelector('.app-container').insertAdjacentHTML('afterbegin', `<div class="crisis-banner">${e.message}</div>`);
    }
})();
</script>
</body>
</html>
