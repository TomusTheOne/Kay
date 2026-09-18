<?php
declare(strict_types=1);

/**
 * Covers the things that can lose money, let someone dive free, or leave a
 * paying diver with nothing in their inbox: server-side pricing, webhook
 * idempotency, and what the confirmation email actually says. No framework —
 * the whole backend is a handful of files, a test runner would outweigh it.
 *
 *   php php/tests/run.php
 */

$passed = 0;
$failed = 0;

function check(string $what, $actual, $expected): void
{
    global $passed, $failed;
    if ($actual === $expected) {
        $passed++;
        echo "  ok   $what\n";
    } else {
        $failed++;
        printf("  FAIL %s — expected %s, got %s\n", $what,
            var_export($expected, true), var_export($actual, true));
    }
}

require __DIR__ . '/../lib/config.php';
require __DIR__ . '/../lib/pricing.php';
require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/mercadopago.php';
require __DIR__ . '/../lib/mail.php';
require __DIR__ . '/../lib/notify.php';

echo "\nPricing is recomputed from the menu, never taken from the client\n";

$base = ['product' => 'cenote-diving', 'option' => 3, 'date' => '2099-12-01',
         'cert' => 'Advanced', 'divers' => 3, 'pickup' => 'meeting-point',
         'name' => 'T', 'email' => 't@e.com', 'locale' => 'en'];

$q = kay_quote($base);
check('cenote diving, 3 dives, 3 divers', $q['total_usd'], 510);
check('deposit is 30%',                   $q['deposit_usd'], 153);

echo "\nPickup is charged per booking, not per diver\n";
check('meeting point is free',      kay_quote(['pickup' => 'meeting-point'] + $base)['total_usd'], 510);
check('town pickup adds 20 once',   kay_quote(['pickup' => 'tulum-town'] + $base)['total_usd'], 530);
check('return-only adds 10 once',   kay_quote(['pickup' => 'outside-town'] + $base)['total_usd'], 520);
check('pickup does not scale with divers',
      kay_quote(['pickup' => 'tulum-town', 'divers' => 6] + $base)['total_usd'], 170 * 6 + 20);
check('an unknown pickup is refused', kay_quote(['pickup' => 'helicopter'] + $base), null);
check('a missing pickup is refused',  kay_quote(['product' => 'snorkel', 'option' => 0,
                                                 'divers' => 1, 'date' => '2099-12-01',
                                                 'name' => 'T', 'email' => 't@e.com']), null);

echo "\nMenu prices, with the free meeting point\n";

// Every price on the menu, exactly as printed.
$menu = [
    ['discover-scuba', 1, 100], ['discover-scuba', 2, 140],
    ['reef-cenote',    2, 140],
    ['cenote-diving',  2, 150], ['cenote-diving',  3, 170],
    ['advanced-ow',    5, 460],
    ['open-water',     5, 450],
    ['snorkel',        0,  80],
];
foreach ($menu as [$slug, $dives, $price]) {
    $r = kay_quote(['product' => $slug, 'option' => $dives, 'divers' => 1] + $base);
    check("menu price $slug ($dives)", $r['total_usd'], $price);
}

// A hostile payload carrying its own total changes nothing.
$tampered = kay_quote($base + ['total_usd' => 1, 'price' => 1, 'deposit_usd' => 0]);
check('a client-supplied total is ignored', $tampered['total_usd'], 510);

// Unknown product, or a size that product is not sold in, is refused.
check('unknown product refused',  kay_quote(['product' => 'free-dive', 'option' => 2] + $base), null);
check('unsold size refused',      kay_quote(['product' => 'reef-cenote', 'option' => 1] + $base), null);
check('snorkel with dives refused', kay_quote(['product' => 'snorkel', 'option' => 4] + $base), null);

// Diver count is clamped, so 0 or 9999 cannot distort the charge.
check('divers clamp low',  kay_quote(['divers' => 0] + $base)['total_usd'],
                           kay_quote(['divers' => 1] + $base)['total_usd']);
check('divers clamp high', kay_quote(['divers' => 9999] + $base)['total_usd'],
                           kay_quote(['divers' => 8] + $base)['total_usd']);

echo "\nDepths match what Kay confirmed\n";
check('discover scuba is 7 m, not 9',  kay_find_product('discover-scuba')['maxDepthM'], 7);
check('cenote diving reaches 38 m',    kay_find_product('cenote-diving')['maxDepthM'], 38);
check('angelita is the deep one',      kay_catalogue()['maxDepthM'], 38);

echo "\nValidation refuses a past date and a malformed email\n";
$ok = ['product' => 'reef-cenote', 'option' => 2, 'date' => '2099-01-01', 'cert' => 'OW',
       'divers' => 2, 'pickup' => 'meeting-point', 'name' => 'Ana', 'email' => 'a@b.co', 'locale' => 'en'];
