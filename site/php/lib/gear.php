<?php
declare(strict_types=1);

/**
 * The equipment questionnaire: height, approximate weight, shoe size, and
 * the wetsuit, BCD and fins sizes each diver wants — so the kit is on the
 * boat in the right sizes before anyone arrives.
 *
 * Every paid or confirmed booking gets a personal link in its confirmation
 * email. The link carries the booking id and an HMAC of it, keyed with a
 * secret that never leaves the server, so a link cannot be guessed from a
 * booking id and nothing new has to be stored to check it.
 *
 * One form per diver: a booking for three is three sets of sizes, filled in
 * once by whoever booked. The snorkel tour only needs a shoe size, for fins.
 */

const KAY_GEAR_SIZES     = ['XS', 'S', 'M', 'L', 'XL', 'XXL', '?'];
const KAY_GEAR_FIN_SIZES = ['XS', 'S', 'M', 'L', 'XL', '?'];
const KAY_GEAR_SHOE      = ['US', 'EU', 'MX', 'UK'];

/** Only a booking that is going ahead has sizes to collect. */
const KAY_GEAR_STATUSES = ['paid', 'confirmed', 'completed'];

function kay_gear_token(string $bookingId): string
{
    return substr(hash_hmac('sha256', 'kay-gear|' . $bookingId, (string) kay_config()['mp_webhook_secret']), 0, 32);
}

function kay_gear_url(array $row): string
{
    $site   = rtrim((string) kay_config()['site_url'], '/');
    $locale = in_array($row['locale'] ?? 'en', ['en', 'es', 'fr'], true) ? $row['locale'] : 'en';
    return $site . '/' . $locale . '/booking/gear/?' . http_build_query([
        'b' => $row['id'], 't' => kay_gear_token((string) $row['id']),
    ]);
}

/** The booking a link opens, or null for a wrong or forged one. */
function kay_gear_booking(PDO $db, string $bookingId, string $token): ?array
{
    if (!preg_match('/^[a-f0-9-]{36}$/', $bookingId) || !hash_equals(kay_gear_token($bookingId), $token)) {
        return null;
    }
    $s = $db->prepare('SELECT * FROM bookings WHERE id = ?');
    $s->execute([$bookingId]);
    $row = $s->fetch();
    return is_array($row) ? $row : null;
}

/** 'snorkel' needs a shoe size only; everything else needs the lot. */
function kay_gear_kind(array $booking): string
{
    $product = kay_find_product((string) $booking['product']);
    return ($product['kind'] ?? '') === 'snorkel' ? 'snorkel' : 'dive';
}

/** @return array<int, array> diver number => answers */
function kay_gear_for(PDO $db, string $bookingId): array
{
    $s = $db->prepare('SELECT * FROM booking_gear WHERE booking_id = ? ORDER BY diver_no');
    $s->execute([$bookingId]);
    $out = [];
    foreach ($s->fetchAll() as $row) {
        $out[(int) $row['diver_no']] = $row;
    }
    return $out;
}

/**
 * Checks and normalises one diver's answers. Heights and weights arrive in
 * whatever the diver uses — the form converts feet and pounds before
 * sending — and are kept in centimetres and kilograms.
 *
 * @return array{error:?string,clean:array}
 */
function kay_gear_clean(array $in, string $kind): array
{
    $clean = [
        'name'      => mb_substr(trim((string) ($in['name'] ?? '')), 0, 120),
        'height_cm' => null,
        'weight_kg' => null,
        'shoe'      => '',
        'wetsuit'   => '',
        'bcd'       => '',
        'fins'      => '',
    ];

    $system = strtoupper((string) ($in['shoeSystem'] ?? ''));
    $size   = str_replace(',', '.', trim((string) ($in['shoeSize'] ?? '')));
    if (!in_array($system, KAY_GEAR_SHOE, true) || !preg_match('/^\d{1,2}(\.5)?$/', $size) || (float) $size < 1) {
        return ['error' => 'shoe', 'clean' => $clean];
    }
    $clean['shoe'] = $system . ' ' . $size;

    $fins = (string) ($in['fins'] ?? '?');
    $clean['fins'] = in_array($fins, KAY_GEAR_FIN_SIZES, true) ? $fins : '?';

    if ($kind === 'snorkel') {
        return ['error' => null, 'clean' => $clean];
    }

    $height = (int) round((float) ($in['heightCm'] ?? 0));
    $weight = (int) round((float) ($in['weightKg'] ?? 0));
    if ($height < 90 || $height > 230) {
        return ['error' => 'height', 'clean' => $clean];
    }
    if ($weight < 20 || $weight > 200) {
        return ['error' => 'weight', 'clean' => $clean];
    }
    $clean['height_cm'] = $height;
    $clean['weight_kg'] = $weight;

    foreach (['wetsuit', 'bcd'] as $item) {
        $v = (string) ($in[$item] ?? '?');
        $clean[$item] = in_array($v, KAY_GEAR_SIZES, true) ? $v : '?';
    }
    return ['error' => null, 'clean' => $clean];
}

/**
 * Saves every diver's answers for one booking, replacing what was there.
 *
 * @param array<int, array> $divers in form order: diver 1, diver 2…
 * @return array{ok:bool,error?:string,diver?:int}
 */
