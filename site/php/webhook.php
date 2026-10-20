<?php
declare(strict_types=1);

require __DIR__ . '/lib/config.php';
require __DIR__ . '/lib/pricing.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/mercadopago.php';
require __DIR__ . '/lib/mail.php';
require __DIR__ . '/lib/notify.php';
// The equipment questionnaire's link, in the confirmation email.
require __DIR__ . '/lib/gear.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    kay_fail(405, 'method not allowed');
}

$raw  = file_get_contents('php://input') ?: '';
$body = json_decode($raw, true);
$body = is_array($body) ? $body : [];

// Both of these read the query string and the body, because Mercado Pago
// does not always use the same one. See kay_mp_notification_id().
$paymentId = kay_mp_notification_id($body);
$type      = kay_mp_notification_type($body);

if ($paymentId === '') {
    kay_mp_log_call('', null, 'no-id', 'topic: ' . ($type !== '' ? $type : 'none given'));
    kay_fail(401, 'bad signature');
}

$why = null;
if (!kay_mp_signature_valid($paymentId, $why)) {
    kay_mp_log_call($paymentId, null, 'bad-signature', (string) $why);
    kay_fail(401, 'bad signature');
}

// Mercado Pago announces the same purchase under several topics —
// merchant_order as well as payment. Only the payment carries the status.
if ($type !== 'payment') {
    kay_mp_log_call($paymentId, null, 'other-topic', $type !== '' ? $type : 'none given');
    kay_ok(['ok' => true, 'ignored' => $type]);
}

// Ask Mercado Pago what happened rather than trusting the notification body.
$response = kay_mp_request('GET', '/v1/payments/' . rawurlencode($paymentId));
if (!$response['ok']) {
    // A non-2xx makes Mercado Pago retry, which is what we want on a transient fault.
    // It is also what the panel's own simulator gets, and correctly so: the
    // payment id it invents does not exist, so there is nothing to look up.
    kay_mp_log_call($paymentId, null, 'lookup-failed',
        'Mercado Pago answered ' . $response['status'] . ' when asked about this payment');
    kay_fail(500, 'lookup failed');
}

$payment   = $response['data'];
$bookingId = (string) ($payment['external_reference'] ?? '');
if ($bookingId === '') {
    kay_mp_log_call($paymentId, null, 'no-reference', 'the payment carries no booking id');
    kay_ok(['ok' => true, 'ignored' => 'no external_reference']);
}

$next = match ((string) ($payment['status'] ?? '')) {
    'approved'                 => 'paid',
    'refunded', 'charged_back' => 'refunded',
    'rejected', 'cancelled'    => 'cancelled',
    default                    => null,
};
if ($next === null) {
    $said = (string) ($payment['status'] ?? 'unknown');
    kay_mp_log_call($paymentId, $bookingId, 'status-waiting', 'Mercado Pago says: ' . $said);
    kay_ok(['ok' => true, 'ignored' => $said]);
}

// See kay_settle_payment() for what may move, and why a payment is never lost.
$settled = kay_settle_payment(kay_db(), $bookingId, $next, $paymentId);

if ($settled === 0) {
    // Already handled, or no longer pending. Both are fine; 200 stops the retries.
    kay_mp_log_call($paymentId, $bookingId, 'already', 'nothing left to move to ' . $next);
    kay_ok(['ok' => true, 'alreadyHandled' => true]);
}

error_log("kay: booking $bookingId settled as $next (payment $paymentId)");
kay_mp_log_call($paymentId, $bookingId, 'settled', $next);

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
