<?php
declare(strict_types=1);

/**
 * Why a diver did not get their email.
 *
 * A confirmation only reaches someone if two separate things happened:
 * Mercado Pago told this server the card went through, and the mail provider
 * accepted the message. Both used to leave their trace in the hosting's
 * error log, which nobody running a dive shop is going to read — so a diver
 * saying "nothing arrived" had no answer. This page is that answer: the two
 * logs side by side, the configuration they depend on, and a button that
 * tries a real send right now.
 */

require __DIR__ . '/_boot.php';
$admin = kay_admin($db);

if (kay_posted($admin) && ($_POST['do'] ?? '') === 'test') {
    // Deliberately not a rendered booking confirmation: this proves one
    // thing — that the provider takes a message from this server with the
    // key in kay-config.php — and anything else in it is noise.
    $site = rtrim((string) (kay_config()['site_url'] ?? ''), '/');
    $line = kay_t('Si tu lis ceci, les e-mails partent bien de {site}.', ['{site}' => $site]);
    $sent = kay_send_email([
        'to'      => (string) $admin['email'],
        'toName'  => (string) $admin['name'],
        'subject' => kay_t('Essai d’envoi — Kay Diving'),
        'html'    => '<p style="font:16px/1.5 system-ui,sans-serif">' . kay_h($line) . '</p>',
        'text'    => $line,
    ], ['kind' => 'test']);
    kay_redirect('system.php?m=' . ($sent ? 'testsent' : 'notsent'));
}

$mail    = kay_mail_health($db);
$hook    = kay_webhook_health($db);
$mailLog = kay_mail_log($db, 25);
$hookLog = kay_webhook_log($db, 25);
$stuck   = kay_abandoned_checkouts($db, 30, 5);
// The two logs were created by a migration, and everything below counts
// "over 30 days". Printing that beside a table born this morning is how a
// page lies without saying anything false.
$since   = kay_migration_applied($db, 9);
$young   = $since !== null && (kay_db_time($since)?->getTimestamp() ?? 0) > time() - 30 * 86400;

kay_page_start(kay_t('Système'), '', $admin);
?>
<header class="head">
  <div>
    <h1><?= kay_th('Système') ?></h1>
    <p class="muted"><?= kay_th('Ce que le site a envoyé, et ce que Mercado Pago a appelé.') ?></p>
  </div>
</header>

<?php if ($young): ?>
<p class="note"><?= kay_th('Les deux journaux ont commencé {when}. Rien n’a été noté avant.', ['{when}' => kay_when($since)]) ?></p>
<?php endif; ?>

<section class="card">
  <header class="card__head"><h2><?= kay_th('E-mails') ?></h2></header>

<?php if (!$mail['ready']): ?>
  <p class="alert"><?= $mail['provider'] === 'off'
      ? kay_th('Aucun e-mail ne peut partir : mail_provider est sur « off » dans kay-config.php, à côté de www/ sur l’hébergement.')
      : kay_th('Aucun e-mail ne peut partir : il manque mail_api_key dans kay-config.php, à côté de www/ sur l’hébergement.') ?></p>
<?php elseif ($mail['key_odd']): ?>
  <p class="alert"><?= kay_th('Cette clé ne ressemble pas à une clé Resend : elle devrait commencer par « re_ ». C’est en général la version masquée du tableau de bord, copiée après coup, qui ne fonctionne pas.') ?></p>
<?php elseif ($mail['key_gap']): ?>
  <p class="alert"><?= kay_th('La clé contient une espace ou un retour à la ligne au milieu : elle a été coupée au collage.') ?></p>
<?php elseif ($mail['failed_30d'] > 0 && $mail['sent_30d'] === 0): ?>
  <p class="alert"><?= kay_th('Le fournisseur a refusé les {n} derniers envois. La raison est dans la colonne Détail.', ['{n}' => kay_int($mail['failed_30d'])]) ?></p>
<?php endif; ?>

  <dl class="facts">
    <div><dt><?= kay_th('Fournisseur') ?></dt><dd><?= kay_h($mail['provider']) ?></dd></div>
    <div><dt><?= kay_th('Clé API') ?></dt>
      <dd><?= $mail['has_key'] ? '<span class="ok">' . kay_th('présente') . '</span>'
                               : '<span class="late">' . kay_th('absente') . '</span>' ?>
<?php if ($mail['has_key']): ?>
        <span class="muted block small"><?= kay_th('{n} caractères · empreinte {mark}',
              ['{n}' => kay_int($mail['key_len']), '{mark}' => $mail['key_mark']]) ?></span>
