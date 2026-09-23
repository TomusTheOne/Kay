<?php
declare(strict_types=1);

/**
 * Who comes to the site, from where, and how far they get towards a booking.
 * Counted by the site itself — see php/lib/traffic.php for what is kept.
 */

require __DIR__ . '/_boot.php';
$admin = kay_admin($db);

$r     = kay_traffic_range((string) ($_GET['range'] ?? '30d'));
$t     = kay_traffic_totals($db, $r['from'], $r['to']);
$prev  = kay_traffic_totals($db, $r['prev_from'], $r['prev_to']);
$paid  = kay_deposits_paid($db, $r['from'], $r['to']);
$live  = kay_traffic_live($db);
$series = kay_traffic_series($db, $r['from'], $r['to'], $r['bucket']);

$bounce     = $t['visits'] > 0 ? $t['bounces'] / $t['visits'] : 0.0;
$bouncePrev = $prev['visits'] > 0 ? $prev['bounces'] / $prev['visits'] : 0.0;
$perVisit   = $t['visits'] > 0 ? $t['pageviews'] / $t['visits'] : 0.0;

$points = [];
foreach ($series as $key => $v) {
    [$label, $short] = match ($r['bucket']) {
        'hour'  => [$key . ' h', $key . 'h'],
        'month' => [ucfirst(kay_fmt_date($key . '-01', 'MMMM y')), kay_fmt_date($key . '-01', 'MMM')],
        default => [kay_date_short($key), kay_fmt_date($key, 'd MMM')],
    };
    $points[] = ['label' => $label, 'short' => $short, 'value' => $v['visitors'],
                 'extra' => kay_int($v['pageviews']) . ' pages vues'];
}

/** A breakdown table: label, a bar for the share, visitors. */
$breakdown = static function (string $title, string $dimension, callable $label, int $limit = 8, string $unit = 'visiteurs')
    use ($db, $r): string {
    $rows = kay_traffic_top($db, $r['from'], $r['to'], $dimension, $limit);
    $out  = '<section class="card card--flush"><header class="card__head"><h2>' . kay_h($title) . '</h2></header>';
    if ($rows === []) {
        return $out . '<p class="empty">Pas encore de données.</p></section>';
    }
    $max  = max(array_column($rows, 'visitors'));
    $out .= '<table class="table table--meter"><thead><tr><th scope="col">' . kay_h($title) . '</th>'
        . '<th scope="col" class="num">' . kay_h(ucfirst($unit)) . '</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $out .= '<tr>' . kay_meter_cell($label($row['value']), $row['visitors'], $max)
            . '<td class="num">' . kay_int($row['visitors']) . '</td></tr>';
    }
    return $out . '</tbody></table></section>';
};

kay_page_start('Trafic', 'traffic.php', $admin);
?>
<header class="head">
  <div><h1>Trafic du site</h1>
    <p class="muted"><span class="live" aria-hidden="true"></span> <?= kay_int($live) ?> visiteur<?= $live > 1 ? 's' : '' ?> ces 30 dernières minutes</p></div>
  <nav class="segmented" aria-label="Période">
<?php foreach (KAY_TRAFFIC_RANGES as $key => $label): ?>
    <a href="<?= kay_h(kay_url('traffic.php', ['range' => $key])) ?>"<?= $key === $r['key'] ? ' aria-current="page"' : '' ?>><?= kay_h($label) ?></a>
<?php endforeach; ?>
  </nav>
</header>

<section class="kpis" aria-label="Chiffres de la période">
  <?= kay_tile('Visiteurs', kay_compact($t['visitors']), $t['visitors'], $prev['visitors'], 'vs période précédente') ?>
  <?= kay_tile('Pages vues', kay_compact($t['pageviews']), $t['pageviews'], $prev['pageviews'],
        number_format($perVisit, 1, ',', '') . ' par visite') ?>
  <?= kay_tile('Rebond', kay_pct($bounce), null, null, 'une page, sans voir le formulaire') ?>
  <?= kay_tile('Acomptes payés', kay_int($paid), $paid, kay_deposits_paid($db, $r['prev_from'], $r['prev_to']),
        $t['visitors'] > 0 ? kay_pct($paid / $t['visitors'], 1) . ' des visiteurs' : '') ?>
</section>

