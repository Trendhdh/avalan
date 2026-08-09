<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Balans — Avalan SmartPay Demo</title>
<link rel="stylesheet" href="assets/main.css">
<link rel="stylesheet" href="https://cdn-uicons.flaticon.com/4.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
</head>
<body>
<main class="app-container">
    <div class="topbar"><div><h1>Balans</h1><div class="subtitle">Kartalar va naqd pul — BalanceEngine</div></div><span class="demo-badge">Demo</span></div>

    <div class="balance-hero">
        <div class="label">Umumiy balans</div>
        <div class="amount"><span id="totalBalance">—</span></div>
        <div class="row">
            <div class="item"><div class="k">Kartalar</div><div class="v" id="cardBalance">—</div></div>
            <div class="item"><div class="k">Naqd pul</div><div class="v" id="cashBalance">—</div></div>
        </div>
    </div>

    <section class="card">
        <h2>Bog'langan kartalar</h2>
        <div id="cardsList"><div class="skeleton"></div></div>
    </section>

    <?php $activeNav = 'balance'; require __DIR__ . '/assets/nav.php'; ?>
</main>

<script src="assets/api-client.js"></script>
<script src="assets/format.js"></script>
<script>
(async function () {
    if (!AvalanDemoApi.isAuthenticated()) { location.href = 'index.php'; return; }
    try {
        const b = await AvalanDemoApi.balance();
        document.getElementById('totalBalance').textContent = fmtSom(b.total_balance.minor_units);
        document.getElementById('cardBalance').textContent = fmtSom(b.card_balance.minor_units);
        document.getElementById('cashBalance').textContent = fmtSom(b.cash_balance.minor_units);
        document.getElementById('cardsList').innerHTML = b.cards.map(c => `
            <div class="list-row">
                <div class="meta"><div class="title">${c.bank}</div><div class="sub">•••• ${c.last4}</div></div>
                <div class="amount">${fmtSom(c.balance.minor_units)}</div>
            </div>`).join('');
    } catch (e) {
        document.querySelector('.app-container').insertAdjacentHTML('afterbegin', `<div class="crisis-banner">${e.message}</div>`);
    }
})();
</script>
</body>
</html>
