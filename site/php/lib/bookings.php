<?php
declare(strict_types=1);

/**
 * Bookings as Kay manages them: searched, moved through their states, edited,
 * created by hand for the people who book over WhatsApp, and paid for in
 * cash on the day.
 *
 * Two rules carried over from the payment endpoints:
 *
 *   - Prices still come from products.json through kay_quote(). The admin
 *     never types a total; changing the party size re-quotes it from the same
 *     catalogue the page and the card use.
 *   - What Mercado Pago wrote stays as it wrote it. The online deposit,
 *     payment_id and paid_at are never edited here. Money taken any other way
 *     goes in booking_payments, beside it.
 */

/** A booking that holds places on the boat and counts as revenue. */
const KAY_ACTIVE = ['paid', 'confirmed', 'completed'];

const KAY_STATUSES = [
    'pending'   => 'Paiement en attente',
    'paid'      => 'Acompte payé',
    'confirmed' => 'Confirmée',
    'completed' => 'Effectuée',
    'no_show'   => 'Absent',
    'cancelled' => 'Annulée',
    'refunded'  => 'Remboursée',
];

/**
 * What can be done to a booking in each state, and where it leads. Anything
 * not listed is refused, whatever the form posted.
 */
const KAY_ACTIONS = [
    'pending'   => ['confirm', 'cancel'],
    'paid'      => ['complete', 'no_show', 'cancel', 'refund'],
    'confirmed' => ['complete', 'no_show', 'cancel'],
    'completed' => ['reopen'],
    'no_show'   => ['reopen'],
    'cancelled' => ['reopen'],
    'refunded'  => [],
];

const KAY_ACTION_LABELS = [
    'confirm'  => 'Confirmer',
    'complete' => 'Plongée effectuée',
    'no_show'  => 'Client absent',
    'cancel'   => 'Annuler',
    'refund'   => 'Marquer remboursée',
    'reopen'   => 'Rouvrir',
];

const KAY_PAYMENT_METHODS = [
    'cash'     => 'Espèces',
    'card'     => 'Carte (terminal)',
    'transfer' => 'Virement',
    'paypal'   => 'PayPal',
    'other'    => 'Autre',
];

const KAY_PAYMENT_KINDS = [
    'deposit' => 'Acompte',
    'balance' => 'Solde',
    'refund'  => 'Remboursement',
];

function kay_uuid(): string
{
    return sprintf(
        '%04x%04x-%04x-4%03x-%04x-%04x%04x%04x',
        random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff),
        random_int(0, 0x0fff), random_int(0, 0x3fff) | 0x8000,
        random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
    );
}

/** For LIKE: the user's % and _ are characters, not wildcards. */
function kay_like(string $q): string
{
    return '%' . addcslashes($q, '%_\\') . '%';
}

/** 'paid','confirmed','completed' as a quoted SQL list — constants only, never input. */
function kay_sql_list(array $values): string
{
    return implode(',', array_map(static fn(string $v): string => "'" . $v . "'", $values));
}

/**
 * Everything a booking has received, in peso cents: the Mercado Pago deposit
 * once it cleared (and was not refunded), plus the ledger.
 */
function kay_received_sql(string $b = 'b'): string
{
    return "(CASE WHEN $b.paid_at IS NOT NULL AND $b.status <> 'refunded' THEN $b.deposit_mxn_cents ELSE 0 END
             + COALESCE((SELECT SUM(bp.amount_mxn_cents) FROM booking_payments bp WHERE bp.booking_id = $b.id), 0))";
}

/** The columns every booking listing selects. */
function kay_booking_select(): string
{
    return 'b.*, ' . kay_received_sql() . ' AS received_mxn_cents,
            c.phone AS customer_phone, c.name AS customer_name, c.tags AS customer_tags';
}

