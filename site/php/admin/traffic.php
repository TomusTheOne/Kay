<?php
declare(strict_types=1);

/**
 * Who comes to the site, from where, and how far they get towards a booking.
 * Counted by the site itself — see php/lib/traffic.php for what is kept.
 */

require __DIR__ . '/_boot.php';
$admin = kay_admin($db);

$r      = kay_traffic_range((string) ($_GET['range'] ?? '30d'));
$t      = kay_traffic_totals($db, $r['from'], $r['to']);
$prev   = kay_traffic_totals($db, $r['prev_from'], $r['prev_to']);
$paid   = kay_deposits_paid($db, $r['from'], $r['to']);
$live   = kay_traffic_live($db);
$series = kay_traffic_series($db, $r['from'], $r['to'], $r['bucket']);

$bounce   = $t['visits'] > 0 ? $t['bounces'] / $t['visits'] : 0.0;
$perVisit = $t['visits'] > 0 ? $t['pageviews'] / $t['visits'] : 0.0;

$points = [];
foreach ($series as $key => $v) {
    [$label, $short] = match ($r['bucket']) {
        'hour'  => [$key . ' h', $key . 'h'],
        'month' => [kay_ucfirst(kay_fmt_date($key . '-01', 'month_year')), kay_fmt_date($key . '-01', 'month')],
        default => [kay_date_short($key), kay_fmt_date($key, 'day_month')],
    };
    $points[] = ['label' => $label, 'short' => $short, 'value' => $v['visitors'],
                 'extra' => kay_t('{n} pages vues', ['{n}' => kay_int($v['pageviews'])])];
}

/** A breakdown table: label, a bar for the share, visitors. */
$breakdown = static function (string $title, string $dimension, callable $label, int $limit = 8, string $unit = '')
    use ($db, $r): string {
    $unit = $unit !== '' ? $unit : kay_t('visiteurs');
    $rows = kay_traffic_top($db, $r['from'], $r['to'], $dimension, $limit);
    $out  = '<section class="card card--flush"><header class="card__head"><h2>' . kay_h($title) . '</h2></header>';
    if ($rows === []) {
        return $out . '<p class="empty">' . kay_th('Pas encore de données.') . '</p></section>';
    }
    $max  = max(array_column($rows, 'visitors'));
    $out .= '<table class="table table--meter"><thead><tr><th scope="col">' . kay_h($title) . '</th>'
        . '<th scope="col" class="num">' . kay_h(kay_ucfirst($unit)) . '</th></tr></thead><tbody>';
    foreach ($rows as $row) {
        $out .= '<tr>' . kay_meter_cell($label($row['value']), $row['visitors'], $max)
            . '<td class="num">' . kay_int($row['visitors']) . '</td></tr>';
    }
    return $out . '</tbody></table></section>';
};

/** 'other', which traffic.php stores for a browser or system it does not know. */
$other = static fn(string $v): string => $v === 'other' || $v === 'Autre' ? kay_th('Autre') : kay_h($v);

kay_page_start(kay_t('Trafic'), 'traffic.php', $admin);
?>
<header class="head">
  <div><h1><?= kay_th('Trafic du site') ?></h1>
    <p class="muted"><span class="live" aria-hidden="true"></span> <?= kay_h(kay_tn($live, '{n} visiteur ces 30 dernières minutes', '{n} visiteurs ces 30 dernières minutes')) ?></p></div>
  <nav class="segmented" aria-label="<?= kay_th('Période') ?>">
<?php foreach (KAY_TRAFFIC_RANGES as $key => $label): ?>
    <a href="<?= kay_h(kay_url('traffic.php', ['range' => $key])) ?>"<?= $key === $r['key'] ? ' aria-current="page"' : '' ?>><?= kay_th($label) ?></a>
<?php endforeach; ?>
  </nav>
</header>

<section class="kpis" aria-label="<?= kay_th('Chiffres de la période') ?>">
  <?= kay_tile(kay_t('Visiteurs'), kay_compact($t['visitors']), $t['visitors'], $prev['visitors'], kay_t('vs période précédente')) ?>
  <?= kay_tile(kay_t('Pages vues'), kay_compact($t['pageviews']), $t['pageviews'], $prev['pageviews'],
        kay_t('{n} par visite', ['{n}' => kay_num($perVisit, 1)])) ?>
  <?= kay_tile(kay_t('Rebond'), kay_pct($bounce), null, null, kay_t('une page, sans voir le formulaire')) ?>
  <?= kay_tile(kay_t('Acomptes payés'), kay_int($paid), $paid, kay_deposits_paid($db, $r['prev_from'], $r['prev_to']),
        $t['visitors'] > 0 ? kay_t('{pct} des visiteurs', ['{pct}' => kay_pct($paid / $t['visitors'], 1)]) : '') ?>
</section>

