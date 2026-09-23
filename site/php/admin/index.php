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
$closed    = kay_closed_days($db, $today, array_key_last($week));
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

kay_page_start(kay_t('Tableau de bord'), 'index.php', $admin);
?>
<header class="head">
  <div>
    <h1><?= kay_th('Bonjour {name}', ['{name}' => $first]) ?></h1>
    <p class="muted"><?= kay_h(kay_ucfirst(kay_date_long($today))) ?> · <?= kay_th('heure de Tulum {time}', ['{time}' => $now->format('H:i')]) ?></p>
  </div>
  <a class="btn btn--primary" href="booking-new.php"><?= kay_th('+ Nouvelle réservation') ?></a>
</header>

<?php if (isset($closed[$today])): ?>
<p class="note"><?= kay_th('Aujourd’hui est fermé aux réservations en ligne.') ?> <a href="availability.php"><?= kay_th('Disponibilité') ?></a></p>
<?php endif; ?>

<section class="kpis" aria-label="<?= kay_th('Chiffres clés') ?>">
  <?= kay_tile(kay_t('Plongeurs sur 7 jours'), kay_int($weekDiv), null, null,
        kay_tn($weekBook, '{n} réservation', '{n} réservations')) ?>
  <?= kay_tile(kay_t('Réservations ce mois-ci'), kay_int($made), $made, $madePrev, kay_t('vs même période le mois dernier')) ?>
  <?= kay_tile(kay_t('Encaissé ce mois-ci'), kay_mxn($cash / 100), (int) ($cash / 100), (int) ($cashPrev / 100), kay_t('acomptes en ligne + paiements saisis')) ?>
  <?= kay_tile(kay_t('Reste à encaisser'), kay_mxn($toCollect / 100), null, null, kay_t('soldes des plongées à venir')) ?>
  <?= kay_tile(kay_t('Visiteurs sur 30 jours'), kay_compact($traffic['visitors']), $traffic['visitors'], $trafPrev['visitors'],
        $traffic['visitors'] > 0 ? kay_t('{pct} ont payé un acompte', ['{pct}' => kay_pct($paid30 / $traffic['visitors'], 1)]) : '') ?>
</section>

<div class="cols">
  <div class="cols__main">

    <section class="card">
      <header class="card__head">
        <h2><?= kay_th('Aujourd’hui') ?></h2>
        <a href="planning.php"><?= kay_th('Planning complet →') ?></a>
      </header>
<?php if ($todaySheet === []): ?>
      <p class="empty"><?= kay_th('Personne ne plonge aujourd’hui.') ?></p>
<?php else: ?>
      <ul class="rows">
<?php foreach ($todaySheet as $b): $money = kay_booking_money($b); ?>
        <li><a class="row" href="<?= kay_h(kay_url('booking.php', ['id' => $b['id']])) ?>">
          <span class="row__time"><?= kay_h(kay_slot_text($b['start_slot'])) ?></span>
          <span class="row__main"><strong><?= kay_h($b['name']) ?></strong>
            <span class="muted"><?= kay_h(kay_product_name($b['product'])) ?> · <?= kay_h(kay_dives_text((int) $b['dives'])) ?> · <?= kay_th('{n} pers.', ['{n}' => (string) (int) $b['divers']]) ?></span></span>
          <span class="row__end"><?= $money['due'] > 0
              ? '<span class="due">' . kay_th('{amount} à encaisser', ['{amount}' => kay_mxn($money['due'])]) . '</span>'
              : kay_status_badge($b['status']) ?></span>
        </a></li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
      <ol class="week" aria-label="<?= kay_th('Les 7 prochains jours') ?>">
<?php foreach ($week as $day => $w): $isClosed = isset($closed[$day]); ?>
        <li><a href="<?= kay_h(kay_url('planning.php', ['date' => $day])) ?>"<?= $day === $today ? ' aria-current="date"' : '' ?> class="<?= $isClosed ? 'is-closed' : '' ?>">
          <span class="week__day"><?= kay_h(kay_fmt_date($day, 'weekday_day')) ?></span>
          <span class="week__n"><?= $w['divers'] ?></span>
          <span class="week__unit"><?= $isClosed ? kay_th('fermé') : kay_h(kay_tn($w['divers'], 'plongeur', 'plongeurs')) ?></span>
        </a></li>
<?php endforeach; ?>
      </ol>
    </section>

    <section class="card">
      <header class="card__head">
        <h2><?= kay_th('Chiffre d’affaires par mois de plongée') ?></h2>
        <span class="muted small"><?= kay_th('réservations confirmées, en MXN') ?></span>
      </header>
<?php
$points = [];
foreach ($revenue as $month => $pesos) {
    $points[] = [
        'label' => kay_fmt_date($month . '-01', 'month_year'),
        'short' => kay_fmt_date($month . '-01', 'month'),
        'value' => $pesos,
    ];
}
echo kay_chart_columns($points, ['unit' => 'MXN', 'title' => kay_t('Chiffre d’affaires par mois'), 'labels' => 12,
                                 'accent' => count($points) - 1]);
