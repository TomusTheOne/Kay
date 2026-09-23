<?php
declare(strict_types=1);

/**
 * Days the website must refuse. One tap on a day closes it, another opens
 * it; a range closes a holiday in one go. Nothing about a booking has to be
 * typed to block a date.
 *
 * Closing a day stops the website from taking it — booking.php refuses it,
 * and the form says so before the payment page. Bookings already made for
 * that day stay as they are, and Kay can still enter one by hand.
 */

require __DIR__ . '/_boot.php';
$admin = kay_admin($db);

$today = kay_today();
$month = (string) ($_GET['month'] ?? $_POST['month'] ?? '');
$first = DateTimeImmutable::createFromFormat('!Y-m-d', $month . '-01', kay_tz());
if ($first === false || $first->format('Y-m') !== $month) {
    $first = kay_now()->modify('first day of this month')->setTime(0, 0);
    $month = $first->format('Y-m');
}
$self  = kay_url('availability.php', ['month' => $month]);
$error = null;

if (kay_posted($admin)) {
    $do = (string) ($_POST['do'] ?? '');
    if ($do === 'toggle') {
        $day = (string) ($_POST['day'] ?? '');
        if ($day < $today) {
            $error = kay_t('Un jour passé ne peut plus être fermé.');
        } else {
            $closed = kay_toggle_day($db, $day, $admin['id']);
            if ($closed === null) {
                $error = kay_t('Date invalide.');
            } else {
                $busy = $closed && kay_days_overview($db, $day, 1)[$day]['bookings'] > 0;
                kay_redirect($self . '&m=' . ($closed ? ($busy ? 'closedbusy' : 'closed') : 'opened'));
            }
        }
    } elseif ($do === 'close' || $do === 'open') {
        $from = (string) ($_POST['from'] ?? '');
        $to   = (string) ($_POST['to'] ?? '') ?: $from;
        if (kay_day_range($from, $to) === []) {
            $error = kay_t('Indiquez une date de début et une date de fin.');
        } elseif (max($from, $to) < $today) {
            $error = kay_t('Ces dates sont passées.');
        } else {
            // Nothing in the past is ever closed or reopened: those days are history.
            $from = max(min($from, $to), $today);
            $to   = max($from, $to);
            if (count(kay_day_range($from, $to)) >= KAY_CLOSE_MAX_DAYS) {
                $error = kay_t('Au plus {n} jours à la fois.', ['{n}' => (string) KAY_CLOSE_MAX_DAYS]);
            } else {
                $do === 'close'
                    ? kay_close_days($db, $from, $to, (string) ($_POST['reason'] ?? ''), $admin['id'])
                    : kay_open_days($db, $from, $to);
                kay_redirect(kay_url('availability.php', ['month' => substr($from, 0, 7), 'm' => $do === 'close' ? 'rangeclosed' : 'rangeopened']));
            }
        }
    }
}

/* ------------------------------------------------------------- the month -- */

$last     = $first->modify('last day of this month');
$closed   = kay_closed_days($db, $first->format('Y-m-d'), $last->format('Y-m-d'));
$bookings = kay_days_overview($db, $first->format('Y-m-d'), (int) $last->format('j'));
$runs     = kay_closed_runs($db, $today, (new DateTimeImmutable($today))->modify('+18 months')->format('Y-m-d'));

// Sunday first in Mexico, Monday in France: whatever the language expects.
$weekStart = class_exists(IntlCalendar::class)
    ? (IntlCalendar::createInstance(null, kay_intl_locale())->getFirstDayOfWeek() === IntlCalendar::DOW_SUNDAY ? 0 : 1)
    : 1;
$lead = ((int) $first->format('w') - $weekStart + 7) % 7;   // empty cells before the 1st

kay_page_start(kay_t('Disponibilité'), 'availability.php', $admin);
?>
<header class="head">
  <div>
    <h1><?= kay_th('Disponibilité') ?></h1>
    <p class="muted"><?= kay_th('Touchez un jour pour le fermer : il ne pourra plus être réservé sur le site. Touchez-le à nouveau pour le rouvrir.') ?></p>
  </div>
</header>
<?= kay_error_box($error) ?>

<section class="card">
  <header class="cal__head">
    <a class="btn btn--ghost" href="<?= kay_h(kay_url('availability.php', ['month' => $first->modify('-1 month')->format('Y-m')])) ?>" aria-label="<?= kay_th('Mois précédent') ?>">‹</a>
    <h2><?= kay_h(kay_ucfirst(kay_fmt_date($first->format('Y-m-d'), 'month_year'))) ?></h2>
    <a class="btn btn--ghost" href="<?= kay_h(kay_url('availability.php', ['month' => $first->modify('+1 month')->format('Y-m')])) ?>" aria-label="<?= kay_th('Mois suivant') ?>">›</a>
  </header>

  <form method="post" class="cal">
    <?= kay_csrf_field($admin) ?>
    <input type="hidden" name="do" value="toggle">
    <input type="hidden" name="month" value="<?= kay_h($month) ?>">
