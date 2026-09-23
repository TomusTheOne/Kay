<?php
declare(strict_types=1);

require __DIR__ . '/_boot.php';

// No account yet: the first one is made on the setup page.
if (kay_admin_count($db) === 0) {
    kay_redirect('setup.php');
}
if (kay_current_admin($db) !== null) {
    kay_redirect('index.php');
}

$error = null;
$email = '';
$next  = kay_safe_next((string) ($_POST['next'] ?? $_GET['next'] ?? ''));

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email  = (string) ($_POST['email'] ?? '');
    $result = kay_login_check($db, $email, (string) ($_POST['password'] ?? ''));
    if ($result['ok']) {
        kay_session_open($db, $result['user']['id']);
        kay_redirect($next);
    }
    $error = $result['error'] === 'throttled'
        ? 'Trop de tentatives. Réessayez dans un quart d’heure.'
        : 'E-mail ou mot de passe incorrect.';
}

kay_page_start('Connexion', '', null);
?>
<section class="auth">
  <img class="auth__mark" src="/assets/brand/icon.svg" alt="" width="56" height="53">
  <h1>Kay Diving <span>admin</span></h1>
  <?= kay_error_box($error) ?>
  <form method="post" class="stack">
    <input type="hidden" name="next" value="<?= kay_h($next) ?>">
    <label class="field"><span>E-mail</span>
      <input type="email" name="email" value="<?= kay_h($email) ?>" autocomplete="username" required autofocus></label>
    <label class="field"><span>Mot de passe</span>
      <input type="password" name="password" autocomplete="current-password" required></label>
    <button class="btn btn--primary btn--block">Se connecter</button>
  </form>
  <p class="muted small">Mot de passe oublié ? Un autre compte admin peut le changer depuis
    « Compte », ou utilisez la <a href="setup.php">page de configuration</a> avec le jeton de
    <code>kay-config.php</code>.</p>
</section>
<?php kay_page_end();
