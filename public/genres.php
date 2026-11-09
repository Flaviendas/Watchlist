<?php
require __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->load();

use App\Repository\UserGenreRepository;
use App\Repository\UserPreferenceRepository;
use App\Service\TmdbService;

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /Watchlist/public/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$genreRepository = new UserGenreRepository();
$preferenceRepository = new UserPreferenceRepository();
$tmdb = new TmdbService($_ENV['TMDB_API_KEY']);
$genres = $tmdb->getGenres();
$selected = $genreRepository->findByUser($userId);
$preferences = $preferenceRepository->findByUser($userId);
$errors = [];
$moodLabels = [
    'evader' => 'M’évader',
    'rire' => 'Rire',
    'frissonner' => 'Frissonner',
    'etre_emu' => 'Être ému·e',
    'surprendre' => 'Me surprendre',
];
$moodGenreMap = [
    'evader' => [12, 14, 878],
    'rire' => [35],
    'frissonner' => [27, 53, 9648],
    'etre_emu' => [18, 10749],
    'surprendre' => [9648, 878, 53],
];
$intensityLabels = [
    'decouverte' => 'Découverte',
    'equilibree' => 'Équilibrée',
    'ciblee' => 'Très ciblée',
];
$selectedMood = (string) $preferences['preferred_mood'];
$selectedIntensity = (string) $preferences['recommendation_intensity'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validGenreIds = array_map('intval', array_column($genres, 'id'));
    $postedIds = array_map('intval', $_POST['genres'] ?? []);
    $selected = array_values(array_unique(array_intersect($postedIds, $validGenreIds)));
    $selectedMood = (string) ($_POST['mood'] ?? '');
    $selectedIntensity = (string) ($_POST['intensity'] ?? '');

    if ($selected === []) {
        $errors[] = 'Choisis au moins un genre pour personnaliser tes suggestions.';
    }
    if (!isset($moodLabels[$selectedMood])) {
        $errors[] = 'Choisis une humeur pour ta sélection.';
    }
    if (!isset($intensityLabels[$selectedIntensity])) {
        $errors[] = 'Choisis le niveau de personnalisation.';
    }
    if (isset($moodGenreMap[$selectedMood]) && array_intersect($moodGenreMap[$selectedMood], $selected) === []) {
        $errors[] = 'Cette envie ne correspond pas aux genres choisis.';
    }

    if ($errors === []) {
        sort($selected);
        $genreRepository->replace($userId, $selected);
        $signature = hash('sha256', implode(',', $selected) . '|' . $selectedMood . '|' . $selectedIntensity);
        $preferenceRepository->save($userId, $selectedMood, $selectedIntensity, $signature);
        header('Location: /Watchlist/public/Home-custom.php');
        exit;
    }
}

$selectedGenreNames = [];
foreach ($genres as $genre) {
    if (in_array((int) $genre['id'], $selected, true)) {
        $selectedGenreNames[] = (string) $genre['name'];
    }
}
$firstSetup = $selected === [] || isset($_GET['setup']);
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<?php require __DIR__ . '/../templates/partials/header.php'; ?>

<section class="taste-settings-page">
    <p class="eyebrow">UNE SÉLECTION À TON IMAGE</p>
    <h1>Mes goûts cinéma</h1>
    <p class="taste-settings-intro">Tes choix servent à remplir automatiquement ta Watchlist avec jusqu’à 10 films et à renouveler le film du soir.</p>

    <?php if ($errors !== []): ?>
        <div class="form-error" role="alert">
            <?php foreach ($errors as $error): ?><p><?= $escape($error) ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="taste-settings-summary">
        <div>
            <p class="taste-summary-label">Mes genres</p>
            <?php if ($selectedGenreNames !== []): ?>
                <div class="taste-summary-chips">
                    <?php foreach ($selectedGenreNames as $name): ?><span><?= $escape($name) ?></span><?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>Choisis quelques genres pour lancer ta sélection.</p>
            <?php endif; ?>
        </div>
        <button class="personal-button personal-button-orange" id="open-taste-modal" type="button">
            <?= $firstSetup ? 'Commencer ma sélection' : 'Modifier mes réponses' ?>
        </button>
    </div>
