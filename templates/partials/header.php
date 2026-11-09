<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$isLoggedIn = isset($_SESSION['user_id']);
$currentPage = strtolower(basename($_SERVER['PHP_SELF'] ?? ''));
$isLoginPage = $currentPage === 'login.php';
$pageTitle = $pageTitle ?? 'CineMatch';
$pageDescription = $pageDescription ?? null;
$mainClass = isset($mainClass) && is_string($mainClass) ? $mainClass : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if ($pageDescription !== null): ?>
        <meta name="description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,1,14..32,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/Watchlist/public/assets/css/style.css">
    <script src="/Watchlist/public/assets/js/menu.js" defer></script>
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>
</head>
<body>
    <header class="site-header">
        <a href="<?= $isLoggedIn ? '/Watchlist/public/Home-custom.php' : '/Watchlist/public/Home.php' ?>" class="logo" aria-label="CineMatch, accueil">
            Cine<span>Match</span><i class="logo-dot" aria-hidden="true"></i>
        </a>
        <button class="menu-toggle" type="button" aria-label="Ouvrir le menu" aria-controls="header-menu" aria-expanded="false">
            <img src="/Watchlist/public/assets/icons/menu-burger.svg" alt="">
        </button>

        <div class="header-menu" id="header-menu">
            <nav class="main-nav" aria-label="Navigation principale">
                <?php if ($isLoggedIn): ?>
                    <a href="/Watchlist/public/Home-custom.php">Pour vous</a>
                    <a href="/Watchlist/public/watchlist.php">Watchlist</a>
                    <a href="/Watchlist/public/watchlist.php?tab=seen">Historique</a>
                <?php else: ?>
                    <?php if (!$isLoginPage): ?>
                        <a href="/Watchlist/public/Home.php#concept">Le concept</a>
                    <?php endif; ?>
                    <a href="/Watchlist/public/Home.php#selection">La sélection</a>
                    <a href="/Watchlist/public/Home.php#comment-ca-marche">Comment ça marche</a>
                <?php endif; ?>
            </nav>

            <div class="account">
                <?php if ($isLoggedIn): ?>
                    <span class="account-label">Mon espace</span>
                    <span class="avatar" aria-hidden="true">C</span>
                    <a class="logout-link" href="/Watchlist/public/logout.php">Déconnexion</a>
                <?php else: ?>
                    <a class="account-login" href="/Watchlist/public/Login.php">Se connecter</a>
                    <a class="account-signup" href="/Watchlist/public/register.php">Créer mon compte</a>
                <?php endif; ?>
            </div>
        </div>
    </header>
    <main<?= $mainClass !== '' ? ' class="' . htmlspecialchars($mainClass, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' : '' ?>>

