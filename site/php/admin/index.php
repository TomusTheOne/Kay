<?php
declare(strict_types=1);

/**
 * The first screen: who is diving this week, what came in this month, what
 * needs a follow-up, and whether the site is being visited.
 */

require __DIR__ . '/_boot.php';
$admin = kay_admin($db);

$now        = kay_now();
$today      = $now->format('Y-m-d');
$monthStart = $now->format('Y-m-01');
// The same number of days into last month, so the comparison is fair on the 5th.
$dayOfMonth = (int) $now->format('j');
$prevStart  = $now->modify('first day of last month')->format('Y-m-d');
$prevEnd    = min(
    (new DateTimeImmutable($prevStart))->modify('+' . ($dayOfMonth - 1) . ' days')->format('Y-m-d'),
    $now->modify('last day of last month')->format('Y-m-d'),
);

$week      = kay_days_overview($db, $today, 7);
$weekDiv   = array_sum(array_column($week, 'divers'));
$weekBook  = array_sum(array_column($week, 'bookings'));
$made      = kay_bookings_made($db, $monthStart, $today);
$madePrev  = kay_bookings_made($db, $prevStart, $prevEnd);
$cash      = kay_money_in($db, $monthStart, $today);
$cashPrev  = kay_money_in($db, $prevStart, $prevEnd);
$toCollect = kay_balance_outstanding($db);

$range     = kay_traffic_range('30d', $now);
$traffic   = kay_traffic_totals($db, $range['from'], $range['to']);
$trafPrev  = kay_traffic_totals($db, $range['prev_from'], $range['prev_to']);
$paid30    = kay_deposits_paid($db, $range['from'], $range['to']);
$series    = kay_traffic_series($db, $range['from'], $range['to'], 'day');

$todaySheet = kay_day_sheet($db, $today);
$tasks      = kay_tasks_open($db, 8);
$abandoned  = kay_abandoned_checkouts($db, 14, 6);
$recent     = kay_recent_bookings($db, 8);
$revenue    = kay_revenue_by_month($db, 12);

$first = explode(' ', trim($admin['name']))[0];

kay_page_start('Tableau de bord', 'index.php', $admin);
?>
<header class="head">
  <div>
    <h1>Bonjour <?= kay_h($first) ?></h1>
    <p class="muted"><?= kay_h(ucfirst(kay_date_long($today))) ?> · heure de Tulum <?= $now->format('H:i') ?></p>
  </div>
  <a class="btn btn--primary" href="booking-new.php">+ Nouvelle réservation</a>
</header>

<section class="kpis" aria-label="Chiffres clés">
  <?= kay_tile('Plongeurs sur 7 jours', kay_int($weekDiv), null, null,
        $weekBook . ' réservation' . ($weekBook > 1 ? 's' : '')) ?>
  <?= kay_tile('Réservations ce mois-ci', kay_int($made), $made, $madePrev, 'vs même période le mois dernier') ?>
  <?= kay_tile('Encaissé ce mois-ci', kay_mxn($cash / 100), (int) ($cash / 100), (int) ($cashPrev / 100), 'acomptes en ligne + paiements saisis') ?>
  <?= kay_tile('Reste à encaisser', kay_mxn($toCollect / 100), null, null, 'soldes des plongées à venir') ?>
  <?= kay_tile('Visiteurs sur 30 jours', kay_compact($traffic['visitors']), $traffic['visitors'], $trafPrev['visitors'],
        $traffic['visitors'] > 0 ? kay_pct($paid30 / $traffic['visitors'], 1) . ' ont payé un acompte' : '') ?>
</section>

<div class="cols">
  <div class="cols__main">

    <section class="card">
      <header class="card__head">
        <h2>Aujourd’hui</h2>
        <a href="planning.php">Planning complet →</a>
      </header>
<?php if ($todaySheet === []): ?>
      <p class="empty">Personne ne plonge aujourd’hui.</p>
<?php else: ?>
      <ul class="rows">
<?php foreach ($todaySheet as $b): $money = kay_booking_money($b); ?>
        <li><a class="row" href="<?= kay_h(kay_url('booking.php', ['id' => $b['id']])) ?>">
          <span class="row__time"><?= kay_h(kay_slot_text($b['start_slot'])) ?></span>
          <span class="row__main"><strong><?= kay_h($b['name']) ?></strong>
            <span class="muted"><?= kay_h(kay_product_name($b['product'])) ?> · <?= kay_h(kay_dives_text((int) $b['dives'])) ?> · <?= (int) $b['divers'] ?> pers.</span></span>
          <span class="row__end"><?= $money['due'] > 0 ? '<span class="due">' . kay_mxn($money['due']) . ' à encaisser</span>' : kay_status_badge($b['status']) ?></span>
        </a></li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
      <ol class="week" aria-label="Les 7 prochains jours">
<?php foreach ($week as $day => $w): ?>
        <li><a href="<?= kay_h(kay_url('planning.php', ['date' => $day])) ?>"<?= $day === $today ? ' aria-current="date"' : '' ?>>
          <span class="week__day"><?= kay_h(kay_fmt_date($day, 'EEE d')) ?></span>
          <span class="week__n"><?= $w['divers'] ?></span>
          <span class="week__unit"><?= $w['divers'] === 1 ? 'plongeur' : 'plongeurs' ?></span>
        </a></li>
