<?php
declare(strict_types=1);

require __DIR__ . '/_boot.php';
$admin = kay_admin($db);

$isNew = isset($_GET['new']);
$id    = (int) ($_GET['id'] ?? 0);
$error = null;

/* ------------------------------------------------------------ a new one -- */

if ($isNew) {
    $values = ['name' => '', 'email' => '', 'phone' => '', 'country' => '', 'locale' => 'en',
               'certification' => '', 'tags' => '', 'notes' => ''];
    if (kay_posted($admin)) {
        $values = array_intersect_key($_POST, $values) + $values;
        $email  = mb_strtolower(trim((string) $values['email']));
        $taken  = null;
        if ($email !== '') {
            $s = $db->prepare('SELECT id FROM customers WHERE email = ?');
            $s->execute([$email]);
            $taken = $s->fetchColumn() ?: null;
        }
        if ($taken !== null) {
            kay_redirect(kay_url('customer.php', ['id' => $taken]));
        }
        if (mb_strlen(trim((string) $values['name'])) < 2) {
            $error = kay_t('Indiquez un nom.');
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = kay_t('L’adresse e-mail n’est pas valide.');
        } else {
            $newId = kay_customer_for($db, $email, ['name' => (string) $values['name'], 'phone' => (string) $values['phone'],
                                                    'locale' => (string) $values['locale']]);
            kay_customer_update($db, $newId, $values);
            kay_redirect(kay_url('customer.php', ['id' => $newId, 'm' => 'saved']));
        }
    }
    kay_page_start(kay_t('Nouveau client'), 'customers.php', $admin);
    echo '<p class="crumbs"><a href="customers.php">' . kay_th('← Clients') . '</a></p>'
        . '<header class="head"><div><h1>' . kay_th('Nouveau client') . '</h1></div></header>'
        . '<section class="card">' . kay_error_box($error)
        . '<form method="post" class="stack">' . kay_csrf_field($admin) . kay_customer_fields($values)
        . '<div class="actions"><button class="btn btn--primary">' . kay_th('Créer') . '</button>'
        . '<a class="btn btn--ghost" href="customers.php">' . kay_th('Annuler') . '</a></div></form></section>';
    kay_page_end();
    exit;
}

/* ------------------------------------------------------------ existing -- */

$c = $id > 0 ? kay_customer_get($db, $id) : null;
if ($c === null) {
    http_response_code(404);
    kay_page_start(kay_t('Introuvable'), 'customers.php', $admin);
    echo '<section class="card"><h1>' . kay_th('Client introuvable') . '</h1><p><a href="customers.php">'
        . kay_th('← Clients') . '</a></p></section>';
    kay_page_end();
    exit;
}
$self = kay_url('customer.php', ['id' => $id]);

if (kay_posted($admin)) {
    $do = (string) ($_POST['do'] ?? '');
    if ($do === 'save') {
        $error = kay_customer_update($db, $id, $_POST);
        if ($error === null) {
            kay_redirect($self . '&m=saved');
        }
    } elseif ($do === 'forget') {
        kay_customer_forget($db, $id);
        kay_note_add($db, $id, null, $admin['id'], 'log', kay_t('Données personnelles effacées à la demande du client.'));
        kay_redirect($self . '&m=forgotten');
    }
}

$bookings = kay_customer_bookings($db, $id);
$notes    = kay_notes_for($db, $id);
$wa       = kay_whatsapp_url((string) $c['phone']);
$values   = $error !== null ? array_intersect_key($_POST, $c) + $c : $c;
$others   = (int) $c['bookings_all'] - (int) $c['bookings'];

kay_page_start($c['name'] !== '' ? $c['name'] : kay_t('Client'), 'customers.php', $admin);
?>
<p class="crumbs"><a href="customers.php"><?= kay_th('← Clients') ?></a></p>
<header class="head">
  <div>
    <h1><?= $c['name'] !== '' ? kay_h($c['name']) : kay_th('(sans nom)') ?>
      <?php if ((int) $c['bookings'] === 0): ?><span class="tag"><?= kay_th('prospect') ?></span><?php endif; ?>
      <?php foreach (array_filter(explode(',', $c['tags'])) as $tag): ?><span class="tag tag--label"><?= kay_h($tag) ?></span><?php endforeach; ?></h1>
    <p class="muted"><?= $c['country'] !== '' ? kay_h(kay_country($c['country'])) . ' · ' : '' ?><?= kay_th('client depuis {when}', ['{when}' => kay_when($c['created_at'])]) ?></p>
  </div>
  <div class="head__actions">
<?php if ($wa !== null): ?><a class="btn btn--ghost" href="<?= kay_h($wa) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
<?php if ((string) $c['email'] !== ''): ?><a class="btn btn--ghost" href="mailto:<?= kay_h($c['email']) ?>"><?= kay_th('E-mail') ?></a><?php endif; ?>
    <a class="btn btn--primary" href="<?= kay_h(kay_url('booking-new.php', ['customer' => $id])) ?>"><?= kay_th('+ Réservation') ?></a>
  </div>
</header>

