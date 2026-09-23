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

$sheet = kay_day_sheet($db, $date);
$strip = kay_days_overview($db, $today, 14);

$pendingHere = $db->prepare("SELECT COUNT(*) FROM bookings WHERE dive_date = ? AND status = 'pending'");
$pendingHere->execute([$date]);
$pendingHere = (int) $pendingHere->fetchColumn();

// By departure, in the catalogue's order, with "time to be set" last.
$groups = [];
foreach ($sheet as $b) {
    $groups[$b['start_slot']][] = $b;
}

$divers = array_sum(array_map(static fn($b) => (int) $b['divers'], $sheet));
$due    = array_sum(array_map(static fn($b) => kay_booking_money($b)['due'], $sheet));
$pickups = count(array_filter($sheet, static fn($b) => $b['pickup'] !== 'meeting-point'));

$offset   = (int) (new DateTimeImmutable($today))->diff(new DateTimeImmutable($date))->format('%r%a');
$relative = match (true) {
    $offset === 0  => 'Aujourd’hui',
    $offset === 1  => 'Demain',
    $offset === -1 => 'Hier',
    $offset > 1    => "Dans $offset jours",
    default        => 'Il y a ' . -$offset . ' jours',
};

kay_page_start('Planning', 'planning.php', $admin);
?>
<header class="head">
  <div>
    <h1><?= kay_h(ucfirst(kay_date_long($date))) ?></h1>
    <p class="muted"><?= kay_h($relative) ?></p>
  </div>
  <div class="head__actions no-print">
    <a class="btn btn--ghost" href="<?= kay_h(kay_url('planning.php', ['date' => $parsed->modify('-1 day')->format('Y-m-d')])) ?>" aria-label="Jour précédent">←</a>
    <form method="get" class="inline"><label class="sr-only" for="d">Date</label>
      <input id="d" type="date" name="date" value="<?= kay_h($date) ?>" data-autosubmit></form>
    <a class="btn btn--ghost" href="<?= kay_h(kay_url('planning.php', ['date' => $parsed->modify('+1 day')->format('Y-m-d')])) ?>" aria-label="Jour suivant">→</a>
    <button class="btn btn--ghost" data-print>Imprimer</button>
  </div>
</header>

<ol class="strip no-print" aria-label="Les 14 prochains jours">
<?php foreach ($strip as $day => $w): ?>
  <li><a href="<?= kay_h(kay_url('planning.php', ['date' => $day])) ?>"<?= $day === $date ? ' aria-current="date"' : '' ?> class="<?= $w['divers'] > 0 ? 'has' : '' ?>">
    <span><?= kay_h(kay_fmt_date($day, 'EEE')) ?></span>
    <strong><?= kay_h(kay_fmt_date($day, 'd')) ?></strong>
    <small><?= $w['divers'] > 0 ? $w['divers'] . ' pl.' : '—' ?></small>
  </a></li>
<?php endforeach; ?>
</ol>

<section class="kpis kpis--small">
  <?= kay_tile('Réservations', kay_int(count($sheet))) ?>
  <?= kay_tile('Plongeurs', kay_int($divers)) ?>
  <?= kay_tile('Ramassages', kay_int($pickups)) ?>
  <?= kay_tile('À encaisser', kay_mxn($due)) ?>
</section>

<?php if ($pendingHere > 0): ?>
<p class="note no-print"><?= $pendingHere ?> paiement<?= $pendingHere > 1 ? 's' : '' ?> en attente pour ce jour, non comptés ici.
  <a href="<?= kay_h(kay_url('bookings.php', ['view' => 'pending', 'from' => $date, 'to' => $date])) ?>">Voir</a></p>
<?php endif; ?>

<?php if ($sheet === []): ?>
<section class="card"><p class="empty">Aucune plongée confirmée ce jour-là.</p>
  <p class="no-print"><a class="btn btn--primary" href="<?= kay_h(kay_url('booking-new.php', ['date' => $date])) ?>">+ Ajouter une réservation</a></p></section>
<?php endif; ?>

<?php foreach (array_merge(array_column(kay_catalogue()['schedules'], 'slug'), array_keys($groups)) as $slot):
    if (empty($groups[$slot])) continue;
    $list = $groups[$slot];
    unset($groups[$slot]);
    $slotDivers = array_sum(array_map(static fn($b) => (int) $b['divers'], $list)); ?>
<section class="card sheet">
  <header class="card__head">
    <h2><?= kay_h(kay_slot_text($slot)) ?></h2>
    <span class="muted"><?= $slotDivers ?> plongeur<?= $slotDivers > 1 ? 's' : '' ?></span>
  </header>
  <ul class="sheet__list">
<?php foreach ($list as $b):
    $m = kay_booking_money($b);
    $phone = (string) ($b['customer_phone'] ?? '');
    $wa = kay_whatsapp_url($phone); ?>
    <li class="sheet__item">
      <div class="sheet__who">
        <a href="<?= kay_h(kay_url('booking.php', ['id' => $b['id']])) ?>"><strong><?= kay_h($b['name']) ?></strong></a>
        <span class="muted"><?= (int) $b['divers'] ?> pers. · <?= kay_h($b['certification'] !== '' ? $b['certification'] : 'sans niveau indiqué') ?> · <?= strtoupper(kay_h($b['locale'])) ?></span>
      </div>
      <div class="sheet__what"><?= kay_h(kay_product_name($b['product'])) ?> · <?= kay_h(kay_dives_text((int) $b['dives'])) ?>
        <?= $b['start_slot'] === 'other' && $b['start_note'] !== '' ? '<br><span class="muted">Souhaite : ' . kay_h($b['start_note']) . '</span>' : '' ?></div>
      <div class="sheet__pickup<?= $b['pickup'] !== 'meeting-point' ? ' is-pickup' : '' ?>"><?= kay_h(kay_pickup_name($b['pickup'])) ?></div>
      <div class="sheet__money">
        <?= $m['due'] > 0 ? '<span class="due">' . kay_mxn($m['due']) . '</span><span class="muted small">à encaisser</span>'
                          : '<span class="ok">Réglé</span>' ?>
      </div>
      <div class="sheet__contact no-print">
        <?php if ($wa !== null): ?><a class="btn btn--ghost btn--small" href="<?= kay_h($wa) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
        <?php if ($b['email'] !== ''): ?><a class="btn btn--ghost btn--small" href="mailto:<?= kay_h($b['email']) ?>">E-mail</a><?php endif; ?>
      </div>
    </li>
<?php endforeach; ?>
  </ul>
</section>
<?php endforeach; ?>
<?php kay_page_end();
