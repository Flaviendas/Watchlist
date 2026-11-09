<?php
require __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->load();

use App\Repository\UserGenreRepository;
use App\Repository\UserMovieRepository;
use App\Service\TmdbService;

session_start();

$tmdbId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$tmdbId || $tmdbId < 1) {
    header('Location: /Watchlist/public/suggestion.php');
    exit;
}

$tmdb = new TmdbService($_ENV['TMDB_API_KEY']);
$movie = $tmdb->getMovieWithCredits($tmdbId);
if (empty($movie['id'])) {
    http_response_code(404);
}

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$title = $movie['title'] ?? 'Film introuvable';
$genres = $movie['genres'] ?? [];
$genreNames = array_column($genres, 'name');
$year = !empty($movie['release_date']) ? substr($movie['release_date'], 0, 4) : 'Année inconnue';
$runtime = !empty($movie['runtime']) ? (int) $movie['runtime'] . ' min' : 'Durée non renseignée';
$countries = array_column($movie['production_countries'] ?? [], 'name');
$director = '';
foreach ($movie['credits']['crew'] ?? [] as $crewMember) {
    if (($crewMember['job'] ?? '') === 'Director') {
        $director = $crewMember['name'];
        break;
    }
}
$cast = array_slice(array_column($movie['credits']['cast'] ?? [], 'name'), 0, 4);
$providers = $tmdb->getProviders($tmdbId)['flatrate'] ?? [];
$providerNames = array_column($providers, 'provider_name');

$isLoggedIn = isset($_SESSION['user_id']);
$isInWatchlist = false;
$isSeen = false;
$matchingGenres = [];
if ($isLoggedIn) {
    $userId = (int) $_SESSION['user_id'];
    $movieRepository = new UserMovieRepository();
    $toWatchIds = array_map('intval', array_column($movieRepository->findByUser($userId, 'to_watch'), 'tmdb_id'));
    $seenIds = array_map('intval', array_column($movieRepository->findByUser($userId, 'seen'), 'tmdb_id'));
    $isInWatchlist = in_array($tmdbId, $toWatchIds, true) || in_array($tmdbId, $seenIds, true);
    $isSeen = in_array($tmdbId, $seenIds, true);

    $preferredGenreIds = (new UserGenreRepository())->findByUser($userId);
    foreach ($genres as $genre) {
        if (in_array((int) $genre['id'], $preferredGenreIds, true)) {
            $matchingGenres[] = $genre['name'];
        }
    }
}
?>

<?php require __DIR__ . '/../templates/partials/header.php'; ?>

