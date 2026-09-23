<?php
declare(strict_types=1);

require __DIR__ . '/_boot.php';
$admin = kay_admin($db);

$id = (string) ($_GET['id'] ?? '');
$b  = preg_match('/^[a-f0-9-]{36}$/', $id) ? kay_booking_get($db, $id) : null;
if ($b === null) {
    http_response_code(404);
    kay_page_start('Introuvable', 'bookings.php', $admin);
    echo '<section class="card"><h1>Réservation introuvable</h1><p><a href="bookings.php">← Réservations</a></p></section>';
    kay_page_end();
    exit;
}

$self  = kay_url('booking.php', ['id' => $b['id']]);
$error = null;

if (kay_posted($admin)) {
    $do = (string) ($_POST['do'] ?? '');
    switch ($do) {
        case 'status':
            $error = kay_booking_act($db, $b, (string) ($_POST['action'] ?? ''), $admin['id']);
            $ok = 'moved';
            break;
        case 'pay':
            $error = kay_payment_add($db, $b, (string) ($_POST['kind'] ?? ''), (int) ($_POST['amount'] ?? 0),
                (string) ($_POST['method'] ?? ''), $admin['id']);
            $ok = 'paid';
            break;
        case 'edit':
            $error = kay_booking_edit($db, $b, $_POST, $admin['id']);
            $ok = 'saved';
            break;
        case 'resend':
            if ($b['email'] === '' || !in_array($b['status'], KAY_ACTIVE, true)) {
                $error = 'Seule une réservation confirmée, avec une adresse e-mail, reçoit une confirmation.';
                break;
            }
            $sent = kay_send_email(kay_booking_emails(kay_booking_email_row($b))['diver']);
            kay_note_add($db, $b['customer_id'] !== null ? (int) $b['customer_id'] : null, (string) $b['id'],
                $admin['id'], 'log', $sent ? 'Confirmation renvoyée à ' . $b['email'] . '.' : 'Échec de l’envoi de la confirmation.');
            kay_redirect($self . '&m=' . ($sent ? 'sent' : 'notsent'));
        default:
            $error = 'Action inconnue.';
    }
    if ($error === null) {
        kay_redirect($self . '&m=' . $ok);
    }
    $b = kay_booking_get($db, $id) ?? $b;
}

$m        = kay_booking_money($b);
$payments = kay_payments_for($db, $b['id']);
$notes    = kay_notes_for($db, null, $b['id']);
$phone    = (string) ($b['customer_phone'] ?? '');
$wa       = kay_whatsapp_url($phone);
$online   = $b['paid_at'] !== null && $b['status'] !== 'refunded' ? (int) round($b['deposit_mxn_cents'] / 100) : 0;

kay_page_start($b['name'], 'bookings.php', $admin);
?>
<p class="crumbs"><a href="bookings.php">← Réservations</a></p>
<header class="head">
  <div>
    <h1><?= kay_h($b['name']) ?> <?= kay_status_badge($b['status']) ?></h1>
    <p class="muted"><?= kay_h(kay_product_name($b['product'])) ?> · <?= kay_h(kay_dives_text((int) $b['dives'])) ?> ·
      <?= kay_h(ucfirst(kay_date_long($b['dive_date']))) ?> · <?= kay_h(kay_slot_text($b['start_slot'], (string) $b['start_note'])) ?></p>
  </div>
  <div class="head__actions">
<?php foreach (KAY_ACTIONS[$b['status']] ?? [] as $action): ?>
    <form method="post" class="inline">
      <?= kay_csrf_field($admin) ?>
      <input type="hidden" name="do" value="status"><input type="hidden" name="action" value="<?= kay_h($action) ?>">
      <button class="btn <?= in_array($action, ['cancel', 'refund', 'no_show'], true) ? 'btn--danger' : ($action === 'complete' || $action === 'confirm' ? 'btn--primary' : 'btn--ghost') ?>"
        <?= in_array($action, ['cancel', 'refund', 'no_show'], true) ? 'data-confirm="' . kay_h(KAY_ACTION_LABELS[$action]) . ' ?"' : '' ?>><?= kay_h(KAY_ACTION_LABELS[$action]) ?></button>
    </form>
