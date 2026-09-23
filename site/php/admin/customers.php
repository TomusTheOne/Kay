<?php
declare(strict_types=1);

require __DIR__ . '/_boot.php';
$admin = kay_admin($db);

$filters = [
    'q'       => trim((string) ($_GET['q'] ?? '')),
    'tag'     => (string) ($_GET['tag'] ?? ''),
    'segment' => isset(KAY_SEGMENTS[$_GET['segment'] ?? '']) ? (string) $_GET['segment'] : 'all',
    'sort'    => (string) ($_GET['sort'] ?? 'recent'),
];

if (($_GET['format'] ?? '') === 'csv') {
    $all = kay_customers_find($db, $filters, 5000)['rows'];
    kay_csv(kay_t('clients') . '-' . kay_today() . '.csv', [
        kay_t('Nom'), kay_t('E-mail'), kay_t('Téléphone'), kay_t('Pays'), kay_t('Langue'), kay_t('Niveau'),
        kay_t('Étiquettes'), kay_t('Réservations'), kay_t('Valeur MXN'), kay_t('Dernière plongée'),
        kay_t('Prochaine plongée'), kay_t('Client depuis'),
    ], array_map(static fn(array $c): array => [
        $c['name'], $c['email'] ?? '', $c['phone'], $c['country'], $c['locale'], $c['certification'],
        str_replace(',', ', ', $c['tags']), $c['bookings'], (int) round($c['value_mxn_cents'] / 100),
        $c['last_dive'] ?? '', $c['next_dive'] ?? '', kay_db_time($c['created_at'])?->format('Y-m-d'),
    ], $all));
}

$per    = 50;
$page   = max(1, (int) ($_GET['p'] ?? 1));
$result = kay_customers_find($db, $filters, $per, ($page - 1) * $per);
$tags   = kay_tags_in_use($db);

kay_page_start(kay_t('Clients'), 'customers.php', $admin);
?>
<header class="head">
  <div><h1><?= kay_th('Clients') ?></h1>
    <p class="muted"><?= kay_th('Toute personne ayant réservé ou ouvert un paiement, et celles que vous ajoutez à la main.') ?></p></div>
  <div class="head__actions">
    <a class="btn btn--ghost" href="<?= kay_h(kay_url('customers.php', ['format' => 'csv'] + $filters)) ?>"><?= kay_th('Exporter (CSV)') ?></a>
    <a class="btn btn--primary" href="customer.php?new=1"><?= kay_th('+ Nouveau client') ?></a>
  </div>
</header>

<nav class="tabs" aria-label="<?= kay_th('Segments') ?>">
<?php foreach (KAY_SEGMENTS as $key => $label): ?>
  <a href="<?= kay_h(kay_url('customers.php', ['segment' => $key] + array_diff_key($filters, ['segment' => 1]))) ?>"<?= $key === $filters['segment'] ? ' aria-current="page"' : '' ?>><?= kay_th($label) ?></a>
<?php endforeach; ?>
</nav>

<form method="get" class="filters">
  <input type="hidden" name="segment" value="<?= kay_h($filters['segment']) ?>">
  <label class="field field--grow"><span><?= kay_th('Rechercher') ?></span>
    <input type="search" name="q" value="<?= kay_h($filters['q']) ?>" placeholder="<?= kay_th('Nom, e-mail, téléphone, étiquette') ?>"></label>
<?php if ($tags !== []): ?>
  <label class="field"><span><?= kay_th('Étiquette') ?></span><select name="tag" data-autosubmit>
    <option value=""><?= kay_th('Toutes') ?></option>
<?php foreach ($tags as $tag => $n): ?>
    <option value="<?= kay_h($tag) ?>"<?= $filters['tag'] === $tag ? ' selected' : '' ?>><?= kay_h($tag) ?> (<?= $n ?>)</option>
<?php endforeach; ?>
  </select></label>
<?php endif; ?>
  <label class="field"><span><?= kay_th('Trier par') ?></span><select name="sort" data-autosubmit>
<?php foreach (KAY_CUSTOMER_SORTS as $k => $label): ?>
    <option value="<?= $k ?>"<?= $filters['sort'] === $k ? ' selected' : '' ?>><?= kay_th($label) ?></option>
<?php endforeach; ?>
  </select></label>
  <button class="btn btn--ghost"><?= kay_th('Filtrer') ?></button>
</form>

<section class="card card--flush">
<?php if ($result['rows'] === []): ?>
  <p class="empty"><?= kay_th('Aucun client ne correspond.') ?></p>
<?php else: ?>
  <div class="table-wrap"><table class="table table--stack">
    <thead><tr><th scope="col"><?= kay_th('Client') ?></th><th scope="col"><?= kay_th('Contact') ?></th><th scope="col" class="num"><?= kay_th('Réservations') ?></th>
      <th scope="col" class="num"><?= kay_th('Valeur') ?></th><th scope="col"><?= kay_th('Dernière plongée') ?></th><th scope="col"><?= kay_th('Prochaine') ?></th></tr></thead>
    <tbody>
<?php foreach ($result['rows'] as $c): ?>
      <tr>
        <td data-label="<?= kay_th('Client') ?>"><a href="<?= kay_h(kay_url('customer.php', ['id' => $c['id']])) ?>"><strong><?= $c['name'] !== '' ? kay_h($c['name']) : kay_th('(sans nom)') ?></strong></a>
          <?php if ((int) $c['bookings'] === 0): ?><span class="tag"><?= kay_th('prospect') ?></span><?php endif; ?>
          <?php foreach (array_filter(explode(',', $c['tags'])) as $tag): ?><span class="tag tag--label"><?= kay_h($tag) ?></span><?php endforeach; ?>
          <span class="muted block small"><?= $c['country'] !== '' ? kay_h(kay_country($c['country'])) . ' · ' : '' ?><?= kay_h($c['certification']) ?></span></td>
        <td data-label="<?= kay_th('Contact') ?>" class="small"><?= kay_h((string) $c['email']) ?><span class="muted block"><?= kay_h($c['phone']) ?></span></td>
        <td data-label="<?= kay_th('Réservations') ?>" class="num"><?= (int) $c['bookings'] ?></td>
        <td data-label="<?= kay_th('Valeur') ?>" class="num"><?= (int) $c['value_mxn_cents'] > 0 ? kay_mxn((int) $c['value_mxn_cents'] / 100) : '<span class="muted">—</span>' ?></td>
        <td data-label="<?= kay_th('Dernière plongée') ?>"><?= $c['last_dive'] ? kay_h(kay_date_short($c['last_dive'])) : '<span class="muted">—</span>' ?></td>
        <td data-label="<?= kay_th('Prochaine') ?>"><?= $c['next_dive'] ? '<strong>' . kay_h(kay_date_short($c['next_dive'])) . '</strong>' : '<span class="muted">—</span>' ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>
</section>
<p class="muted small"><?= kay_h(kay_tn($result['total'], '{n} client.', '{n} clients.')) ?></p>
<?= kay_pager('customers.php', $filters, $result['total'], $page, $per) ?>
<?php kay_page_end();
