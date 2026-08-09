<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Avalan SmartPay — Demo</title>
<link rel="stylesheet" href="assets/main.css">
<link rel="stylesheet" href="https://cdn-uicons.flaticon.com/4.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
</head>
<body>
<div class="login-screen">
    <div class="logo">A</div>
    <h1>Avalan SmartPay</h1>
    <p>Ochiq kod arxitektura namunasi — moliyaviy dvijoklar (Balance, Liability, Risk, SmartPay, Score) haqiqiy backend hisob-kitoblarini bitta fixture foydalanuvchi ustida ko'rsatadi.</p>
    <button class="btn btn-primary" id="loginBtn" type="button">Demo hisobga kirish</button>
    <div class="login-error" id="loginError"></div>
</div>

<script src="assets/api-client.js"></script>
<script>
    if (AvalanDemoApi.isAuthenticated()) {
        location.href = 'home.php';
    }
    document.getElementById('loginBtn').addEventListener('click', async () => {
        const errorEl = document.getElementById('loginError');
        errorEl.textContent = '';
        try {
            await AvalanDemoApi.login();
            location.href = 'home.php';
        } catch (e) {
            errorEl.textContent = e.message || 'Kirishda xatolik yuz berdi.';
        }
    });
</script>
</body>
</html>
