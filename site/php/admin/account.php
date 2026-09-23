<?php
declare(strict_types=1);

require __DIR__ . '/_boot.php';
$admin = kay_admin($db);

$error = null;
$newUser = ['email' => '', 'name' => '', 'locale' => 'es'];

if (kay_posted($admin)) {
    $do = (string) ($_POST['do'] ?? '');

    if ($do === 'lang') {
        kay_admin_set_locale($db, $admin['id'], (string) ($_POST['locale'] ?? ''));
        kay_redirect('account.php?m=lang');
    } elseif ($do === 'password') {
        $check = kay_login_check($db, $admin['email'], (string) ($_POST['current'] ?? ''));
        $next  = (string) ($_POST['password'] ?? '');
        if (!$check['ok']) {
            $error = $check['error'] === 'throttled'
                ? kay_t('Trop de tentatives. Réessayez plus tard.')
                : kay_t('Le mot de passe actuel est incorrect.');
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
        $newUser = [
            'email'  => mb_strtolower(trim((string) ($_POST['email'] ?? ''))),
            'name'   => trim((string) ($_POST['name'] ?? '')),
            'locale' => isset(KAY_ADMIN_LANGS[$_POST['locale'] ?? '']) ? (string) $_POST['locale'] : 'es',
        ];
        $password = (string) ($_POST['password'] ?? '');
        $exists = $db->prepare('SELECT 1 FROM admin_users WHERE email = ?');
        $exists->execute([$newUser['email']]);
        if (!filter_var($newUser['email'], FILTER_VALIDATE_EMAIL)) {
            $error = kay_t('Adresse e-mail invalide.');
        } elseif (mb_strlen($newUser['name']) < 2) {
            $error = kay_t('Indiquez un nom.');
        } elseif ($exists->fetchColumn()) {
            $error = kay_t('Un compte existe déjà avec cette adresse.');
        } elseif (($problem = kay_password_problem($password, $newUser['email'])) !== null) {
            $error = $problem;
        } else {
            kay_admin_create($db, $newUser['email'], $newUser['name'], $password, $newUser['locale']);
            kay_redirect('account.php?m=user');
        }
    } elseif ($do === 'remove') {
        $target = (int) ($_POST['id'] ?? 0);
        if ($target === $admin['id']) {
            $error = kay_t('Vous ne pouvez pas supprimer votre propre compte.');
        } else {
            $db->prepare('DELETE FROM admin_sessions WHERE user_id = ?')->execute([$target]);
            $db->prepare('DELETE FROM admin_users WHERE id = ?')->execute([$target]);
            kay_redirect('account.php?m=removed');
        }
    }
}

$users = $db->query('SELECT id, email, name, locale, created_at, last_login_at FROM admin_users ORDER BY name')->fetchAll();
$sessions = $db->prepare('SELECT user_agent, created_at, last_seen_at, token_hash FROM admin_sessions
                           WHERE user_id = ? AND expires_at > NOW() ORDER BY last_seen_at DESC');
$sessions->execute([$admin['id']]);
$sessions = $sessions->fetchAll();

/** The admin's languages as a <select>, each named in itself. */
$langSelect = static function (string $current): string {
    $out = '<select name="locale">';
    foreach (KAY_ADMIN_LANGS as $code => $name) {
        $out .= '<option value="' . $code . '" lang="' . $code . '"' . ($code === $current ? ' selected' : '') . '>'
            . kay_h($name) . '</option>';
    }
    return $out . '</select>';
};

kay_page_start(kay_t('Compte'), 'account.php', $admin);
?>
<header class="head">
  <div><h1><?= kay_th('Compte') ?></h1><p class="muted"><?= kay_h($admin['name']) ?> · <?= kay_h($admin['email']) ?></p></div>
  <form method="post" action="logout.php" class="inline">
    <?= kay_csrf_field($admin) ?><button class="btn btn--ghost"><?= kay_th('Se déconnecter') ?></button>
  </form>
</header>
<?= kay_error_box($error) ?>

<?php if (kay_setup_token() !== null): ?>
<p class="note"><?= kay_th('Le fichier kay-admin-token.txt est toujours sur le serveur : avec sa phrase, n’importe quel mot de passe peut être réinitialisé depuis /admin/setup.php. Gardez-le si cela vous rassure, ou supprimez-le par FTP — vous pourrez toujours le remettre.') ?></p>
<?php endif; ?>

<div class="cols cols--even cols--first">
  <section class="card">
    <header class="card__head"><h2><?= kay_th('Langue de l’admin') ?></h2></header>
    <form method="post" class="stack">
      <?= kay_csrf_field($admin) ?>
      <input type="hidden" name="do" value="lang">
      <label class="field"><span><?= kay_th('Langue') ?></span><?= $langSelect(kay_lang()) ?></label>
      <p class="small muted"><?= kay_th('Pour votre compte seulement. Les e-mails aux plongeurs restent dans la langue de chaque réservation.') ?></p>
      <button class="btn btn--primary"><?= kay_th('Enregistrer') ?></button>
    </form>
  </section>

  <section class="card">
    <header class="card__head"><h2><?= kay_th('Changer de mot de passe') ?></h2></header>
    <form method="post" class="stack">
      <?= kay_csrf_field($admin) ?>
      <input type="hidden" name="do" value="password">
      <input type="hidden" name="username" value="<?= kay_h($admin['email']) ?>" autocomplete="username">
      <label class="field"><span><?= kay_th('Mot de passe actuel') ?></span><input type="password" name="current" autocomplete="current-password" required></label>
      <label class="field"><span><?= kay_th('Nouveau mot de passe') ?></span><input type="password" name="password" autocomplete="new-password" minlength="<?= KAY_PASSWORD_MIN ?>" required>
        <small><?= kay_th('Au moins {n} caractères. Vos autres appareils seront déconnectés.', ['{n}' => (string) KAY_PASSWORD_MIN]) ?></small></label>
      <button class="btn btn--primary"><?= kay_th('Changer') ?></button>
    </form>
  </section>
</div>

<section class="card">
  <header class="card__head"><h2><?= kay_th('Vos sessions') ?></h2></header>
  <ul class="rows rows--tight">
<?php foreach ($sessions as $s): $ua = kay_ua_parse((string) $s['user_agent']); ?>
    <li class="row"><span class="row__main"><?= ($ua['browser'] === 'other' ? kay_th('Autre') : kay_h($ua['browser'])) . ' · ' . ($ua['os'] === 'other' ? kay_th('Autre') : kay_h($ua['os'])) ?>
      <?= hash_equals($admin['token_hash'], $s['token_hash']) ? '<span class="tag">' . kay_th('cet appareil') . '</span>' : '' ?>
      <span class="muted small block"><?= kay_th('Dernière activité {when}', ['{when}' => kay_when($s['last_seen_at'])]) ?></span></span></li>
<?php endforeach; ?>
  </ul>
<?php if (count($sessions) > 1): ?>
  <form method="post"><?= kay_csrf_field($admin) ?><input type="hidden" name="do" value="others">
    <button class="btn btn--ghost"><?= kay_th('Déconnecter les autres appareils') ?></button></form>
<?php endif; ?>
</section>

<section class="card card--flush">
  <header class="card__head"><h2><?= kay_th('Accès à l’admin') ?></h2></header>
  <div class="table-wrap"><table class="table table--stack">
    <thead><tr><th scope="col"><?= kay_th('Nom') ?></th><th scope="col"><?= kay_th('E-mail') ?></th><th scope="col"><?= kay_th('Langue') ?></th>
      <th scope="col"><?= kay_th('Dernière connexion') ?></th><th scope="col"></th></tr></thead>
    <tbody>
<?php foreach ($users as $u): ?>
      <tr>
        <td data-label="<?= kay_th('Nom') ?>"><?= kay_h($u['name']) ?></td>
        <td data-label="<?= kay_th('E-mail') ?>"><?= kay_h($u['email']) ?></td>
        <td data-label="<?= kay_th('Langue') ?>"><?= kay_h(KAY_ADMIN_LANGS[$u['locale']] ?? $u['locale']) ?></td>
        <td data-label="<?= kay_th('Dernière connexion') ?>"><?= kay_h(kay_when($u['last_login_at'])) ?></td>
        <td class="num"><?php if ((int) $u['id'] !== $admin['id']): ?>
          <form method="post" class="inline"><?= kay_csrf_field($admin) ?>
            <input type="hidden" name="do" value="remove"><input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
            <button class="btn btn--danger btn--small" data-confirm="<?= kay_th('Supprimer l’accès de {name} ?', ['{name}' => $u['name']]) ?>"><?= kay_th('Supprimer') ?></button></form>
        <?php endif; ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table></div>
  <details class="card__foot">
    <summary class="btn btn--ghost"><?= kay_th('+ Donner accès à quelqu’un') ?></summary>
    <form method="post" class="stack">
      <?= kay_csrf_field($admin) ?>
      <input type="hidden" name="do" value="create">
      <div class="grid">
        <label class="field"><span><?= kay_th('Nom') ?></span><input name="name" value="<?= kay_h($newUser['name']) ?>" required></label>
        <label class="field"><span><?= kay_th('E-mail') ?></span><input type="email" name="email" value="<?= kay_h($newUser['email']) ?>" autocomplete="off" required></label>
        <label class="field"><span><?= kay_th('Mot de passe provisoire') ?></span><input type="password" name="password" autocomplete="new-password" minlength="<?= KAY_PASSWORD_MIN ?>" required></label>
        <label class="field"><span><?= kay_th('Langue') ?></span><?= $langSelect($newUser['locale']) ?></label>
      </div>
      <p class="small muted"><?= kay_th('Tous les comptes ont les mêmes droits. Transmettez le mot de passe en personne ; la personne pourra le changer ici.') ?></p>
      <button class="btn btn--primary"><?= kay_th('Créer le compte') ?></button>
    </form>
  </details>
</section>
<?php kay_page_end();
