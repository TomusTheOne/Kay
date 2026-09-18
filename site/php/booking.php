<?php
declare(strict_types=1);

require __DIR__ . '/lib/config.php';
require __DIR__ . '/lib/pricing.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/mercadopago.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    kay_fail(405, 'method not allowed');
}

$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) > 8192) {
    kay_fail(400, 'bad request');
}

try {
    $input = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    kay_fail(400, 'bad json');
}
if (!is_array($input)) {
    kay_fail(400, 'bad json');
}

if (($reason = kay_validate($input)) !== null) {
    kay_fail(422, $reason);
}

$quote = kay_quote($input);
if ($quote === null) {
    kay_fail(422, 'unknown product or option');
}

$config      = kay_config();
// Straight from the catalogue's peso price — see kay_quote().
$depositMxn  = $quote['deposit_mxn'];
$locale      = in_array($input['locale'] ?? 'en', ['en', 'es', 'fr'], true) ? $input['locale'] : 'en';
$bookingId   = sprintf(
    '%04x%04x-%04x-4%03x-%04x-%04x%04x%04x',
    random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff),
    random_int(0, 0x0fff), random_int(0, 0x3fff) | 0x8000,
    random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
);

// The row goes in FIRST. If checkout creation then fails we are left with an
// abandoned pending booking, which is harmless; the reverse — a payment with
// nothing to attach it to — is not.
$db = kay_db();
$slots = array_column(kay_catalogue()['schedules'], 'slug');
$slot  = in_array($input['slot'] ?? '', $slots, true) ? (string) $input['slot'] : '0800';

$db->prepare(
    'INSERT INTO bookings
       (id, product, dives, dive_date, divers, certification, pickup, start_slot,
        start_note, name, email, locale,
        total_usd_cents, total_mxn_cents, deposit_usd_cents, deposit_mxn_cents)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
)->execute([
    $bookingId,
    $quote['product']['slug'],
    $quote['option']['dives'],
    $input['date'],
    $quote['divers'],
    mb_substr(trim((string) ($input['cert'] ?? '')), 0, 64),
    $quote['pickup']['slug'],
    $slot,
    mb_substr(trim((string) ($input['slotNote'] ?? '')), 0, 120),
    mb_substr(trim((string) $input['name']), 0, 160),
    mb_strtolower(trim((string) $input['email'])),
    $locale,
    $quote['total_usd'] * 100,
    $quote['total_mxn'] * 100,
    $quote['deposit_usd'] * 100,
    $depositMxn * 100,
]);

$site = rtrim((string) $config['site_url'], '/');

$response = kay_mp_request('POST', '/checkout/preferences', [
    'items' => [[
        'id'          => $quote['product']['slug'],
        'title'       => sprintf('Kay Diving — %s × %d', $quote['product']['slug'], $quote['divers']),
        'description' => sprintf('Deposit %d%% · balance on the day', (int) round(kay_deposit_rate() * 100)),
        'quantity'    => 1,
        'unit_price'  => $depositMxn,
        'currency_id' => 'MXN',
    ]],
    'payer' => [
        'name'  => mb_substr(trim((string) $input['name']), 0, 160),
        'email' => mb_strtolower(trim((string) $input['email'])),
    ],
    // Our own id travels with the payment and comes back on the webhook.
    'external_reference'   => $bookingId,
    'back_urls' => [
        'success' => "$site/$locale/booking/thanks/",
        'pending' => "$site/$locale/booking/pending/",
        'failure' => "$site/$locale/booking/failed/",
    ],
    'auto_return'          => 'approved',
    'statement_descriptor' => 'KAY DIVING',
    'notification_url'     => "$site/api/webhook.php",
]);

if (!$response['ok'] || empty($response['data']['init_point'])) {
    $db->prepare('UPDATE bookings SET status = ? WHERE id = ?')
       ->execute(['cancelled', $bookingId]);
    kay_fail(502, 'checkout unavailable');
}

$db->prepare('UPDATE bookings SET preference_id = ? WHERE id = ?')
   ->execute([(string) $response['data']['id'], $bookingId]);

kay_ok([
    'checkoutUrl' => $response['data']['init_point'],
    'bookingId'   => $bookingId,
]);