function kay_gear_save(PDO $db, array $booking, array $divers): array
{
    if (!in_array($booking['status'], KAY_GEAR_STATUSES, true)) {
        return ['ok' => false, 'error' => 'closed'];
    }
    $count = (int) $booking['divers'];
    if (count($divers) !== $count) {
        return ['ok' => false, 'error' => 'count'];
    }

    $kind = kay_gear_kind($booking);
    $rows = [];
    foreach (array_values($divers) as $i => $in) {
        $r = kay_gear_clean(is_array($in) ? $in : [], $kind);
        if ($r['error'] !== null) {
            return ['ok' => false, 'error' => $r['error'], 'diver' => $i + 1];
        }
        $rows[$i + 1] = $r['clean'];
    }

    $first = kay_gear_for($db, (string) $booking['id']) === [];
    $db->prepare('DELETE FROM booking_gear WHERE booking_id = ?')->execute([$booking['id']]);
    $insert = $db->prepare(
        'INSERT INTO booking_gear (booking_id, diver_no, name, height_cm, weight_kg, shoe, wetsuit, bcd, fins)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    foreach ($rows as $n => $r) {
        $insert->execute([$booking['id'], $n, $r['name'], $r['height_cm'], $r['weight_kg'],
                          $r['shoe'], $r['wetsuit'], $r['bcd'], $r['fins']]);
    }

    if (function_exists('kay_note_add')) {
        kay_note_add($db, $booking['customer_id'] !== null ? (int) $booking['customer_id'] : null, (string) $booking['id'],
            null, 'log', $first
                ? kay_t('Tailles reçues pour {n} plongeur(s).', ['{n}' => (string) $count])
                : kay_t('Tailles mises à jour par le client.'));
    }
    return ['ok' => true];
}

/**
 * Bookings on the given days that still have no sizes, for the dashboard's
 * "missing sizes" list and the day sheet.
 */
function kay_gear_missing(PDO $db, string $from, string $to): array
{
    $s = $db->prepare(
        "SELECT b.id, b.name, b.dive_date, b.product, b.divers, b.start_slot, b.email, c.phone AS customer_phone
           FROM bookings b
           LEFT JOIN customers c ON c.id = b.customer_id
          WHERE b.dive_date BETWEEN ? AND ? AND b.status IN ('paid','confirmed')
            AND NOT EXISTS (SELECT 1 FROM booking_gear g WHERE g.booking_id = b.id)
          ORDER BY b.dive_date, FIELD(b.start_slot,'0800','0830','0900','other')"
    );
    $s->execute([$from, $to]);
    return $s->fetchAll();
}

/**
 * The questionnaire on its own, for a booking Kay entered by hand — or for a
 * diver who lost the confirmation. In the diver's language, like every email
 * a diver receives.
 *
 * @return array{to:string,toName:string,subject:string,html:string,text:string,replyTo:string}
 */
function kay_gear_email(array $row): array
{
    $locale = in_array($row['locale'] ?? 'en', ['en', 'es', 'fr'], true) ? $row['locale'] : 'en';
    $m      = kay_messages($locale);
    $d      = $m['emails']['diver'];
    $g      = $m['emails']['gear'];
    $date   = kay_format_date((string) $row['dive_date'], $locale);
    $url    = kay_gear_url($row + ['locale' => $locale]);
    $text   = kay_gear_kind($row) === 'snorkel' ? $d['gearTextSnorkel'] : $d['gearText'];
    $name   = (string) ($m['products']['items'][$row['product']]['name'] ?? $row['product']);
    $hi     = strtr((string) $d['hi'], ['{name}' => (string) $row['name']]);

    $inner = kay_mail_header((string) $g['kicker'], $name . ' · ' . $date)
        . kay_mail_text('<p style="margin:0 0 12px;">' . kay_e($hi) . '</p><p style="margin:0 0 16px;">' . kay_e($text) . '</p>'
            . kay_mail_button($url, (string) $d['gearCta'])
            . '<p style="margin:22px 0 0;">' . kay_e((string) $d['signoff']) . '<br><strong>' . kay_e((string) $d['team']) . '</strong></p>')
        . kay_mail_footer(rtrim((string) kay_config()['site_url'], '/'));

    $subject = strtr((string) $g['subject'], ['{date}' => $date]);
    return [
        'to'      => (string) $row['email'],
        'toName'  => (string) $row['name'],
        'subject' => $subject,
        'html'    => kay_mail_shell((string) $g['preheader'], $inner),
        'text'    => implode("\n", [$hi, '', $text, $url, '', $d['signoff'], $d['team']]),
        'replyTo' => (string) (kay_config()['mail_from'] ?? 'contact@kaydiving.com'),
    ];
}

/** "1.68 m · 60 kg · US 9 · traje M · BCD M · aletas M", for the day sheet. */
function kay_gear_line(array $g): string
{
    $parts = [];
    if ($g['height_cm'] !== null) {
        $parts[] = number_format((int) $g['height_cm'] / 100, 2) . ' m';
    }
    if ($g['weight_kg'] !== null) {
        $parts[] = $g['weight_kg'] . ' kg';
    }
    if ($g['shoe'] !== '') {
        $parts[] = $g['shoe'];
    }
    foreach (['wetsuit' => kay_t('combi'), 'bcd' => 'BCD', 'fins' => kay_t('palmes')] as $col => $label) {
        if ($g[$col] !== '') {
            $parts[] = $label . ' ' . ($g[$col] === '?' ? kay_t('à voir') : $g[$col]);
        }
    }
    return implode(' · ', $parts);
}
