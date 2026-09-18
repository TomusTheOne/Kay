<?php
declare(strict_types=1);

/**
 * Secrets live ONE LEVEL ABOVE the web root, so Apache can never serve them
 * even if a rule is misconfigured. On OVH the FTP account looks like:
 *
 *   /home/xxx/kay-config.php   ← this file's target, NOT reachable by URL
 *   /home/xxx/www/             ← the web root
 *   /home/xxx/www/api/         ← where these scripts live
 *
 * Copy kay-config.example.php to kay-config.php beside www/ and fill it in.
 */

function kay_config(): array
{
    static $config = null;
    if ($config !== null) {
        return $config;
    }

    $path = __DIR__ . '/../../../kay-config.php';
    if (!is_file($path)) {
        // Never leak the path we looked in.
        error_log('kay: configuration file missing');
        kay_fail(503, 'not configured');
    }

    /** @var array $loaded */
    $loaded = require $path;
    $required = ['db_host', 'db_name', 'db_user', 'db_pass', 'mp_access_token', 'mp_webhook_secret'];
    foreach ($required as $key) {
        if (empty($loaded[$key])) {
            error_log("kay: configuration key missing: $key");
            kay_fail(503, 'not configured');
        }
    }

    $config = $loaded + [
        'deposit_rate' => 0.30,   // share taken up front, balance on the day
        // No exchange rate. Kay quotes in both currencies and the catalogue
        // carries both, so nothing here converts one into the other — a rate
        // sitting between his price list and the card is how a 3200-peso dive
        // becomes a 3500-peso charge.
        'site_url'     => 'https://kaydiving.com',

        // Email is deliberately NOT in $required: a missing key must stop
        // confirmations, never bookings. With no mail_api_key the sender
        // logs what it would have sent and the payment still goes through.
        'mail_provider'  => 'resend',           // 'resend' | 'brevo' | 'off'
        'mail_from'      => 'contact@kaydiving.com',
        'mail_from_name' => 'Kay Diving Tulum',
        'mail_to_shop'   => 'contact@kaydiving.com',
    ];
    return $config;
}

/** JSON error, then stop. Never echoes internals back to the caller. */
function kay_fail(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message], JSON_UNESCAPED_SLASHES);
    exit;
}

function kay_ok(array $payload): never
{
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}
