<?php
declare(strict_types=1);

require __DIR__ . '/_boot.php';
$admin = kay_admin($db);

$error = null;
$newUser = ['email' => '', 'name' => ''];

if (kay_posted($admin)) {
    $do = (string) ($_POST['do'] ?? '');

    if ($do === 'password') {
        $check = kay_login_check($db, $admin['email'], (string) ($_POST['current'] ?? ''));
        $next  = (string) ($_POST['password'] ?? '');
        if (!$check['ok']) {
            $error = $check['error'] === 'throttled' ? 'Trop de tentatives. Réessayez plus tard.' : 'Le mot de passe actuel est incorrect.';
        } elseif (($problem = kay_password_problem($next, $admin['email'])) !== null) {
            $error = $problem;
        } else {
            kay_admin_set_password($db, $admin['id'], $next, $admin['token_hash']);
            kay_redirect('account.php?m=password');
        }
    } elseif ($do === 'others') {
        kay_sessions_revoke_others($db, $admin['id'], $admin['token_hash']);
        kay_redirect('account.php?m=signedout');
    } elseif ($do === 'create') {
        $newUser = ['email' => mb_strtolower(trim((string) ($_POST['email'] ?? ''))), 'name' => trim((string) ($_POST['name'] ?? ''))];
        $password = (string) ($_POST['password'] ?? '');
        $exists = $db->prepare('SELECT 1 FROM admin_users WHERE email = ?');
        $exists->execute([$newUser['email']]);
        if (!filter_var($newUser['email'], FILTER_VALIDATE_EMAIL)) {
            $error = 'Adresse e-mail invalide.';
        } elseif (mb_strlen($newUser['name']) < 2) {
            $error = 'Indiquez un nom.';
        } elseif ($exists->fetchColumn()) {
            $error = 'Un compte existe déjà avec cette adresse.';
        } elseif (($problem = kay_password_problem($password, $newUser['email'])) !== null) {
            $error = $problem;
        } else {
            kay_admin_create($db, $newUser['email'], $newUser['name'], $password);
            kay_redirect('account.php?m=user');
        }
    } elseif ($do === 'remove') {
        $target = (int) ($_POST['id'] ?? 0);
        if ($target === $admin['id']) {
            $error = 'Vous ne pouvez pas supprimer votre propre compte.';
        } else {
            $db->prepare('DELETE FROM admin_sessions WHERE user_id = ?')->execute([$target]);
            $db->prepare('DELETE FROM admin_users WHERE id = ?')->execute([$target]);
            kay_redirect('account.php?m=removed');
        }
    }
}

