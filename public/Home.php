<?php
use App\Service\TmdbService;

require __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->load();

$tmdb = new TmdbService($_ENV['TMDB_API_KEY']);
$movies = array_slice($tmdb->getPopularMovies(), 0, 4);
$featuredMovie = $movies[0] ?? null;
$exampleMovies = array_slice($movies, 1, 3);
$featuredDetails = $featuredMovie
    ? $tmdb->getMovie((int) $featuredMovie['id'])
    : [];

$escape = static fn ($value): string => htmlspecialchars(
    (string) $value,
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$runtime = (int) ($featuredDetails['runtime'] ?? 0);
$runtimeLabel = $runtime > 0
    ? intdiv($runtime, 60) . ' h ' . str_pad((string) ($runtime % 60), 2, '0', STR_PAD_LEFT)
    : '';
$featuredGenres = implode(', ', array_column($featuredDetails['genres'] ?? [], 'name'));
$featuredMeta = array_filter([
    substr((string) ($featuredDetails['release_date'] ?? ''), 0, 4),
    $runtimeLabel,
    $featuredGenres,
]);
?>
<?php
$pageTitle = 'CineMatch — Votre prochain coup de cinéma';
$pageDescription = 'CineMatch vous aide à choisir votre prochain film selon vos goûts et votre envie du soir.';
require __DIR__ . '/../templates/partials/header.php';
?>
<!-- Promesse principale : texte à gauche, film mis en avant à droite -->
        <section class="hero section-shell" id="concept">
            <div class="hero-copy">
                <p class="eyebrow">Votre prochain coup de cinéma</p>
                <h1>Pour<br>vous.</h1>
                <p class="hero-description">Vos goûts, votre envie du soir, les films qui vous ont marqué. Enfin une sélection qui vous ressemble, pas une liste à parcourir sans fin.</p>
                <a class="button button-orange" href="/Watchlist/public/register.php">Trouver mon prochain film <span aria-hidden="true">↗</span></a>
                <p class="hero-note">Un compte gratuit. Votre curiosité fait le reste.</p>
            </div>

            <div class="featured-film">
                <?php if ($featuredMovie): ?>
                    <?php $featuredImage = $featuredMovie['backdrop_path'] ?: $featuredMovie['poster_path']; ?>
                    <?php if (!empty($featuredImage)): ?>
                        <img class="featured-image" src="https://image.tmdb.org/t/p/w1280<?= $escape($featuredImage) ?>" alt="Visuel du film <?= $escape($featuredMovie['title']) ?>">
                    <?php else: ?>
                        <div class="featured-image image-placeholder" role="img" aria-label="Visuel indisponible"></div>
                    <?php endif; ?>
                    <div class="featured-caption">
                        <p class="eyebrow">Une envie d’ailleurs ?</p>
                        <p class="selection-number">Sélection CineMatch / 01</p>
                    </div>
                    <h2><?= $escape($featuredDetails['title'] ?? $featuredMovie['title']) ?></h2>
                    <p class="featured-meta"><?= $escape(implode(' · ', $featuredMeta)) ?></p>
                <?php else: ?>
                    <div class="featured-image image-placeholder" role="img" aria-label="Visuel indisponible"></div>
                    <p class="eyebrow">Une envie d’ailleurs ?</p>
                    <h2>Un film à découvrir</h2>
                <?php endif; ?>
            </div>

            <div class="hero-signature">
                <span>Pas un classement. Une rencontre.</span>
                <a href="#comment-ca-marche">Découvrir CineMatch ↓</a>
            </div>
        </section>

        <!-- Présentation du principe et des trois étapes -->
        <section class="how-section section-shell" id="comment-ca-marche">
            <div class="section-heading">
                <p class="eyebrow">01 / Le principe</p>
                <h2>Le bon film.<br>Au bon moment.</h2>
                <p>Le meilleur film n’est pas toujours le même. CineMatch relie ce que vous aimez à ce que vous avez envie de ressentir, ici et maintenant.</p>
            </div>
            <div class="steps-grid">
                <article class="step-card">
                    <span class="step-number">01</span>
                    <h3>Donnez le ton</h3>
                    <p>Quelques goûts et quelques films aimés suffisent pour commencer.</p>
                </article>
                <article class="step-card">
                    <span class="step-number">02</span>
                    <h3>Écoutez votre envie</h3>
                    <p>Un soir léger, une claque visuelle ou une histoire qui remue ?</p>
                </article>
                <article class="step-card">
                    <span class="step-number">03</span>
                    <h3>Laissez-vous surprendre</h3>
                    <p>Une recommandation expliquée. Et des alternatives si ce n’est pas le soir.</p>
                </article>
            </div>
        </section>

        <!-- Aperçu de films issus de TMDB -->
        <section class="selection-section section-shell" id="selection">
            <div class="section-title-row">
                <div>
                    <p class="eyebrow">02 / Ouvrez le champ</p>
                    <h2>Pas plus de films.<br>Les vôtres.</h2>
                    <p class="section-lead">Un aperçu. Votre sélection sera personnelle.</p>
                </div>
                <a class="accent-link" href="/Watchlist/public/register.php">Créer ma sélection ↗</a>
            </div>

            <div class="example-grid">
                <?php foreach ($exampleMovies as $movie): ?>
                    <article class="example-card">
                        <?php $image = $movie['backdrop_path'] ?: $movie['poster_path']; ?>
                        <?php if (!empty($image)): ?>
                            <img src="https://image.tmdb.org/t/p/w780<?= $escape($image) ?>" alt="Visuel du film <?= $escape($movie['title']) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="example-image image-placeholder" role="img" aria-label="Visuel indisponible"></div>
                        <?php endif; ?>
                        <p class="eyebrow">Pour vous</p>
                        <h3><?= $escape($movie['title']) ?></h3>
                        <p class="movie-year"><?= $escape(substr((string) ($movie['release_date'] ?? ''), 0, 4)) ?></p>
                        <p class="movie-reason">Une découverte choisie selon vos goûts.</p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Mise en avant des goûts et des genres -->
        <section class="taste-section section-shell">
            <div class="taste-copy">
                <p class="eyebrow">03 / Votre univers</p>
                <h2>Vous avez du goût.<br>On part de là.</h2>
                <p>Genres, cinéastes, atmosphères : racontez ce qui vous attire. Aimez les thrillers et les comédies romantiques. Rien ne vous enferme dans une case.</p>
            </div>
            <div class="taste-panel">
                <p class="eyebrow">Quelques affinités, mille possibilités</p>
                <div class="genre-chips" aria-label="Exemples de genres">
                    <span>Science-fiction</span><span>Thriller</span><span>Drame</span>
                </div>
                <div class="taste-notes">
                    <p>Les images qui restent.</p>
                    <p>Les histoires qui questionnent.</p>
                    <p>Votre profil évolue avec chaque film et chaque avis.</p>
                </div>
            </div>
        </section>

        <!-- Présentation du choix d'humeur du soir -->
        <section class="mood-section section-shell">
            <div class="section-heading">
                <p class="eyebrow">04 / L’humeur compte</p>
                <h2>Même vous.<br>Une autre soirée.</h2>
                <p>Une envie de rire, de frissonner ou de vous évader ? Choisissez votre envie du soir pour orienter votre sélection.</p>
            </div>
            <div class="mood-panel">
                <p class="panel-label">Ce soir, j’ai envie de…</p>
                <div class="mood-chips">
                    <span>M’évader</span><span>Rire</span><span>Frissonner</span><span>Être ému·e</span>
                </div>
                <p class="muted-note">Une envie du jour, pas une étiquette pour toujours.</p>
            </div>
        </section>

        <!-- Exemple d'historique de films vus -->
        <section class="history-section section-shell">
            <div class="history-copy">
                <p class="eyebrow">05 / Votre histoire de cinéma</p>
                <h2>On ne repart<br>pas de zéro.</h2>
                <p>Ajoutez vos films déjà vus un par un. Vos goûts et vos découvertes passées aident à composer votre prochaine sélection.</p>
                <a class="accent-link" href="/Watchlist/public/register.php">Ajouter mes films déjà vus ↗</a>
            </div>
            <div class="history-list">
                <div><span>Blade Runner</span><span>1982 · Aimé</span></div>
                <div><span>Interstellar</span><span>2014 · Aimé</span></div>
                <div><span>Lost in Translation</span><span>2003 · Aimé</span></div>
            </div>
        </section>

        <!-- Mise en avant de la watchlist automatique -->
        <section class="watchlist-promo section-shell">
            <div>
                <p class="eyebrow">06 / Rien ne se perd</p>
                <h2>Votre prochaine séance<br>est déjà dans la liste.</h2>
                <p>CineMatch enrichit automatiquement votre watchlist selon vos affinités. Chaque suggestion a sa raison. Gardez-la, marquez-la comme vue ou retirez-la : vous décidez.</p>
            </div>
            <div class="watchlist-cta">
                <a class="button button-orange" href="/Watchlist/public/register.php">Créer ma watchlist <span aria-hidden="true">↗</span></a>
                <p>Votre sélection, toujours sous votre contrôle.</p>
            </div>
        </section>

        <!-- Trois bénéfices du service -->
        <section class="benefits-section section-shell">
            <p class="eyebrow">07 / Pour le plaisir de regarder</p>
            <div class="benefits-grid">
                <article><h3>Moins d’hésitation</h3><p>Une sélection resserrée pour passer moins de temps à choisir.</p></article>
                <article><h3>Plus de découvertes</h3><p>Des chemins de traverse, au-delà de vos genres habituels.</p></article>
                <article><h3>Des raisons, pas un score</h3><p>Comprenez ce qui relie chaque film à votre univers.</p></article>
            </div>
        </section>

        <!-- Questions fréquentes -->
        <section class="faq-section section-shell">
            <div class="section-heading">
                <p class="eyebrow">08 / Avant la séance</p>
                <h2>Une dernière<br>question ?</h2>
                <p>Tout ce qu’il faut savoir avant d’entrer dans votre salle.</p>
            </div>
            <div class="faq-list">
                <details><summary>Comment ma sélection est-elle personnalisée ?</summary><p>Elle se construit à partir de vos goûts, de votre envie du soir et de vos films déjà vus. Vos avis l’aident ensuite à évoluer.</p></details>
                <details><summary>Comment ajouter mes films déjà vus ?</summary><p>Depuis « Déjà vus » dans la watchlist, recherchez et ajoutez vos films un par un.</p></details>
                <details><summary>Est-ce que je peux regarder les films ici ?</summary><p>Non. CineMatch est un service de recommandation, pas une plateforme de diffusion. Il vous aide à choisir votre prochaine séance.</p></details>
                <details><summary>Puis-je modifier la watchlist automatique ?</summary><p>Bien sûr. Retirez une proposition ou marquez un film comme déjà vu. Votre liste reste sous votre contrôle.</p></details>
            </div>
        </section>

        <!-- Appel final à créer un compte -->
        <section class="final-cta section-shell">
            <p class="eyebrow">09 / Le générique peut attendre</p>
            <h2>Ce soir, regardez<br>quelque chose qui reste.</h2>
            <p>Votre prochain film commence par vous.</p>
            <a class="button button-orange" href="/Watchlist/public/register.php">Créer mon compte gratuit <span aria-hidden="true">↗</span></a>
        </section>
    </main>

    <!-- Pied de page et crédits des données -->
    <footer class="public-footer">
        <div class="footer-top">
            <a class="brand" href="/Watchlist/public/Home.php" aria-label="CineMatch, accueil">
                <span class="brand-cine">Cine</span><span class="brand-match">Match</span><i aria-hidden="true"></i>
            </a>
            <nav aria-label="Liens de pied de page">
                <a href="#concept">À propos</a>
                <a href="#">Confidentialité</a>
                <a href="#">Conditions</a>
                <a href="mailto:contact@cinematch.example">Contact</a>
            </nav>
        </div>
        <div class="footer-bottom">
            <p>© 2026 CineMatch. Le cinéma, à votre rythme.</p>
            <p>Moins chercher. Plus ressentir.</p>
        </div>
        <p class="credits">Données et images : TMDB · Disponibilités : JustWatch. CineMatch n’est ni approuvé ni certifié par TMDB.</p>
    </footer>
</body>
</html>



