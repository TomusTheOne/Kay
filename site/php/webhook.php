<?php
declare(strict_types=1);

require __DIR__ . '/lib/config.php';
require __DIR__ . '/lib/pricing.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/mercadopago.php';
require __DIR__ . '/lib/mail.php';
require __DIR__ . '/lib/notify.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    kay_fail(405, 'method not allowed');
}

$raw  = file_get_contents('php://input') ?: '';
$body = json_decode($raw, true) ?? [];

$paymentId = (string) ($_GET['data.id'] ?? $body['data']['id'] ?? '');
if ($paymentId === '' || !kay_mp_signature_valid($paymentId)) {
    kay_fail(401, 'bad signature');
}

$type = (string) ($body['type'] ?? $_GET['type'] ?? '');
if ($type !== 'payment') {
    kay_ok(['ok' => true, 'ignored' => $type]);
}

// Ask Mercado Pago what happened rather than trusting the notification body.
$response = kay_mp_request('GET', '/v1/payments/' . rawurlencode($paymentId));
if (!$response['ok']) {
    // A non-2xx makes Mercado Pago retry, which is what we want on a transient fault.
    kay_fail(500, 'lookup failed');
}

$payment   = $response['data'];
$bookingId = (string) ($payment['external_reference'] ?? '');
if ($bookingId === '') {
    kay_ok(['ok' => true, 'ignored' => 'no external_reference']);
}

$next = match ((string) ($payment['status'] ?? '')) {
    'approved'                 => 'paid',
    'refunded', 'charged_back' => 'refunded',
    'rejected', 'cancelled'    => 'cancelled',
    default                    => null,
};
if ($next === null) {
    kay_ok(['ok' => true, 'ignored' => $payment['status'] ?? 'unknown']);
}

// Idempotent: only a pending booking moves. Mercado Pago retries, and a replay
// of an old notification must not resurrect a refunded booking.
$statement = kay_db()->prepare(
    "UPDATE bookings
        SET status = ?, payment_id = ?, paid_at = IF(? = 'paid', NOW(), NULL)
      WHERE id = ? AND status = 'pending'"
);
$statement->execute([$next, $paymentId, $next, $bookingId]);

if ($statement->rowCount() === 0) {
    // Already handled, or no longer pending. Both are fine; 200 stops the retries.
    kay_ok(['ok' => true, 'alreadyHandled' => true]);
}

error_log("kay: booking $bookingId settled as $next (payment $paymentId)");

// Only a cleared deposit earns a confirmation. A refund or a rejection is
// Kay's conversation to have, not an automatic email.
if ($next === 'paid') {
    $row = kay_db()->prepare('SELECT * FROM bookings WHERE id = ?');
    $row->execute([$bookingId]);
    $booking = $row->fetch();
    if (is_array($booking)) {
        kay_notify_booking($booking);
    }
}

kay_ok(['ok' => true, 'status' => $next]);