</section>

<dialog class="taste-modal" id="taste-modal" aria-labelledby="taste-modal-title">
    <form action="" method="post" class="taste-modal-form" id="genre-form">
        <header class="taste-modal-header">
            <div>
                <p class="eyebrow">POUR PERSONNALISER TA SÉLECTION</p>
                <h2 id="taste-modal-title"><?= $firstSetup ? 'Faisons connaissance.' : 'Tes préférences' ?></h2>
            </div>
            <button class="taste-modal-close" type="button" aria-label="Fermer la fenêtre" data-close-taste-modal>×</button>
        </header>

        <section class="taste-question">
            <p class="taste-step">01 / TES GENRES</p>
            <h3>Quels genres tu aimes regarder ?</h3>
            <div class="genre-toolbar">
                <label class="genre-search-label" for="genre-search">Rechercher un genre</label>
                <input class="genre-search" type="search" id="genre-search" placeholder="Ex. aventure, comédie…">
                <p class="genre-selected-count" aria-live="polite"><strong data-selected-count><?= count($selected) ?></strong> choisi(s)</p>
            </div>
            <div class="genre-selected-summary" data-selected-summary aria-live="polite"></div>
            <?php if ($genres === []): ?>
                <p class="empty-text">Les genres ne sont pas disponibles pour le moment. Réessaie un peu plus tard.</p>
            <?php else: ?>
                <div class="genre-grid">
                    <?php foreach ($genres as $genre): ?>
                        <?php $genreId = (int) $genre['id']; $genreName = (string) $genre['name']; ?>
                        <label class="genre-option" data-genre="<?= $escape(mb_strtolower($genreName)) ?>">
                            <input type="checkbox" name="genres[]" value="<?= $genreId ?>" <?= in_array($genreId, $selected, true) ? 'checked' : '' ?>>
                            <span><?= $escape($genreName) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <fieldset class="taste-question taste-choice-group">
            <legend class="taste-step">02 / TON ENVIE</legend>
            <h3>Qu’est-ce qui te ferait plaisir ce soir ?</h3>
            <div class="taste-choice-grid">
                <?php foreach ($moodLabels as $value => $label): ?>
                    <label class="taste-choice mood-choice" data-mood-genres="<?= $escape(implode(',', $moodGenreMap[$value])) ?>">
                        <input type="radio" name="mood" value="<?= $escape($value) ?>" <?= $selectedMood === $value ? 'checked' : '' ?> required>
                        <span><?= $escape($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <fieldset class="taste-question taste-choice-group">
            <legend class="taste-step">03 / LA PERSONNALISATION</legend>
            <h3>À quel point l’humeur doit guider les films ?</h3>
            <div class="taste-choice-grid taste-intensity-grid">
                <?php foreach ($intensityLabels as $value => $label): ?>
                    <label class="taste-choice">
                        <input type="radio" name="intensity" value="<?= $escape($value) ?>" <?= $selectedIntensity === $value ? 'checked' : '' ?> required>
                        <span><?= $escape($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="taste-modal-hint">« Découverte » favorise tes genres. « Très ciblée » privilégie ton envie du soir.</p>
        </fieldset>

        <footer class="taste-modal-footer">
            <button class="personal-button personal-button-dark" type="button" data-close-taste-modal>Plus tard</button>
            <button class="personal-button personal-button-orange" type="submit">Enregistrer et découvrir ↗</button>
        </footer>
    </form>
</dialog>
<script src="/Watchlist/public/assets/js/genres.js" defer></script>
<script>
    const tasteModal = document.querySelector('#taste-modal');
    document.querySelector('#open-taste-modal')?.addEventListener('click', () => tasteModal.showModal());
    document.querySelectorAll('[data-close-taste-modal]').forEach((button) => {
        button.addEventListener('click', () => tasteModal.close());
    });
    window.addEventListener('DOMContentLoaded', () => tasteModal.showModal());
</script>

<?php require __DIR__ . '/../templates/partials/footer.php'; ?>