<?php endif; ?></dd></div>
    <div><dt><?= kay_th('Expéditeur') ?></dt><dd><?= kay_h($mail['from']) ?></dd></div>
    <div><dt><?= kay_th('Avis de commande à') ?></dt><dd><?= kay_h($mail['to_shop']) ?></dd></div>
    <div><dt><?= kay_th('Envoyés sur 30 jours') ?></dt><dd><?= kay_int($mail['sent_30d']) ?></dd></div>
    <div><dt><?= kay_th('Échecs sur 30 jours') ?></dt>
      <dd><?= $mail['failed_30d'] > 0 ? '<span class="late">' . kay_int($mail['failed_30d']) . '</span>' : '0' ?></dd></div>
  </dl>

  <div class="actions">
    <?php /* The one thing this page exists to let someone do, so it is the
             loud button and not an outline anyone can read as a label. */ ?>
    <form method="post"><?= kay_csrf_field($admin) ?><input type="hidden" name="do" value="test">
      <button class="btn btn--primary"><?= kay_th('M’envoyer un e-mail de test') ?></button></form>
  </div>
  <p class="muted small"><?= kay_th('L’empreinte change dès que la clé change : si elle est la même qu’avant une modification, le fichier n’a pas été enregistré.') ?></p>
  <p class="muted small"><?= kay_th('Le test part vers {email}. S’il arrive mais que les plongeurs ne reçoivent rien, le domaine n’est pas vérifié chez le fournisseur : son tableau de bord le dira.', ['{email}' => $admin['email']]) ?></p>
</section>

<section class="card">
  <header class="card__head"><h2><?= kay_th('Derniers envois') ?></h2></header>
<?php if ($mailLog === []): ?>
  <p class="empty"><?= kay_th('Rien encore. Chaque e-mail que le site tente d’envoyer s’inscrit ici.') ?></p>
<?php else: ?>
  <div class="table-wrap"><table class="table table--stack table--compact">
    <thead><tr><th scope="col"><?= kay_th('Quand') ?></th><th scope="col"><?= kay_th('Type') ?></th>
      <th scope="col"><?= kay_th('Destinataire') ?></th><th scope="col"><?= kay_th('État') ?></th>
      <th scope="col"><?= kay_th('Détail') ?></th></tr></thead>
    <tbody>
<?php foreach ($mailLog as $row): ?>
      <tr>
        <td data-label="<?= kay_th('Quand') ?>"><?= kay_h(kay_when($row['sent_at'])) ?></td>
        <td data-label="<?= kay_th('Type') ?>"><?= kay_h(kay_mail_kind((string) $row['kind'])) ?>
<?php if (($row['booking_id'] ?? null) !== null): ?>
          <a class="block small" href="<?= kay_h(kay_url('booking.php', ['id' => $row['booking_id']])) ?>"><?= kay_th('la réservation') ?></a>
<?php endif; ?></td>
        <td data-label="<?= kay_th('Destinataire') ?>" class="small"><?= kay_h((string) $row['recipient']) ?></td>
        <td data-label="<?= kay_th('État') ?>"><?= (int) $row['ok'] === 1
              ? '<span class="ok">' . kay_th('parti') . '</span>'
              : '<span class="late">' . kay_th('refusé') . '</span>' ?></td>
        <td data-label="<?= kay_th('Détail') ?>" class="small"><?= (string) $row['error'] !== ''
              ? kay_h((string) $row['error'])
              : '<span class="muted">' . kay_h((string) $row['provider_ref']) . '</span>' ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>
</section>

<section class="card">
  <header class="card__head"><h2><?= kay_th('Mercado Pago') ?></h2></header>

<?php if ($hook['from_mp_30d'] === 0): ?>
  <p class="alert"><?= kay_th('Mercado Pago n’a appelé aucune fois en 30 jours. Sans cet appel, un paiement ne devient jamais une réservation réglée et aucune confirmation n’est écrite. À vérifier dans son panneau, Webhooks, mode productif : l’URL doit être {url} et l’événement « Paiements » coché.', ['{url}' => rtrim((string) kay_config()['site_url'], '/') . '/api/webhook.php']) ?></p>
<?php elseif ($hook['wrong_secret_30d'] > 0 && $hook['settled_30d'] === 0): ?>
  <p class="alert"><?= kay_th('Mercado Pago appelle, et ses {n} appels ont été refusés : la clé secrète du webhook dans kay-config.php n’est pas celle de son panneau.', ['{n}' => kay_int($hook['wrong_secret_30d'])]) ?></p>