function kay_booking_get(PDO $db, string $id): ?array
{
    $s = $db->prepare('SELECT ' . kay_booking_select() . '
                         FROM bookings b LEFT JOIN customers c ON c.id = b.customer_id
                        WHERE b.id = ?');
    $s->execute([$id]);
    $row = $s->fetch();
    return is_array($row) ? $row : null;
}

/**
 * The money on one booking, in whole pesos.
 *
 * @return array{total:int,received:int,balance:int,due:int}
 *         due is what is still to collect on a booking that is going ahead;
 *         a negative balance is an overpayment to give back.
 */
function kay_booking_money(array $row): array
{
    $total    = (int) round(((int) $row['total_mxn_cents']) / 100);
    $received = (int) round(((int) ($row['received_mxn_cents'] ?? 0)) / 100);
    $balance  = $total - $received;
    $going    = in_array($row['status'], ['paid', 'confirmed'], true);
    return [
        'total'    => $total,
        'received' => $received,
        'balance'  => $balance,
        'due'      => $going ? max(0, $balance) : 0,
    ];
}

/* ------------------------------------------------------------- searching -- */

const KAY_BOOKING_VIEWS = [
    'upcoming'  => 'À venir',
    'past'      => 'Passées',
    'pending'   => 'Paiement en attente',
    'cancelled' => 'Annulées',
    'all'       => 'Toutes',
];

/**
 * @param array{view?:string,q?:string,product?:string,from?:string,to?:string} $f
 * @return array{rows:array,total:int}
 */
function kay_bookings_find(PDO $db, array $f, int $limit = 50, int $offset = 0): array
{
    $where  = [];
    $params = [];
    $today  = kay_today();
    $active = kay_sql_list(KAY_ACTIVE);

    $view = (string) ($f['view'] ?? 'upcoming');
    switch ($view) {
        case 'upcoming':
            $where[]  = "b.status IN ($active) AND b.dive_date >= ?";
            $params[] = $today;
            $order    = "b.dive_date ASC, FIELD(b.start_slot,'0800','0830','0900','other'), b.created_at";
            break;
        case 'past':
            $where[]  = "b.status IN ($active,'no_show') AND b.dive_date < ?";
            $params[] = $today;
            $order    = 'b.dive_date DESC, b.created_at DESC';
            break;
        case 'pending':
            $where[] = "b.status = 'pending'";
            $order   = 'b.created_at DESC';
            break;
        case 'cancelled':
            $where[] = "b.status IN ('cancelled','refunded')";
            $order   = 'b.created_at DESC';
            break;
        default:
            $order = 'b.created_at DESC';
    }

    $q = trim((string) ($f['q'] ?? ''));
    if ($q !== '') {
        $where[] = '(b.name LIKE ? OR b.email LIKE ? OR b.id LIKE ? OR c.phone LIKE ? OR c.name LIKE ?)';
        $like    = kay_like($q);
        array_push($params, $like, $like, addcslashes($q, '%_\\') . '%', $like, $like);
    }
    if (!empty($f['product'])) {
        $where[]  = 'b.product = ?';
        $params[] = (string) $f['product'];
    }
    foreach (['from' => '>=', 'to' => '<='] as $key => $op) {
        $d = (string) ($f[$key] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
            $where[]  = "b.dive_date $op ?";
            $params[] = $d;
        }
    }

    $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $from     = "FROM bookings b LEFT JOIN customers c ON c.id = b.customer_id $sqlWhere";

    $count = $db->prepare("SELECT COUNT(*) $from");
    $count->execute($params);

    $limit  = max(1, min(5000, $limit));
    $offset = max(0, $offset);
    $s = $db->prepare('SELECT ' . kay_booking_select() . " $from ORDER BY $order LIMIT $limit OFFSET $offset");
    $s->execute($params);

    return ['rows' => $s->fetchAll(), 'total' => (int) $count->fetchColumn()];
}

/** How many bookings each tab would show, for the counts beside them. */
function kay_booking_view_counts(PDO $db): array
{
    $active = kay_sql_list(KAY_ACTIVE);
    $s = $db->prepare(
        "SELECT
           SUM(status IN ($active) AND dive_date >= ?)            AS upcoming,
           SUM(status IN ($active,'no_show') AND dive_date < ?)   AS past,
           SUM(status = 'pending')                               AS pending,
           SUM(status IN ('cancelled','refunded'))               AS cancelled,
           COUNT(*)                                              AS `all`
         FROM bookings"
    );
    $today = kay_today();
    $s->execute([$today, $today]);
    return array_map('intval', $s->fetch() ?: []);
}

/* ------------------------------------------------------------ the day ----- */

/** Everyone diving on one day, in departure order. */
function kay_day_sheet(PDO $db, string $date): array
{
    $active = kay_sql_list(KAY_ACTIVE);
    $s = $db->prepare('SELECT ' . kay_booking_select() . "
                         FROM bookings b LEFT JOIN customers c ON c.id = b.customer_id
                        WHERE b.dive_date = ? AND b.status IN ($active)
                        ORDER BY FIELD(b.start_slot,'0800','0830','0900','other'), b.created_at");
    $s->execute([$date]);
    return $s->fetchAll();
}

/**
 * Bookings and divers per day, for the strip of days above the day sheet.
 *
 * @return array<string, array{bookings:int,divers:int}>
 */
function kay_days_overview(PDO $db, string $from, int $days): array
{
    $start = new DateTimeImmutable($from);
    $out   = [];
    for ($i = 0; $i < $days; $i++) {
        $out[$start->modify("+$i day")->format('Y-m-d')] = ['bookings' => 0, 'divers' => 0];
    }

    $active = kay_sql_list(KAY_ACTIVE);
    $s = $db->prepare(
        "SELECT dive_date, COUNT(*) AS n, SUM(divers) AS divers
           FROM bookings
          WHERE status IN ($active) AND dive_date BETWEEN ? AND ?
          GROUP BY dive_date"
    );
    $s->execute([$from, $start->modify('+' . ($days - 1) . ' day')->format('Y-m-d')]);
    foreach ($s->fetchAll() as $row) {
        $out[$row['dive_date']] = ['bookings' => (int) $row['n'], 'divers' => (int) $row['divers']];
    }
    return $out;
}

/* --------------------------------------------------------------- changes -- */

/**
 * Moves a booking through KAY_ACTIONS. The UPDATE carries the state the page
 * was looking at, so if Mercado Pago's webhook moved the booking in the
 * meantime, the click fails instead of overwriting a payment.
 *
 * @return string|null an error in the admin's language, or null on success
 */
function kay_booking_act(PDO $db, array $row, string $action, ?int $authorId): ?string
{
    $from = (string) $row['status'];
    if (!in_array($action, KAY_ACTIONS[$from] ?? [], true)) {
        return kay_t('Cette action n’est pas possible sur une réservation « {status} ».',
            ['{status}' => kay_t(KAY_STATUSES[$from] ?? $from)]);
    }

    $to = match ($action) {
        'confirm'  => 'confirmed',
        'complete' => 'completed',
        'no_show'  => 'no_show',
        'cancel'   => 'cancelled',
        'refund'   => 'refunded',
        // Back to what it was before: paid if Mercado Pago took the deposit.
        'reopen'   => $row['paid_at'] !== null ? 'paid' : 'confirmed',
    };

    $s = $db->prepare('UPDATE bookings SET status = ? WHERE id = ? AND status = ?');
    $s->execute([$to, $row['id'], $from]);
    if ($s->rowCount() === 0) {
        return kay_t('La réservation a changé entre-temps. Rechargez la page.');
    }

    kay_note_add($db, $row['customer_id'] !== null ? (int) $row['customer_id'] : null, (string) $row['id'],
        $authorId, 'log', kay_t('Statut : {from} → {to}',
            ['{from}' => kay_t(KAY_STATUSES[$from]), '{to}' => kay_t(KAY_STATUSES[$to])]));
    return null;
}

/**
 * Money received outside Mercado Pago. A refund is entered as a positive
 * amount and stored negative, so received is always a plain sum.
 */
function kay_payment_add(PDO $db, array $row, string $kind, int $amountMxn, string $method, ?int $authorId): ?string
{
    if (!isset(KAY_PAYMENT_KINDS[$kind])) {
        return kay_t('Type de paiement inconnu.');
    }
    if (!isset(KAY_PAYMENT_METHODS[$method])) {
        return kay_t('Moyen de paiement inconnu.');
    }
    if ($amountMxn <= 0 || $amountMxn > 1000000) {
        return kay_t('Le montant doit être un nombre de pesos positif.');
    }

    $signed = $kind === 'refund' ? -$amountMxn : $amountMxn;
    $db->prepare(
        'INSERT INTO booking_payments (booking_id, kind, amount_mxn_cents, method, author_id) VALUES (?, ?, ?, ?, ?)'
    )->execute([$row['id'], $kind, $signed * 100, $method, $authorId]);

    kay_note_add($db, $row['customer_id'] !== null ? (int) $row['customer_id'] : null, (string) $row['id'],
        $authorId, 'log', kay_t('{kind} encaissé : {amount} ({method})', [
            '{kind}' => kay_t(KAY_PAYMENT_KINDS[$kind]), '{amount}' => kay_mxn($signed),
            '{method}' => kay_t(KAY_PAYMENT_METHODS[$method]),
        ]));
    return null;
}

function kay_payments_for(PDO $db, string $bookingId): array
{
    $s = $db->prepare(
        'SELECT p.*, u.name AS author
           FROM booking_payments p LEFT JOIN admin_users u ON u.id = p.author_id
          WHERE p.booking_id = ? ORDER BY p.created_at'
    );
    $s->execute([$bookingId]);
    return $s->fetchAll();
}

/**
 * Validates the booking form shared by "new" and "edit". Kay may book outside
 * the shark season — he knows when the sharks arrive, the form does not — and
 * may record a dive that already happened, so neither is refused here.
 *
 * @return array{error:?string,quote:?array,clean:array} error in the admin's language
 */
function kay_booking_input(array $in): array
{
    $clean = [
        'product'  => (string) ($in['product'] ?? ''),
        'option'   => (int) ($in['option'] ?? -1),
        'date'     => (string) ($in['date'] ?? ''),
        'slot'     => (string) ($in['slot'] ?? '0800'),
        'slotNote' => mb_substr(trim((string) ($in['slotNote'] ?? '')), 0, 120),
        'divers'   => (int) ($in['divers'] ?? 1),
        'pickup'   => (string) ($in['pickup'] ?? 'meeting-point'),
        'cert'     => mb_substr(trim((string) ($in['cert'] ?? '')), 0, 64),
        'name'     => mb_substr(trim((string) ($in['name'] ?? '')), 0, 160),
        'email'    => mb_strtolower(trim((string) ($in['email'] ?? ''))),
        'locale'   => in_array($in['locale'] ?? '', ['en', 'es', 'fr'], true) ? (string) $in['locale'] : 'en',
    ];

    // "product:dives" from the one select that picks both.
    if (isset($in['item']) && preg_match('/^([a-z0-9-]+):(\d+)$/', (string) $in['item'], $m)) {
        $clean['product'] = $m[1];
        $clean['option']  = (int) $m[2];
    }

    $error = null;
    $date  = DateTimeImmutable::createFromFormat('!Y-m-d', $clean['date']);
    if (mb_strlen($clean['name']) < 2) {
        $error = kay_t('Indiquez le nom du client.');
    } elseif ($clean['email'] !== '' && !filter_var($clean['email'], FILTER_VALIDATE_EMAIL)) {
        $error = kay_t('L’adresse e-mail n’est pas valide.');
    } elseif ($date === false || $date->format('Y-m-d') !== $clean['date']) {
        $error = kay_t('Choisissez une date de plongée.');
    } elseif ($clean['divers'] < 1 || $clean['divers'] > 8) {
        $error = kay_t('Entre 1 et 8 plongeurs par réservation.');
    }

    $slots = array_column(kay_catalogue()['schedules'], 'slug');
    if (!in_array($clean['slot'], $slots, true)) {
        $clean['slot'] = '0800';
    }

    $quote = kay_quote([
        'product' => $clean['product'], 'option' => $clean['option'],
        'divers'  => $clean['divers'],  'pickup' => $clean['pickup'],
    ]);
    if ($error === null && $quote === null) {
        $error = kay_t('Choisissez une sortie et un transport du catalogue.');
    }
    return ['error' => $error, 'quote' => $quote, 'clean' => $clean];
}

/**
 * A booking Kay takes himself. It is 'confirmed' from the start and carries
 * no online deposit; whatever he took in cash goes in the ledger.
 *
 * @return array{ok:bool,id?:string,error?:string}
 */
function kay_booking_create(PDO $db, array $in, ?int $authorId): array
{
    ['error' => $error, 'quote' => $quote, 'clean' => $c] = kay_booking_input($in);
    if ($error !== null) {
        return ['ok' => false, 'error' => $error];
    }

    $deposit = max(0, (int) ($in['deposit'] ?? 0));
    $method  = (string) ($in['method'] ?? 'cash');
    if ($deposit > $quote['total_mxn']) {
        return ['ok' => false, 'error' => kay_t('L’acompte dépasse le prix total.')];
    }
    if ($deposit > 0 && !isset(KAY_PAYMENT_METHODS[$method])) {
        return ['ok' => false, 'error' => kay_t('Moyen de paiement inconnu.')];
    }

    // Started from a customer's page: that customer, even one Kay only knows
    // by WhatsApp and who therefore has no email to be found by.
    $given = (int) ($in['customer'] ?? 0);
    $known = $db->prepare('SELECT id FROM customers WHERE id = ?');
    $known->execute([$given]);
    $customerId = $given > 0 && $known->fetchColumn() !== false
        ? $given
        : kay_customer_for($db, $c['email'], [
            'name'  => $c['name'], 'phone' => mb_substr(trim((string) ($in['phone'] ?? '')), 0, 40),
            'locale' => $c['locale'], 'certification' => $c['cert'],
        ]);

    $id = kay_uuid();
    $db->prepare(
        "INSERT INTO bookings
           (id, customer_id, source, status, product, dives, dive_date, divers, certification, pickup,
            start_slot, start_note, name, email, locale,
            total_usd_cents, total_mxn_cents, deposit_usd_cents, deposit_mxn_cents)
         VALUES (?, ?, 'manual', 'confirmed', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0)"
    )->execute([
        $id, $customerId, $quote['product']['slug'], $quote['option']['dives'], $c['date'],
        $quote['divers'], $c['cert'], $quote['pickup']['slug'], $c['slot'], $c['slotNote'],
        $c['name'], $c['email'], $c['locale'],
        $quote['total_usd'] * 100, $quote['total_mxn'] * 100,
    ]);

    kay_note_add($db, $customerId, $id, $authorId, 'log', kay_t('Réservation saisie dans l’admin.'));
    $row = kay_booking_get($db, $id);
    if ($deposit > 0 && $row !== null) {
        kay_payment_add($db, $row, 'deposit', $deposit, $method, $authorId);
    }
    return ['ok' => true, 'id' => $id];
}

/**
 * Changes what was booked. The price is re-quoted from the catalogue only if
 * something that sets it changed — otherwise a booking made at last year's
 * prices would silently move to this year's when someone fixes a typo in the
 * name. Money already received is never touched.
 *
 * @return string|null an error in the admin's language, or null on success
 */
function kay_booking_edit(PDO $db, array $row, array $in, ?int $authorId): ?string
{
    ['error' => $error, 'quote' => $quote, 'clean' => $c] = kay_booking_input($in);
    if ($error !== null) {
        return $error;
    }

    $priced = $c['product'] !== $row['product']
        || $c['option'] !== (int) $row['dives']
        || $c['divers'] !== (int) $row['divers']
        || $c['pickup'] !== $row['pickup'];

    $labels = [
        'dive_date' => kay_t('date'), 'start_slot' => kay_t('départ'), 'start_note' => kay_t('horaire souhaité'),
        'certification' => kay_t('niveau'), 'name' => kay_t('nom'), 'email' => kay_t('e-mail'),
        'product' => kay_t('sortie'), 'dives' => kay_t('plongées'), 'divers' => kay_t('plongeurs'),
        'pickup' => kay_t('transport'),
    ];
    $next = [
        'dive_date' => $c['date'], 'start_slot' => $c['slot'], 'start_note' => $c['slotNote'],
        'certification' => $c['cert'], 'name' => $c['name'], 'email' => $c['email'],
        'product' => $quote['product']['slug'], 'dives' => $quote['option']['dives'],
        'divers' => $quote['divers'], 'pickup' => $quote['pickup']['slug'],
    ];
    if ($priced) {
        $next['total_usd_cents'] = $quote['total_usd'] * 100;
        $next['total_mxn_cents'] = $quote['total_mxn'] * 100;
    }

    $changes = [];
    foreach ($next as $column => $value) {
        if ((string) $row[$column] !== (string) $value) {
            $changes[$column] = $value;
        }
    }
    if ($changes === []) {
        return null;
    }

    $sets = implode(', ', array_map(static fn(string $col): string => "$col = ?", array_keys($changes)));
    $db->prepare("UPDATE bookings SET $sets WHERE id = ?")
       ->execute([...array_values($changes), $row['id']]);

    $said = [];
    foreach ($changes as $column => $value) {
        if (isset($labels[$column])) {
            $said[] = sprintf('%s %s → %s', $labels[$column], $row[$column] === '' ? '—' : $row[$column], $value === '' ? '—' : $value);
        }
    }
    if (isset($changes['total_mxn_cents'])) {
        $said[] = kay_t('prix {from} → {to}', [
            '{from}' => kay_mxn((int) $row['total_mxn_cents'] / 100),
            '{to}'   => kay_mxn((int) $changes['total_mxn_cents'] / 100),
        ]);
    }
    kay_note_add($db, $row['customer_id'] !== null ? (int) $row['customer_id'] : null, (string) $row['id'],
        $authorId, 'log', kay_t('Modifiée : {changes}', ['{changes}' => implode(', ', $said)]));
    return null;
}

/**
 * The row the confirmation email is rendered from. The email's "paid" line
 * reads the deposit column; for a booking paid in cash that column is zero,
 * so it is given what was actually received instead.
 */
function kay_booking_email_row(array $row): array
{
    $received = max(0, (int) ($row['received_mxn_cents'] ?? 0));
    $total    = max(1, (int) $row['total_mxn_cents']);
    $row['deposit_mxn_cents'] = $received;
    $row['deposit_usd_cents'] = (int) round((int) $row['total_usd_cents'] * $received / $total);
    return $row;
}

/* ----------------------------------------------------------------- money --
   Days are Tulum days; created_at and paid_at are the database's own clock.
   The two differ by a few hours at most, which moves a booking made near
   midnight into the neighbouring day and nothing else.                     */

/**
 * The end of an inclusive date range, as an exclusive bound. Computed here
 * rather than by adding an INTERVAL to a bound parameter in SQL, which MySQL
 * versions do not all type the same way.
 */
function kay_day_after(string $ymd): string
{
    return (new DateTimeImmutable($ymd))->modify('+1 day')->format('Y-m-d');
}

/**
 * Money that came in between two dates, inclusive, in peso cents: online
 * deposits by the day they cleared, the ledger by the day it was entered.
 */
function kay_money_in(PDO $db, string $from, string $to): int
{
    $until = kay_day_after($to);
    $s = $db->prepare(
        "SELECT
           (SELECT COALESCE(SUM(deposit_mxn_cents), 0) FROM bookings
             WHERE paid_at >= ? AND paid_at < ? AND status <> 'refunded')
         + (SELECT COALESCE(SUM(amount_mxn_cents), 0) FROM booking_payments
             WHERE created_at >= ? AND created_at < ?)"
    );
    $s->execute([$from, $until, $from, $until]);
    return (int) $s->fetchColumn();
}

/** Bookings that went ahead, by the day they were made. */
function kay_bookings_made(PDO $db, string $from, string $to): int
{
    $active = kay_sql_list(KAY_ACTIVE);
    $s = $db->prepare(
        "SELECT COUNT(*) FROM bookings
          WHERE status IN ($active,'no_show') AND created_at >= ? AND created_at < ?"
    );
    $s->execute([$from, kay_day_after($to)]);
    return (int) $s->fetchColumn();
}

/** Web bookings whose deposit cleared, by the day it cleared — the end of the funnel. */
function kay_deposits_paid(PDO $db, string $from, string $to): int
{
    $s = $db->prepare(
        "SELECT COUNT(*) FROM bookings
          WHERE source = 'web' AND paid_at >= ? AND paid_at < ?"
    );
    $s->execute([$from, kay_day_after($to)]);
    return (int) $s->fetchColumn();
}

/** Still to collect on everything from today on, in peso cents. */
function kay_balance_outstanding(PDO $db): int
{
    $s = $db->prepare(
        "SELECT COALESCE(SUM(GREATEST(b.total_mxn_cents - " . kay_received_sql() . ", 0)), 0)
           FROM bookings b
          WHERE b.status IN ('paid','confirmed') AND b.dive_date >= ?"
    );
    $s->execute([kay_today()]);
    return (int) $s->fetchColumn();
}

/**
 * Booked revenue per month of diving, for the last $months months including
 * this one, in whole pesos.
 *
 * @return array<string,int> 'YYYY-MM' => pesos
 */
function kay_revenue_by_month(PDO $db, int $months = 12): array
{
    $first = kay_now()->modify('first day of this month')->modify('-' . ($months - 1) . ' month');
    $out   = [];
    for ($i = 0; $i < $months; $i++) {
        $out[$first->modify("+$i month")->format('Y-m')] = 0;
    }

    $active = kay_sql_list(KAY_ACTIVE);
    $s = $db->prepare(
        "SELECT DATE_FORMAT(dive_date, '%Y-%m') AS m, SUM(total_mxn_cents) AS cents
           FROM bookings
          WHERE status IN ($active) AND dive_date >= ?
          GROUP BY m"
    );
    $s->execute([$first->format('Y-m-01')]);
    foreach ($s->fetchAll() as $row) {
        if (isset($out[$row['m']])) {
            $out[$row['m']] = (int) round($row['cents'] / 100);
        }
    }
    return $out;
}

/**
 * Checkouts that were opened and never paid: people who wanted to dive and
 * stopped at the card form. Anyone who went back and booked successfully
 * afterwards is left out, so the list is only the ones worth a message.
 */
function kay_abandoned_checkouts(PDO $db, int $days = 14, int $limit = 10): array
{
    $active = kay_sql_list(KAY_ACTIVE);
    $s = $db->prepare(
        "SELECT b.id, b.name, b.email, b.product, b.dives, b.dive_date, b.divers, b.created_at,
                b.customer_id, b.total_mxn_cents
           FROM bookings b
          WHERE b.status = 'pending' AND b.source = 'web'
            AND b.created_at < NOW() - INTERVAL 30 MINUTE
            AND b.created_at > NOW() - INTERVAL ? DAY
            AND b.dive_date >= ?
            AND NOT EXISTS (
                SELECT 1 FROM bookings later
                 WHERE later.email = b.email AND later.status IN ($active,'no_show')
                   AND later.created_at >= b.created_at)
          ORDER BY b.created_at DESC
          LIMIT " . max(1, $limit)
    );
    $s->execute([$days, kay_today()]);
    return $s->fetchAll();
}

function kay_recent_bookings(PDO $db, int $limit = 8): array
{
    $s = $db->query('SELECT ' . kay_booking_select() . "
                       FROM bookings b LEFT JOIN customers c ON c.id = b.customer_id
                      WHERE b.status <> 'pending' OR b.created_at > NOW() - INTERVAL 30 MINUTE
                      ORDER BY b.created_at DESC LIMIT " . max(1, $limit));
    return $s->fetchAll();
}
