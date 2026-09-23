<?php
declare(strict_types=1);

/**
 * The first account, or a forgotten password.
 *
 * Both need the phrase in kay-admin-token.txt, uploaded by FTP beside
 * kay-config.php (see kay_setup_token()). Without it, the first person to
 * find /admin/setup.php after a deploy would own the admin; with it, only
 * someone who can already reach the database password can. The same throttle
 * as the login applies, so the phrase cannot be guessed.
 */

require __DIR__ . '/_boot.php';

$misplaced = kay_setup_token_misplaced();
$token  = $misplaced ? null : kay_setup_token();
$first  = kay_admin_count($db) === 0;
$error  = null;
$done   = false;
$values = ['email' => '', 'name' => ''];

if ($token !== null && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $values = [
        'email' => mb_strtolower(trim((string) ($_POST['email'] ?? ''))),
        'name'  => trim((string) ($_POST['name'] ?? '')),
    ];
    $password = (string) ($_POST['password'] ?? '');

    if (kay_login_throttled($db, 'setup')) {
        $error = 'Trop de tentatives. Réessayez dans un quart d’heure.';
    } elseif (!hash_equals($token, trim((string) ($_POST['token'] ?? '')))) {
        kay_login_failed($db, 'setup');
        $error = 'La phrase ne correspond pas à celle du fichier kay-admin-token.txt.';
    } elseif (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse e-mail invalide.';
    } elseif (($problem = kay_password_problem($password, $values['email'])) !== null) {
        $error = $problem;
    } elseif ($first) {
        if (mb_strlen($values['name']) < 2) {
            $error = 'Indiquez votre nom.';
        } else {
            $id = kay_admin_create($db, $values['email'], $values['name'], $password);
            kay_session_open($db, $id);
            kay_redirect('index.php');
        }
    } else {
        $s = $db->prepare('SELECT id FROM admin_users WHERE email = ?');
        $s->execute([$values['email']]);
        $id = $s->fetchColumn();
        if ($id === false) {
            $error = 'Aucun compte admin avec cette adresse.';
        } else {
            kay_admin_set_password($db, (int) $id, $password);
            $done = true;
        }
    }
}

kay_page_start($first ? 'Premier compte' : 'Mot de passe oublié', '', null);
?>
<section class="auth">
  <img class="auth__mark" src="/assets/brand/icon.svg" alt="" width="56" height="53">
  <h1><?= $first ? 'Créer le compte admin' : 'Nouveau mot de passe' ?></h1>

<?php if ($misplaced): ?>
  <p class="alert">Le fichier <code>kay-admin-token.txt</code> a été déposé dans le dossier
    <code>www</code>, où n’importe qui pourrait le lire. Il n’est pas utilisé.</p>
  <p>Avec votre logiciel FTP, supprimez-le du dossier <code>www</code>, puis déposez-le un niveau
    au-dessus, à côté de <code>kay-config.php</code>. Rechargez ensuite cette page.</p>
<?php elseif ($token === null): ?>
  <p>Pour <?= $first ? 'créer le premier compte' : 'réinitialiser un mot de passe' ?>, il faut
    d’abord prouver que vous avez accès à l’hébergement :</p>
  <ol class="steps">
    <li>Sur votre ordinateur, créez un fichier texte nommé <code>kay-admin-token.txt</code>
      (Bloc-notes sur Windows, TextEdit en « texte brut » sur Mac).</li>
    <li>Écrivez-y une phrase de votre choix d’au moins 20 caractères, par exemple une phrase
      que vous seul connaissez. Enregistrez.</li>
    <li>Dans votre logiciel FTP, ouvrez le dossier où se trouve <code>kay-config.php</code>
      (celui qui contient aussi le dossier <code>www</code>) et déposez-y le fichier.
      <strong>Pas dans <code>www</code>.</strong></li>
    <li>Rechargez cette page et tapez la même phrase.</li>
  </ol>
  <p class="muted small">Ce fichier ne touche à rien d’autre : une faute de frappe dedans ne peut
    pas casser le site. Une fois le compte créé, vous pouvez le supprimer par FTP.</p>
<?php elseif ($done): ?>
  <p class="flash">Mot de passe changé. Toutes les sessions de ce compte ont été fermées.</p>
  <p><a class="btn btn--primary btn--block" href="login.php">Se connecter</a></p>
<?php else: ?>
  <?= kay_error_box($error) ?>
  <p class="muted"><?= $first
      ? 'Aucun compte n’existe encore. Celui-ci pourra ensuite en créer d’autres depuis « Compte ».'
      : 'Choisissez un nouveau mot de passe pour un compte existant.' ?></p>
  <form method="post" class="stack">
    <label class="field"><span>Phrase secrète</span>
      <input type="text" name="token" autocomplete="off" autocapitalize="off" spellcheck="false" required>
      <small>La phrase écrite dans <code>kay-admin-token.txt</code>, à l’identique.</small></label>
<?php if ($first): ?>
    <label class="field"><span>Votre nom</span>
      <input name="name" value="<?= kay_h($values['name']) ?>" autocomplete="name" required></label>
<?php endif; ?>
    <label class="field"><span>E-mail</span>
      <input type="email" name="email" value="<?= kay_h($values['email']) ?>" autocomplete="username" required></label>
    <label class="field"><span>Mot de passe</span>
      <input type="password" name="password" autocomplete="new-password" minlength="<?= KAY_PASSWORD_MIN ?>" required>
      <small>Au moins <?= KAY_PASSWORD_MIN ?> caractères.</small></label>
    <button class="btn btn--primary btn--block"><?= $first ? 'Créer le compte' : 'Changer le mot de passe' ?></button>
  </form>
<?php if (!$first): ?>
  <p class="muted small"><a href="login.php">← Retour à la connexion</a></p>
<?php endif; ?>
<?php endif; ?>
</section>
<?php kay_page_end();