?>
    </section>

    <section class="card">
      <header class="card__head">
        <h2><?= kay_th('Dernières réservations') ?></h2>
        <a href="bookings.php?view=all"><?= kay_th('Toutes →') ?></a>
      </header>
<?php if ($recent === []): ?>
      <p class="empty"><?= kay_th('Aucune réservation pour l’instant.') ?></p>
<?php else: ?>
      <div class="table-wrap"><table class="table table--stack">
        <thead><tr><th scope="col"><?= kay_th('Reçue') ?></th><th scope="col"><?= kay_th('Client') ?></th><th scope="col"><?= kay_th('Sortie') ?></th>
          <th scope="col"><?= kay_th('Plongée') ?></th><th scope="col" class="num"><?= kay_th('Total') ?></th><th scope="col"><?= kay_th('Statut') ?></th></tr></thead>
        <tbody>
<?php foreach ($recent as $b): ?>
          <tr>
            <td data-label="<?= kay_th('Reçue') ?>"><?= kay_h(kay_when($b['created_at'])) ?></td>
            <td data-label="<?= kay_th('Client') ?>"><a href="<?= kay_h(kay_url('booking.php', ['id' => $b['id']])) ?>"><?= kay_h($b['name']) ?></a>
              <?= $b['source'] === 'manual' ? '<span class="tag">' . kay_th('saisie') . '</span>' : '' ?></td>
            <td data-label="<?= kay_th('Sortie') ?>"><?= kay_h(kay_product_name($b['product'])) ?> · <?= kay_th('{n} pers.', ['{n}' => (string) (int) $b['divers']]) ?></td>
            <td data-label="<?= kay_th('Plongée') ?>"><?= kay_h(kay_date_short($b['dive_date'])) ?></td>
            <td data-label="<?= kay_th('Total') ?>" class="num"><?= kay_mxn((int) $b['total_mxn_cents'] / 100) ?></td>
            <td data-label="<?= kay_th('Statut') ?>"><?= kay_status_badge($b['status']) ?></td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table></div>
<?php endif; ?>
    </section>
  </div>

  <aside class="cols__side">
    <section class="card">
      <header class="card__head"><h2><?= kay_th('À faire') ?></h2></header>
<?php if ($tasks === []): ?>
      <p class="empty"><?= kay_th('Rien en attente. Les relances se créent depuis la fiche d’un client.') ?></p>
<?php else: ?>
      <ul class="tasks">
<?php foreach ($tasks as $t): $late = $t['due_on'] < $today; ?>
        <li class="task<?= $late ? ' is-late' : '' ?>">
          <form method="post" action="note.php">
            <?= kay_csrf_field($admin) ?>
            <input type="hidden" name="action" value="done">
            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
            <input type="hidden" name="back" value="index.php">
            <button class="check" aria-label="<?= kay_th('Marquer comme fait : {task}', ['{task}' => mb_substr($t['body'], 0, 60)]) ?>"></button>
          </form>
          <div>
            <p><?= nl2br(kay_h($t['body'])) ?></p>
            <p class="small muted"><?= $late ? '<strong class="late">' . kay_th('En retard') . '</strong> · ' : '' ?><?= kay_h(kay_date_short($t['due_on'])) ?>
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
      <header class="card__head"><h2><?= kay_th('Paiements non aboutis') ?></h2></header>
      <p class="small muted"><?= kay_th('Ont ouvert le paiement ces 14 derniers jours sans payer, et n’ont pas réservé depuis. Souvent une question sans réponse : un message suffit.') ?></p>
<?php if ($abandoned === []): ?>
      <p class="empty"><?= kay_th('Aucun.') ?></p>
<?php else: ?>
      <ul class="rows rows--tight">
<?php foreach ($abandoned as $b): ?>
        <li><a class="row" href="<?= kay_h(kay_url('booking.php', ['id' => $b['id']])) ?>">
          <span class="row__main"><strong><?= kay_h($b['name']) ?></strong>
            <span class="muted small"><?= kay_h(kay_product_name($b['product'])) ?> · <?= kay_th('{n} pers.', ['{n}' => (string) (int) $b['divers']]) ?> · <?= kay_h(kay_date_short($b['dive_date'])) ?></span></span>
          <span class="row__end small muted"><?= kay_h(kay_when($b['created_at'])) ?></span>
        </a></li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
    </section>

    <section class="card">
      <header class="card__head">
        <h2><?= kay_th('Visiteurs, 30 jours') ?></h2>
        <a href="traffic.php"><?= kay_th('Trafic →') ?></a>
      </header>
      <p class="big"><?= kay_int($traffic['visitors']) ?> <span class="muted small"><?= kay_th('visiteurs · {n} pages vues', ['{n}' => kay_int($traffic['pageviews'])]) ?></span></p>
<?php
$mini = [];
foreach ($series as $day => $v) {
    $mini[] = ['label' => kay_date_short($day), 'value' => $v['visitors']];
}
echo kay_chart_columns($mini, ['unit' => kay_t('visiteurs'), 'title' => kay_t('Visiteurs par jour, 30 jours'), 'mini' => true,
                               'accent' => count($mini) - 1]);
?>
    </section>
  </aside>
</div>
<?php kay_page_end();
