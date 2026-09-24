<?php
declare(strict_types=1);

/**
 * The equipment questionnaire's endpoint, behind /{locale}/booking/gear/.
 *
 *   GET  ?b=<booking>&t=<token>   what the form needs to draw itself
 *   POST {b, t, divers: [...]}    the answers, one entry per diver
 *
 * The token is an HMAC of the booking id (see lib/gear.php): without the
 * link from the confirmation email there is nothing to read or write. What
 * comes back is only what the form shows — never the email, never money.
 */

require __DIR__ . '/lib/config.php';
require __DIR__ . '/lib/pricing.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/migrate.php';
require __DIR__ . '/lib/i18n.php';
require __DIR__ . '/lib/bookings.php';
require __DIR__ . '/lib/crm.php';
require __DIR__ . '/lib/gear.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$db = kay_db();

// The table is created by whichever comes first after a deploy: the admin,
// or a diver opening this link. Idempotent, and locked against itself.
try {
    kay_migrate($db);
} catch (Throwable $e) {
    error_log('kay: migration from gear.php failed: ' . $e->getMessage());
    kay_fail(503, 'not ready');
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($method === 'POST') {
    $raw = file_get_contents('php://input', false, null, 0, 16384);
    $in  = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($in)) {
        kay_fail(400, 'bad json');
    }
} elseif ($method === 'GET') {
    $in = $_GET;
} else {
    kay_fail(405, 'method not allowed');
}

$booking = kay_gear_booking($db, (string) ($in['b'] ?? ''), (string) ($in['t'] ?? ''));
if ($booking === null) {
    kay_fail(404, 'not found');
}
if (!in_array($booking['status'], KAY_GEAR_STATUSES, true)) {
    kay_fail(410, 'closed');
}

if ($method === 'POST') {
    $result = kay_gear_save($db, $booking, is_array($in['divers'] ?? null) ? $in['divers'] : []);
    if (!$result['ok']) {
        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $result['error'], 'diver' => $result['diver'] ?? null]);
        exit;
    }
    kay_ok(['ok' => true]);
}

$answers = [];
foreach (kay_gear_for($db, (string) $booking['id']) as $n => $g) {
    $answers[] = [
        'diver' => $n, 'name' => $g['name'],
        'heightCm' => $g['height_cm'] !== null ? (int) $g['height_cm'] : null,
        'weightKg' => $g['weight_kg'] !== null ? (int) $g['weight_kg'] : null,
        'shoe' => $g['shoe'], 'wetsuit' => $g['wetsuit'], 'bcd' => $g['bcd'], 'fins' => $g['fins'],
    ];
}
kay_ok([
    'product' => $booking['product'],
    'kind'    => kay_gear_kind($booking),
    'date'    => $booking['dive_date'],
    'divers'  => (int) $booking['divers'],
    'name'    => $booking['name'],
    'answers' => $answers,
]);
