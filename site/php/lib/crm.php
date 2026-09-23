<?php
declare(strict_types=1);

/**
 * The CRM: one row per person, whatever they booked and however they booked
 * it, and a timeline of everything said to them or planned for them.
 *
 * Customers are derived, not typed in twice. Every booking that arrives from
 * the website is attached to the customer with the same email the next time
 * the admin is opened — kay_crm_sync() — so booking.php, on the payment path,
 * does not change at all and cannot fail because of the CRM.
 */

const KAY_NOTE_KINDS = [
    'note'     => 'Note',
    'call'     => 'Appel',
    'whatsapp' => 'WhatsApp',
    'email'    => 'E-mail',
    'task'     => 'À faire',
    'log'      => 'Historique',
];

const KAY_CUSTOMER_SORTS = [
    'recent'   => 'Activité récente',
    'next'     => 'Prochaine plongée',
    'value'    => 'Valeur',
    'bookings' => 'Réservations',
    'name'     => 'Nom',
];

const KAY_SEGMENTS = [
    'all'      => 'Tous',
    'clients'  => 'Clients',
    'leads'    => 'Prospects',
    'upcoming' => 'Plongée à venir',
    'repeat'   => 'Fidèles',
];

/** "Nitrox, VIP ,nitrox" → "nitrox,vip". */
function kay_tags_clean(string $tags): string
{
    $out = [];
    foreach (explode(',', $tags) as $tag) {
        $tag = mb_substr(mb_strtolower(trim(preg_replace('/\s+/u', ' ', $tag) ?? '')), 0, 30);
        if ($tag !== '' && !in_array($tag, $out, true)) {
            $out[] = $tag;
        }
    }
    return mb_substr(implode(',', array_slice($out, 0, 12)), 0, 255);
}

/**
 * The customer for this email, created if need be. Blanks are filled from
 * what the latest booking says; nothing Kay typed on the customer is
 * overwritten by a booking, except the certification, which only goes up.
 *
 * @param array{name?:string,phone?:string,locale?:string,certification?:string,created_at?:string} $fields
 */
function kay_customer_for(PDO $db, string $email, array $fields): int
{
    $email = mb_strtolower(trim($email));
    $name  = mb_substr(trim((string) ($fields['name'] ?? '')), 0, 160);
    $phone = mb_substr(trim((string) ($fields['phone'] ?? '')), 0, 40);
    $cert  = mb_substr(trim((string) ($fields['certification'] ?? '')), 0, 64);
    $loc   = in_array($fields['locale'] ?? '', ['en', 'es', 'fr'], true) ? (string) $fields['locale'] : 'en';

    if ($email !== '') {
        $s = $db->prepare('SELECT id FROM customers WHERE email = ?');
        $s->execute([$email]);
        $id = $s->fetchColumn();
        if ($id !== false) {
            $db->prepare(
                "UPDATE customers
                    SET name  = IF(name = '', ?, name),
                        phone = IF(phone = '', ?, phone),
                        certification = IF(? <> '', ?, certification),
                        locale = ?
                  WHERE id = ?"
            )->execute([$name, $phone, $cert, $cert, $loc, $id]);
            return (int) $id;
        }
    }

    try {
        $db->prepare(
            'INSERT INTO customers (email, name, phone, locale, certification, created_at)
             VALUES (?, ?, ?, ?, ?, COALESCE(?, NOW()))'
        )->execute([$email === '' ? null : $email, $name, $phone, $loc, $cert, $fields['created_at'] ?? null]);
    } catch (PDOException $e) {
        // Two requests creating the same person at once: the other one won.
        if ($email !== '' && $e->getCode() === '23000') {
            return kay_customer_for($db, $email, $fields);
        }
        throw $e;
    }
    return (int) $db->lastInsertId();
}

/**
 * Attaches website bookings to their customer. Oldest first, so the latest
 * booking's certification and language are the ones that stick; the
 * customer's first-seen date is the date of their first booking.
 *
 * @return int bookings attached by this call
 */
function kay_crm_sync(PDO $db, int $max = 500): int
{
    $rows = $db->query(
        "SELECT id, email, name, locale, certification, created_at
           FROM bookings
          WHERE customer_id IS NULL AND email <> ''
          ORDER BY created_at
          LIMIT " . max(1, $max)
    )->fetchAll();

    $attach = $db->prepare('UPDATE bookings SET customer_id = ? WHERE id = ? AND customer_id IS NULL');
    foreach ($rows as $row) {
        $attach->execute([
            kay_customer_for($db, (string) $row['email'], [
                'name' => $row['name'], 'locale' => $row['locale'],
                'certification' => $row['certification'], 'created_at' => $row['created_at'],
            ]),
            $row['id'],
        ]);
    }
    return count($rows);
}

