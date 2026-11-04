<?php
declare(strict_types=1);

/**
 * Transactional email over HTTP, in the same shape as the Mercado Pago
 * client: one cURL call, no Composer on a shared host.
 *
 * PHP's own mail() is deliberately not used here. It hands the message to
 * the web server, not to the mailbox behind contact@kaydiving.com, so
 * nothing signs it for the domain: DKIM is absent, SPF does not align, and
 * Gmail files a booking confirmation as spam more often than not. A sender
 * that signs for kaydiving.com fixes that — and, just as importantly, can
 * tell us afterwards whether the diver actually received it. When someone
 * writes "I never got anything", a log is the difference between an answer
 * and a shrug.
 *
 * Two providers are supported because the choice is a trade, not a winner:
 *
 *   resend  100/day and 3,000/month free, and nothing added to the message.
 *           Its return path lives on a send.<domain> subdomain, so the MX
 *           records of the mailbox already hosted at OVH stay untouched.
 *   brevo   300/day free and a fuller dashboard, but every free message
 *           carries a "Sent with Brevo" line at the bottom.
 *
 * Switching is one line in kay-config.php. 'off' disables sending, which is
 * what the test runner and a half-configured host use.
 */

const KAY_MAIL_ENDPOINTS = [
    'brevo'  => 'https://api.brevo.com/v3/smtp/email',
    'resend' => 'https://api.resend.com/emails',
];

/**
 * @param array{to:string,toName?:string,subject:string,html:string,text:string,replyTo?:string} $message
 * @param array{booking?:?string,kind?:string} $context  which booking this
 *        message belongs to and what it is — 'diver', 'shop', 'gear', 'test'.
 *        Only used for the record kept in mail_log; sending ignores it.
 * @return bool true when the provider accepted it. Never throws: the caller
 *              is a webhook that has already taken money, and a bounced
 *              confirmation must not turn a settled payment into a retry.
 */
function kay_send_email(array $message, array $context = []): bool
{
    $config   = kay_config();
    $provider = (string) ($config['mail_provider'] ?? 'off');

    // Every outcome below is written to mail_log before it is returned. The
    // whole reason this file exists rather than mail() is to be able to
    // answer "I never got anything", and an answer nobody can read — one
    // line in the hosting's error log — is not an answer.
    $record = static function (bool $ok, int $status, string $ref, string $error)
        use ($message, $context, $provider): void {
        kay_mail_log_write([
            'booking'   => $context['booking'] ?? null,
            'kind'      => (string) ($context['kind'] ?? ''),
            'recipient' => (string) ($message['to'] ?? ''),
            'subject'   => (string) ($message['subject'] ?? ''),
            'provider'  => $provider,
            'ok'        => $ok,
            'status'    => $status,
            'ref'       => $ref,
            'error'     => $error,
        ]);
    };

    if ($provider === 'off' || empty($config['mail_api_key'])) {
        error_log('kay: mail not configured, would have sent: ' . $message['subject']);
        $record(false, 0, '', $provider === 'off'
            ? "mail_provider is 'off' in kay-config.php"
            : 'mail_api_key is missing from kay-config.php');
        return false;
    }
    if (!isset(KAY_MAIL_ENDPOINTS[$provider])) {
        error_log("kay: unknown mail provider '$provider'");
        $record(false, 0, '', "unknown mail provider '$provider' in kay-config.php");
        return false;
    }

    [$headers, $body] = kay_mail_payload($provider, $message, $config);

    $ch = curl_init(KAY_MAIL_ENDPOINTS[$provider]);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_POSTFIELDS     => json_encode($body, JSON_UNESCAPED_UNICODE),
        // Short: the diver is not waiting on this, but Mercado Pago is
        // waiting on our 200 and gives up after a while.
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $raw    = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        error_log("kay: $provider transport failure: $err");
        $record(false, 0, '', $err !== '' ? $err : 'the request never reached the provider');
        return false;
    }
    if ($status < 200 || $status >= 300) {
        error_log("kay: $provider refused the message ($status): $raw");
        $record(false, $status, '', kay_mail_reason((string) $raw));
        return false;
    }
    $record(true, $status, kay_mail_ref((string) $raw), '');
    return true;
}