<?php for ($i = 0; $i < 7; $i++):
    // Any week will do for the names: 2024-01-07 was a Sunday.
    $name = (new DateTimeImmutable('2024-01-07'))->modify('+' . (($i + $weekStart) % 7) . ' days')->format('Y-m-d'); ?>
    <span class="cal__dow" aria-hidden="true"><?= kay_h(kay_fmt_date($name, 'weekday')) ?></span>
<?php endfor; ?>
<?php for ($i = 0; $i < $lead; $i++): ?>
    <span class="cal__pad" aria-hidden="true"></span>
<?php endfor; ?>
<?php for ($d = $first; $d <= $last; $d = $d->modify('+1 day')):
    $ymd      = $d->format('Y-m-d');
    $isClosed = isset($closed[$ymd]);
    $isPast   = $ymd < $today;
    $n        = $bookings[$ymd]['bookings'] ?? 0;
    $classes  = trim(($isClosed ? 'is-closed ' : '') . ($isPast ? 'is-past ' : '') . ($ymd === $today ? 'is-today' : ''));
    $state    = $isClosed ? kay_t('fermé') : kay_t('ouvert');
    $label    = kay_ucfirst(kay_date_long($ymd)) . ' — ' . $state . ($n > 0 ? ', ' . kay_tn($n, '{n} réservation', '{n} réservations') : '')
        . ($isClosed && $closed[$ymd] !== '' ? ' (' . $closed[$ymd] . ')' : ''); ?>
    <button type="submit" name="day" value="<?= $ymd ?>" class="cal__day <?= $classes ?>"
      aria-pressed="<?= $isClosed ? 'true' : 'false' ?>" aria-label="<?= kay_h($label) ?>" title="<?= kay_h($label) ?>"<?= $isPast ? ' disabled' : '' ?>>
      <span class="cal__n"><?= (int) $d->format('j') ?></span>
      <?php if ($isClosed): ?><span class="cal__state"><?= kay_th('Fermé') ?></span><?php endif; ?>
      <?php if ($n > 0): ?><span class="cal__busy"><?= kay_h(kay_tn($n, '{n} résa', '{n} résas')) ?></span><?php endif; ?>
    </button>
<?php endfor; ?>
  </form>
  <p class="cal__legend small muted">
    <span class="cal__key cal__key--closed"></span> <?= kay_th('Fermé aux réservations en ligne') ?>
    <span class="cal__key cal__key--busy"></span> <?= kay_th('Réservations déjà prises') ?>
  </p>
</section>

<div class="cols cols--even">
  <section class="card">
    <header class="card__head"><h2><?= kay_th('Fermer plusieurs jours') ?></h2></header>
    <form method="post" class="stack">
      <?= kay_csrf_field($admin) ?>
      <input type="hidden" name="month" value="<?= kay_h($month) ?>">
      <div class="grid grid--2">
        <label class="field"><span><?= kay_th('Du') ?></span><input type="date" name="from" min="<?= kay_h($today) ?>" required></label>
        <label class="field"><span><?= kay_th('Au') ?></span><input type="date" name="to" min="<?= kay_h($today) ?>"></label>
      </div>
      <label class="field"><span><?= kay_th('Motif') ?> <small><?= kay_th('(facultatif, visible ici seulement)') ?></small></span>
        <input name="reason" maxlength="120" placeholder="<?= kay_th('Vacances, bateau en réparation, complet…') ?>"></label>
      <div class="actions">
        <button class="btn btn--primary" name="do" value="close"><?= kay_th('Fermer ces dates') ?></button>
        <button class="btn btn--ghost" name="do" value="open"><?= kay_th('Rouvrir ces dates') ?></button>
      </div>
      <p class="small muted"><?= kay_th('Les réservations déjà prises ces jours-là restent valables. Vous pouvez toujours en saisir une à la main.') ?></p>
    </form>
  </section>

  <section class="card">
    <header class="card__head"><h2><?= kay_th('Prochaines fermetures') ?></h2></header>
<?php if ($runs === []): ?>
    <p class="empty"><?= kay_th('Aucune date fermée. Le site prend les réservations tous les jours.') ?></p>
<?php else: ?>
    <ul class="rows rows--tight">
<?php foreach ($runs as $run): ?>
      <li class="row">
        <span class="row__main"><strong><?= $run['from'] === $run['to']
            ? kay_h(kay_ucfirst(kay_date_short($run['from'])))
            : kay_th('Du {from} au {to}', ['{from}' => kay_date_short($run['from']), '{to}' => kay_date_short($run['to'])]) ?></strong>
          <span class="muted small"><?= kay_h(kay_tn($run['days'], '{n} jour', '{n} jours')) ?><?= $run['reason'] !== '' ? ' · ' . kay_h($run['reason']) : '' ?></span></span>
        <form method="post" class="row__end">
          <?= kay_csrf_field($admin) ?>
          <input type="hidden" name="do" value="open">
          <input type="hidden" name="from" value="<?= kay_h($run['from']) ?>">
          <input type="hidden" name="to" value="<?= kay_h($run['to']) ?>">
          <input type="hidden" name="month" value="<?= kay_h($month) ?>">
          <button class="btn btn--ghost btn--small"><?= kay_th('Rouvrir') ?></button>
        </form>
      </li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
  </section>
</div>
<?php kay_page_end();