<?php endforeach; ?>
      </ol>
    </section>

    <section class="card">
      <header class="card__head">
        <h2>Chiffre d’affaires par mois de plongée</h2>
        <span class="muted small">réservations confirmées, en MXN</span>
      </header>
<?php
$points = [];
$i = 0;
foreach ($revenue as $month => $pesos) {
    $points[] = [
        'label' => ucfirst(kay_fmt_date($month . '-01', 'MMMM y')),
        'short' => kay_fmt_date($month . '-01', 'MMM'),
        'value' => $pesos,
    ];
}
echo kay_chart_columns($points, ['unit' => 'MXN', 'title' => 'Chiffre d’affaires par mois', 'labels' => 12,
                                 'accent' => count($points) - 1]);
?>
    </section>

    <section class="card">
      <header class="card__head">
        <h2>Dernières réservations</h2>
        <a href="bookings.php?view=all">Toutes →</a>
      </header>
<?php if ($recent === []): ?>
      <p class="empty">Aucune réservation pour l’instant.</p>
<?php else: ?>
      <div class="table-wrap"><table class="table table--stack">
        <thead><tr><th scope="col">Reçue</th><th scope="col">Client</th><th scope="col">Sortie</th>
          <th scope="col">Plongée</th><th scope="col" class="num">Total</th><th scope="col">Statut</th></tr></thead>
        <tbody>
<?php foreach ($recent as $b): ?>
          <tr>
            <td data-label="Reçue"><?= kay_h(kay_when($b['created_at'])) ?></td>
            <td data-label="Client"><a href="<?= kay_h(kay_url('booking.php', ['id' => $b['id']])) ?>"><?= kay_h($b['name']) ?></a>
              <?= $b['source'] === 'manual' ? '<span class="tag">saisie</span>' : '' ?></td>
            <td data-label="Sortie"><?= kay_h(kay_product_name($b['product'])) ?> · <?= (int) $b['divers'] ?> pers.</td>
            <td data-label="Plongée"><?= kay_h(kay_date_short($b['dive_date'])) ?></td>
            <td data-label="Total" class="num"><?= kay_mxn((int) $b['total_mxn_cents'] / 100) ?></td>
            <td data-label="Statut"><?= kay_status_badge($b['status']) ?></td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table></div>
<?php endif; ?>
    </section>
  </div>

  <aside class="cols__side">
    <section class="card">
      <header class="card__head"><h2>À faire</h2></header>
<?php if ($tasks === []): ?>
      <p class="empty">Rien en attente. Les relances se créent depuis la fiche d’un client.</p>
<?php else: ?>
      <ul class="tasks">
<?php foreach ($tasks as $t): $late = $t['due_on'] < $today; ?>
        <li class="task<?= $late ? ' is-late' : '' ?>">
          <form method="post" action="note.php">
            <?= kay_csrf_field($admin) ?>
            <input type="hidden" name="action" value="done">
            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
            <input type="hidden" name="back" value="index.php">
            <button class="check" aria-label="Marquer comme fait : <?= kay_h(mb_substr($t['body'], 0, 60)) ?>"></button>
          </form>
          <div>
            <p><?= nl2br(kay_h($t['body'])) ?></p>
            <p class="small muted"><?= $late ? '<strong class="late">En retard</strong> · ' : '' ?><?= kay_h(kay_date_short($t['due_on'])) ?>
<?php if ($t['customer_id'] !== null): ?>
              · <a href="<?= kay_h(kay_url('customer.php', ['id' => $t['customer_id']])) ?>"><?= kay_h($t['customer_name']) ?></a>
<?php endif; ?></p>
          </div>
        </li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
    </section>

    <section class="card">
      <header class="card__head"><h2>Paiements non aboutis</h2></header>
      <p class="small muted">Ont ouvert le paiement ces 14 derniers jours sans payer, et n’ont pas réservé depuis. Souvent une question sans réponse : un message suffit.</p>
<?php if ($abandoned === []): ?>
      <p class="empty">Aucun.</p>
<?php else: ?>
      <ul class="rows rows--tight">
<?php foreach ($abandoned as $b): ?>
        <li><a class="row" href="<?= kay_h(kay_url('booking.php', ['id' => $b['id']])) ?>">
          <span class="row__main"><strong><?= kay_h($b['name']) ?></strong>
            <span class="muted small"><?= kay_h(kay_product_name($b['product'])) ?> · <?= (int) $b['divers'] ?> pers. · <?= kay_h(kay_date_short($b['dive_date'])) ?></span></span>
          <span class="row__end small muted"><?= kay_h(kay_when($b['created_at'])) ?></span>
        </a></li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
    </section>

    <section class="card">
      <header class="card__head">
        <h2>Visiteurs, 30 jours</h2>
        <a href="traffic.php">Trafic →</a>
      </header>
      <p class="big"><?= kay_int($traffic['visitors']) ?> <span class="muted small">visiteurs · <?= kay_int($traffic['pageviews']) ?> pages vues</span></p>
<?php
$mini = [];
foreach ($series as $day => $v) {
    $mini[] = ['label' => kay_date_short($day), 'value' => $v['visitors']];
}
echo kay_chart_columns($mini, ['unit' => 'visiteurs', 'title' => 'Visiteurs par jour, 30 jours', 'mini' => true,
                               'accent' => count($mini) - 1]);
?>
    </section>
  </aside>
</div>
<?php kay_page_end();