<?php endforeach; ?>
  </div>
</header>
<?= kay_error_box($error) ?>
<?php if (in_array('refund', KAY_ACTIONS[$b['status']] ?? [], true)): ?>
<p class="note small">« Marquer remboursée » enregistre le remboursement ici ; l’argent se rend depuis le tableau de bord Mercado Pago.</p>
<?php endif; ?>

<div class="cols">
  <div class="cols__main">
    <section class="card">
      <header class="card__head"><h2>Détails</h2></header>
      <dl class="facts">
        <div><dt>Plongée</dt><dd><?= kay_h(ucfirst(kay_date_long($b['dive_date']))) ?></dd></div>
        <div><dt>Départ</dt><dd><?= kay_h(kay_slot_text($b['start_slot'], (string) $b['start_note'])) ?></dd></div>
        <div><dt>Sortie</dt><dd><?= kay_h(kay_product_name($b['product'])) ?> · <?= kay_h(kay_dives_text((int) $b['dives'])) ?></dd></div>
        <div><dt>Plongeurs</dt><dd><?= (int) $b['divers'] ?></dd></div>
        <div><dt>Niveau</dt><dd><?= kay_h($b['certification'] !== '' ? $b['certification'] : '—') ?></dd></div>
        <div><dt>Transport</dt><dd><?= kay_h(kay_pickup_name($b['pickup'])) ?></dd></div>
        <div><dt>Langue</dt><dd><?= kay_h(strtoupper($b['locale'])) ?></dd></div>
        <div><dt>Origine</dt><dd><?= $b['source'] === 'manual' ? 'Saisie dans l’admin' : 'Site web' ?></dd></div>
        <div><dt>Reçue</dt><dd><?= kay_h(kay_when($b['created_at'])) ?></dd></div>
        <div><dt>Référence</dt><dd><code><?= kay_h($b['id']) ?></code></dd></div>
<?php if ($b['payment_id'] !== null): ?>
        <div><dt>Paiement MP</dt><dd><code><?= kay_h($b['payment_id']) ?></code></dd></div>
<?php endif; ?>
      </dl>
      <details class="edit">
        <summary class="btn btn--ghost">Modifier la réservation</summary>
        <form method="post" class="stack">
          <?= kay_csrf_field($admin) ?>
          <input type="hidden" name="do" value="edit">
          <?= kay_booking_fields([
              'product' => $b['product'], 'option' => (int) $b['dives'], 'date' => $b['dive_date'],
              'slot' => $b['start_slot'], 'slotNote' => $b['start_note'], 'divers' => (int) $b['divers'],
              'pickup' => $b['pickup'], 'cert' => $b['certification'], 'name' => $b['name'], 'email' => $b['email'],
          ], false) ?>
          <p class="small muted">Changer la sortie, le nombre de plongeurs ou le transport recalcule le prix depuis le catalogue. Les paiements déjà reçus ne changent pas.</p>
          <button class="btn btn--primary">Enregistrer</button>
        </form>
      </details>
    </section>

    <section class="card">
      <header class="card__head"><h2>Historique</h2></header>
      <form method="post" action="note.php" class="note-form">
        <?= kay_csrf_field($admin) ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="booking_id" value="<?= kay_h($b['id']) ?>">
        <input type="hidden" name="customer_id" value="<?= (int) ($b['customer_id'] ?? 0) ?>">
        <input type="hidden" name="back" value="<?= kay_h($self) ?>">
        <label class="sr-only" for="note-body">Note</label>
        <textarea id="note-body" name="body" rows="2" placeholder="Une note sur cette réservation…" required></textarea>
        <div class="note-form__row">
          <select name="kind" aria-label="Type">
<?php foreach (KAY_NOTE_KINDS as $k => $label): if ($k === 'log') continue; ?>
            <option value="<?= $k ?>"><?= kay_h($label) ?></option>