<section class="kpis kpis--small">
  <?= kay_tile(kay_t('Réservations'), kay_int((int) $c['bookings']), null, null,
        $others > 0 ? kay_tn($others, '{n} non aboutie ou annulée', '{n} non abouties ou annulées') : '') ?>
  <?= kay_tile(kay_t('Valeur'), kay_mxn((int) $c['value_mxn_cents'] / 100)) ?>
  <?= kay_tile(kay_t('Dernière plongée'), $c['last_dive'] ? kay_h(kay_date_short($c['last_dive'])) : '—') ?>
  <?= kay_tile(kay_t('Prochaine plongée'), $c['next_dive'] ? kay_h(kay_date_short($c['next_dive'])) : '—') ?>
</section>

<div class="cols">
  <div class="cols__main">
    <section class="card card--flush">
      <header class="card__head"><h2><?= kay_th('Réservations') ?></h2></header>
<?php if ($bookings === []): ?>
      <p class="empty"><?= kay_th('Aucune réservation.') ?></p>
<?php else: ?>
      <div class="table-wrap"><table class="table table--stack">
        <thead><tr><th scope="col"><?= kay_th('Plongée') ?></th><th scope="col"><?= kay_th('Sortie') ?></th><th scope="col" class="num"><?= kay_th('Total') ?></th>
          <th scope="col" class="num"><?= kay_th('Reste') ?></th><th scope="col"><?= kay_th('Statut') ?></th></tr></thead>
        <tbody>
<?php foreach ($bookings as $b): $m = kay_booking_money($b); ?>
          <tr>
            <td data-label="<?= kay_th('Plongée') ?>"><a href="<?= kay_h(kay_url('booking.php', ['id' => $b['id']])) ?>"><?= kay_h(kay_date_short($b['dive_date'])) ?></a></td>
            <td data-label="<?= kay_th('Sortie') ?>"><?= kay_h(kay_product_name($b['product'])) ?> · <?= kay_th('{n} pers.', ['{n}' => (string) (int) $b['divers']]) ?></td>
            <td data-label="<?= kay_th('Total') ?>" class="num"><?= kay_mxn($m['total']) ?></td>
            <td data-label="<?= kay_th('Reste') ?>" class="num"><?= $m['due'] > 0 ? '<span class="due">' . kay_mxn($m['due']) . '</span>' : '—' ?></td>
            <td data-label="<?= kay_th('Statut') ?>"><?= kay_status_badge($b['status']) ?></td>
          </tr>
<?php endforeach; ?>
        </tbody>
      </table></div>
<?php endif; ?>
    </section>

    <section class="card">
      <header class="card__head"><h2><?= kay_th('Suivi') ?></h2></header>
      <form method="post" action="note.php" class="note-form">
        <?= kay_csrf_field($admin) ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="customer_id" value="<?= $id ?>">
        <input type="hidden" name="back" value="<?= kay_h($self) ?>">
        <label class="sr-only" for="note-body"><?= kay_th('Note') ?></label>
        <textarea id="note-body" name="body" rows="2" placeholder="<?= kay_th('Appel, message, préférence, relance à faire…') ?>" required></textarea>
        <div class="note-form__row">
          <select name="kind" aria-label="<?= kay_th('Type') ?>">
<?php foreach (KAY_NOTE_KINDS as $k => $label): if ($k === 'log') continue; ?>
            <option value="<?= $k ?>"><?= kay_th($label) ?></option>
<?php endforeach; ?>
          </select>
          <input type="date" name="due_on" aria-label="<?= kay_th('Échéance (pour « À faire »)') ?>">
          <button class="btn btn--ghost"><?= kay_th('Ajouter') ?></button>
        </div>
        <p class="small muted"><?= kay_th('Choisissez « À faire » avec une date pour une relance : elle apparaîtra sur le tableau de bord.') ?></p>
      </form>
      <?= kay_timeline($notes, $admin, $self) ?>
    </section>
  </div>

  <aside class="cols__side">
    <section class="card">
      <header class="card__head"><h2><?= kay_th('Fiche') ?></h2></header>
      <?= kay_error_box($error) ?>
      <form method="post" class="stack">
        <?= kay_csrf_field($admin) ?>
        <input type="hidden" name="do" value="save">
        <?= kay_customer_fields($values) ?>
        <button class="btn btn--primary btn--block"><?= kay_th('Enregistrer') ?></button>
      </form>
    </section>

    <section class="card">
      <details>
        <summary class="small muted"><?= kay_th('Effacer les données personnelles…') ?></summary>
        <p class="small"><?= kay_th('Pour une demande d’effacement (RGPD) : le nom, l’e-mail, le téléphone et les notes disparaissent de la fiche et de toutes ses réservations. Les montants et les dates restent, pour la comptabilité. Irréversible.') ?></p>
        <form method="post">
          <?= kay_csrf_field($admin) ?>
          <input type="hidden" name="do" value="forget">
          <button class="btn btn--danger btn--block" data-confirm="<?= kay_th('Effacer définitivement les données personnelles de ce client ?') ?>"><?= kay_th('Effacer') ?></button>
        </form>
      </details>
    </section>
  </aside>
</div>
<?php kay_page_end();
