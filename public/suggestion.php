<?php
require __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->load();

use App\Repository\MovieReviewRepository;
use App\Repository\UserGenreRepository;
use App\Repository\UserMovieRepository;
use App\Service\TmdbService;

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /Watchlist/public/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$genreRepository = new UserGenreRepository();
$genreIds = $genreRepository->findByUser($userId);

if ($genreIds === []) {
    header('Location: /Watchlist/public/genres.php');
    exit;
}

$knownIds = (new UserMovieRepository())->findAllIds($userId);
$reviews = (new MovieReviewRepository())->findAllByUser($userId);
$likedGenreIds = [];
$dislikedGenreIds = [];
foreach ($reviews as $review) {
    if ($review['feeling'] === 'liked') {
        $likedGenreIds = array_merge($likedGenreIds, $review['genre_ids']);
    } elseif ($review['feeling'] === 'disliked') {
        $dislikedGenreIds = array_merge($dislikedGenreIds, $review['genre_ids']);
    }
}
$suggestionGenreIds = array_values(array_unique(array_merge($genreIds, $likedGenreIds)));
$dislikedGenreIds = array_values(array_diff(array_unique($dislikedGenreIds), $genreIds));

$tmdb = new TmdbService($_ENV['TMDB_API_KEY']);
$candidates = $tmdb->discover($suggestionGenreIds, $dislikedGenreIds);
$movies = array_values(array_filter(
    $candidates,
    static fn (array $movie): bool => !in_array((int) $movie['id'], $knownIds, true)
));
$movies = array_slice($movies, 0, 12);

foreach ($movies as &$movie) {
    $movie['providers'] = $tmdb->getProviders((int) $movie['id'])['flatrate'] ?? [];
}
unset($movie);

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>

<?php require __DIR__ . '/../templates/partials/header.php'; ?>

<section class="suggestion-page">
    <header class="suggestion-heading">
        <p class="eyebrow">VOTRE PROCHAINE SÉANCE</p>
        <h1>Ta sélection</h1>
        <p>Tes genres préférés et tes avis après visionnage guident cette sélection. Ouvre une fiche pour en savoir plus, puis ajoute le film à ta Watchlist.</p>
        <a href="/Watchlist/public/genres.php">Modifier mes genres préférés ↗</a>
    </header>

    <?php if ($movies === []): ?>
        <div class="search-empty">
            <h2>Tu as déjà parcouru toutes les suggestions.</h2>
            <p>Élargis tes genres préférés ou reviens plus tard pour découvrir de nouveaux films.</p>
            <a href="/Watchlist/public/genres.php">Élargir ma sélection</a>
        </div>
    <?php else: ?>
        <div class="suggestions">
            <?php foreach ($movies as $movie): ?>
                <?php
                $id = (int) $movie['id'];
                $title = $movie['title'] ?? 'Film sans titre';
                $movieUrl = '/Watchlist/public/movie.php?id=' . $id;
                $providers = array_column($movie['providers'], 'provider_name');
                ?>
                <article class="movie-card movie-card-clickable">
                    <a class="movie-card-overlay-link" href="<?= $movieUrl ?>" aria-label="Ouvrir la fiche de <?= $escape($title) ?>"></a>

                    <?php if (!empty($movie['poster_path'])): ?>
                        <img src="https://image.tmdb.org/t/p/w500<?= $escape($movie['poster_path']) ?>" alt="Affiche de <?= $escape($title) ?>" loading="lazy">
                    <?php else: ?>
                        <div class="image-fallback">Affiche indisponible</div>
                    <?php endif; ?>

                    <div class="movie-card-body">
                        <h2><?= $escape($title) ?></h2>
                        <p><?= $escape(mb_strimwidth((string) ($movie['overview'] ?? ''), 0, 160, '…')) ?></p>

                        <p class="movie-card-platform">
                            <?= $providers !== []
                                ? 'Disponible sur : ' . $escape(implode(', ', $providers))
                                : 'Aucune offre de streaming renseignée pour la France.' ?>
                        </p>

                        <div class="movie-card-actions">
                            <form action="/Watchlist/public/add-movie.php" method="post">
                                <input type="hidden" name="tmdb_id" value="<?= $id ?>">
                                <button type="submit" class="btn-seen">Ajouter à ma Watchlist</button>
                            </form>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../templates/partials/footer.php'; ?>

