<?php
declare(strict_types=1);

/**
 * What each partner sent, over a date range: the page Kay pays commissions
 * from.
 *
 * Only paid bookings count. A pending row is someone who opened the payment
 * page and closed it, and a commission on that is a commission on nothing.
 * The range is by DIVE date, not by the date the booking came in: a tour sold
 * in June and dived in July is July's business, which is how the concierge
 * will remember it too.
 *
 * No commission is calculated here. Kay settles with each partner on his own
 * terms — a percentage with one, a flat amount with another — so this page
 * gives him the counts and the money, and stays out of the arithmetic.
 */

require __DIR__ . '/_boot.php';
$admin = kay_admin($db);

// Default to the month that is ending, which is when anyone asks this
// question at all.
$from = (string) ($_GET['from'] ?? '');
$to   = (string) ($_GET['to'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $from = kay_now()->modify('first day of this month')->format('Y-m-d');
    $to   = kay_now()->modify('last day of this month')->format('Y-m-d');
}
if ($from > $to) {
    [$from, $to] = [$to, $from];
}

$report = kay_referral_report($db, $from, $to);
$loose  = kay_referral_freetext($db, $from, $to);
$money  = static fn(array $r): string => kay_mxn((int) round((int) $r['mxn_cents'] / 100));

if (($_GET['format'] ?? '') === 'csv') {
    kay_csv(
        kay_t('recommandations') . '-' . $from . '-' . $to . '.csv',
        [kay_t('Partenaire'), kay_t('Réservations'), kay_t('Plongeurs'), kay_t('Total MXN'), kay_t('Total USD')],
        array_map(static fn(array $r): array => [
            $r['name'] !== '' ? $r['name'] : $r['slug'],
            (int) $r['bookings'],
            (int) $r['divers'],
            (int) round((int) $r['mxn_cents'] / 100),
            (int) round((int) $r['usd_cents'] / 100),
        ], $report['rows']),
    );
}

kay_page_start(kay_t('Recommandations'), 'referrals.php', $admin);
?>
<header class="head">
  <div>
    <h1><?= kay_th('Recommandations') ?></h1>
    <p class="muted"><?= kay_th('Ce que chaque partenaire vous a envoyé, par date de plongée. Seules les réservations payées comptent.') ?></p>
  </div>
  <div class="head__actions">
    <a class="btn btn--ghost" href="<?= kay_h(kay_url('referrals.php', ['format' => 'csv', 'from' => $from, 'to' => $to])) ?>"><?= kay_th('Exporter (CSV)') ?></a>
    <a class="btn btn--primary" href="partners.php"><?= kay_th('Gérer les partenaires') ?></a>
  </div>
</header>

<form class="filters" method="get">
  <label class="field"><span><?= kay_th('Du') ?></span><input type="date" name="from" value="<?= kay_h($from) ?>"></label>
  <label class="field"><span><?= kay_th('Au') ?></span><input type="date" name="to" value="<?= kay_h($to) ?>"></label>
  <button class="btn btn--ghost"><?= kay_th('Afficher') ?></button>
</form>

<section class="card">
<?php if ($report['rows'] === []): ?>
  <p class="empty"><?= kay_th('Aucune réservation payée sur cette période ne vient d’un partenaire de la liste.') ?></p>
<?php else: ?>
  <div class="table-wrap"><table class="table table--stack">
    <thead><tr>
      <th scope="col"><?= kay_th('Partenaire') ?></th>
      <th scope="col" class="num"><?= kay_th('Réservations') ?></th>
      <th scope="col" class="num"><?= kay_th('Plongeurs') ?></th>
      <th scope="col" class="num"><?= kay_th('Total vendu') ?></th>
    </tr></thead>
    <tbody>
<?php foreach ($report['rows'] as $r): ?>
      <tr>
        <td data-label="<?= kay_th('Partenaire') ?>"><strong><?= kay_h($r['name'] !== '' ? $r['name'] : $r['slug']) ?></strong>
<?php if ($r['name'] === ''): ?>
          <span class="muted block small"><?= kay_th('Partenaire supprimé de la liste') ?></span>
<?php endif; ?></td>
        <td data-label="<?= kay_th('Réservations') ?>" class="num"><?= kay_int((int) $r['bookings']) ?></td>
        <td data-label="<?= kay_th('Plongeurs') ?>" class="num"><?= kay_int((int) $r['divers']) ?></td>
        <td data-label="<?= kay_th('Total vendu') ?>" class="num"><?= kay_h($money($r)) ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
    <tfoot><tr>
      <th scope="row"><?= kay_th('Total') ?></th>
      <td class="num"><?= kay_int($report['totals']['bookings']) ?></td>
      <td class="num"><?= kay_int($report['totals']['divers']) ?></td>
      <td class="num"><?= kay_h(kay_mxn((int) round($report['totals']['mxn_cents'] / 100))) ?></td>
    </tr></tfoot>
  </table></div>
<?php endif; ?>
</section>

<?php if ($loose !== []): ?>
<section class="card">
  <header class="card__head"><h2><?= kay_th('Écrit à la main') ?></h2></header>
  <p class="muted"><?= kay_th('Ce que des clients ont tapé eux-mêmes, faute de trouver le nom dans la liste. Un nom qui revient ici est un partenaire à ajouter.') ?></p>
  <div class="table-wrap"><table class="table table--stack">
    <thead><tr>
      <th scope="col"><?= kay_th('Ce qu’ils ont écrit') ?></th>
      <th scope="col" class="num"><?= kay_th('Réservations') ?></th>
      <th scope="col" class="num"><?= kay_th('Plongeurs') ?></th>
    </tr></thead>
    <tbody>
<?php foreach ($loose as $r): ?>
      <tr>
        <td data-label="<?= kay_th('Ce qu’ils ont écrit') ?>"><?= kay_h($r['text']) ?></td>
        <td data-label="<?= kay_th('Réservations') ?>" class="num"><?= kay_int((int) $r['bookings']) ?></td>
        <td data-label="<?= kay_th('Plongeurs') ?>" class="num"><?= kay_int((int) $r['divers']) ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>
<?php kay_page_end();