<section class="card">
  <header class="card__head">
    <h2><?= kay_h(match ($r['bucket']) {
        'hour'  => kay_t('Visiteurs par heure'),
        'month' => kay_t('Visiteurs par mois'),
        default => kay_t('Visiteurs par jour'),
    }) ?></h2>
    <span class="muted small"><?= kay_th('heure de Tulum') ?></span>
  </header>
  <?= kay_chart_columns($points, ['unit' => kay_t('visiteurs'), 'title' => kay_t('Visiteurs sur la période'),
                                  'labels' => $r['bucket'] === 'month' ? 12 : 8]) ?>
</section>

<div class="cols cols--even">
  <section class="card">
    <header class="card__head"><h2><?= kay_th('Du visiteur à l’acompte') ?></h2></header>
<?php
$steps = [
    [kay_t('Visiteurs'), $t['visitors'], kay_t('ont ouvert une page du site')],
    [kay_t('Ont vu le formulaire'), $t['booking_views'], kay_t('ont fait défiler jusqu’à la réservation')],
    [kay_t('Ont ouvert le paiement'), $t['checkouts'], kay_t('sont partis vers Mercado Pago')],
    [kay_t('Ont payé l’acompte'), $paid, kay_t('réservations web payées sur la période')],
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
        <p class="small muted"><?= kay_h($hint) ?><?= $from ? ' · ' . kay_th('{pct} de l’étape précédente', ['{pct}' => kay_pct($n / $from)]) : '' ?></p>
      </li>
<?php endforeach; ?>
    </ol>
  </section>
  <?= $breakdown(kay_t('Sources'), 'source', static fn(string $v): string => $v === ''
        ? kay_th('Accès direct') . ' <span class="muted">' . kay_th('(lien tapé, favori, WhatsApp…)') . '</span>'
        : kay_h($v)) ?>
</div>

<div class="cols cols--even">
  <?= $breakdown(kay_t('Pays'), 'country', static fn(string $v): string => kay_h(kay_country($v))) ?>
  <?= $breakdown(kay_t('Pages'), 'path', static fn(string $v): string => '<code>' . kay_h($v) . '</code>') ?>
</div>

<div class="cols cols--even">
  <?= $breakdown(kay_t('Langue du site'), 'locale', static fn(string $v): string => isset(KAY_SITE_LANGS[$v]) ? kay_th(KAY_SITE_LANGS[$v]) : kay_th('Autre')) ?>
  <?= $breakdown(kay_t('Langue du navigateur'), 'lang', static fn(string $v): string => isset(KAY_LANG_NAMES[$v])
        ? kay_th(KAY_LANG_NAMES[$v]) : ($v !== '' ? kay_h(strtoupper($v)) : kay_th('Inconnue'))) ?>
</div>

<div class="cols cols--even">
  <?= $breakdown(kay_t('Appareils'), 'device', static fn(string $v): string => isset(KAY_DEVICE_NAMES[$v]) ? kay_th(KAY_DEVICE_NAMES[$v]) : kay_h($v)) ?>
  <?= $breakdown(kay_t('Navigateurs'), 'browser', $other) ?>
</div>

<div class="cols cols--even">
  <?= $breakdown(kay_t('Sorties choisies au paiement'), 'product', static fn(string $v): string => kay_h(kay_product_name($v)), 8, kay_t('paiements ouverts')) ?>
  <?= $breakdown(kay_t('Campagnes (utm_campaign)'), 'utm_campaign', static fn(string $v): string => kay_h($v)) ?>
</div>

<section class="card method">
  <h2><?= kay_th('Comment c’est compté') ?></h2>
  <ul class="small">
    <li><strong><?= kay_th('Aucun cookie, aucune adresse IP enregistrée.') ?></strong> <?= kay_th('Un visiteur est une empreinte anonyme recalculée chaque jour avec une clé qui est ensuite détruite : impossible de le suivre d’un jour à l’autre, ni de remonter à une personne. Pas de bandeau de consentement nécessaire.') ?></li>
    <li><strong><?= kay_th('Visiteurs = visiteurs uniques par jour, additionnés.') ?></strong> <?= kay_th('Quelqu’un qui revient trois jours compte trois fois.') ?></li>
    <li><strong><?= kay_th('Le pays vient du fuseau horaire de l’appareil,') ?></strong> <?= kay_th('pas de l’adresse IP : un Canadien déjà à Tulum compte pour le Mexique.') ?></li>
    <li><?= kay_th('Les robots d’indexation et vos propres visites (tout navigateur connecté à cet admin) ne sont pas comptés. Les bloqueurs de publicité peuvent masquer une petite part des visites.') ?></li>
    <li><?= kay_th('Pour suivre une campagne, ajoutez') ?> <code>?utm_source=instagram&amp;utm_campaign=<?= kay_th('promo-mai') ?></code> <?= kay_th('au lien partagé.') ?></li>
  </ul>
</section>
<?php kay_page_end();