check('a good payload passes', kay_validate($ok), null);
check('malformed email',       kay_validate(['email' => 'nope'] + $ok), 'email');
check('empty name',            kay_validate(['name' => ''] + $ok), 'name');
check('nonsense date',         kay_validate(['date' => '2099-13-45'] + $ok), 'date');
check('past date',             kay_validate(['date' => '2000-01-01'] + $ok), 'date-past');

echo "\nA booking settles exactly once, however often the webhook fires\n";
$db = kay_db();
$id = '11111111-2222-4333-8444-555555555555';
$db->prepare('DELETE FROM bookings WHERE id = ?')->execute([$id]);
$db->prepare(
    'INSERT INTO bookings (id, product, dives, dive_date, divers, pickup, start_slot,
                           name, email, total_usd_cents, deposit_usd_cents, deposit_mxn_cents)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?)'
)->execute([$id, 'cenote-diving', 3, '2099-12-01', 3, 'tulum-town', '0830',
            'Test', 't@example.com', 53000, 15900, 278250]);

$settle = static function (string $status) use ($db, $id): int {
    $s = $db->prepare("UPDATE bookings SET status = ?, payment_id = 'PAY-1',
                         paid_at = IF(? = 'paid', NOW(), NULL)
                       WHERE id = ? AND status = 'pending'");
    $s->execute([$status, $status, $id]);
    return $s->rowCount();
};

check('first notification settles it', $settle('paid'), 1);
check('a retry is a no-op',            $settle('paid'), 0);
check('and stays a no-op',             $settle('paid'), 0);
check('a replayed rejection cannot un-pay it', $settle('cancelled'), 0);

$row = $db->query("SELECT status, payment_id FROM bookings WHERE id = '$id'")->fetch();
check('status is paid',       $row['status'], 'paid');
check('payment id recorded',  $row['payment_id'], 'PAY-1');
$db->prepare('DELETE FROM bookings WHERE id = ?')->execute([$id]);

echo "\nWebhook signatures reject anything not signed with our secret\n";
$sign = static function (string $id, string $rid, string $ts, string $key = 'test-secret'): string {
    return hash_hmac('sha256', "id:$id;request-id:$rid;ts:$ts;", $key);
};
$now = (string) time();
$_SERVER['HTTP_X_REQUEST_ID'] = 'REQ-1';

$_SERVER['HTTP_X_SIGNATURE'] = "ts=$now,v1=" . $sign('PAY-1', 'REQ-1', $now);
check('a correctly signed notification passes', kay_mp_signature_valid('PAY-1'), true);
check('a different payment id fails',           kay_mp_signature_valid('PAY-2'), false);

$_SERVER['HTTP_X_SIGNATURE'] = "ts=$now,v1=" . $sign('PAY-1', 'REQ-1', $now, 'guessed');
check('a wrong secret fails',                   kay_mp_signature_valid('PAY-1'), false);

$old = (string) (time() - 3600);
$_SERVER['HTTP_X_SIGNATURE'] = "ts=$old,v1=" . $sign('PAY-1', 'REQ-1', $old);
check('an hour-old notification is refused',    kay_mp_signature_valid('PAY-1'), false);

$_SERVER['HTTP_X_SIGNATURE'] = '';
check('an unsigned notification is refused',    kay_mp_signature_valid('PAY-1'), false);

echo "\nThe confirmation says what was actually booked\n";

// A settled booking exactly as the webhook reads it back out of MySQL.
$row = [
    'id'                => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
    'product'           => 'cenote-diving',
    'dives'             => 3,
    'dive_date'         => '2099-12-01',
    'divers'            => 3,
    'certification'     => 'Advanced Open Water',
    'pickup'            => 'tulum-town',
    'start_slot'        => '0830',
    'start_note'        => '',
    'name'              => 'Ana Ruiz',
    'email'             => 'ana@example.com',
    'locale'            => 'en',
    'total_usd_cents'   => 53000,
    'deposit_usd_cents' => 15900,
    'deposit_mxn_cents' => 278250,
];
$mail  = kay_booking_emails($row);
$diver = $mail['diver'];
$shop  = $mail['shop'];

check('it goes to the diver',            $diver['to'], 'ana@example.com');
check('a reply reaches the shop',        $diver['replyTo'], 'contact@kaydiving.com');
check('the shop copy goes to the shop',  $shop['to'], 'contact@kaydiving.com');
check('Kay replies straight to the diver', $shop['replyTo'], 'ana@example.com');

// The subject carries a date a human can read, not 2099-12-01.
check('the subject names the day', str_contains($diver['subject'], 'Tuesday 1 December 2099'), true);
check('the shop subject sorts by date', str_starts_with($shop['subject'], 'PAID · 2099-12-01'), true);

// The product is named from messages/, never left as a slug.
check('it names the product, not the slug', str_contains($diver['html'], 'Cenote Diving'), true);
check('no slug leaks into the email',       str_contains($diver['html'], 'cenote-diving'), false);
check('it counts the dives',                str_contains($diver['html'], '3 dives'), true);

// Money comes from the stored row, and the three figures agree.
check('the total is shown',   str_contains($diver['html'], '$530 USD'), true);
check('the deposit is shown', str_contains($diver['html'], '$159 USD'), true);
check('the balance is total minus deposit', str_contains($diver['html'], '$371 USD'), true);

// The pickup the diver paid for is the pickup the email names.
check('the pickup is named',     str_contains($diver['html'], 'Anywhere in Tulum town'), true);
check('its surcharge is shown',  str_contains($diver['html'], '+$20'), true);
check('the departure is shown',  str_contains($diver['html'], '08:30 – 13:30'), true);
check('the reference travels',   str_contains($diver['html'], $row['id']), true);
check('the plain-text part is not empty', strlen($diver['text']) > 200, true);

// A diver who asked for another time gets told it is not confirmed yet.
$other = kay_booking_emails(['start_slot' => 'other', 'start_note' => 'around 11am'] + $row);
check('an unfixed time is flagged', str_contains($other['diver']['html'], 'Time to be confirmed'), true);
check('their own words are kept',   str_contains($other['diver']['html'], 'around 11am'), true);

// The snorkel tour has no dives to count.
$snorkel = kay_booking_emails(['product' => 'snorkel', 'dives' => 0] + $row);
check('a half day is not "0 dives"', str_contains($snorkel['diver']['html'], 'Half day'), true);
check('and it names the tour',       str_contains($snorkel['diver']['html'], 'Snorkel tour'), true);

// One diver reads as one diver.
$solo = kay_booking_emails(['divers' => 1] + $row);
check('singular for one diver', str_contains($solo['diver']['html'], '1 diver<'), true);

// Nothing a stranger typed into the booking form reaches the inbox as markup.
$hostile = kay_booking_emails([
    'name' => '<script>alert(1)</script>',
    'certification' => '"><img src=x onerror=alert(1)>',
] + $row);
check('a script tag in the name is escaped',
      str_contains($hostile['diver']['html'], '<script>'), false);
check('it survives as text',
      str_contains($hostile['diver']['html'], '&lt;script&gt;'), true);
check('and in the shop copy too',
      str_contains($hostile['shop']['html'], '<script>'), false);
// The handler name survives as visible text, which is harmless; what must
// not survive is the tag and the quote that would have opened an attribute.
check('the injected tag is escaped',
      str_contains($hostile['diver']['html'], '<img'), false);
check('the quote that would break out is escaped',
      str_contains($hostile['diver']['html'], '&quot;&gt;&lt;img'), true);

// A locale we never shipped must not cost someone their confirmation.
$odd = kay_booking_emails(['locale' => 'de'] + $row);
check('an unknown locale falls back to English',
      str_contains($odd['diver']['subject'], 'Your dive is booked'), true);

// Every locale renders, so a Spanish booking is not a fatal error.
foreach (['en', 'es', 'fr'] as $locale) {
    $one = kay_booking_emails(['locale' => $locale] + $row);
    check("$locale renders", strlen($one['diver']['html']) > 1000, true);
}

echo "\nEach provider gets the request shape it actually expects\n";

$fake = ['mail_api_key' => 'KEY', 'mail_from' => 'contact@kaydiving.com',
         'mail_from_name' => 'Kay Diving Tulum'];

[$headers, $body] = kay_mail_payload('resend', $diver, $fake);
check('resend authorises with a bearer token', $headers[0], 'Authorization: Bearer KEY');
check('resend takes the sender as one string', $body['from'], 'Kay Diving Tulum <contact@kaydiving.com>');
check('resend takes recipients as a list',     $body['to'], ['ana@example.com']);
check('resend spells it reply_to',             $body['reply_to'], 'contact@kaydiving.com');
check('resend spells the body html',           isset($body['html'], $body['text']), true);

[$headers, $body] = kay_mail_payload('brevo', $diver, $fake);
check('brevo authorises with its own header',  $headers[0], 'api-key: KEY');
check('brevo takes the sender as a pair',      $body['sender'],
      ['name' => 'Kay Diving Tulum', 'email' => 'contact@kaydiving.com']);
check('brevo names the recipient',             $body['to'][0]['name'], 'Ana Ruiz');
check('brevo spells it htmlContent',           isset($body['htmlContent'], $body['textContent']), true);

echo "\nMail is off until it is configured, and says so rather than failing\n";
check('nothing is sent while unconfigured', kay_send_email($diver), false);


printf("\n%d passed, %d failed\n\n", $passed, $failed);
exit($failed === 0 ? 0 : 1);