/**
 * The provider's own id for an accepted message, so a send can be followed up
 * in their dashboard — which is where a delivery, a bounce or a spam
 * complaint is recorded. Resend answers {"id":"..."}, Brevo {"messageId":...}.
 */
function kay_mail_ref(string $raw): string
{
    $data = json_decode($raw, true);
    return is_array($data)
        ? trim((string) ($data['id'] ?? $data['messageId'] ?? ''))
        : '';
}

/**
 * Why the provider said no, in the few words it used. Both providers answer
 * a refusal as JSON with a message in it; anything else is kept raw, because
 * a reason nobody expected is still better than "it failed".
 */
function kay_mail_reason(string $raw): string
{
    $data = json_decode($raw, true);
    if (is_array($data)) {
        $said = $data['message'] ?? $data['error'] ?? $data['name'] ?? null;
        if (is_string($said) && trim($said) !== '') {
            return trim($said);
        }
    }
    return trim($raw);
}

/**
 * One row per attempt, kept in the database rather than the error log so
 * that whoever runs the shop can see it.
 *
 * Best effort, deliberately: send-live.php never opens a database, the table
 * only exists once the admin has applied the migration, and a confirmation
 * that really was sent must never be reported as failed because a note about
 * it could not be written. Every caller that has a database has already used
 * it — the webhook settled a payment, the admin loaded a page — so the
 * connection here is one that is already open.
 */
