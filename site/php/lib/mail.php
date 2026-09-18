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
 * @return bool true when the provider accepted it. Never throws: the caller
 *              is a webhook that has already taken money, and a bounced
 *              confirmation must not turn a settled payment into a retry.
 */
function kay_send_email(array $message): bool
{
    $config   = kay_config();
    $provider = (string) ($config['mail_provider'] ?? 'off');

    if ($provider === 'off' || empty($config['mail_api_key'])) {
        error_log('kay: mail not configured, would have sent: ' . $message['subject']);
        return false;
    }
    if (!isset(KAY_MAIL_ENDPOINTS[$provider])) {
        error_log("kay: unknown mail provider '$provider'");
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
        return false;
    }
    if ($status < 200 || $status >= 300) {
        error_log("kay: $provider refused the message ($status): $raw");
        return false;
    }
    return true;
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
    $key      = (string) ($config['mail_api_key'] ?? '');

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