<section class="card">
  <header class="card__head">
    <h2>Visiteurs <?= ['hour' => 'par heure', 'day' => 'par jour', 'month' => 'par mois'][$r['bucket']] ?></h2>
    <span class="muted small">heure de Tulum</span>
  </header>
  <?= kay_chart_columns($points, ['unit' => 'visiteurs', 'title' => 'Visiteurs sur la période',
                                  'labels' => $r['bucket'] === 'month' ? 12 : 8]) ?>
</section>

<div class="cols cols--even">
  <section class="card">
    <header class="card__head"><h2>Du visiteur à l’acompte</h2></header>
<?php
$steps = [
    ['Visiteurs', $t['visitors'], 'ont ouvert une page du site'],
    ['Ont vu le formulaire', $t['booking_views'], 'ont fait défiler jusqu’à la réservation'],
    ['Ont ouvert le paiement', $t['checkouts'], 'sont partis vers Mercado Pago'],
    ['Ont payé l’acompte', $paid, 'réservations web payées sur la période'],
];
$top = max(1, $t['visitors']);
?>
    <ol class="funnel">
<?php foreach ($steps as $i => [$label, $n, $hint]):
    $from = $i > 0 ? $steps[$i - 1][1] : null; ?>
      <li>
        <p class="funnel__label"><strong><?= kay_h($label) ?></strong>
          <span class="funnel__n"><?= kay_int($n) ?></span></p>
        <span class="funnel__track" aria-hidden="true"><span class="funnel__bar w-<?= min(100, (int) round($n / $top * 100)) ?>"></span></span>
        <p class="small muted"><?= kay_h($hint) ?><?= $from ? ' · ' . kay_pct($n / $from) . ' de l’étape précédente' : '' ?></p>
      </li>
<?php endforeach; ?>
    </ol>
  </section>
  <?= $breakdown('Sources', 'source', static fn(string $v): string => $v === '' ? 'Accès direct <span class="muted">(lien tapé, favori, WhatsApp…)</span>' : kay_h($v)) ?>
</div>

<div class="cols cols--even">
  <?= $breakdown('Pays', 'country', static fn(string $v): string => kay_h(kay_country($v))) ?>
  <?= $breakdown('Pages', 'path', static fn(string $v): string => '<code>' . kay_h($v) . '</code>') ?>
</div>

<div class="cols cols--even">
  <?= $breakdown('Langue du site', 'locale', static fn(string $v): string => kay_h(['en' => 'Anglais', 'es' => 'Espagnol', 'fr' => 'Français'][$v] ?? ($v ?: 'Autre'))) ?>
  <?= $breakdown('Langue du navigateur', 'lang', static fn(string $v): string => kay_h(KAY_LANG_NAMES[$v] ?? ($v !== '' ? strtoupper($v) : 'Inconnue'))) ?>
</div>

<div class="cols cols--even">
  <?= $breakdown('Appareils', 'device', static fn(string $v): string => kay_h(KAY_DEVICE_NAMES[$v] ?? $v)) ?>
  <?= $breakdown('Navigateurs', 'browser', static fn(string $v): string => kay_h($v)) ?>
</div>

<div class="cols cols--even">
  <?= $breakdown('Sorties choisies au paiement', 'product', static fn(string $v): string => kay_h(kay_product_name($v)), 8, 'paiements ouverts') ?>
  <?= $breakdown('Campagnes (utm_campaign)', 'utm_campaign', static fn(string $v): string => kay_h($v)) ?>
</div>

<section class="card method">
  <h2>Comment c’est compté</h2>
  <ul class="small">
    <li><strong>Aucun cookie, aucune adresse IP enregistrée.</strong> Un visiteur est une empreinte anonyme recalculée chaque jour avec une clé qui est ensuite détruite : impossible de le suivre d’un jour à l’autre, ni de remonter à une personne. Pas de bandeau de consentement nécessaire.</li>
    <li><strong>Visiteurs = visiteurs uniques par jour, additionnés.</strong> Quelqu’un qui revient trois jours compte trois fois.</li>
    <li><strong>Le pays vient du fuseau horaire de l’appareil</strong>, pas de l’adresse IP : un Canadien déjà à Tulum compte pour le Mexique.</li>
    <li>Les robots d’indexation et vos propres visites (tout navigateur connecté à cet admin) ne sont pas comptés. Les bloqueurs de publicité peuvent masquer une petite part des visites.</li>
    <li>Pour suivre une campagne, ajoutez <code>?utm_source=instagram&amp;utm_campaign=promo-mai</code> au lien partagé.</li>
  </ul>
</section>
<?php kay_page_end();
