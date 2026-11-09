<?php
require __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->load();

use App\Repository\UserMovieRepository;
use App\Service\TmdbService;

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /Watchlist/public/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$tab = ($_GET['tab'] ?? '') === 'seen' ? 'seen' : 'to_watch';
$search = trim((string) ($_GET['q'] ?? ''));
$selectedGenre = trim((string) ($_GET['genre'] ?? ''));

$repository = new UserMovieRepository();
$toWatchRows = $repository->findByUser($userId, 'to_watch');
$seenRows = $repository->findByUser($userId, 'seen');
$rows = $tab === 'seen' ? $seenRows : $toWatchRows;

$tmdb = new TmdbService($_ENV['TMDB_API_KEY']);
$movies = [];
foreach ($rows as $row) {
    $movie = $tmdb->getMovie((int) $row['tmdb_id']);
    if (!$movie) {
        continue;
    }
    $movie['providers'] = $tmdb->getProviders((int) $row['tmdb_id']);
    $movies[] = $movie;
}

$availableGenres = [];
foreach ($movies as $movie) {
    foreach ($movie['genres'] ?? [] as $genre) {
        if (!empty($genre['name'])) {
            $availableGenres[$genre['name']] = $genre['name'];
        }
    }
}
sort($availableGenres, SORT_LOCALE_STRING);

$filteredMovies = array_values(array_filter($movies, static function (array $movie) use ($search, $selectedGenre): bool {
    $searchableText = ($movie['title'] ?? '') . ' ' . ($movie['overview'] ?? '');
    if ($search !== '' && mb_stripos($searchableText, $search) === false) {
        return false;
    }
    if ($selectedGenre !== '' && !in_array($selectedGenre, array_column($movie['genres'] ?? [], 'name'), true)) {
        return false;
    }
    return true;
}));

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>

<?php require __DIR__ . '/../templates/partials/header.php'; ?>

<section class="page-top">
    <p class="eyebrow">VOTRE CINÉMA, À VOTRE RYTHME</p>
    <div class="page-head">
        <h1>Watchlist<br>auto.</h1>
        <div class="page-intro">
            <p class="page-intro-title">Votre réserve de belles séances.</p>
            <p class="page-intro-text">Une sélection construite selon vos goûts. Ajoutez, retirez ou marquez les films comme vus : votre prochaine séance reste entre vos mains.</p>
        </div>
    </div>
</section>

<section class="watchlist-callout">
    <div>
        <h2>Vous avez déjà une histoire de cinéma.</h2>
        <p>Ajoutez les films que vous avez déjà vus pour aider CineMatch à mieux comprendre vos goûts.</p>
    </div>
    <a class="btn-seen" href="/Watchlist/public/suggestion.php">Découvrir des films</a>
</section>

<nav class="tabs" aria-label="Catégories de la watchlist">
    <a href="/Watchlist/public/watchlist.php" class="<?= $tab === 'to_watch' ? 'is-active' : '' ?>" <?= $tab === 'to_watch' ? 'aria-current="page"' : '' ?>>À voir · <?= count($toWatchRows) ?></a>
    <a href="/Watchlist/public/watchlist.php?tab=seen" class="<?= $tab === 'seen' ? 'is-active' : '' ?>" <?= $tab === 'seen' ? 'aria-current="page"' : '' ?>>Déjà vus · <?= count($seenRows) ?></a>
</nav>

<form class="watchlist-filters" action="/Watchlist/public/watchlist.php" method="get">
    <input type="hidden" name="tab" value="<?= $escape($tab) ?>">
    <label for="movie-search">Rechercher un film</label>
    <input id="movie-search" type="search" name="q" value="<?= $escape($search) ?>" placeholder="Titre ou résumé">
    <label for="genre-filter">Genre</label>
    <select id="genre-filter" name="genre">
        <option value="">Tous les genres</option>
        <?php foreach ($availableGenres as $genreName): ?>
            <option value="<?= $escape($genreName) ?>" <?= $selectedGenre === $genreName ? 'selected' : '' ?>><?= $escape($genreName) ?></option>
        <?php endforeach; ?>
    </select>
    <button type="submit" class="btn-seen">Filtrer</button>
    <?php if ($search !== '' || $selectedGenre !== ''): ?>
        <a href="/Watchlist/public/watchlist.php<?= $tab === 'seen' ? '?tab=seen' : '' ?>">Effacer les filtres</a>
    <?php endif; ?>
</form>