/* ------------------------------------------------------------- searching -- */

/** Per-customer figures, computed from their bookings. */
function kay_customer_stats_sql(): string
{
    $active = kay_sql_list(KAY_ACTIVE);
    return "COUNT(b.id) AS bookings_all,
            COALESCE(SUM(b.status IN ($active)), 0) AS bookings,
            COALESCE(SUM(CASE WHEN b.status IN ($active) THEN b.total_mxn_cents END), 0) AS value_mxn_cents,
            MAX(CASE WHEN b.status IN ($active) AND b.dive_date < :today1 THEN b.dive_date END) AS last_dive,
            MIN(CASE WHEN b.status IN ($active) AND b.dive_date >= :today2 THEN b.dive_date END) AS next_dive,
            MAX(b.created_at) AS last_booking_at";
}

/**
 * @param array{q?:string,tag?:string,segment?:string,sort?:string} $f
 * @return array{rows:array,total:int}
 */
function kay_customers_find(PDO $db, array $f, int $limit = 50, int $offset = 0): array
{
    $where  = [];
    $params = ['today1' => kay_today(), 'today2' => kay_today()];

    $q = trim((string) ($f['q'] ?? ''));
    if ($q !== '') {
        $where[] = '(c.name LIKE :q1 OR c.email LIKE :q2 OR c.phone LIKE :q3 OR c.tags LIKE :q4)';
        $params += ['q1' => kay_like($q), 'q2' => kay_like($q), 'q3' => kay_like($q), 'q4' => kay_like($q)];
    }
    $tag = kay_tags_clean((string) ($f['tag'] ?? ''));
    if ($tag !== '' && !str_contains($tag, ',')) {
        $where[] = "CONCAT(',', c.tags, ',') LIKE :tag";
        $params['tag'] = '%,' . addcslashes($tag, '%_\\') . ',%';
    }

    $having = match ((string) ($f['segment'] ?? 'all')) {
        'clients'  => 'HAVING bookings > 0',
        'leads'    => 'HAVING bookings = 0',
        'upcoming' => 'HAVING next_dive IS NOT NULL',
        'repeat'   => 'HAVING bookings >= 2',
        default    => '',
    };
    // Sorted outside the GROUP BY: MariaDB will not order by an expression
    // over an aggregate's alias.
    $order = match ((string) ($f['sort'] ?? 'recent')) {
        'name'     => 't.name ASC',
        'value'    => 't.value_mxn_cents DESC, t.name',
        'bookings' => 't.bookings DESC, t.name',
        'next'     => 't.next_dive IS NULL, t.next_dive ASC',
        default    => 'COALESCE(t.last_booking_at, t.created_at) DESC',
    };

    $sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $inner = 'SELECT c.*, ' . kay_customer_stats_sql() . "
                FROM customers c LEFT JOIN bookings b ON b.customer_id = c.id
                $sqlWhere
               GROUP BY c.id
               $having";

    $count = $db->prepare("SELECT COUNT(*) FROM ($inner) t");
    $count->execute($params);

    $limit  = max(1, min(5000, $limit));
    $offset = max(0, $offset);
    $s = $db->prepare("SELECT * FROM ($inner) t ORDER BY $order LIMIT $limit OFFSET $offset");
    $s->execute($params);

    return ['rows' => $s->fetchAll(), 'total' => (int) $count->fetchColumn()];
}

/** Every tag in use, most used first, for the filter. */
function kay_tags_in_use(PDO $db): array
{
    $counts = [];
    foreach ($db->query("SELECT tags FROM customers WHERE tags <> ''")->fetchAll(PDO::FETCH_COLUMN) as $tags) {
        foreach (explode(',', (string) $tags) as $tag) {
            $counts[$tag] = ($counts[$tag] ?? 0) + 1;
        }
    }
    arsort($counts);
    return $counts;
}

