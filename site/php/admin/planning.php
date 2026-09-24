<?php
declare(strict_types=1);

/**
 * The day sheet: everyone diving on one day, by departure, with what they
 * need picked up and what they still owe. Built to be read on a phone at the
 * dive centre, and printed on paper the evening before.
 */

require __DIR__ . '/_boot.php';
$admin = kay_admin($db);

$date = (string) ($_GET['date'] ?? '');
$parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, kay_tz());
if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
    $parsed = kay_now()->setTime(0, 0);
    $date   = $parsed->format('Y-m-d');
}
$today = kay_today();

$sheet  = kay_day_sheet($db, $date);
$strip  = kay_days_overview($db, $today, 14);
$closed = kay_closed_days($db, min($today, $date), max(array_key_last($strip), $date));

$pendingHere = $db->prepare("SELECT COUNT(*) FROM bookings WHERE dive_date = ? AND status = 'pending'");
$pendingHere->execute([$date]);
$pendingHere = (int) $pendingHere->fetchColumn();

// By departure, in the catalogue's order, with "time to be set" last.
$groups = [];
foreach ($sheet as $b) {
    $groups[$b['start_slot']][] = $b;
}

$divers  = array_sum(array_map(static fn($b) => (int) $b['divers'], $sheet));
$due     = array_sum(array_map(static fn($b) => kay_booking_money($b)['due'], $sheet));
$pickups = count(array_filter($sheet, static fn($b) => $b['pickup'] !== 'meeting-point'));

$offset   = (int) (new DateTimeImmutable($today))->diff(new DateTimeImmutable($date))->format('%r%a');
$relative = match (true) {
    $offset === 0  => kay_t('Aujourd’hui'),
    $offset === 1  => kay_t('Demain'),
    $offset === -1 => kay_t('Hier'),
    $offset > 1    => kay_t('Dans {n} jours', ['{n}' => (string) $offset]),
    default        => kay_t('Il y a {n} jours', ['{n}' => (string) -$offset]),
};

kay_page_start(kay_t('Planning'), 'planning.php', $admin);
?>
<header class="head">
  <div>
    <h1><?= kay_h(kay_ucfirst(kay_date_long($date))) ?></h1>
    <p class="muted"><?= kay_h($relative) ?></p>
  </div>
  <div class="head__actions no-print">
    <a class="btn btn--ghost" href="<?= kay_h(kay_url('planning.php', ['date' => $parsed->modify('-1 day')->format('Y-m-d')])) ?>" aria-label="<?= kay_th('Jour précédent') ?>">←</a>
    <form method="get" class="inline"><label class="sr-only" for="d"><?= kay_th('Date') ?></label>
      <input id="d" type="date" name="date" value="<?= kay_h($date) ?>" data-autosubmit></form>
    <a class="btn btn--ghost" href="<?= kay_h(kay_url('planning.php', ['date' => $parsed->modify('+1 day')->format('Y-m-d')])) ?>" aria-label="<?= kay_th('Jour suivant') ?>">→</a>
    <button class="btn btn--ghost" data-print><?= kay_th('Imprimer') ?></button>
  </div>
</header>

<ol class="strip no-print" aria-label="<?= kay_th('Les 14 prochains jours') ?>">
<?php foreach ($strip as $day => $w): $isClosed = isset($closed[$day]); ?>
  <li><a href="<?= kay_h(kay_url('planning.php', ['date' => $day])) ?>"<?= $day === $date ? ' aria-current="date"' : '' ?> class="<?= trim(($w['divers'] > 0 ? 'has ' : '') . ($isClosed ? 'is-closed' : '')) ?>">
    <span><?= kay_h(kay_fmt_date($day, 'weekday')) ?></span>
    <strong><?= kay_h(kay_fmt_date($day, 'day')) ?></strong>
    <small><?= $isClosed ? kay_th('fermé') : ($w['divers'] > 0 ? kay_th('{n} pl.', ['{n}' => (string) $w['divers']]) : '—') ?></small>
  </a></li>
<?php endforeach; ?>
</ol>

<?php if (isset($closed[$date])): ?>
<p class="note no-print"><?= kay_th('Ce jour est fermé aux réservations en ligne.') ?>
  <?= $closed[$date] !== '' ? '« ' . kay_h($closed[$date]) . ' » ·' : '' ?>
  <a href="<?= kay_h(kay_url('availability.php', ['month' => substr($date, 0, 7)])) ?>"><?= kay_th('Disponibilité') ?></a></p>
<?php endif; ?>

