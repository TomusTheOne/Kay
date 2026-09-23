<?php
declare(strict_types=1);

require __DIR__ . '/_boot.php';
$admin = kay_admin($db);

$view = (string) ($_GET['view'] ?? 'upcoming');
$view = isset(KAY_BOOKING_VIEWS[$view]) ? $view : 'upcoming';
$filters = [
    'view'    => $view,
    'q'       => trim((string) ($_GET['q'] ?? '')),
    'product' => (string) ($_GET['product'] ?? ''),
    'from'    => (string) ($_GET['from'] ?? ''),
    'to'      => (string) ($_GET['to'] ?? ''),
];

// The same filters, as a spreadsheet — for the accountant, or a season review.
if (($_GET['format'] ?? '') === 'csv') {
    $all = kay_bookings_find($db, $filters, 5000)['rows'];
    kay_csv(kay_t('reservations') . '-' . kay_today() . '.csv', [
        kay_t('Référence'), kay_t('Reçue le'), kay_t('Origine'), kay_t('Statut'), kay_t('Date de plongée'),
        kay_t('Départ'), kay_t('Sortie'), kay_t('Plongées'), kay_t('Plongeurs'), kay_t('Niveau'),
        kay_t('Transport'), kay_t('Client'), kay_t('E-mail'), kay_t('Téléphone'), kay_t('Langue'),
        kay_t('Total MXN'), kay_t('Total USD'), kay_t('Encaissé MXN'), kay_t('Reste MXN'),
        kay_t('Paiement Mercado Pago'),
    ], array_map(static function (array $b): array {
        $m = kay_booking_money($b);
        return [
            $b['id'], kay_db_time($b['created_at'])?->format('Y-m-d H:i'), $b['source'] === 'manual' ? kay_t('admin') : kay_t('site'),
            kay_t(KAY_STATUSES[$b['status']] ?? $b['status']), $b['dive_date'], kay_slot_text($b['start_slot'], (string) $b['start_note']),
            kay_product_name($b['product']), $b['dives'], $b['divers'], $b['certification'],
            kay_pickup_name($b['pickup']), $b['name'], $b['email'], $b['customer_phone'] ?? '', $b['locale'],
            $m['total'], (int) round($b['total_usd_cents'] / 100), $m['received'], $m['balance'], $b['payment_id'] ?? '',
        ];
    }, $all));
}

$per    = 50;
$page   = max(1, (int) ($_GET['p'] ?? 1));
$result = kay_bookings_find($db, $filters, $per, ($page - 1) * $per);
$counts = kay_booking_view_counts($db);

kay_page_start(kay_t('Réservations'), 'bookings.php', $admin);
?>
<header class="head">
  <div><h1><?= kay_th('Réservations') ?></h1></div>
  <div class="head__actions">
    <a class="btn btn--ghost" href="<?= kay_h(kay_url('bookings.php', ['format' => 'csv'] + $filters)) ?>"><?= kay_th('Exporter (CSV)') ?></a>
    <a class="btn btn--primary" href="booking-new.php"><?= kay_th('+ Nouvelle réservation') ?></a>
  </div>
</header>

<nav class="tabs" aria-label="<?= kay_th('Vues') ?>">
<?php foreach (KAY_BOOKING_VIEWS as $key => $label): ?>
  <a href="<?= kay_h(kay_url('bookings.php', ['view' => $key] + array_diff_key($filters, ['view' => 1]))) ?>"<?= $key === $view ? ' aria-current="page"' : '' ?>>
    <?= kay_th($label) ?> <span class="count"><?= kay_int($counts[$key] ?? 0) ?></span></a>
<?php endforeach; ?>
</nav>

<form method="get" class="filters">
  <input type="hidden" name="view" value="<?= kay_h($view) ?>">
  <label class="field field--grow"><span><?= kay_th('Rechercher') ?></span>
    <input type="search" name="q" value="<?= kay_h($filters['q']) ?>" placeholder="<?= kay_th('Nom, e-mail, téléphone, référence') ?>"></label>
  <label class="field"><span><?= kay_th('Sortie') ?></span>
    <select name="product" data-autosubmit>
      <option value=""><?= kay_th('Toutes') ?></option>
<?php foreach (kay_catalogue()['products'] as $p): ?>
      <option value="<?= kay_h($p['slug']) ?>"<?= $filters['product'] === $p['slug'] ? ' selected' : '' ?>><?= kay_h(kay_product_name($p['slug'])) ?></option>
<?php endforeach; ?>
    </select></label>
  <label class="field"><span><?= kay_th('Du') ?></span><input type="date" name="from" value="<?= kay_h($filters['from']) ?>"></label>
  <label class="field"><span><?= kay_th('Au') ?></span><input type="date" name="to" value="<?= kay_h($filters['to']) ?>"></label>
  <button class="btn btn--ghost"><?= kay_th('Filtrer') ?></button>
</form>

<section class="card card--flush">
<?php if ($result['rows'] === []): ?>
  <p class="empty"><?= kay_th('Aucune réservation ne correspond.') ?></p>
<?php else: ?>
  <div class="table-wrap"><table class="table table--stack">
    <thead><tr>
      <th scope="col"><?= kay_th('Plongée') ?></th><th scope="col"><?= kay_th('Client') ?></th><th scope="col"><?= kay_th('Sortie') ?></th>
      <th scope="col"><?= kay_th('Transport') ?></th><th scope="col" class="num"><?= kay_th('Total') ?></th><th scope="col" class="num"><?= kay_th('Reste') ?></th>
      <th scope="col"><?= kay_th('Statut') ?></th>
    </tr></thead>
    <tbody>
<?php foreach ($result['rows'] as $b): $m = kay_booking_money($b); ?>
      <tr>
        <td data-label="<?= kay_th('Plongée') ?>"><strong><?= kay_h(kay_date_short($b['dive_date'])) ?></strong>
          <span class="muted block"><?= kay_h(kay_slot_text($b['start_slot'])) ?></span></td>
        <td data-label="<?= kay_th('Client') ?>"><a href="<?= kay_h(kay_url('booking.php', ['id' => $b['id']])) ?>"><?= kay_h($b['name']) ?></a>
          <span class="muted block small"><?= kay_h($b['email']) ?></span></td>
        <td data-label="<?= kay_th('Sortie') ?>"><?= kay_h(kay_product_name($b['product'])) ?>
          <span class="muted block small"><?= kay_h(kay_dives_text((int) $b['dives'])) ?> · <?= kay_th('{n} pers.', ['{n}' => (string) (int) $b['divers']]) ?></span></td>
        <td data-label="<?= kay_th('Transport') ?>" class="small"><?= kay_h(kay_pickup_name($b['pickup'])) ?></td>
        <td data-label="<?= kay_th('Total') ?>" class="num"><?= kay_mxn($m['total']) ?></td>
        <td data-label="<?= kay_th('Reste') ?>" class="num"><?= $m['due'] > 0 ? '<span class="due">' . kay_mxn($m['due']) . '</span>' : '<span class="muted">—</span>' ?></td>
        <td data-label="<?= kay_th('Statut') ?>"><?= kay_status_badge($b['status']) ?>
          <?= $b['source'] === 'manual' ? '<span class="tag">' . kay_th('saisie') . '</span>' : '' ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>
</section>
<p class="muted small"><?= kay_h(kay_tn($result['total'], '{n} réservation.', '{n} réservations.')) ?></p>
<?= kay_pager('bookings.php', $filters, $result['total'], $page, $per) ?>
<?php kay_page_end();