function kay_mail_log_write(array $row): void
{
    if (!function_exists('kay_db')) {
        return;
    }
    try {
        kay_db()->prepare(
            'INSERT INTO mail_log
               (booking_id, kind, recipient, subject, provider, ok, http_status, provider_ref, error)
             VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute([
            ($row['booking'] ?? '') !== '' ? (string) $row['booking'] : null,
            mb_substr((string) $row['kind'], 0, 16),
            mb_substr((string) $row['recipient'], 0, 190),
            mb_substr((string) $row['subject'], 0, 190),
            mb_substr((string) $row['provider'], 0, 16),
            $row['ok'] ? 1 : 0,
            (int) $row['status'],
            mb_substr((string) $row['ref'], 0, 120),
            mb_substr((string) $row['error'], 0, 250),
        ]);
    } catch (Throwable $e) {
        error_log('kay: the send itself was fine, the record of it was not: ' . $e->getMessage());
    }
}

/**
 * The request each provider expects. Separated from the sending so the field
 * names can be asserted in the test runner: a typo here would otherwise only
 * show up as a 422 in the error log, after a real diver paid.
 *
 * @return array{0:string[],1:array} headers, body
 */
function kay_mail_payload(string $provider, array $message, array $config): array
{
    $from     = (string) ($config['mail_from'] ?? 'contact@kaydiving.com');
    $fromName = (string) ($config['mail_from_name'] ?? 'Kay Diving Tulum');
    $replyTo  = (string) ($message['replyTo'] ?? $from);
    $toName   = (string) ($message['toName'] ?? '');
    // Trimmed: a key pasted out of a provider's dashboard often arrives with
    // a trailing space or a newline attached, and 'Bearer re_xxx ' is refused
    // with the same "API key is invalid" as a key that is genuinely wrong —
    // which sends whoever is looking off to make a second key that will fail
    // exactly as the first one did.
    $key      = trim((string) ($config['mail_api_key'] ?? ''));

    if ($provider === 'brevo') {
        return [
            ['api-key: ' . $key, 'Content-Type: application/json', 'Accept: application/json'],
            [
                'sender'      => ['name' => $fromName, 'email' => $from],
                'to'          => [array_filter(['email' => $message['to'], 'name' => $toName])],
                'replyTo'     => ['email' => $replyTo],
                'subject'     => $message['subject'],
                'htmlContent' => $message['html'],
                'textContent' => $message['text'],
            ],
        ];
    }

    return [
        ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
        [
            // Resend wants the sender as one RFC 5322 string, not a pair.
            'from'     => sprintf('%s <%s>', $fromName, $from),
            'to'       => [$message['to']],
            'reply_to' => $replyTo,
            'subject'  => $message['subject'],
            'html'     => $message['html'],
            'text'     => $message['text'],
        ],
    ];
}

/* ------------------------------------------------------------- the record --
   Read back by the admin's Système page, and by every booking page, so a
   diver who says nothing arrived gets an answer rather than a shrug.       */

/**
 * The last attempts, newest first — all of them, or only one booking's.
 *
 * Returns nothing rather than failing if the table is not there yet: a
 * deploy can land minutes before the admin applies the migration, and a
 * missing log must never be what takes the admin space down.
 *
 * @return list<array<string,mixed>>
 */
function kay_mail_log(PDO $db, int $limit = 40, ?string $booking = null): array
{
    $sql = 'SELECT * FROM mail_log';
    $args = [];
    if ($booking !== null) {
        $sql .= ' WHERE booking_id = ?';
        $args[] = $booking;
    }
    $sql .= ' ORDER BY id DESC LIMIT ' . max(1, min(200, $limit));

    try {
        $q = $db->prepare($sql);
        $q->execute($args);
        return $q->fetchAll();
    } catch (Throwable) {
        return [];
    }
}

/**
 * Whether email is configured at all, and how the last month has gone.
 *
 * 'ready' answers the only question that matters before a diver pays: would
 * a confirmation leave this server right now? A provider set to 'off', or a
 * missing API key, means no — and that is a setting in kay-config.php, not
 * something the admin can fix on screen, so the page says where to go.
 */
function kay_mail_health(PDO $db): array
{
    $config   = kay_config();
    $provider = (string) ($config['mail_provider'] ?? 'off');
    $key      = trim((string) ($config['mail_api_key'] ?? ''));
    $hasKey   = $key !== '';

    // Enough about the key to tell three things apart that look identical
    // from the outside — a key the provider rejects, a key pasted wrong, and
    // a file that was edited but never actually saved — and not one
    // character more. The mark is a truncated hash: it cannot be turned back
    // into the key, and it changes when the key does, which is the whole
    // point when someone says "I already replaced it".
    $health = [
        'provider'  => $provider,
        'has_key'   => $hasKey,
        'ready'     => $provider !== 'off' && $hasKey && isset(KAY_MAIL_ENDPOINTS[$provider]),
        'key_len'   => mb_strlen($key),
        'key_mark'  => $hasKey ? substr(hash('sha256', $key), 0, 6) : '',
        // Resend's keys all start re_. Anything else here is a wrong paste —
        // most often the masked form the dashboard shows after the fact.
        'key_odd'   => $provider === 'resend' && $hasKey && !str_starts_with($key, 're_'),
        'key_gap'   => $hasKey && preg_match('/\s/', $key) === 1,
        'from'      => (string) ($config['mail_from'] ?? ''),
        'to_shop'   => (string) ($config['mail_to_shop'] ?? ''),
        'sent_30d'  => 0,
        'failed_30d' => 0,
        'last_ok'   => null,
        'last_fail' => null,
        // The newest attempt was refused. Checked as well as the counts
        // because a provider that takes nine messages and refuses the tenth
        // is still a diver with nothing in their inbox.
        'failing'   => false,
    ];

    try {
        $since = $db->query(
            "SELECT SUM(ok = 1) AS good, SUM(ok = 0) AS bad
               FROM mail_log WHERE sent_at >= NOW() - INTERVAL 30 DAY"
        )->fetch() ?: [];
        $health['sent_30d']   = (int) ($since['good'] ?? 0);
        $health['failed_30d'] = (int) ($since['bad'] ?? 0);

        foreach ([1 => 'last_ok', 0 => 'last_fail'] as $ok => $key) {
            $q = $db->prepare('SELECT * FROM mail_log WHERE ok = ? ORDER BY id DESC LIMIT 1');
            $q->execute([$ok]);
            $health[$key] = $q->fetch() ?: null;
        }
        $health['failing'] = $health['last_fail'] !== null
            && ($health['last_ok'] === null
                || (int) $health['last_fail']['id'] > (int) $health['last_ok']['id']);
    } catch (Throwable) {
        // No table yet. The configuration above is still worth showing.
    }

    return $health;
}