function kay_customer_get(PDO $db, int $id): ?array
{
    $s = $db->prepare('SELECT c.*, ' . kay_customer_stats_sql() . '
                         FROM customers c LEFT JOIN bookings b ON b.customer_id = c.id
                        WHERE c.id = :id GROUP BY c.id');
    $s->execute(['id' => $id, 'today1' => kay_today(), 'today2' => kay_today()]);
    $row = $s->fetch();
    return is_array($row) ? $row : null;
}

function kay_customer_bookings(PDO $db, int $id): array
{
    $s = $db->prepare('SELECT ' . kay_booking_select() . '
                         FROM bookings b LEFT JOIN customers c ON c.id = b.customer_id
                        WHERE b.customer_id = ? ORDER BY b.dive_date DESC, b.created_at DESC');
    $s->execute([$id]);
    return $s->fetchAll();
}

/** @return string|null an error in the admin's language, or null on success */
function kay_customer_update(PDO $db, int $id, array $in): ?string
{
    $email = mb_strtolower(trim((string) ($in['email'] ?? '')));
    $name  = mb_substr(trim((string) ($in['name'] ?? '')), 0, 160);
    if (mb_strlen($name) < 2) {
        return kay_t('Indiquez un nom.');
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return kay_t('L’adresse e-mail n’est pas valide.');
    }
    if ($email !== '') {
        $s = $db->prepare('SELECT name FROM customers WHERE email = ? AND id <> ?');
        $s->execute([$email, $id]);
        $other = $s->fetchColumn();
        if ($other !== false) {
            return kay_t('Cette adresse est déjà celle de « {name} ».', ['{name}' => (string) $other]);
        }
    }
    $country = strtoupper(trim((string) ($in['country'] ?? '')));

    $db->prepare(
        'UPDATE customers
            SET name = ?, email = ?, phone = ?, country = ?, locale = ?, certification = ?, tags = ?, notes = ?
          WHERE id = ?'
    )->execute([
        $name,
        $email === '' ? null : $email,
        mb_substr(trim((string) ($in['phone'] ?? '')), 0, 40),
        preg_match('/^[A-Z]{2}$/', $country) ? $country : '',
        in_array($in['locale'] ?? '', ['en', 'es', 'fr'], true) ? $in['locale'] : 'en',
        mb_substr(trim((string) ($in['certification'] ?? '')), 0, 64),
        kay_tags_clean((string) ($in['tags'] ?? '')),
        mb_substr(trim((string) ($in['notes'] ?? '')), 0, 5000),
        $id,
    ]);
    return null;
}

/**
 * The right to be forgotten, without losing the accounts: the person's name,
 * email and phone go, from the customer and from every booking; the notes go;
 * what was sold, when and for how much stays, because the books must add up.
 */
function kay_customer_forget(PDO $db, int $id): void
{
    $anonymous = kay_t('Client anonymisé');
    $db->prepare(
        "UPDATE bookings SET name = ?, email = '', start_note = '' WHERE customer_id = ?"
    )->execute([$anonymous, $id]);
    $db->prepare('DELETE FROM crm_notes WHERE customer_id = ?')->execute([$id]);
    $db->prepare(
        "UPDATE customers
            SET name = ?, email = NULL, phone = '', country = '', certification = '',
                tags = '', notes = NULL
          WHERE id = ?"
    )->execute([$anonymous, $id]);
}

/* -------------------------------------------------------------- timeline -- */

function kay_note_add(PDO $db, ?int $customerId, ?string $bookingId, ?int $authorId,
                      string $kind, string $body, ?string $dueOn = null): int
{
    $kind = isset(KAY_NOTE_KINDS[$kind]) ? $kind : 'note';
    $due  = $dueOn !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueOn) ? $dueOn : null;
    $db->prepare(
        'INSERT INTO crm_notes (customer_id, booking_id, author_id, kind, body, due_on) VALUES (?, ?, ?, ?, ?, ?)'
    )->execute([$customerId, $bookingId, $authorId, $kind, mb_substr(trim($body), 0, 5000),
                $kind === 'task' ? ($due ?? kay_today()) : null]);
    return (int) $db->lastInsertId();
}

/** The timeline of a customer, or of one booking. Newest first. */
function kay_notes_for(PDO $db, ?int $customerId, ?string $bookingId = null): array
{
    $s = $db->prepare(
        'SELECT n.*, u.name AS author
           FROM crm_notes n LEFT JOIN admin_users u ON u.id = n.author_id
          WHERE ' . ($bookingId !== null ? 'n.booking_id = ?' : 'n.customer_id = ?') . '
          ORDER BY n.created_at DESC, n.id DESC
          LIMIT 200'
    );
    $s->execute([$bookingId ?? $customerId]);
    return $s->fetchAll();
}

function kay_task_done(PDO $db, int $noteId, bool $done = true): void
{
    $db->prepare("UPDATE crm_notes SET done_at = IF(?, NOW(), NULL) WHERE id = ? AND kind = 'task'")
       ->execute([$done ? 1 : 0, $noteId]);
}

/** Open follow-ups, oldest due first. */
function kay_tasks_open(PDO $db, int $limit = 12): array
{
    return $db->query(
        "SELECT n.*, c.name AS customer_name
           FROM crm_notes n LEFT JOIN customers c ON c.id = n.customer_id
          WHERE n.kind = 'task' AND n.done_at IS NULL
          ORDER BY n.due_on ASC, n.id ASC
          LIMIT " . max(1, $limit)
    )->fetchAll();
}
