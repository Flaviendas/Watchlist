<?php
use App\Repository\UserGenreRepository;
use App\Repository\UserMovieRepository;
use App\Repository\MovieReviewRepository;
use App\Repository\UserPreferenceRepository;
use App\Service\TmdbService;

require __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->load();
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /Watchlist/public/Login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$genreRepository = new UserGenreRepository();
$movieRepository = new UserMovieRepository();
$preferenceRepository = new UserPreferenceRepository();
$storedPreferences = $preferenceRepository->findByUser($userId);
$genreIds = array_map('intval', $genreRepository->findByUser($userId));

if ($genreIds === []) {
    header('Location: /Watchlist/public/genres.php');
    exit;
}

$tmdb = new TmdbService($_ENV['TMDB_API_KEY']);
$genreMap = [];
foreach ($tmdb->getGenres() as $genre) {
    $genreMap[(int) $genre['id']] = $genre['name'];
}

$moodGenreMap = [
    'evader' => [12, 14, 878],
    'rire' => [35],
    'frissonner' => [27, 53, 9648],
    'etre_emu' => [18, 10749],
    'surprendre' => [9648, 878, 53],
];
$moodLabels = [
    'evader' => 'M’évader',
    'rire' => 'Rire',
    'frissonner' => 'Frissonner',
    'etre_emu' => 'Être ému·e',
    'surprendre' => 'Me surprendre',
];
$availableMoodLabels = array_filter(
    $moodLabels,
    static fn (string $label, string $key): bool => array_intersect($moodGenreMap[$key], $genreIds) !== [],
    ARRAY_FILTER_USE_BOTH
);
if ($availableMoodLabels === []) {
    $availableMoodLabels = $moodLabels;
}
$selectedMood = trim((string) ($_GET['mood'] ?? $storedPreferences['preferred_mood']));
if (!isset($availableMoodLabels[$selectedMood])) {
    $selectedMood = (string) array_key_first($availableMoodLabels);
}
$selectedIntensity = trim((string) ($_GET['intensity'] ?? $storedPreferences['recommendation_intensity']));
$intensityWeights = ['decouverte' => ['mood' => 25, 'taste' => 90, 'liked' => 50], 'equilibree' => ['mood' => 70, 'taste' => 60, 'liked' => 35], 'ciblee' => ['mood' => 180, 'taste' => 20, 'liked' => 10]];
if (!isset($intensityWeights[$selectedIntensity])) {
    $selectedIntensity = 'equilibree';
}

// Les avis classent les films, la sélection du soir reste prioritaire.
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
$likedGenreIds = array_values(array_unique(array_map('intval', $likedGenreIds)));
$dislikedGenreIds = array_values(array_unique(array_map('intval', $dislikedGenreIds)));
$moodIds = array_map('intval', $moodGenreMap[$selectedMood]);
$genresToAvoid = array_values(array_diff($dislikedGenreIds, $genreIds, $moodIds));

$profileGenres = $genreIds;
sort($profileGenres);
$reviewSignals = array_map(static fn (array $review): string => $review['tmdb_id'] . '-' . $review['feeling'] . '-' . implode(',', $review['genre_ids']), $reviews);
sort($reviewSignals);
$profileSignature = hash('sha256', 'strict-genres-v2|' . implode(',', $profileGenres) . '|' . $selectedMood . '|' . $selectedIntensity . '|' . implode(';', $reviewSignals));
if ($storedPreferences['profile_signature'] !== '' && $storedPreferences['profile_signature'] !== $profileSignature) {
    $movieRepository->removeAutomaticToWatch($userId);
}
$preferenceRepository->save($userId, $selectedMood, $selectedIntensity, $profileSignature);

