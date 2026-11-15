<?php
declare(strict_types=1);

/**
 * The concierges, hotels and agencies who send people, and the link each one
 * hands out.
 *
 * The link is the point of this page. A guest asked at a hotel desk at
 * breakfast and standing in front of the booking form at midnight cannot be
 * relied on to remember which hotel it was — so the partner gives out
 * kaydiving.com/es/?ref=their-slug, and the form fills itself in. The
 * dropdown is the fallback, not the mechanism.
 */

require __DIR__ . '/_boot.php';
$admin = kay_admin($db);

$self  = kay_url('partners.php');
$error = null;

if (kay_posted($admin)) {
    $do   = (string) ($_POST['do'] ?? '');
    $name = trim((string) ($_POST['name'] ?? ''));
    $slug = kay_partner_slug((string) ($_POST['slug'] ?? '') ?: $name);

    if ($do === 'save') {
        if ($name === '') {
            $error = kay_t('Indiquez un nom.');
        } elseif ($slug === '') {
            $error = kay_t('Ce nom ne donne aucune adresse utilisable : ajoutez des lettres ou des chiffres.');
        } else {
            kay_partner_save($db, $slug, $name, (string) ($_POST['note'] ?? ''), ($_POST['active'] ?? '1') === '1');
            kay_redirect($self . '?m=saved');
        }
    } elseif ($do === 'toggle') {
        $p = kay_partner($db, (string) ($_POST['slug'] ?? ''));
        if ($p !== null) {
            kay_partner_save($db, $p['slug'], $p['name'], $p['note'], !(int) $p['active']);
            kay_redirect($self . '?m=saved');
        }
    }
}

$partners = kay_partners($db);
$site     = rtrim((string) (kay_config()['site_url'] ?? ''), '/');

kay_page_start(kay_t('Partenaires'), 'partners.php', $admin);
?>
<header class="head">
  <div>
    <h1><?= kay_th('Partenaires') ?></h1>
    <p class="muted"><?= kay_th('Qui vous envoie des clients. Chacun apparaît dans la liste « Recommandé par » du formulaire de réservation, et reçoit son propre lien.') ?></p>
  </div>
</header>
<?= kay_error_box($error) ?>

<section class="card">
  <header class="card__head"><h2><?= kay_th('Ajouter un partenaire') ?></h2></header>
  <form method="post" class="filters">
    <?= kay_csrf_field($admin) ?>
    <input type="hidden" name="do" value="save">
    <label class="field field--grow"><span><?= kay_th('Nom') ?></span>
      <input name="name" required placeholder="<?= kay_th('Hôtel Jashita') ?>"></label>
    <label class="field field--grow"><span><?= kay_th('Note (pour vous)') ?></span>
      <input name="note" placeholder="<?= kay_th('Contact, commission convenue…') ?>"></label>
    <button class="btn btn--primary"><?= kay_th('Ajouter') ?></button>
  </form>
  <p class="muted small"><?= kay_th('La note ne sort jamais du panneau : le site n’affiche que le nom.') ?></p>
</section>

<section class="card">
  <header class="card__head"><h2><?= kay_th('La liste') ?></h2></header>
<?php if ($partners === []): ?>
  <p class="empty"><?= kay_th('Aucun partenaire pour l’instant. Tant que la liste est vide, le formulaire affiche une simple case à remplir.') ?></p>
<?php else: ?>
  <div class="table-wrap"><table class="table table--stack">
    <thead><tr>
      <th scope="col"><?= kay_th('Nom') ?></th>
      <th scope="col"><?= kay_th('Son lien') ?></th>
      <th scope="col"><?= kay_th('Dans le formulaire') ?></th>
    </tr></thead>
    <tbody>
<?php foreach ($partners as $p):
    $link = $site . '/es/?ref=' . rawurlencode($p['slug']); ?>
      <tr>
        <td data-label="<?= kay_th('Nom') ?>"><strong><?= kay_h($p['name']) ?></strong>
<?php if ($p['note'] !== ''): ?>
          <span class="muted block small"><?= kay_h($p['note']) ?></span>
<?php endif; ?></td>
        <td data-label="<?= kay_th('Son lien') ?>">
          <code class="small"><?= kay_h($link) ?></code>
          <button type="button" class="btn btn--ghost btn--small" data-copy="<?= kay_h($link) ?>" data-copied="<?= kay_th('Copié') ?>"><?= kay_th('Copier') ?></button></td>
        <td data-label="<?= kay_th('Dans le formulaire') ?>">
          <form method="post" style="display:inline">
            <?= kay_csrf_field($admin) ?>
            <input type="hidden" name="do" value="toggle">
            <input type="hidden" name="slug" value="<?= kay_h($p['slug']) ?>">
            <button class="btn btn--ghost btn--small">
              <?= (int) $p['active'] ? kay_th('Visible — masquer') : kay_th('Masqué — afficher') ?></button>
          </form></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="muted small"><?= kay_th('Masquer un partenaire le retire du formulaire sans toucher aux réservations déjà faites : elles restent à son nom dans le rapport.') ?></p>
<?php endif; ?>
</section>
<?php kay_page_end();
