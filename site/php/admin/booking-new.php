<?php
declare(strict_types=1);

/**
 * A booking Kay takes himself — WhatsApp, the phone, someone at the counter.
 * Priced from the same catalogue as the website; confirmed from the start;
 * whatever was paid in cash goes in the ledger. A day closed to the website
 * can still be booked here: closing a day stops strangers, not Kay.
 */

require __DIR__ . '/_boot.php';
$admin = kay_admin($db);

$values = [
    'product' => kay_catalogue()['products'][0]['slug'],
    'option'  => kay_catalogue()['products'][0]['options'][0]['dives'],
    'date'    => (string) ($_GET['date'] ?? ''),
    'slot'    => '0800', 'slotNote' => '', 'divers' => 2, 'pickup' => 'meeting-point',
    'cert'    => '', 'name' => '', 'email' => '', 'phone' => '', 'locale' => 'en',
];

// "New booking" from a customer's page starts with who they are.
$customerId = (int) ($_GET['customer'] ?? $_POST['customer'] ?? 0);
if ($customerId > 0 && ($c = kay_customer_get($db, $customerId)) !== null) {
    $values = ['name' => $c['name'], 'email' => (string) $c['email'], 'phone' => $c['phone'],
               'locale' => $c['locale'], 'cert' => $c['certification']] + $values;
}

$error = null;
$deposit = 0;
$method  = 'cash';
$notify  = false;

if (kay_posted($admin)) {
    $deposit = max(0, (int) ($_POST['deposit'] ?? 0));
    $method  = (string) ($_POST['method'] ?? 'cash');
    $notify  = !empty($_POST['notify']);
    $result  = kay_booking_create($db, $_POST, $admin['id']);

    if ($result['ok']) {
        $row = kay_booking_get($db, $result['id']);
        if ($notify && $row !== null && $row['email'] !== '') {
            $sent = kay_send_email(kay_booking_emails(kay_booking_email_row($row))['diver']);
            kay_note_add($db, $row['customer_id'] !== null ? (int) $row['customer_id'] : null, $row['id'],
                $admin['id'], 'log', $sent
                    ? kay_t('Confirmation envoyée à {email}.', ['{email}' => $row['email']])
                    : kay_t('Échec de l’envoi de la confirmation.'));
        }
        kay_redirect(kay_url('booking.php', ['id' => $result['id'], 'm' => 'created']));
    }

    $error  = $result['error'];
    $clean  = kay_booking_input($_POST)['clean'];
    $values = ['phone' => (string) ($_POST['phone'] ?? '')] + $clean + $values;
}

kay_page_start(kay_t('Nouvelle réservation'), 'bookings.php', $admin);
?>
<p class="crumbs"><a href="bookings.php"><?= kay_th('← Réservations') ?></a></p>
<header class="head"><div><h1><?= kay_th('Nouvelle réservation') ?></h1>
  <p class="muted"><?= kay_th('Pour une réservation prise par téléphone, WhatsApp ou au comptoir. Elle est confirmée dès l’enregistrement.') ?></p></div></header>

<section class="card">
  <?= kay_error_box($error) ?>
  <form method="post" class="stack">
    <?= kay_csrf_field($admin) ?>
    <input type="hidden" name="customer" value="<?= $customerId ?>">
    <?= kay_booking_fields($values, true) ?>

    <fieldset class="fieldset">
      <legend><?= kay_th('Acompte déjà reçu') ?></legend>
      <div class="grid">
        <label class="field"><span><?= kay_th('Montant (MXN)') ?></span>
          <input type="number" name="deposit" min="0" step="1" value="<?= $deposit ?: '' ?>" placeholder="0"></label>
        <label class="field"><span><?= kay_th('Moyen') ?></span><select name="method">
<?php foreach (KAY_PAYMENT_METHODS as $k => $label): ?>
          <option value="<?= $k ?>"<?= $k === $method ? ' selected' : '' ?>><?= kay_th($label) ?></option>
<?php endforeach; ?>
        </select></label>
      </div>
    </fieldset>

    <label class="check-field"><input type="checkbox" name="notify" value="1"<?= $notify ? ' checked' : '' ?>>
      <?= kay_th('Envoyer l’e-mail de confirmation au client (s’il a une adresse)') ?></label>

    <div class="actions">
      <button class="btn btn--primary"><?= kay_th('Enregistrer la réservation') ?></button>
      <a class="btn btn--ghost" href="bookings.php"><?= kay_th('Annuler') ?></a>
    </div>
  </form>
</section>
<?php kay_page_end();