// La sélection automatique ajoute au plus dix films, tout en conservant les choix manuels.
$watchRows = $movieRepository->findByUser($userId, 'to_watch');
$automaticCount = count(array_filter($watchRows, static fn (array $row): bool => $row['source'] === 'automatic'));
$automaticSlots = max(0, 10 - $automaticCount);
$preferenceCandidates = $tmdb->discover($genreIds, $genresToAvoid);
$moodCandidates = $tmdb->discover($moodIds, $genresToAvoid);
$candidateMap = [];
foreach (array_merge($preferenceCandidates, $moodCandidates) as $candidate) {
    $candidateMap[(int) $candidate['id']] = $candidate;
}
$candidates = array_values($candidateMap);
$knownIds = array_map('intval', $movieRepository->findAllIds($userId));
$movies = [];
foreach ($candidates as $movie) {
    if (in_array((int) $movie['id'], $knownIds, true)) {
        continue;
    }

    $movieGenres = array_map('intval', $movie['genre_ids'] ?? []);
    if (array_intersect($movieGenres, $genreIds) === []) {
        continue;
    }

    $movies[] = $movie;
}
$scoreMovie = static function (array $movie) use ($moodIds, $genreIds, $likedGenreIds, $intensityWeights, $selectedIntensity): int {
    $movieGenres = array_map('intval', $movie['genre_ids'] ?? []);
    $weights = $intensityWeights[$selectedIntensity];
    return (count(array_intersect($movieGenres, $moodIds)) * $weights['mood'])
        + (count(array_intersect($movieGenres, $genreIds)) * $weights['taste'])
        + (count(array_intersect($movieGenres, $likedGenreIds)) * $weights['liked']);
};
usort($movies, static function (array $a, array $b) use ($scoreMovie): int {
    $scoreDifference = $scoreMovie($b) <=> $scoreMovie($a);
    if ($scoreDifference !== 0) {
        return $scoreDifference;
    }
    $popularityDifference = (float) ($b['popularity'] ?? 0) <=> (float) ($a['popularity'] ?? 0);
    return $popularityDifference !== 0 ? $popularityDifference : ((int) $a['id'] <=> (int) $b['id']);
});
foreach ($movies as $candidate) {
    if ($automaticSlots <= 0) {
        break;
    }
    $movieRepository->addAutomatic($userId, (int) $candidate['id']);
    $automaticSlots--;
}

// Chaque jour, le film principal avance dans les films à voir.
$watchRows = $movieRepository->findByUser($userId, 'to_watch');
$automaticRows = array_values(array_filter($watchRows, static fn (array $row): bool => $row['source'] === 'automatic'));
$dailyRows = $automaticRows !== [] ? $automaticRows : $watchRows;
$watchIds = array_map(static fn (array $row): int => (int) $row['tmdb_id'], $dailyRows);
$watchCount = count($watchIds);
$dayNumber = (int) (new DateTimeImmutable('1970-01-01'))->diff(new DateTimeImmutable('today'))->format('%a');
$dailyIndex = $watchCount > 0 ? ($dayNumber + $userId) % $watchCount : 0;
$rotatedIds = $watchCount > 0
    ? array_merge(array_slice($watchIds, $dailyIndex), array_slice($watchIds, 0, $dailyIndex))
    : [];