<?php if (isset($_GET['review_saved'])): ?>
    <p class="form-success" role="status">Ton avis a bien été enregistré.</p>
<?php endif; ?>

<p class="count">
    <?= count($filteredMovies) ?> film<?= count($filteredMovies) > 1 ? 's' : '' ?>
    <?= $tab === 'seen' ? 'déjà vu' . (count($filteredMovies) > 1 ? 's' : '') : 'à voir' ?>
</p>

<?php if (empty($filteredMovies)): ?>
    <div class="empty-state">
        <?php if (empty($movies)): ?>
            <p class="empty-title"><?= $tab === 'seen' ? 'Votre historique est encore vide.' : 'Votre watchlist est encore vide.' ?></p>
            <p class="empty-text"><?= $tab === 'seen' ? 'Quand vous aurez vu un film, vous pourrez le retrouver ici.' : 'Ajoutez des films depuis les suggestions pour préparer votre prochaine séance.' ?></p>
            <a class="btn-seen" href="/Watchlist/public/suggestion.php">Voir les suggestions</a>
        <?php else: ?>
            <p class="empty-title">Aucun film ne correspond à ces filtres.</p>
            <p class="empty-text">Essayez un autre titre ou choisissez un autre genre.</p>
            <a href="/Watchlist/public/watchlist.php<?= $tab === 'seen' ? '?tab=seen' : '' ?>">Effacer les filtres</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php foreach ($filteredMovies as $movie): ?>
    <?php
    $title = $movie['title'] ?? 'Film sans titre';
    $year = !empty($movie['release_date']) ? substr($movie['release_date'], 0, 4) : 'Année inconnue';
    $runtime = !empty($movie['runtime']) ? (int) $movie['runtime'] . ' min' : 'Durée inconnue';
    $genreNames = implode(', ', array_column($movie['genres'] ?? [], 'name'));
    $overview = trim((string) ($movie['overview'] ?? ''));
    $providerNames = array_column($movie['providers']['flatrate'] ?? [], 'provider_name');
    ?>
    <article class="film-row">
        <?php if (!empty($movie['backdrop_path'])): ?>
            <img class="film-thumb" src="https://image.tmdb.org/t/p/w500<?= $escape($movie['backdrop_path']) ?>" alt="Image du film <?= $escape($title) ?>" loading="lazy">
        <?php else: ?>
            <div class="film-thumb film-thumb-placeholder" aria-hidden="true"></div>
        <?php endif; ?>

        <div class="film-body">
            <p class="eyebrow">POUR VOUS</p>
            <h2><a href="/Watchlist/public/movie.php?id=<?= (int) ($movie['id'] ?? 0) ?>"><?= $escape($title) ?></a></h2>
            <p class="film-meta"><?= $escape($year) ?> · <?= $escape($runtime) ?><?= $genreNames !== '' ? ' · ' . $escape($genreNames) : '' ?></p>
            <p class="film-reason"><?= $escape($overview !== '' ? mb_strimwidth($overview, 0, 180, '…') : 'Le résumé de ce film n’est pas disponible.') ?></p>
            <p class="film-platform"><?= $providerNames ? 'Disponible sur : ' . $escape(implode(', ', $providerNames)) : 'Aucune offre de streaming renseignée pour la France.' ?></p>
        </div>

        <form action="/Watchlist/public/Movie-action.php" method="post" class="film-actions">
            <input type="hidden" name="tmdb_id" value="<?= (int) ($movie['id'] ?? 0) ?>">
            <?php if ($tab === 'to_watch'): ?>
                <button type="submit" name="action" value="seen" class="btn-seen">Déjà vu</button>
            <?php endif; ?>
            <?php if ($tab === 'seen'): ?>
                <a class="btn-review" href="/Watchlist/public/review.php?tmdb_id=<?= (int) ($movie['id'] ?? 0) ?>">Modifier mon avis</a>
            <?php endif; ?>
            <button type="submit" name="action" value="remove" class="btn-remove">Retirer</button>
        </form>
    </article>
<?php endforeach; ?>

<?php if ($tab === 'to_watch' && count($toWatchRows) > 0): ?>
    <section class="watchlist-summary">
        <p><?= count($toWatchRows) ?> film<?= count($toWatchRows) > 1 ? 's' : '' ?> à découvrir · Chaque suggestion raconte une affinité.</p>
        <a href="/Watchlist/public/genres.php">Affiner mes goûts ↗</a>
    </section>
<?php endif; ?>

<?php require __DIR__ . '/../templates/partials/footer.php'; ?>




