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

    $user = (new UserRepository())->findByEmail($email);

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        header('Location: /Watchlist/public/watchlist.php');
        exit;
    }

    $errors[] = 'Adresse e-mail ou mot de passe incorrect.';
}

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>

<?php require __DIR__ . '/../templates/partials/header.php'; ?>

<section class="auth-page">
    <div class="auth-layout">
        <aside class="auth-promo">
            <div>
                <p class="eyebrow">REPRENDRE LE FIL</p>
                <h1>La suite<br>vous<br>attend.</h1>
            </div>

            <div class="auth-promo-image" role="img" aria-label="Visuel cinéma"></div>

            <p>À retrouver dans votre sélection · Blade Runner 2049</p>
        </aside>

        <div class="auth-content">
            <section class="auth-login" aria-labelledby="login-title">
                <h2 id="login-title">Content de vous revoir.</h2>
                <p>Retrouvez votre watchlist et une nouvelle envie de cinéma.</p>

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
                            autocomplete="current-password"
                            required
                        >
                    </div>

                    <div class="auth-options">
                        <span></span>
                        <a href="#password-recovery">Mot de passe oublié ?</a>
                    </div>

                    <button type="submit" class="btn-primary">Me connecter <span aria-hidden="true">↗</span></button>
                </form>

                <p class="auth-switch">Première séance ? <a href="/Watchlist/public/register.php">Créer mon compte ↗</a></p>
            </section>

            <section class="auth-panel auth-recovery" id="password-recovery" aria-labelledby="recovery-title">
                <h2 id="recovery-title">Un trou de mémoire ?</h2>
                <p>Recevez un lien par e-mail pour choisir un nouveau mot de passe.</p>

                <!-- Le traitement d'envoi du lien de réinitialisation reste à créer côté PHP. -->
                <div class="auth-field">
                    <label for="recovery-email">E-mail de récupération</label>
                    <input
                        type="email"
                        id="recovery-email"
                        placeholder="vous@exemple.fr"
                        autocomplete="email"
                        disabled
                    >
                </div>
                <button type="button" class="btn-secondary" disabled>Recevoir le lien</button>
                <p class="auth-note">La récupération du mot de passe sera activée prochainement.</p>
            </section>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../templates/partials/footer.php'; ?>