$moviesToEnrich = [];
foreach (array_slice($rotatedIds, 0, 4) as $tmdbId) {
    $movie = $tmdb->getMovie($tmdbId);
    if ($movie === []) {
        continue;
    }
    $movie['providers'] = $tmdb->getProviders($tmdbId)['flatrate'] ?? [];
    $moviesToEnrich[] = $movie;
}
$featuredMovie = $moviesToEnrich[0] ?? null;
$otherMovies = array_slice($moviesToEnrich, 1);
$featuredDetails = $featuredMovie ?? [];
$seenMovies = $movieRepository->findByUser($userId, 'seen');
$preferredGenreNames = array_values(array_filter(array_map(
    static fn (int $id): string => $genreMap[$id] ?? '',
    $genreIds
)));
$escape = static fn ($value): string => htmlspecialchars(
    (string) $value,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$featuredImage = '';
if ($featuredMovie) {
    $featuredImage = !empty($featuredMovie['backdrop_path'])
        ? $featuredMovie['backdrop_path']
        : ($featuredMovie['poster_path'] ?? '');
}
$featuredRuntime = (int) ($featuredDetails['runtime'] ?? 0);
$featuredRuntimeLabel = $featuredRuntime > 0
    ? intdiv($featuredRuntime, 60) . ' h ' . str_pad((string) ($featuredRuntime % 60), 2, '0', STR_PAD_LEFT)
    : '';
$featuredGenres = implode(' · ', array_column($featuredDetails['genres'] ?? [], 'name'));
$featuredMeta = array_filter([
    substr((string) ($featuredDetails['release_date'] ?? ''), 0, 4),
    $featuredRuntimeLabel,
    $featuredGenres,
]);
$featuredProviders = array_column($featuredMovie['providers'] ?? [], 'provider_name');
$greetingEnding = match ($selectedMood) {
    'rire' => 'On rit un peu ?',
    'frissonner' => 'On frissonne ?',
    'etre_emu' => 'On se laisse toucher ?',
    'surprendre' => 'On se surprend ?',
    default => 'On s’évade ?',
};
?>
<?php
$pageTitle = 'CineMatch — Pour vous';
$mainClass = 'personal-main';
require __DIR__ . '/../templates/partials/header.php';
?>
<!-- Salutation et sélection du soir -->
        <section class="personal-intro">
            <div class="personal-intro-heading">
                <div>
                    <p class="personal-eyebrow"><?= $escape(date('d/m/Y')) ?> / Votre sélection</p>
                    <h1>Bonsoir, cinéphile.<br><?= $escape($greetingEnding) ?></h1>
                </div>
                <a class="personal-text-link" href="/Watchlist/public/genres.php">Mes goûts ↗</a>
            </div>

            <!-- Les genres associés à l'humeur affinent la recherche TMDB -->
            <form class="mood-panel" action="/Watchlist/public/home-custom.php" method="get">
                <div class="mood-row">
                    <p class="mood-label">Ce soir, j’ai envie de</p>
                    <div class="mood-options">
                        <?php foreach ($availableMoodLabels as $moodValue => $moodLabel): ?>
                            <label class="mood-option <?= $selectedMood === $moodValue ? 'is-selected' : '' ?>">
                                <input type="radio" name="mood" value="<?= $escape($moodValue) ?>" <?= $selectedMood === $moodValue ? 'checked' : '' ?> onchange="this.form.submit()">
                                <span><?= $escape($moodLabel) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="mood-tools">
                    <label for="intensity">Poids de mon humeur : <select id="intensity" name="intensity" onchange="this.form.submit()"><option value="decouverte" <?= $selectedIntensity === 'decouverte' ? 'selected' : '' ?>>Découverte</option><option value="equilibree" <?= $selectedIntensity === 'equilibree' ? 'selected' : '' ?>>Équilibrée</option><option value="ciblee" <?= $selectedIntensity === 'ciblee' ? 'selected' : '' ?>>Très ciblée</option></select></label>
                    <span class="mood-intensity-help">Choisis si tes goûts préférés ou ton envie du soir doivent peser le plus.</span>
                    <button class="mood-refresh" type="submit">Actualiser ma sélection ↗</button>
                </div>
            </form>
        </section>

        <!-- Recommandation principale -->
        <section class="featured-recommendation" aria-label="Recommandation principale">
            <div class="featured-visual">
                <?php if ($featuredMovie && $featuredImage !== ''): ?>
                    <img src="https://image.tmdb.org/t/p/w1280<?= $escape($featuredImage) ?>" alt="Visuel du film <?= $escape($featuredMovie['title']) ?>">
                <?php else: ?>
                    <div class="personal-image-placeholder" role="img" aria-label="Aucun film ne correspond à ces critères"></div>
                <?php endif; ?>
                <p class="visual-caption">Néons. Silence. Vertige existentiel.</p>
            </div>

            <div class="featured-copy">
                <p class="personal-eyebrow">Le film de votre soirée</p>
                <?php if ($featuredMovie): ?>
                    <h2><?= $escape($featuredDetails['title'] ?? $featuredMovie['title']) ?></h2>
                    <p class="featured-meta"><?= $escape(implode(' · ', $featuredMeta)) ?></p>
                    <p class="featured-reason">
                        <?php if ($preferredGenreNames !== []): ?>
                            Une proposition inspirée par vos goûts : <?= $escape(implode(', ', array_slice($preferredGenreNames, 0, 2))) ?>.
                        <?php else: ?>
                            Une proposition choisie selon vos films déjà vus.
                        <?php endif; ?>
                    </p>
                    <div class="featured-actions">
                        <a class="personal-button personal-button-orange" href="/Watchlist/public/suggestion.php">Explorer les suggestions <span aria-hidden="true">↗</span></a>
                        <a class="personal-button personal-button-dark" href="/Watchlist/public/watchlist.php">Déjà dans ma Watchlist</a>
                        <form action="/Watchlist/public/Movie-action.php" method="post">
                            <input type="hidden" name="tmdb_id" value="<?= (int) $featuredMovie['id'] ?>">
                            <input type="hidden" name="action" value="seen">
                            <button class="personal-button personal-button-dark" type="submit">✓ Déjà vu</button>
                        </form>
                    </div>
                    <a class="next-suggestion" href="/Watchlist/public/suggestion.php">Pas ce soir ? Explorer une autre proposition ↓</a>
                    <p class="featured-providers">Où regarder · <?= $featuredProviders !== [] ? $escape(implode(', ', $featuredProviders)) : 'Aucune offre renseignée' ?></p>
                <?php else: ?>
                    <h2>Aucune proposition pour le moment</h2>
                    <p class="featured-reason">Essaie une autre humeur ou ajoute des genres à tes goûts.</p>
                    <a class="personal-button personal-button-orange" href="/Watchlist/public/genres.php">Modifier mes goûts</a>
                <?php endif; ?>
            </div>
        </section>

        <!-- Recommandations secondaires -->
        <section class="other-recommendations">
            <div class="recommendations-heading">
                <h2>D’autres façons de s’évader.</h2>
                <a class="personal-text-link" href="/Watchlist/public/watchlist.php">Ma Watchlist complète ↗</a>
            </div>
            <div class="recommendation-grid">
                <?php foreach ($otherMovies as $movie): ?>
                    <?php $cardImage = !empty($movie['backdrop_path']) ? $movie['backdrop_path'] : ($movie['poster_path'] ?? ''); ?>
                    <article class="recommendation-card">
                        <?php if ($cardImage !== ''): ?>
                            <img src="https://image.tmdb.org/t/p/w780<?= $escape($cardImage) ?>" alt="Visuel du film <?= $escape($movie['title']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="recommendation-image-placeholder" role="img" aria-label="Visuel indisponible"></div>
                        <?php endif; ?>
                        <p class="personal-eyebrow">Pour vous</p>
                        <h3><?= $escape($movie['title']) ?></h3>
                        <p class="recommendation-meta"><?= $escape(substr((string) ($movie['release_date'] ?? ''), 0, 4)) ?></p>
                        <p class="recommendation-reason">Une proposition liée à vos goûts et à votre envie du soir.</p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Résumé de l'historique -->
        <section class="personal-history">
            <div>
                <p class="personal-eyebrow">Votre histoire de cinéma</p>
                <h2>Vos derniers films font la suite.</h2>
                <p><?= count($seenMovies) ?> film<?= count($seenMovies) === 1 ? '' : 's' ?> dans votre historique. Vos avis aideront CineMatch à affiner les prochaines propositions.</p>
            </div>
            <a class="personal-text-link" href="/Watchlist/public/watchlist.php?tab=seen">Voir mon historique ↗</a>
        </section>
    </main>

    <!-- Pied de page avec crédits des données -->
    <footer class="personal-footer">
        <div class="personal-footer-top">
            <a href="/Watchlist/public/Home.php" class="logo" aria-label="CineMatch, accueil">Cine<span>Match</span><i class="logo-dot" aria-hidden="true"></i></a>
            <nav aria-label="Liens de pied de page"><a href="/Watchlist/public/Home.php#concept">À propos</a><a href="#">Confidentialité</a><a href="#">Conditions</a><a href="mailto:contact@cinematch.example">Contact</a></nav>
        </div>
        <div class="personal-footer-bottom">
            <p>© 2026 CineMatch. Le cinéma, à votre rythme.</p>
            <p>Moins chercher. Plus ressentir.</p>
        </div>
        <p class="personal-credits">Données et images : TMDB · Disponibilités : JustWatch. CineMatch n’est ni approuvé ni certifié par TMDB.</p>
    </footer>
</body>
</html>





