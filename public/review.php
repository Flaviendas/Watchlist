<?php
require __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->load();

use App\Repository\MovieReviewRepository;
use App\Repository\UserMovieRepository;
use App\Service\TmdbService;

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /Watchlist/public/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$tmdbId = filter_input(INPUT_GET, 'tmdb_id', FILTER_VALIDATE_INT);
if (!$tmdbId || $tmdbId < 1) {
    header('Location: /Watchlist/public/watchlist.php?tab=seen');
    exit;
}

$movieRepository = new UserMovieRepository();
if (!$movieRepository->isSeen($userId, $tmdbId)) {
    header('Location: /Watchlist/public/movie.php?id=' . $tmdbId);
    exit;
}

$tmdb = new TmdbService($_ENV['TMDB_API_KEY']);
$movie = $tmdb->getMovieWithCredits($tmdbId);
if (empty($movie['id'])) {
    http_response_code(404);
}

$reviewRepository = new MovieReviewRepository();
$review = $reviewRepository->find($userId, $tmdbId);
$errors = [];
$feelingOptions = ['liked', 'mixed', 'disliked'];
$emotionOptions = ['Émerveillement', 'Mélancolie', 'Tension', 'Joie', 'Surprise', 'Apaisement', 'Inconfort'];
$intensityOptions = ['light', 'moderate', 'strong', 'very_strong'];
$intensityLabels = [
    'light' => 'Légère',
    'moderate' => 'Modérée',
    'strong' => 'Forte',
    'very_strong' => 'Très forte',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $feeling = (string) ($_POST['feeling'] ?? '');
    $emotions = array_values(array_intersect($emotionOptions, (array) ($_POST['emotions'] ?? [])));
    $intensity = (string) ($_POST['intensity'] ?? '');
    $intensity = in_array($intensity, $intensityOptions, true) ? $intensity : null;
    $note = trim((string) ($_POST['note'] ?? ''));

    if (!in_array($feeling, $feelingOptions, true)) {
        $errors[] = 'Choisis le ressenti qui correspond le mieux à ta séance.';
    }
    if (mb_strlen($note) > 1000) {
        $errors[] = 'Ton commentaire doit faire 1 000 caractères maximum.';
    }

    if ($errors === []) {
        $movieGenreIds = array_map('intval', array_column($movie['genres'] ?? [], 'id'));
        $reviewRepository->save($userId, $tmdbId, $feeling, $emotions, $intensity, $note, $movieGenreIds);
        header('Location: /Watchlist/public/watchlist.php?tab=seen&review_saved=1');
        exit;
    }

    $review = [
        'feeling' => $feeling,
        'emotions' => $emotions,
        'intensity' => $intensity,
        'note' => $note,
    ];
}

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$title = $movie['title'] ?? 'Film';
$year = !empty($movie['release_date']) ? substr($movie['release_date'], 0, 4) : 'Année inconnue';
$runtime = !empty($movie['runtime']) ? ((int) $movie['runtime']) . ' min' : 'Durée inconnue';
$director = '';
foreach ($movie['credits']['crew'] ?? [] as $crewMember) {
    if (($crewMember['job'] ?? '') === 'Director') {
        $director = $crewMember['name'];
        break;
    }
}
?>

<?php require __DIR__ . '/../templates/partials/header.php'; ?>