<?php endif; ?>

  <dl class="facts">
    <div><dt><?= kay_th('Appels de Mercado Pago') ?></dt><dd><?= kay_int($hook['from_mp_30d']) ?></dd></div>
    <div><dt><?= kay_th('Refusés') ?></dt>
      <dd><?= $hook['wrong_secret_30d'] > 0 ? '<span class="late">' . kay_int($hook['wrong_secret_30d']) . '</span>' : '0' ?></dd></div>
    <div><dt><?= kay_th('Réservations réglées') ?></dt><dd><?= kay_int($hook['settled_30d']) ?></dd></div>
    <div><dt><?= kay_th('Appels non signés') ?></dt><dd class="muted"><?= kay_int($hook['unsigned_30d']) ?></dd></div>
    <div><dt><?= kay_th('Dernier appel') ?></dt>
      <dd><?= $hook['last_call'] !== null ? kay_h(kay_when($hook['last_call']['called_at'])) : '—' ?></dd></div>
  </dl>
<?php if ($hook['unsigned_30d'] > 0): ?>
  <p class="muted small"><?= kay_th('L’adresse du webhook est publique : n’importe qui peut la solliciter. Mercado Pago signe toujours ses appels, donc un appel non signé ne vient pas de lui et n’est pas pris en compte.') ?></p>
<?php endif; ?>

<?php if ($hookLog !== []): ?>
  <div class="table-wrap"><table class="table table--stack table--compact">
    <thead><tr><th scope="col"><?= kay_th('Quand') ?></th><th scope="col"><?= kay_th('Résultat') ?></th>
      <th scope="col"><?= kay_th('Paiement') ?></th><th scope="col"><?= kay_th('Détail') ?></th></tr></thead>
    <tbody>
<?php foreach ($hookLog as $row): ?>
      <tr>
        <td data-label="<?= kay_th('Quand') ?>"><?= kay_h(kay_when($row['called_at'])) ?></td>
<?php
    $refused = in_array($row['outcome'], ['bad-signature', 'no-id'], true);
    // Refused and unsigned is a stranger at the door, not Mercado Pago
    // turned away: shown, but in grey, and named for what it is.
    $label = $refused && (int) $row['signed'] === 0
        ? kay_t('Appel non signé, ignoré')
        : kay_webhook_outcome((string) $row['outcome']);
?>
        <td data-label="<?= kay_th('Résultat') ?>"><?= in_array($row['outcome'], ['settled', 'already'], true)
              ? '<span class="ok">' . kay_h($label) . '</span>'
              : ($refused && (int) $row['signed'] === 1
                  ? '<span class="late">' . kay_h($label) . '</span>'
                  : '<span class="muted">' . kay_h($label) . '</span>') ?>
<?php if (($row['booking_id'] ?? null) !== null): ?>
          <a class="block small" href="<?= kay_h(kay_url('booking.php', ['id' => $row['booking_id']])) ?>"><?= kay_th('la réservation') ?></a>
<?php endif; ?></td>
        <td data-label="<?= kay_th('Paiement') ?>" class="small"><?= (string) $row['payment_id'] !== ''
              ? kay_h((string) $row['payment_id']) : '<span class="muted">—</span>' ?></td>
        <td data-label="<?= kay_th('Détail') ?>" class="small"><?= kay_h((string) $row['detail']) ?></td>
      </tr>
<?php endforeach; ?>
    </tbody>
  </table></div>
<?php endif; ?>
</section>

<?php if ($stuck !== []): ?>
<section class="card">
  <header class="card__head"><h2><?= kay_th('Parties au paiement, jamais revenues') ?></h2></header>
  <p class="muted small"><?= kay_th('Le formulaire a été rempli et le paiement n’est jamais arrivé. C’est normal quand quelqu’un se ravise ; ça ne l’est plus si Mercado Pago dit l’avoir encaissé.') ?></p>
  <ul class="rows rows--tight">
<?php foreach ($stuck as $row): ?>
    <li class="row">
      <div class="row__main"><a href="<?= kay_h(kay_url('booking.php', ['id' => $row['id']])) ?>"><strong><?= kay_h($row['name']) ?></strong></a>
        <span class="muted block small"><?= kay_h((string) $row['email']) ?> · <?= kay_h(kay_date_short($row['dive_date'])) ?></span></div>
      <div class="row__end muted small"><?= kay_h(kay_when($row['created_at'])) ?></div>
    </li>
<?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
<?php kay_page_end(); ?>
