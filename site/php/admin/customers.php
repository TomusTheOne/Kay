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
    kay_csv('clients-' . kay_today() . '.csv', [
        'Nom', 'E-mail', 'Téléphone', 'Pays', 'Langue', 'Niveau', 'Étiquettes',
        'Réservations', 'Valeur MXN', 'Dernière plongée', 'Prochaine plongée', 'Client depuis',
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

kay_page_start('Clients', 'customers.php', $admin);
?>
<header class="head">
  <div><h1>Clients</h1>
    <p class="muted">Toute personne ayant réservé ou ouvert un paiement, et celles que vous ajoutez à la main.</p></div>
  <div class="head__actions">
    <a class="btn btn--ghost" href="<?= kay_h(kay_url('customers.php', ['format' => 'csv'] + $filters)) ?>">Exporter (CSV)</a>
    <a class="btn btn--primary" href="customer.php?new=1">+ Nouveau client</a>
  </div>
</header>

<nav class="tabs" aria-label="Segments">
<?php foreach (KAY_SEGMENTS as $key => $label): ?>
  <a href="<?= kay_h(kay_url('customers.php', ['segment' => $key] + array_diff_key($filters, ['segment' => 1]))) ?>"<?= $key === $filters['segment'] ? ' aria-current="page"' : '' ?>><?= kay_h($label) ?></a>
<?php endforeach; ?>
</nav>

<form method="get" class="filters">
  <input type="hidden" name="segment" value="<?= kay_h($filters['segment']) ?>">
  <label class="field field--grow"><span>Rechercher</span>
    <input type="search" name="q" value="<?= kay_h($filters['q']) ?>" placeholder="Nom, e-mail, téléphone, étiquette"></label>
<?php if ($tags !== []): ?>
  <label class="field"><span>Étiquette</span><select name="tag" data-autosubmit>
    <option value="">Toutes</option>
<?php foreach ($tags as $tag => $n): ?>
    <option value="<?= kay_h($tag) ?>"<?= $filters['tag'] === $tag ? ' selected' : '' ?>><?= kay_h($tag) ?> (<?= $n ?>)</option>
<?php endforeach; ?>
  </select></label>
<?php endif; ?>
  <label class="field"><span>Trier par</span><select name="sort" data-autosubmit>
<?php foreach (['recent' => 'Activité récente', 'next' => 'Prochaine plongée', 'value' => 'Valeur', 'bookings' => 'Réservations', 'name' => 'Nom'] as $k => $label): ?>
    <option value="<?= $k ?>"<?= $filters['sort'] === $k ? ' selected' : '' ?>><?= kay_h($label) ?></option>
<?php endforeach; ?>
  </select></label>
  <button class="btn btn--ghost">Filtrer</button>
</form>

<section class="card card--flush">
<?php if ($result['rows'] === []): ?>
  <p class="empty">Aucun client ne correspond.</p>
<?php else: ?>
  <div class="table-wrap"><table class="table table--stack">
    <thead><tr><th scope="col">Client</th><th scope="col">Contact</th><th scope="col" class="num">Réservations</th>
      <th scope="col" class="num">Valeur</th><th scope="col">Dernière plongée</th><th scope="col">Prochaine</th></tr></thead>
    <tbody>
<?php foreach ($result['rows'] as $c): ?>
      <tr>
        <td data-label="Client"><a href="<?= kay_h(kay_url('customer.php', ['id' => $c['id']])) ?>"><strong><?= kay_h($c['name'] !== '' ? $c['name'] : '(sans nom)') ?></strong></a>
          <?php if ((int) $c['bookings'] === 0): ?><span class="tag">prospect</span><?php endif; ?>
          <?php foreach (array_filter(explode(',', $c['tags'])) as $tag): ?><span class="tag tag--label"><?= kay_h($tag) ?></span><?php endforeach; ?>
          <span class="muted block small"><?= $c['country'] !== '' ? kay_h(kay_country($c['country'])) . ' · ' : '' ?><?= kay_h($c['certification']) ?></span></td>
        <td data-label="Contact" class="small"><?= kay_h((string) $c['email']) ?><span class="muted block"><?= kay_h($c['phone']) ?></span></td>
        <td data-label="Réservations" class="num"><?= (int) $c['bookings'] ?></td>
        <td data-label="Valeur" class="num"><?= (int) $c['value_mxn_cents'] > 0 ? kay_mxn((int) $c['value_mxn_cents'] / 100) : '<span class="muted">—</span>' ?></td>
        <td data-label="Dernière plongée"><?= $c['last_dive'] ? kay_h(kay_date_short($c['last_dive'])) : '<span class="muted">—</span>' ?></td>
        <td data-label="Prochaine"><?= $c['next_dive'] ? '<strong>' . kay_h(kay_date_short($c['next_dive'])) . '</strong>' : '<span class="muted">—</span>' ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>
</section>
<p class="muted small"><?= kay_int($result['total']) ?> client<?= $result['total'] > 1 ? 's' : '' ?>.</p>
<?= kay_pager('customers.php', $filters, $result['total'], $page, $per) ?>
<?php kay_page_end();