$users = $db->query('SELECT id, email, name, created_at, last_login_at FROM admin_users ORDER BY name')->fetchAll();
$sessions = $db->prepare('SELECT user_agent, created_at, last_seen_at, token_hash FROM admin_sessions
                           WHERE user_id = ? AND expires_at > NOW() ORDER BY last_seen_at DESC');
$sessions->execute([$admin['id']]);
$sessions = $sessions->fetchAll();

kay_page_start('Compte', 'account.php', $admin);
?>
<header class="head">
  <div><h1>Compte</h1><p class="muted"><?= kay_h($admin['name']) ?> · <?= kay_h($admin['email']) ?></p></div>
  <form method="post" action="logout.php" class="inline">
    <?= kay_csrf_field($admin) ?><button class="btn btn--ghost">Se déconnecter</button>
  </form>
</header>
<?= kay_error_box($error) ?>

<div class="cols cols--even">
  <section class="card">
    <header class="card__head"><h2>Changer de mot de passe</h2></header>
    <form method="post" class="stack">
      <?= kay_csrf_field($admin) ?>
      <input type="hidden" name="do" value="password">
      <input type="hidden" name="username" value="<?= kay_h($admin['email']) ?>" autocomplete="username">
      <label class="field"><span>Mot de passe actuel</span><input type="password" name="current" autocomplete="current-password" required></label>
      <label class="field"><span>Nouveau mot de passe</span><input type="password" name="password" autocomplete="new-password" minlength="<?= KAY_PASSWORD_MIN ?>" required>
        <small>Au moins <?= KAY_PASSWORD_MIN ?> caractères. Vos autres appareils seront déconnectés.</small></label>
      <button class="btn btn--primary">Changer</button>
    </form>
  </section>

  <section class="card">
    <header class="card__head"><h2>Vos sessions</h2></header>
    <ul class="rows rows--tight">
<?php foreach ($sessions as $s): ?>
      <li class="row"><span class="row__main"><?= kay_h(kay_ua_parse((string) $s['user_agent'])['browser'] . ' · ' . kay_ua_parse((string) $s['user_agent'])['os']) ?>
        <?= hash_equals($admin['token_hash'], $s['token_hash']) ? '<span class="tag">cet appareil</span>' : '' ?>
        <span class="muted small block">Dernière activité <?= kay_h(kay_when($s['last_seen_at'])) ?></span></span></li>
<?php endforeach; ?>
    </ul>
<?php if (count($sessions) > 1): ?>
    <form method="post"><?= kay_csrf_field($admin) ?><input type="hidden" name="do" value="others">
      <button class="btn btn--ghost">Déconnecter les autres appareils</button></form>
<?php endif; ?>
  </section>
</div>

<?php if (kay_setup_token() !== null): ?>
<p class="note">Le fichier <code>kay-admin-token.txt</code> est toujours sur le serveur : avec sa
  phrase, n’importe quel mot de passe peut être réinitialisé depuis <code>/admin/setup.php</code>.
  Gardez-le si cela vous rassure, ou supprimez-le par FTP — vous pourrez toujours le remettre.</p>
<?php endif; ?>

<section class="card card--flush">
  <header class="card__head"><h2>Accès à l’admin</h2></header>
  <div class="table-wrap"><table class="table table--stack">
    <thead><tr><th scope="col">Nom</th><th scope="col">E-mail</th><th scope="col">Dernière connexion</th><th scope="col"></th></tr></thead>
    <tbody>
<?php foreach ($users as $u): ?>
      <tr>
        <td data-label="Nom"><?= kay_h($u['name']) ?></td>
        <td data-label="E-mail"><?= kay_h($u['email']) ?></td>
        <td data-label="Dernière connexion"><?= kay_h(kay_when($u['last_login_at'])) ?></td>
        <td class="num"><?php if ((int) $u['id'] !== $admin['id']): ?>
          <form method="post" class="inline"><?= kay_csrf_field($admin) ?>
            <input type="hidden" name="do" value="remove"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
            <button class="btn btn--danger btn--small" data-confirm="Supprimer l’accès de <?= kay_h($u['name']) ?> ?">Supprimer</button></form>
        <?php endif; ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table></div>
  <details class="card__foot">
    <summary class="btn btn--ghost">+ Donner accès à quelqu’un</summary>
    <form method="post" class="stack">
      <?= kay_csrf_field($admin) ?>
      <input type="hidden" name="do" value="create">
      <div class="grid">
        <label class="field"><span>Nom</span><input name="name" value="<?= kay_h($newUser['name']) ?>" required></label>
        <label class="field"><span>E-mail</span><input type="email" name="email" value="<?= kay_h($newUser['email']) ?>" autocomplete="off" required></label>
        <label class="field"><span>Mot de passe provisoire</span><input type="password" name="password" autocomplete="new-password" minlength="<?= KAY_PASSWORD_MIN ?>" required></label>
      </div>
      <p class="small muted">Tous les comptes ont les mêmes droits. Transmettez le mot de passe en personne ; la personne pourra le changer ici.</p>
      <button class="btn btn--primary">Créer le compte</button>
    </form>
  </details>
</section>
<?php kay_page_end();
