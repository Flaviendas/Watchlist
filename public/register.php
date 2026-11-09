<?php
require __DIR__ . '/../vendor/autoload.php';
Dotenv\Dotenv::createImmutable(__DIR__ . '/..')->load();

use App\Repository\UserRepository;

session_start();

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Adresse e-mail invalide.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Les mots de passe ne correspondent pas.';
    }

    if ($errors === []) {
        $repository = new UserRepository();

        if ($repository->findByEmail($email)) {
            $errors[] = 'Cette adresse e-mail est déjà utilisée.';
        } else {
            $repository->create($email, password_hash($password, PASSWORD_DEFAULT));
            header('Location: /Watchlist/public/login.php');
            exit;
        }
    }
}

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>

<?php require __DIR__ . '/../templates/partials/header.php'; ?>

<section class="auth-page">
    <div class="auth-layout">
        <aside class="auth-promo">
            <div>
                <p class="eyebrow">VOTRE FAUTEUIL VOUS ATTEND</p>
                <h1>Tout commence<br>par un bon film.</h1>
            </div>

            <div class="auth-promo-image" role="img" aria-label="Visuel cinéma"></div>

            <p class="auth-promo-description">
                Des films qui font écho à vos goûts. Des découvertes qui les bousculent.
                Et toujours une bonne raison d’appuyer sur lecture.
            </p>

            <ul class="auth-benefits">
                <li>Sélection personnelle</li>
                <li>Watchlist automatique</li>
            </ul>
        </aside>

        <div class="auth-content">
            <section class="auth-register-panel" aria-labelledby="register-title">
                <header class="auth-register-heading">
                    <h2 id="register-title">Créer mon compte</h2>
                    <p>Déjà un compte ? <a href="/Watchlist/public/login.php">Se connecter ↗</a></p>
                </header>

                <?php if ($errors !== []): ?>
                    <div class="form-error" role="alert">
                        <?php foreach ($errors as $error): ?>
                            <p><?= $escape($error) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form class="auth-form" action="" method="post">
                    <div class="auth-field">
                        <label for="email">Adresse e-mail</label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?= $escape($email) ?>"
                            autocomplete="email"
                            required
                        >
                    </div>

                    <div class="auth-field">
                        <label for="password">Mot de passe</label>
                        <input
                            type="password"
                            id="password"
                            name="password"
                            autocomplete="new-password"
                            minlength="8"
                            required
                        >
                        <small>8 caractères minimum.</small>
                    </div>

                    <div class="auth-field">
                        <label for="password_confirm">Confirmer le mot de passe</label>
                        <input
                            type="password"
                            id="password_confirm"
                            name="password_confirm"
                            autocomplete="new-password"
                            minlength="8"
                            required
                        >
                    </div>

                    <button type="submit" class="btn-primary">Créer mon compte gratuit <span aria-hidden="true">↗</span></button>
                </form>
            </section>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../templates/partials/footer.php'; ?>

