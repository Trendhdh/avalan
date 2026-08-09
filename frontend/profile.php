<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profil — Avalan SmartPay Demo</title>
<link rel="stylesheet" href="assets/main.css">
<link rel="stylesheet" href="https://cdn-uicons.flaticon.com/4.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
</head>
<body>
<main class="app-container">
    <div class="topbar"><div><h1>Profil</h1><div class="subtitle">Foydalanuvchi ma'lumotlari</div></div><span class="demo-badge">Demo</span></div>

    <section class="card" style="text-align:center;">
        <div class="avatar" style="width:64px; height:64px; font-size:1.2rem; margin:0 auto 10px;" id="avatarInitials">—</div>
        <div style="font-weight:700; font-size:1.05rem;" id="fullName">—</div>
        <div class="text-muted" style="font-size:0.82rem;" id="phone">—</div>
        <div class="text-muted" style="font-size:0.76rem; margin-top:4px;">A'zo: <span id="memberSince">—</span></div>
    </section>

    <section class="card">
        <h2>Moliyaviy reyting</h2>
        <div class="list-row"><div class="meta"><div class="title">Rank</div></div><div class="amount" id="rankVal">—</div></div>
        <div class="list-row"><div class="meta"><div class="title">Rating</div></div><div class="amount" id="ratingVal">—</div></div>
        <a href="financial-profile.php" class="text-accent" style="font-size:0.82rem; font-weight:600;">To'liq tahlilni ko'rish →</a>
    </section>

    <section class="card">
        <button class="btn btn-ghost" id="logoutBtn" type="button">Chiqish</button>
    </section>

    <?php $activeNav = 'profile'; require __DIR__ . '/assets/nav.php'; ?>
</main>

<script src="assets/api-client.js"></script>
<script>
(async function () {
    if (!AvalanDemoApi.isAuthenticated()) { location.href = 'index.php'; return; }
    try {
        const p = await AvalanDemoApi.profile();
        document.getElementById('avatarInitials').textContent = p.user.avatar_initials;
        document.getElementById('fullName').textContent = p.user.full_name;
        document.getElementById('phone').textContent = p.user.phone;
        document.getElementById('memberSince').textContent = p.user.member_since;
        document.getElementById('rankVal').textContent = p.score.rank + ' — ' + p.score.rank_label;
        document.getElementById('ratingVal').textContent = p.score.rating + '/1000';
    } catch (e) {
        document.querySelector('.app-container').insertAdjacentHTML('afterbegin', `<div class="crisis-banner">${e.message}</div>`);
    }

    document.getElementById('logoutBtn').addEventListener('click', () => {
        AvalanDemoApi.logout();
        location.href = 'index.php';
    });
})();
</script>
</body>
</html>
