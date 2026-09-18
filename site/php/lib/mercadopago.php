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

    // Reject anything more than five minutes old, so a captured notification
    // cannot be replayed later.
    if (abs(time() - (int) $parts['ts']) > 300) {
        error_log('kay: webhook signature timestamp outside tolerance');
        return false;
    }

    $manifest = sprintf('id:%s;request-id:%s;ts:%s;', $dataId, $requestId, $parts['ts']);
    $expected = hash_hmac('sha256', $manifest, kay_config()['mp_webhook_secret']);

    return hash_equals($expected, $parts['v1']);
}
