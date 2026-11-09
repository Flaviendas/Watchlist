<?php
require __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->load();

use App\Repository\UserMovieRepository;

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /Watchlist/public/login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /Watchlist/public/watchlist.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$tmdbId = (int) ($_POST['tmdb_id'] ?? 0);
$action = (string) ($_POST['action'] ?? '');
$repository = new UserMovieRepository();

if ($tmdbId > 0 && $action === 'seen') {
    $repository->markSeen($userId, $tmdbId);
    header('Location: /Watchlist/public/review.php?tmdb_id=' . $tmdbId);
    exit;
}

if ($tmdbId > 0 && $action === 'remove') {
    $repository->remove($userId, $tmdbId);
}

header('Location: /Watchlist/public/watchlist.php');
exit;