<?php endforeach; ?>
          </select>
          <input type="date" name="due_on" aria-label="Échéance (pour « À faire »)">
          <button class="btn btn--ghost">Ajouter</button>
        </div>
      </form>
      <?= kay_timeline($notes, $admin, $self) ?>
    </section>
  </div>

  <aside class="cols__side">
    <section class="card">
      <header class="card__head"><h2>Paiement</h2></header>
      <dl class="money">
        <div><dt>Total</dt><dd><?= kay_mxn($m['total']) ?><small><?= kay_usd((int) round($b['total_usd_cents'] / 100)) ?></small></dd></div>
        <div><dt>Acompte en ligne</dt><dd><?= $b['paid_at'] !== null ? kay_mxn($online) : '<span class="muted">' . ($b['source'] === 'web' ? 'non payé' : '—') . '</span>' ?></dd></div>
<?php foreach ($payments as $p): ?>
        <div><dt><?= kay_h(KAY_PAYMENT_KINDS[$p['kind']] ?? $p['kind']) ?> · <?= kay_h(KAY_PAYMENT_METHODS[$p['method']] ?? $p['method']) ?>
          <small><?= kay_h(kay_when($p['created_at'])) ?><?= $p['author'] ? ' · ' . kay_h($p['author']) : '' ?></small></dt>
          <dd><?= kay_mxn((int) $p['amount_mxn_cents'] / 100) ?></dd></div>
<?php endforeach; ?>
        <div class="money__total"><dt><?= $m['balance'] < 0 ? 'Trop-perçu' : 'Reste à payer' ?></dt>
          <dd class="<?= $m['due'] > 0 ? 'due' : '' ?>"><?= kay_mxn(abs($m['balance'])) ?></dd></div>
      </dl>
<?php if (in_array($b['status'], ['paid', 'confirmed', 'completed', 'no_show', 'pending'], true)): ?>
      <form method="post" class="stack">
        <?= kay_csrf_field($admin) ?>
        <input type="hidden" name="do" value="pay">
        <div class="grid grid--2">
          <label class="field"><span>Type</span><select name="kind">
<?php foreach (KAY_PAYMENT_KINDS as $k => $label): ?>
            <option value="<?= $k ?>"<?= ($m['due'] > 0 && $k === 'balance') ? ' selected' : '' ?>><?= kay_h($label) ?></option>
<?php endforeach; ?>
          </select></label>
          <label class="field"><span>Montant (MXN)</span>
            <input type="number" name="amount" min="1" step="1" value="<?= $m['due'] > 0 ? $m['due'] : '' ?>" required></label>
        </div>
        <label class="field"><span>Moyen</span><select name="method">
<?php foreach (KAY_PAYMENT_METHODS as $k => $label): ?>
          <option value="<?= $k ?>"><?= kay_h($label) ?></option>
<?php endforeach; ?>
        </select></label>
        <button class="btn btn--primary btn--block">Enregistrer le paiement</button>
      </form>
<?php endif; ?>
    </section>

    <section class="card">
      <header class="card__head"><h2>Contact</h2></header>
      <ul class="contact">
<?php if ($b['email'] !== ''): ?>
        <li><a href="mailto:<?= kay_h($b['email']) ?>"><?= kay_h($b['email']) ?></a></li>
<?php endif; ?>
<?php if ($phone !== ''): ?>
        <li><a href="tel:<?= kay_h(preg_replace('/[^\d+]/', '', $phone)) ?>"><?= kay_h($phone) ?></a>
          <?= $wa !== null ? ' · <a href="' . kay_h($wa) . '" target="_blank" rel="noopener">WhatsApp</a>' : '' ?></li>
<?php endif; ?>
<?php if ($b['customer_id'] !== null): ?>
        <li><a href="<?= kay_h(kay_url('customer.php', ['id' => $b['customer_id']])) ?>">Fiche client →</a></li>
<?php endif; ?>
      </ul>
<?php if ($b['email'] !== '' && in_array($b['status'], KAY_ACTIVE, true)): ?>
      <form method="post">
        <?= kay_csrf_field($admin) ?>
        <input type="hidden" name="do" value="resend">
        <button class="btn btn--ghost btn--block" data-confirm="Renvoyer la confirmation à <?= kay_h($b['email']) ?> ?">Renvoyer la confirmation</button>
      </form>
<?php endif; ?>
    </section>
  </aside>
</div>
<?php kay_page_end();