<section class="kpis kpis--small">
  <?= kay_tile(kay_t('Réservations'), kay_int(count($sheet))) ?>
  <?= kay_tile(kay_t('Plongeurs'), kay_int($divers)) ?>
  <?= kay_tile(kay_t('Ramassages'), kay_int($pickups)) ?>
  <?= kay_tile(kay_t('À encaisser'), kay_mxn($due)) ?>
</section>

<?php if ($pendingHere > 0): ?>
<p class="note no-print"><?= kay_h(kay_tn($pendingHere, '{n} paiement en attente pour ce jour, non compté ici.', '{n} paiements en attente pour ce jour, non comptés ici.')) ?>
  <a href="<?= kay_h(kay_url('bookings.php', ['view' => 'pending', 'from' => $date, 'to' => $date])) ?>"><?= kay_th('Voir') ?></a></p>
<?php endif; ?>

<?php if ($sheet === []): ?>
<section class="card"><p class="empty"><?= kay_th('Aucune plongée confirmée ce jour-là.') ?></p>
  <p class="no-print"><a class="btn btn--primary" href="<?= kay_h(kay_url('booking-new.php', ['date' => $date])) ?>"><?= kay_th('+ Ajouter une réservation') ?></a></p></section>
<?php endif; ?>

<?php foreach (array_merge(array_column(kay_catalogue()['schedules'], 'slug'), array_keys($groups)) as $slot):
    if (empty($groups[$slot])) continue;
    $list = $groups[$slot];
    unset($groups[$slot]);
    $slotDivers = array_sum(array_map(static fn($b) => (int) $b['divers'], $list)); ?>
<section class="card sheet">
  <header class="card__head">
    <h2><?= kay_h(kay_slot_text($slot)) ?></h2>
    <span class="muted"><?= kay_h(kay_tn($slotDivers, '{n} plongeur', '{n} plongeurs')) ?></span>
  </header>
  <ul class="sheet__list">
<?php foreach ($list as $b):
    $m = kay_booking_money($b);
    $gear = kay_gear_for($db, (string) $b['id']);
    $phone = (string) ($b['customer_phone'] ?? '');
    $wa = kay_whatsapp_url($phone); ?>
    <li class="sheet__item">
      <div class="sheet__who">
        <a href="<?= kay_h(kay_url('booking.php', ['id' => $b['id']])) ?>"><strong><?= kay_h($b['name']) ?></strong></a>
        <span class="muted"><?= kay_th('{n} pers.', ['{n}' => (string) (int) $b['divers']]) ?> · <?= $b['certification'] !== '' ? kay_h($b['certification']) : kay_th('sans niveau indiqué') ?> · <?= strtoupper(kay_h($b['locale'])) ?></span>
      </div>
      <div class="sheet__what"><?= kay_h(kay_product_name($b['product'])) ?> · <?= kay_h(kay_dives_text((int) $b['dives'])) ?>
        <?= $b['start_slot'] === 'other' && $b['start_note'] !== '' ? '<br><span class="muted">' . kay_th('Souhaite : {note}', ['{note}' => $b['start_note']]) . '</span>' : '' ?></div>
      <div class="sheet__pickup<?= $b['pickup'] !== 'meeting-point' ? ' is-pickup' : '' ?>"><?= kay_h(kay_pickup_name($b['pickup'])) ?></div>
      <div class="sheet__money">
        <?= $m['due'] > 0 ? '<span class="due">' . kay_mxn($m['due']) . '</span><span class="muted small">' . kay_th('à encaisser') . '</span>'
                          : '<span class="ok">' . kay_th('Réglé') . '</span>' ?>
      </div>
      <div class="sheet__gear">
<?php if ($gear === []): ?>
        <span class="tag tag--task"><?= kay_th('Tailles non reçues') ?></span>
<?php else: foreach ($gear as $n => $g): ?>
        <span><strong><?= kay_h($g['name'] !== '' ? $g['name'] : '#' . $n) ?></strong> — <?= kay_h(kay_gear_line($g)) ?></span>
<?php endforeach; endif; ?>
      </div>
      <div class="sheet__contact no-print">
        <?php if ($wa !== null): ?><a class="btn btn--ghost btn--small" href="<?= kay_h($wa) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
        <?php if ($b['email'] !== ''): ?><a class="btn btn--ghost btn--small" href="mailto:<?= kay_h($b['email']) ?>"><?= kay_th('E-mail') ?></a><?php endif; ?>
      </div>
    </li>
<?php endforeach; ?>
  </ul>
</section>
<?php endforeach; ?>
<?php kay_page_end();