<section class="movie-page">
    <div class="movie-page-inner">
        <nav class="movie-breadcrumb" aria-label="Fil d’Ariane">
            <a href="/Watchlist/public/suggestion.php">← Retour à la sélection</a>
            <span><?= $isSeen ? 'Déjà vu' : ($isInWatchlist ? 'Dans votre watchlist' : 'À découvrir') ?></span>
        </nav>

        <?php if (empty($movie['id'])): ?>
            <div class="search-empty">
                <h1>Film introuvable</h1>
                <p>TMDB n’a pas pu retrouver ce film. Retourne aux suggestions pour en choisir un autre.</p>
            </div>
        <?php else: ?>
            <div class="movie-layout">
                <?php if (!empty($movie['poster_path'])): ?>
                    <img class="movie-artwork" src="https://image.tmdb.org/t/p/w780<?= $escape($movie['poster_path']) ?>" alt="Affiche de <?= $escape($title) ?>">
                <?php elseif (!empty($movie['backdrop_path'])): ?>
                    <img class="movie-artwork" src="https://image.tmdb.org/t/p/w780<?= $escape($movie['backdrop_path']) ?>" alt="Image du film <?= $escape($title) ?>">
                <?php else: ?>
                    <div class="movie-artwork image-fallback">Affiche indisponible</div>
                <?php endif; ?>

                <div class="movie-summary">
                    <p class="eyebrow">SÉLECTION CINEMATCH</p>
                    <h1><?= $escape($title) ?></h1>
                    <p class="movie-meta">
                        <?= $escape($year) ?> · <?= $escape($runtime) ?>
                        <?= $genreNames !== [] ? ' · ' . $escape(implode(', ', $genreNames)) : '' ?>
                        <?= $countries !== [] ? ' · ' . $escape(implode(', ', $countries)) : '' ?>
                    </p>

                    <?php if ($director !== ''): ?>
                        <p class="movie-director">Un film de <?= $escape($director) ?></p>
                    <?php endif; ?>

                    <?php if ($isLoggedIn): ?>
                        <div class="movie-actions">
                            <?php if ($isSeen): ?>
                                <a class="btn-primary" href="/Watchlist/public/review.php?tmdb_id=<?= $tmdbId ?>">Noter mon ressenti</a>
                            <?php elseif ($isInWatchlist): ?>
                                <form action="/Watchlist/public/Movie-action.php" method="post">
                                    <input type="hidden" name="tmdb_id" value="<?= $tmdbId ?>">
                                    <button class="btn-primary" type="submit" name="action" value="seen">J’ai déjà vu ce film</button>
                                </form>
                                <span class="movie-status">Déjà ajouté à votre Watchlist</span>
                            <?php else: ?>
                                <form action="/Watchlist/public/add-movie.php" method="post">
                                    <input type="hidden" name="tmdb_id" value="<?= $tmdbId ?>">
                                    <button class="btn-primary" type="submit">Ajouter à ma Watchlist</button>
                                </form>
                                <form action="/Watchlist/public/Movie-action.php" method="post">
                                    <input type="hidden" name="tmdb_id" value="<?= $tmdbId ?>">
                                    <button class="btn-secondary" type="submit" name="action" value="seen">Je l’ai déjà vu</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p><a class="btn-primary" href="/Watchlist/public/login.php">Connectez-vous pour l’ajouter</a></p>
                    <?php endif; ?>

                    <section class="movie-synopsis">
                        <h2>Le film</h2>
                        <p><?= $escape($movie['overview'] ?? 'Le synopsis n’est pas disponible pour le moment.') ?></p>
                    </section>

                    <section class="movie-facts">
                        <div>
                            <p class="movie-fact-label">Genres</p>
                            <p class="movie-fact-value"><?= $genreNames !== [] ? $escape(implode(' · ', $genreNames)) : 'Non renseignés' ?></p>
                        </div>
                        <div>
                            <p class="movie-fact-label">Durée</p>
                            <p class="movie-fact-value"><?= $escape($runtime) ?></p>
                        </div>
                        <?php if ($cast !== []): ?>
                            <div>
                                <p class="movie-fact-label">Avec</p>
                                <p class="movie-fact-value"><?= $escape(implode(', ', $cast)) ?></p>
                            </div>
                        <?php endif; ?>
                    </section>

                    <section class="movie-providers">
                        <h2>Où le regarder ?</h2>
                        <?php if ($providerNames !== []): ?>
                            <div class="movie-provider-list">
                                <?php foreach (array_unique($providerNames) as $providerName): ?>
                                    <span class="movie-provider"><?= $escape($providerName) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <p class="movie-note">Disponibilités indiquées pour la France via TMDB / JustWatch.</p>
                        <?php else: ?>
                            <p class="movie-note">Aucune offre de streaming n’est renseignée pour la France.</p>
                        <?php endif; ?>
                    </section>
                </div>
            </div>

            <section class="movie-affinities">
                <h2>Pourquoi ce film pourrait vous plaire</h2>
                <div class="movie-affinity-list">
                    <?php if ($matchingGenres !== []): ?>
                        <article class="movie-affinity">
                            <strong>Un genre qui vous ressemble</strong>
                            <p>Vous avez choisi <?= $escape(implode(', ', $matchingGenres)) ?> dans vos genres préférés.</p>
                        </article>
                    <?php else: ?>
                        <article class="movie-affinity">
                            <strong>Une nouvelle piste</strong>
                            <p>Ce film peut vous faire découvrir un autre genre de cinéma.</p>
                        </article>
                    <?php endif; ?>
                    <article class="movie-affinity">
                        <strong>Une histoire à découvrir</strong>
                        <p>Parcourez le synopsis et les informations avant de décider de votre prochaine séance.</p>
                    </article>
                    <article class="movie-affinity">
                        <strong>À votre rythme</strong>
                        <p>Ajoutez-le à votre Watchlist ou indiquez que vous l’avez déjà vu.</p>
                    </article>
                </div>
                <?php if ($isLoggedIn && $matchingGenres === []): ?>
                    <p><a href="/Watchlist/public/genres.php">Mettre à jour mes genres préférés ↗</a></p>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../templates/partials/footer.php'; ?>