<section class="review-page">
    <a class="review-back" href="/Watchlist/public/watchlist.php?tab=seen">← Retour à mon historique</a>

    <?php if (empty($movie['id'])): ?>
        <div class="search-empty">
            <h1>Film introuvable</h1>
            <p>Impossible de charger ce film depuis TMDB.</p>
        </div>
    <?php else: ?>
        <div class="review-layout">
            <aside class="review-film">
                <p class="eyebrow">APRÈS LE GÉNÉRIQUE</p>
                <h1><?= $escape($title) ?></h1>

                <?php if (!empty($movie['backdrop_path'])): ?>
                    <img class="review-film-image" src="https://image.tmdb.org/t/p/w780<?= $escape($movie['backdrop_path']) ?>" alt="Image du film <?= $escape($title) ?>">
                <?php elseif (!empty($movie['poster_path'])): ?>
                    <img class="review-film-image" src="https://image.tmdb.org/t/p/w500<?= $escape($movie['poster_path']) ?>" alt="Affiche de <?= $escape($title) ?>">
                <?php else: ?>
                    <div class="review-film-image image-fallback">Image indisponible</div>
                <?php endif; ?>

                <p class="review-film-meta">
                    <?= $director !== '' ? $escape($director) . ' · ' : '' ?>
                    <?= $escape($year) ?> · <?= $escape($runtime) ?>
                    <?php if (!empty($movie['genres'])): ?>
                        <br><?= $escape(implode(' · ', array_column($movie['genres'], 'name'))) ?>
                    <?php endif; ?>
                </p>
                <p class="review-film-note">Pas de bonne ou de mauvaise réponse. Ton ressenti aide CineMatch à mieux choisir la suite.</p>
            </aside>

            <form class="review-form" action="" method="post">
                <?php if ($errors !== []): ?>
                    <div class="form-error" role="alert">
                        <?php foreach ($errors as $error): ?>
                            <p><?= $escape($error) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <fieldset class="review-group">
                    <legend class="review-group-heading">
                        <span><strong>Alors, ce film ?</strong></span>
                    </legend>
                    <div class="rating-options">
                        <?php
                        $feelings = [
                            'liked' => 'Aimé',
                            'mixed' => 'Mitigé',
                            'disliked' => 'Pas aimé',
                        ];
                        foreach ($feelings as $value => $label):
                        ?>
                            <div class="rating-option">
                                <input type="radio" id="feeling-<?= $value ?>" name="feeling" value="<?= $value ?>" <?= ($review['feeling'] ?? '') === $value ? 'checked' : '' ?> required>
                                <label for="feeling-<?= $value ?>"><?= $escape($label) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <fieldset class="review-group">
                    <legend class="review-group-heading">
                        <strong>Qu’est-ce qui reste ?</strong>
                        <span class="review-optional">Facultatif · plusieurs choix</span>
                    </legend>
                    <div class="emotion-options">
                        <?php foreach ($emotionOptions as $index => $emotion): ?>
                            <?php $emotionId = 'emotion-' . $index; ?>
                            <div class="emotion-option">
                                <input type="checkbox" id="<?= $emotionId ?>" name="emotions[]" value="<?= $escape($emotion) ?>" <?= in_array($emotion, $review['emotions'] ?? [], true) ? 'checked' : '' ?>>
                                <label for="<?= $emotionId ?>"><?= $escape($emotion) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <fieldset class="review-group">
                    <legend class="review-group-heading">
                        <strong>Avec quelle intensité ?</strong>
                        <span class="review-optional">Facultatif</span>
                    </legend>
                    <div class="intensity-options">
                        <?php foreach ($intensityLabels as $value => $label): ?>
                            <div class="intensity-option">
                                <input type="radio" id="intensity-<?= $value ?>" name="intensity" value="<?= $value ?>" <?= ($review['intensity'] ?? '') === $value ? 'checked' : '' ?>>
                                <label for="intensity-<?= $value ?>"><?= $escape($label) ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </fieldset>

                <div class="review-group">
                    <label class="review-group-heading" for="review-note">
                        <strong>Un mot sur ta séance ?</strong>
                        <span class="review-optional">Facultatif</span>
                    </label>
                    <textarea class="review-textarea" id="review-note" name="note" maxlength="1000" placeholder="Écris quelques mots sur ce que tu as ressenti…"><?= $escape($review['note'] ?? '') ?></textarea>
                    <div class="review-counter"><span>1 000 caractères maximum</span></div>
                </div>

                <div class="review-actions">
                    <button type="submit">Enregistrer mon avis</button>
                    <a href="/Watchlist/public/watchlist.php?tab=seen">Plus tard</a>
                </div>

                <p class="review-disclaimer">Ton avis sera associé à ce film dans ton historique. Tu pourras le modifier quand tu le souhaites.</p>
            </form>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../templates/partials/footer.php'; ?>



