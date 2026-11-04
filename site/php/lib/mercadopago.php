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
 * The payment a notification is about, whatever shape it arrives in.
 *
 * Webhooks v2 put the id in the JSON body as data.id and repeat it in the
 * query string under that same name — which PHP cannot hand back under that
 * name: it still rewrites dots in parameter NAMES to underscores, a
 * register_globals habit from the 1990s, so ?data.id=123 arrives as
 * $_GET['data_id'] and a lookup of $_GET['data.id'] is always empty.
 *
 * Legacy IPN, which a notification_url set on the preference rather than in
 * the panel can still receive, names it differently again:
 * ?topic=payment&id=123. Reading all three costs nothing and is the
 * difference between a booking that settles and one that sits at pending
 * while Mercado Pago retries into a 401 and then gives up.
 */
function kay_mp_notification_id(array $body): string
{
    foreach ([$_GET['data.id'] ?? null, $_GET['data_id'] ?? null,
              $body['data']['id'] ?? null, $_GET['id'] ?? null, $body['id'] ?? null] as $candidate) {
        if (is_scalar($candidate) && (string) $candidate !== '') {
            return (string) $candidate;
        }
    }
    return '';
}

/** 'payment', 'merchant_order', … under either naming. */
function kay_mp_notification_type(array $body): string
{
    foreach ([$body['type'] ?? null, $_GET['type'] ?? null,
              $body['topic'] ?? null, $_GET['topic'] ?? null] as $candidate) {
        if (is_string($candidate) && $candidate !== '') {
            return $candidate;
        }
    }
    return '';
}

/**
 * Anyone can POST to the webhook, so the signature is checked before the
 * payload is believed — otherwise a stranger could mark any booking paid.
 *
 * x-signature: "ts=<unix>,v1=<hmac>" over "id:<data.id>;request-id:<rid>;ts:<ts>;"
 *
 * $reason comes back saying which test failed, for the record kept in
 * webhook_log: a 401 that says "unsigned" and a 401 that says "wrong secret"
 * send whoever is looking to two completely different places.
 */
function kay_mp_signature_valid(string $dataId, ?string &$reason = null): bool
{
    $signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
    $requestId = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
    $reason    = null;
    if ($signature === '') {
        $reason = 'no x-signature header: not a webhook v2 notification';
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
        $reason = 'x-signature carries neither ts nor v1';
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
        $reason = sprintf('signed %ds away from this server\'s clock', time() - $ts);
        return false;
    }

    // Mercado Pago builds the manifest out of the fields it actually sent,
    // and its own documentation says to leave out any part that is absent.
    // Every real notification so far has carried x-request-id — but one
    // without it is signed over 'id:…;ts:…;', and refusing that would strand
    // a paid booking at pending for ever. Both forms are checked against the
    // same secret, so accepting either weakens nothing.
    $manifests = [sprintf('id:%s;request-id:%s;ts:%s;', $dataId, $requestId, $parts['ts'])];
    if ($requestId === '') {
        $manifests[] = sprintf('id:%s;ts:%s;', $dataId, $parts['ts']);
    }

    $secret = (string) kay_config()['mp_webhook_secret'];
    foreach ($manifests as $manifest) {
        if (hash_equals(hash_hmac('sha256', $manifest, $secret), $parts['v1'])) {
            return true;
        }
    }

    $reason = 'v1 does not match the signing secret in kay-config.php';
    return false;
}

/**
 * Every call Mercado Pago makes, refused ones included, so the admin can
 * tell "it never called" from "it called and we turned it away" — the two
 * halves of a booking that stays pending after the card went through.
 *
 * The endpoint is public, so the table is capped: a stranger posting at it
 * can churn the log but cannot grow the database. The pruning runs on one
 * insert in fifty, which keeps it out of the path of a real notification.
 */
function kay_mp_log_call(string $paymentId, ?string $bookingId, string $outcome, string $detail = ''): void
{
    try {
        $db = kay_db();
        $db->prepare(
            'INSERT INTO webhook_log (payment_id, booking_id, outcome, detail, signed)
             VALUES (?,?,?,?,?)'
        )->execute([
            mb_substr($paymentId, 0, 64),
            ($bookingId ?? '') !== '' ? $bookingId : null,
            mb_substr($outcome, 0, 24),
            mb_substr($detail, 0, 190),
            ($_SERVER['HTTP_X_SIGNATURE'] ?? '') !== '' ? 1 : 0,
        ]);
        if ((int) $db->lastInsertId() % 50 === 0) {
            // A subquery on the table being deleted from needs the extra
            // derived table; MySQL refuses it written the obvious way.
            $db->exec(
                'DELETE FROM webhook_log WHERE id < (
                   SELECT keep FROM (SELECT MAX(id) - 400 AS keep FROM webhook_log) AS bound
                 )'
            );
        }
    } catch (Throwable $e) {
        error_log('kay: webhook call not logged: ' . $e->getMessage());
    }
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

/**
 * The last calls Mercado Pago made, newest first.
 *
 * @return list<array<string,mixed>>
 */
function kay_webhook_log(PDO $db, int $limit = 30): array
{
    try {
        return $db->query(
            'SELECT * FROM webhook_log ORDER BY id DESC LIMIT ' . max(1, min(200, $limit))
        )->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

/**
 * Has Mercado Pago been calling, and have the calls been getting through?
 *
 * 'calls_30d' at zero on a site that has taken a payment is the whole
 * diagnosis: the notification never arrived, so nothing was ever settled and
 * no confirmation was ever rendered, let alone sent. Refused calls separate
 * that from the other failure — it called, and we turned it away.
 */
function kay_webhook_health(PDO $db): array
{
    $health = [
        'calls_30d'        => 0,
        'from_mp_30d'      => 0,
        'unsigned_30d'     => 0,
        'wrong_secret_30d' => 0,
        'settled_30d'      => 0,
        'last_call'        => null,
        'last_settled'     => null,
    ];

    try {
        // Signed and unsigned are counted apart, and it matters: the URL is
        // public, so anyone — a scanner, a curl from a developer — can leave
        // a refused call in here. Only Mercado Pago can sign one. Counting
        // them together makes a stranger poking the endpoint look exactly
        // like Mercado Pago calling with the wrong secret, which sends
        // whoever reads this page to change a key that was never wrong.
        $counts = $db->query(
            "SELECT COUNT(*)      AS calls,
                    SUM(signed = 1) AS from_mp,
                    SUM(signed = 0) AS unsigned_calls,
                    SUM(signed = 1 AND outcome IN ('bad-signature','no-id')) AS wrong_secret,
                    SUM(outcome = 'settled') AS settled
               FROM webhook_log WHERE called_at >= NOW() - INTERVAL 30 DAY"
        )->fetch() ?: [];
        $health['calls_30d']        = (int) ($counts['calls'] ?? 0);
        $health['from_mp_30d']      = (int) ($counts['from_mp'] ?? 0);
        $health['unsigned_30d']     = (int) ($counts['unsigned_calls'] ?? 0);
        $health['wrong_secret_30d'] = (int) ($counts['wrong_secret'] ?? 0);
        $health['settled_30d']      = (int) ($counts['settled'] ?? 0);

        $health['last_call'] = $db->query('SELECT * FROM webhook_log ORDER BY id DESC LIMIT 1')->fetch() ?: null;
        $health['last_settled'] = $db->query(
            "SELECT * FROM webhook_log WHERE outcome = 'settled' ORDER BY id DESC LIMIT 1"
        )->fetch() ?: null;
    } catch (Throwable) {
        // No table yet; the page says so rather than failing.
    }

    return $health;
}
