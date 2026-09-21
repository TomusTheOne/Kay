<?php
declare(strict_types=1);

/**
 * Sends one real confirmation email, to an address you name, using the live
 * kay-config.php on the host.
 *
 * Why this exists: everything about these emails is covered by run.php except
 * the one thing that only a live key can prove — that the provider accepts the
 * request and the message actually arrives. Otherwise the first proof would be
 * a real diver paying a real deposit and then telling Kay nothing came, which
 * is the worst possible place to discover a wrong API key.
 *
 *   ssh <ftp-user>@<cluster>.hosting.ovh.net
 *   php www/api/tests/send-live.php you@example.com
 *   php www/api/tests/send-live.php you@example.com fr
 *
 * The booking it renders is invented and never touches the database.
 */

// CLI only. This file sits under a directory the .htaccess already answers 404
// for, but a rule is a rule and this is a send button: belt and braces, so no
// request over HTTP can ever reach it however the server is reconfigured.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$to     = $argv[1] ?? '';
$wanted = $argv[2] ?? 'en';
$locale = in_array($wanted, ['en', 'es', 'fr'], true) ? $wanted : 'en';

if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "usage: php send-live.php <email> [en|es|fr]\n");
    exit(1);
}

require __DIR__ . '/../lib/config.php';
require __DIR__ . '/../lib/pricing.php';
require __DIR__ . '/../lib/mail.php';
require __DIR__ . '/../lib/notify.php';

$config = kay_config();
printf("provider : %s\n", $config['mail_provider'] ?? 'off');
printf("from     : %s <%s>\n", $config['mail_from_name'], $config['mail_from']);
printf("key      : %s\n", empty($config['mail_api_key'])
    ? 'MISSING — nothing will be sent'
    : substr((string) $config['mail_api_key'], 0, 6) . '…');

// A plausible booking, built from the real catalogue so the figures and the
// product name are the ones a diver would actually be sent.
$quote = kay_quote([
    'product' => 'cenote-diving', 'option' => 2, 'divers' => 2,
    'pickup'  => 'tulum-town',    'date'   => date('Y-m-d', strtotime('+10 days')),
    'name'    => 'Test', 'email' => $to,
]);
if ($quote === null) {
    fwrite(STDERR, "could not build a quote from the catalogue\n");
    exit(1);
}

$row = [
    'id'            => 'test-' . bin2hex(random_bytes(4)),
    'product'       => $quote['product']['slug'],
    'dives'         => $quote['option']['dives'],
    'dive_date'     => date('Y-m-d', strtotime('+10 days')),
    'divers'        => $quote['divers'],
    'certification' => 'Open Water',
    'pickup'        => $quote['pickup']['slug'],
    'start_slot'    => '0800',
    'start_note'    => '',
    'name'          => 'Test Diver',
    'email'         => $to,
    'locale'        => $locale,
    'total_usd_cents'   => $quote['total_usd']   * 100,
    'total_mxn_cents'   => $quote['total_mxn']   * 100,
    'deposit_usd_cents' => $quote['deposit_usd'] * 100,
    'deposit_mxn_cents' => $quote['deposit_mxn'] * 100,
];

$mail = kay_booking_emails($row);
printf("\nsubject  : %s\n", $mail['diver']['subject']);
printf("to       : %s\n\n", $to);

// Only the diver's copy. The shop copy would land in Kay's inbox looking like
// a real booking, and a day sheet nobody can dive is worse than no test.
$ok = kay_send_email($mail['diver']);

if ($ok) {
    echo "ACCEPTED by the provider. Check the inbox, and the spam folder:\n";
    echo "  - arrived, inbox  -> DNS and key are both right\n";
    echo "  - arrived, spam   -> sending works, authentication needs looking at\n";
    echo "  - never arrives   -> read the provider's own log, it will say why\n";
    exit(0);
}

echo "REFUSED. The reason is in the host's error log, one line starting 'kay:'.\n";
echo "Most likely: the key is missing from kay-config.php, or the domain is\n";
echo "not verified yet at the provider.\n";
exit(1);
