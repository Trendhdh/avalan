<?php
/**
 * Bottom navigation — static PHP partial, same pattern as production's
 * assets/nav.php. `$activeNav` is set by each page before including
 * this file so the current tab is highlighted.
 */
$activeNav = $activeNav ?? '';
?>
<nav class="bottom-nav">
    <div class="nav-link <?= $activeNav === 'home' ? 'active' : '' ?>" onclick="location.href='home.php'">
        <i class="fi fi-rr-home"></i><span>Bosh sahifa</span>
    </div>
    <div class="nav-link <?= $activeNav === 'balance' ? 'active' : '' ?>" onclick="location.href='balance.php'">
        <i class="fi fi-rr-credit-card"></i><span>Balans</span>
    </div>
    <div class="nav-link <?= $activeNav === 'smartpay' ? 'active' : '' ?>" onclick="location.href='smartpay.php'">
        <i class="fi fi-rr-shield-check"></i><span>SmartPay</span>
    </div>
    <div class="nav-link <?= $activeNav === 'loans' ? 'active' : '' ?>" onclick="location.href='loans.php'">
        <i class="fi fi-rr-hand-holding-usd"></i><span>Qarzlar</span>
    </div>
    <div class="nav-link <?= $activeNav === 'profile' ? 'active' : '' ?>" onclick="location.href='profile.php'">
        <i class="fi fi-rr-user"></i><span>Profil</span>
    </div>
</nav>
