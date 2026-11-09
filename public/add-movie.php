<?php
require __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->load();

use App\Repository\UserMovieRepository;

session_start();

// 1. pas connecté → retour à la connexion
if (!isset($_SESSION['user_id'])) {
    header('Location: /Watchlist/public/login.php');
    exit;
}

// 2. ajouter le film
$userId = (int) $_SESSION['user_id'];
$tmdbId = (int) ($_POST['tmdb_id'] ?? 0);

if ($tmdbId > 0) {
    (new UserMovieRepository())->add($userId, $tmdbId);
}

// 3. retour à la watchlist
header('Location: /Watchlist/public/watchlist.php');
exit;