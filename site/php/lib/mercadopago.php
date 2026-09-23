<?php
declare(strict_types=1);

/**
 * Mercado Pago over plain cURL. The official SDK would mean Composer on a
 * shared host; two REST calls do not justify that.
 */

const KAY_MP_API = 'https://api.mercadopago.com';

function kay_mp_request(string $method, string $path, ?array $body = null): array
{
    $ch = curl_init(KAY_MP_API . $path);
    $headers = [
        'Authorization: Bearer ' . kay_config()['mp_access_token'],
        'Content-Type: application/json',
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }

    $raw    = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        error_log("kay: mercadopago transport failure: $err");
        return ['ok' => false, 'status' => 0, 'data' => []];
    }

    $data = json_decode($raw, true) ?? [];
    if ($status < 200 || $status >= 300) {
        error_log("kay: mercadopago $method $path returned $status: $raw");
    }
    return ['ok' => $status >= 200 && $status < 300, 'status' => $status, 'data' => $data];
}

/**
 * Anyone can POST to the webhook, so the signature is checked before the
 * payload is believed — otherwise a stranger could mark any booking paid.
 *
 * x-signature: "ts=<unix>,v1=<hmac>" over "id:<data.id>;request-id:<rid>;ts:<ts>;"
 */
function kay_mp_signature_valid(string $dataId): bool
{
    $signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
    $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
    if ($signature === '') {
        return false;
    }

    $parts = [];
    foreach (explode(',', $signature) as $chunk) {
        $pair = array_map('trim', explode('=', $chunk, 2));
        if (count($pair) === 2) {
            $parts[$pair[0]] = $pair[1];
        }
    }
    if (empty($parts['ts']) || empty($parts['v1'])) {
        return false;
    }

    // Mercado Pago sends this in MILLISECONDS — its own documentation shows
    // ts:1742505638683, thirteen digits. Compared against time(), which is
    // seconds, every real notification looked fifty-five thousand years old
    // and was refused: the diver would pay, the booking would sit at pending
    // for ever, and Mercado Pago would retry into a 401 until it gave up.
    // Seconds are accepted too, in case the format ever changes back.
    $ts = (int) $parts['ts'];
    if ($ts > 1e12) {
        $ts = intdiv($ts, 1000);
    }

    // Reject anything more than five minutes old, so a captured notification
    // cannot be replayed later.
    if (abs(time() - $ts) > 300) {
        error_log('kay: webhook signature timestamp outside tolerance');
        return false;
    }

    $manifest = sprintf('id:%s;request-id:%s;ts:%s;', $dataId, $requestId, $parts['ts']);
    $expected = hash_hmac('sha256', $manifest, kay_config()['mp_webhook_secret']);

    return hash_equals($expected, $parts['v1']);
}

/**
 * Applies what Mercado Pago says happened to a payment to its booking, and
 * returns the number of rows that moved: 1, or 0 when there was nothing to do.
 *
 * Idempotent: Mercado Pago retries, and a replay of an old notification must
 * not resurrect a refunded booking or un-pay a paid one.
 *
 * A payment, though, is never dropped. 'paid' also lands on a booking that
 * left 'pending' without ever being paid: a card refused and then retried on
 * the same checkout (the refusal already moved the row to 'cancelled'), an
 * OXXO cash payment approved days later, or a booking Kay confirmed or
 * cancelled by hand in the admin meanwhile. Money that has been taken must be
 * on the booking, where the admin shows it; if the booking was cancelled, Kay
 * sees a paid booking and refunds it. `paid_at IS NULL` is what keeps this
 * idempotent — a booking is paid once, and a retry finds nothing to move.
 * 'refunded' is not in the list, and nothing but a pending row is ever moved
 * to cancelled or refunded.
 *
 * 'confirmed' only exists once the admin has migrated the schema; before
 * that it matches no row, which is exactly right.
 */
function kay_settle_payment(PDO $db, string $bookingId, string $next, string $paymentId): int
{
    if ($next === 'paid') {
        $s = $db->prepare(
            "UPDATE bookings
                SET status = 'paid', payment_id = ?, paid_at = NOW()
              WHERE id = ? AND paid_at IS NULL AND status IN ('pending','confirmed','cancelled')"
        );
        $s->execute([$paymentId, $bookingId]);
        return $s->rowCount();
    }

    $s = $db->prepare(
        "UPDATE bookings
            SET status = ?, payment_id = ?, paid_at = NULL
          WHERE id = ? AND status = 'pending'"
    );
    $s->execute([$next, $paymentId, $bookingId]);
    return $s->rowCount();
}
