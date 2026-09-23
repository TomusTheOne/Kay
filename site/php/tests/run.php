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
require __DIR__ . '/../lib/migrate.php';
require __DIR__ . '/../lib/auth.php';
require __DIR__ . '/../lib/bookings.php';
require __DIR__ . '/../lib/crm.php';
require __DIR__ . '/../lib/traffic.php';
require __DIR__ . '/../lib/view.php';

echo "\nPricing is recomputed from the menu, never taken from the client\n";

$base = ['product' => 'cenote-diving', 'option' => 3, 'date' => '2099-12-01',
         'cert' => 'Advanced', 'divers' => 3, 'pickup' => 'meeting-point',
         'name' => 'T', 'email' => 't@e.com', 'locale' => 'en'];

$q = kay_quote($base);
check('cenote diving, 3 dives, 3 divers', $q['total_usd'], 750);
check('deposit is 30%',                   $q['deposit_usd'], 225);

echo "\nPesos are Kay's own figures, never a converted dollar price\n";
// He works at 16 to the dollar everywhere but Discover Scuba's single dive,
// which he rounded to 2300. A single rate cannot reproduce that, so both
// currencies are carried and the peso one is what gets charged.
check('3 x 3 cenote dives in pesos', kay_quote($base)['total_mxn'], 12000);
check('the deposit in pesos',        kay_quote($base)['deposit_mxn'], 3600);

// The slate in the browser computes the deposit from DEPOSIT_RATE in
// content/products.ts, which reads the same key. If the rate ever moves back
// into kay-config.php the two drift, and the diver is quoted one figure while
// Mercado Pago charges another.
check('the rate comes from the catalogue, not the host config',
      kay_deposit_rate(), 0.3);
check('and the quote uses that rate, not a literal',
      kay_quote($base)['deposit_mxn'],
      (int) round(kay_quote($base)['total_mxn'] * kay_deposit_rate()));
check('products.json is where it lives',
      isset(kay_catalogue()['depositRate']), true);
$ds1 = kay_quote(['product' => 'discover-scuba', 'option' => 1, 'divers' => 1] + $base);
check('discover scuba, one dive, in dollars', $ds1['total_usd'], 140);
check('...and the 2300 pesos he actually asks', $ds1['total_mxn'], 2300);
check('which is NOT 140 x 16',                 $ds1['total_mxn'] === 140 * 16, false);

echo "\nPickup is charged per booking, not per diver\n";
check('meeting point is free',      kay_quote(['pickup' => 'meeting-point'] + $base)['total_usd'], 750);
check('town pickup adds 20 once',   kay_quote(['pickup' => 'tulum-town'] + $base)['total_usd'], 770);
check('return-only adds 10 once',   kay_quote(['pickup' => 'outside-town'] + $base)['total_usd'], 760);
check('pickup does not scale with divers',
      kay_quote(['pickup' => 'tulum-town', 'divers' => 6] + $base)['total_usd'], 250 * 6 + 20);
check('and not in pesos either',
      kay_quote(['pickup' => 'tulum-town', 'divers' => 6] + $base)['total_mxn'], 4000 * 6 + 320);
check('an unknown pickup is refused', kay_quote(['pickup' => 'helicopter'] + $base), null);
check('a missing pickup is refused',  kay_quote(['product' => 'snorkel', 'option' => 0,
                                                 'divers' => 1, 'date' => '2099-12-01',
                                                 'name' => 'T', 'email' => 't@e.com']), null);

echo "\nMenu prices, with the free meeting point\n";

// Every price on the menu, exactly as printed.
// Kay's 2026 list, both currencies, exactly as he wrote them.
$menu = [
    ['discover-scuba', 1, 140, 2300], ['discover-scuba', 2, 200, 3200],
    ['reef-cenote',    2, 200, 3200],
    ['cenote-diving',  2, 200, 3200], ['cenote-diving',  3, 250, 4000],
    ['advanced-ow',    5, 550, 8800],
    ['open-water',     5, 600, 9600],
    ['snorkel',        0, 125, 2000],
    ['bull-sharks',    2, 250, 4000],
];
foreach ($menu as [$slug, $dives, $usd, $mxn]) {
    $r = kay_quote(['product' => $slug, 'option' => $dives, 'divers' => 1] + $base);
    check("menu price $slug ($dives) in USD", $r['total_usd'], $usd);
    check("menu price $slug ($dives) in MXN", $r['total_mxn'], $mxn);
}

// A hostile payload carrying its own total changes nothing.
$tampered = kay_quote($base + ['total_usd' => 1, 'price' => 1, 'priceMxn' => 1, 'deposit_usd' => 0]);
check('a client-supplied total is ignored',      $tampered['total_usd'], 750);
check('nor can the peso price be sent from the browser', $tampered['total_mxn'], 12000);

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

echo "\nA seasonal dive cannot be booked out of season\n";
// The bull sharks are off Playa del Carmen from November to March. The window
// wraps the year end, so it is a union of months, not a range — a July date
// must be refused and a January one accepted.
$shark = ['product' => 'bull-sharks', 'option' => 2, 'cert' => 'Advanced', 'divers' => 2,
          'pickup' => 'meeting-point', 'name' => 'Ana', 'email' => 'a@b.co', 'locale' => 'en'];
check('July is refused',      kay_validate(['date' => '2099-07-15'] + $shark), 'out-of-season');
check('October is refused',   kay_validate(['date' => '2099-10-31'] + $shark), 'out-of-season');
check('November is fine',     kay_validate(['date' => '2099-11-01'] + $shark), null);
check('December is fine',     kay_validate(['date' => '2099-12-20'] + $shark), null);
check('January is fine',      kay_validate(['date' => '2099-01-10'] + $shark), null);
check('March is fine',        kay_validate(['date' => '2099-03-31'] + $shark), null);
check('April is refused',     kay_validate(['date' => '2099-04-01'] + $shark), 'out-of-season');
// Everything else is sold all year and must not pick up the restriction.
check('a cenote dive in July is fine',
      kay_validate(['date' => '2099-07-15', 'product' => 'cenote-diving', 'option' => 2] + $shark), null);

echo "\nMercado Pago's own page names the dive, never the slug\n";
// It showed "discover-scuba" to someone about to enter a card.
$item = kay_checkout_item(kay_quote($base), 'en');
check('the product is named',      str_contains($item[0], 'Cenote Diving'), true);
check('the slug does not leak',    str_contains($item[0], 'cenote-diving'), false);
check('the party size is there',   str_contains($item[0], '3 divers'), true);
$solo = kay_checkout_item(kay_quote(['divers' => 1] + $base), 'en');
check('one diver reads as one',    str_contains($solo[0], '1 diver'), true);
// Whoever booked in Spanish pays on a Spanish page.
$es = kay_checkout_item(kay_quote($base), 'es');
check('es names it in Spanish',    str_contains($es[0], 'Buceo en Cenotes'), true);
check('es counts in Spanish',      str_contains($es[0], 'buzos'), true);
$fr = kay_checkout_item(kay_quote($base), 'fr');
check('fr names it in French',     str_contains($fr[0], 'Plongée'), true);
// The deposit line carries the real rate, not a number typed twice.
check('the description states the rate',
      str_contains($item[1], (string) (int) round(kay_deposit_rate() * 100) . '%'), true);

echo "\nThe schema follows the code, and applying it twice changes nothing\n";
$db = kay_db();
kay_migrate($db);
check('the database is at the latest version', kay_schema_version($db), max(array_keys(kay_migrations())));
check('a second run has nothing to do',        kay_migrate($db), []);
$columns = array_column($db->query('SHOW COLUMNS FROM bookings')->fetchAll(), 'Type', 'Field');
check('bookings know about manual confirmation', str_contains($columns['status'], "'confirmed'"), true);
check('bookings can point at a customer',        isset($columns['customer_id']), true);
check('schema.sql still splits into one statement', count(kay_sql_statements((string) file_get_contents(__DIR__ . '/../schema.sql'))), 1);

echo "\nA booking settles exactly once, however often the webhook fires\n";
$id = '11111111-2222-4333-8444-555555555555';
$seed = static function (string $status = 'pending') use ($db, $id): void {
    $db->prepare('DELETE FROM bookings WHERE id = ?')->execute([$id]);
    $db->prepare(
        'INSERT INTO bookings (id, product, dives, dive_date, divers, pickup, start_slot,
                               name, email, total_usd_cents, total_mxn_cents,
                               deposit_usd_cents, deposit_mxn_cents, status)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([$id, 'cenote-diving', 3, '2099-12-01', 3, 'tulum-town', '0830',
                'Test', 't@example.com', 77000, 1232000, 23100, 369600, $status]);
};
// The webhook's own statement, not a copy of it: an earlier version of this
// test re-typed the SQL, and would have kept passing whatever the webhook did.
$settle = static fn(string $status, string $payment = 'PAY-1'): int => kay_settle_payment($db, $id, $status, $payment);

$seed();
check('first notification settles it', $settle('paid'), 1);
check('a retry is a no-op',            $settle('paid'), 0);
check('and stays a no-op',             $settle('paid'), 0);
check('a replayed rejection cannot un-pay it', $settle('cancelled'), 0);

$row = $db->query("SELECT status, payment_id FROM bookings WHERE id = '$id'")->fetch();
check('status is paid',       $row['status'], 'paid');
check('payment id recorded',  $row['payment_id'], 'PAY-1');

echo "\nA payment that arrives after the booking left 'pending' is never lost\n";
// A card refused, then a second card on the same checkout: the refusal moves
// the booking to cancelled first, and the approval used to find nothing.
$seed();
check('the refusal cancels it',            $settle('cancelled', 'PAY-A'), 1);
check('the retry that succeeds lands',     $settle('paid', 'PAY-B'), 1);
$row = $db->query("SELECT status, payment_id, paid_at FROM bookings WHERE id = '$id'")->fetch();
check('it is paid, with the second payment', [$row['status'], $row['payment_id']], ['paid', 'PAY-B']);
check('and has a paid date', $row['paid_at'] !== null, true);
// Kay confirmed it by hand while an OXXO payment was still pending.
$seed('confirmed');
check('a manual confirmation still takes the payment', $settle('paid'), 1);
// Refunded is final.
$seed('refunded');
check('a refunded booking is not paid again', $settle('paid'), 0);
// Completed means paid already, by one route or another.
$seed('completed');
check('nor is a completed one',               $settle('paid'), 0);
$db->prepare('DELETE FROM bookings WHERE id = ?')->execute([$id]);

echo "\nWebhook signatures reject anything not signed with our secret\n";
$sign = static function (string $id, string $rid, string $ts, string $key = 'test-secret'): string {
    return hash_hmac('sha256', "id:$id;request-id:$rid;ts:$ts;", $key);
};
// Mercado Pago timestamps notifications in MILLISECONDS. The earlier version
// of this test signed with seconds on both sides, so it confirmed my own
// arithmetic rather than Mercado Pago's — and passed while every real
// notification would have been refused as fifty-five thousand years old.
$nowMs  = (string) (time() * 1000);
$nowSec = (string) time();
$_SERVER['HTTP_X_REQUEST_ID'] = 'REQ-1';

$_SERVER['HTTP_X_SIGNATURE'] = "ts=$nowMs,v1=" . $sign('PAY-1', 'REQ-1', $nowMs);
check('a notification timestamped in ms passes', kay_mp_signature_valid('PAY-1'), true);

$_SERVER['HTTP_X_SIGNATURE'] = "ts=$nowSec,v1=" . $sign('PAY-1', 'REQ-1', $nowSec);
check('...and one in seconds still does',        kay_mp_signature_valid('PAY-1'), true);

$oldMs = (string) ((time() - 3600) * 1000);
$_SERVER['HTTP_X_SIGNATURE'] = "ts=$oldMs,v1=" . $sign('PAY-1', 'REQ-1', $oldMs);
check('an hour-old one in ms is still refused',  kay_mp_signature_valid('PAY-1'), false);

$now = $nowMs;
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
    // The real catalogue figures: 3 cenote dives x 3 divers (4,000 MXN / $250
    // each) plus the Tulum-town pickup, deposit at 30%.
    'total_usd_cents'   => 77000,
    'total_mxn_cents'   => 1232000,
    'deposit_usd_cents' => 23100,
    'deposit_mxn_cents' => 369600,
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

// Money comes from the stored row, and the figures agree in both currencies.
check('the total is shown in pesos',   str_contains($diver['html'], '$12,320 MXN'), true);
check('and in dollars beside it',      str_contains($diver['html'], '$770 USD'), true);
// The card was debited in pesos, so that is the only figure called "paid" —
// quoting a dollar deposit nobody was charged is how a diver disputes a charge.
check('the deposit is shown as charged', str_contains($diver['html'], '$3,696 MXN'), true);
check('no dollar deposit is claimed',    str_contains($diver['html'], '$231 USD'), false);
check('the balance is total minus deposit', str_contains($diver['html'], '$8,624 MXN'), true);
check('the balance is also in dollars',     str_contains($diver['html'], '$539 USD'), true);

// Kay's day sheet carries the same two figures, so the till matches the email.
check('the shop sees what was charged', str_contains($shop['html'], '$3,696 MXN'), true);
check('and what is still owed',         str_contains($shop['html'], '$8,624 MXN'), true);

// A row written before the peso column existed must still produce an email.
$legacy = kay_booking_emails(['total_mxn_cents' => 0] + $row);
check('an old row falls back to dollars', str_contains($legacy['diver']['html'], '$770 USD'), true);

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


echo "\nThe admin lets in the right password, and nobody who keeps guessing\n";
// A TEST-NET address, so the throttle never counts anyone real.
$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
$adminEmail = 'test-admin-' . bin2hex(random_bytes(3)) . '@example.test';
$cleanAuth = static function () use ($db, $adminEmail): void {
    $db->prepare('DELETE FROM admin_login_attempts WHERE ip_hash = ? OR email = ?')->execute([kay_ip_hash(), $adminEmail]);
};
$cleanAuth();
$adminId = kay_admin_create($db, strtoupper($adminEmail), 'Test Admin', 'correct horse battery');
check('the email is stored lower-case',   $db->query("SELECT email FROM admin_users WHERE id = $adminId")->fetchColumn(), $adminEmail);
check('the password is never stored',     str_contains((string) $db->query("SELECT password_hash FROM admin_users WHERE id = $adminId")->fetchColumn(), 'horse'), false);
check('the right password signs in',      kay_login_check($db, $adminEmail, 'correct horse battery')['ok'], true);
check('whatever the case of the email',   kay_login_check($db, strtoupper($adminEmail), 'correct horse battery')['ok'], true);
check('a wrong password does not',        kay_login_check($db, $adminEmail, 'wrong')['error'] ?? null, 'invalid');
check('an unknown account says the same', kay_login_check($db, 'nobody@example.test', 'wrong')['error'] ?? null, 'invalid');
for ($i = 0; $i < 4; $i++) {
    kay_login_check($db, $adminEmail, 'wrong');
}
check('five misses lock the account for a while',
      kay_login_check($db, $adminEmail, 'correct horse battery')['error'] ?? null, 'throttled');
$cleanAuth();
check('a short password is refused', kay_password_problem('short') !== null, true);
check('the email as password is refused', kay_password_problem($adminEmail . 'x', $adminEmail . 'x') !== null, true);
check('a long one is fine',          kay_password_problem('a perfectly long one'), null);
check('the setup token needs 20 characters to exist', strlen((string) kay_setup_token()) === 0 || strlen((string) kay_setup_token()) >= 20, true);

echo "\nA session is a random cookie whose hash alone is stored\n";
$token = kay_session_open($db, $adminId);
check('the token is 32 random bytes', (bool) preg_match('/^[a-f0-9]{64}$/', $token), true);
check('the database holds only its hash',
      (int) $db->query("SELECT COUNT(*) FROM admin_sessions WHERE token_hash = '" . hash('sha256', $token) . "'")->fetchColumn(), 1);
check('nor the token itself',
      (int) $db->query("SELECT COUNT(*) FROM admin_sessions WHERE token_hash = '$token'")->fetchColumn(), 0);
$_COOKIE[KAY_ADMIN_COOKIE] = $token;
$me = kay_current_admin($db);
check('the cookie signs the admin in', $me['id'] ?? null, $adminId);
$_COOKIE[KAY_ADMIN_COOKIE] = str_repeat('a', 64);
check('a made-up cookie does not', kay_current_admin($db), null);
$_COOKIE[KAY_ADMIN_COOKIE] = "' OR 1=1 --";
check('nor does SQL in the cookie', kay_current_admin($db), null);
$db->prepare('UPDATE admin_sessions SET expires_at = NOW() - INTERVAL 1 SECOND WHERE token_hash = ?')->execute([hash('sha256', $token)]);
$_COOKIE[KAY_ADMIN_COOKIE] = $token;
check('an expired session is refused', kay_current_admin($db), null);
$other = kay_session_open($db, $adminId);
$keep  = kay_session_open($db, $adminId);
kay_admin_set_password($db, $adminId, 'another long password', hash('sha256', $keep));
$_COOKIE[KAY_ADMIN_COOKIE] = $other;
check('a new password signs out the other devices', kay_current_admin($db), null);
$_COOKIE[KAY_ADMIN_COOKIE] = $keep;
check('but not the one that changed it', kay_current_admin($db)['id'] ?? null, $adminId);

echo "\nA form is only accepted from the admin's own pages\n";
$me = kay_current_admin($db);
unset($_SERVER['HTTP_ORIGIN']);
$_SERVER['HTTP_HOST'] = 'kaydiving.com';
check('the session token is accepted',  kay_csrf_valid($me, $me['csrf']), true);
check('a wrong token is not',           kay_csrf_valid($me, str_repeat('0', 64)), false);
check('no token is not',                kay_csrf_valid($me, ''), false);
$_SERVER['HTTP_ORIGIN'] = 'https://evil.example';
check('another origin is not, even with the token', kay_csrf_valid($me, $me['csrf']), false);
$_SERVER['HTTP_ORIGIN'] = 'https://kaydiving.com';
check('our own origin is',              kay_csrf_valid($me, $me['csrf']), true);
$_SERVER['HTTP_HOST'] = 'kaydiving.com:8443';
$_SERVER['HTTP_ORIGIN'] = 'https://kaydiving.com:8443';
check('so is our own origin on another port', kay_csrf_valid($me, $me['csrf']), true);
$_SERVER['HTTP_ORIGIN'] = 'https://kaydiving.com:9999';
check('but not a different port',             kay_csrf_valid($me, $me['csrf']), false);
$_SERVER['HTTP_HOST'] = 'kaydiving.com';
unset($_SERVER['HTTP_ORIGIN'], $_COOKIE[KAY_ADMIN_COOKIE]);
check('the ?next= after login stays in the admin', kay_safe_next('https://evil.example/admin/x.php'), 'index.php');
check('and accepts an admin page',                 kay_safe_next('/admin/booking.php?id=abc'), '/admin/booking.php?id=abc');

echo "\nBookings Kay takes himself are priced from the same catalogue\n";
$testEmail = 'crm-' . bin2hex(random_bytes(3)) . '@example.test';
$made = kay_booking_create($db, [
    'item' => 'cenote-diving:3', 'date' => '2099-12-02', 'slot' => '0900', 'divers' => 2,
    'pickup' => 'tulum-town', 'cert' => 'Advanced Open Water', 'name' => 'Walk In',
    'email' => strtoupper($testEmail), 'phone' => '+1 416 555 0100', 'locale' => 'fr',
    'deposit' => 2000, 'method' => 'cash',
    // Whatever a form might add, the price is the catalogue's.
    'total_mxn' => 1, 'price' => 1,
], $adminId);
check('the booking is created', $made['ok'], true);
$manual = kay_booking_get($db, $made['id']);
check('it is confirmed from the start',   $manual['status'], 'confirmed');
check('and marked as entered by hand',    $manual['source'], 'manual');
check('priced from the catalogue',        (int) $manual['total_mxn_cents'], (4000 * 2 + 320) * 100);
check('in dollars too',                   (int) $manual['total_usd_cents'], (250 * 2 + 20) * 100);
check('with no online deposit',           (int) $manual['deposit_mxn_cents'], 0);
$money = kay_booking_money($manual);
check('the cash deposit counts as received', $money['received'], 2000);
check('and the rest is due',              $money['due'], 8320 - 2000);
check('it has a customer',                $manual['customer_id'] !== null, true);
check('a missing name is refused',        kay_booking_create($db, ['item' => 'snorkel:0', 'date' => '2099-01-01', 'pickup' => 'meeting-point', 'name' => ''], null)['ok'], false);
check('an unsold size is refused',        kay_booking_create($db, ['item' => 'snorkel:4', 'date' => '2099-01-01', 'pickup' => 'meeting-point', 'name' => 'Ana'], null)['ok'], false);
check('a deposit above the price is refused',
      kay_booking_create($db, ['item' => 'snorkel:0', 'date' => '2099-01-01', 'pickup' => 'meeting-point', 'name' => 'Ana', 'deposit' => 99999], null)['ok'], false);
// Kay knows when the sharks arrive; the form does not second-guess him.
check('Kay may book the sharks out of season',
      ($shark = kay_booking_create($db, ['item' => 'bull-sharks:2', 'date' => '2099-07-01', 'pickup' => 'meeting-point',
                                         'name' => 'Early Shark', 'email' => $testEmail], null))['ok'], true);

echo "\nThe balance is collected, and the ledger adds up\n";
check('a zero payment is refused',   kay_payment_add($db, $manual, 'balance', 0, 'cash', $adminId) !== null, true);
check('an unknown method is refused', kay_payment_add($db, $manual, 'balance', 100, 'bitcoin', $adminId) !== null, true);
check('the balance is recorded',     kay_payment_add($db, $manual, 'balance', 6320, 'card', $adminId), null);
$manual = kay_booking_get($db, $made['id']);
check('nothing is due any more',     kay_booking_money($manual)['due'], 0);
check('everything was received',     kay_booking_money($manual)['received'], 8320);
kay_payment_add($db, $manual, 'refund', 320, 'cash', $adminId);
$manual = kay_booking_get($db, $made['id']);
check('a refund is taken off what was received', kay_booking_money($manual)['received'], 8000);
check('the confirmation email says what was paid, not zero',
      (int) kay_booking_email_row($manual)['deposit_mxn_cents'], 800000);

echo "\nA booking only moves the way the rules allow\n";
check('a confirmed booking can be completed', kay_booking_act($db, $manual, 'complete', $adminId), null);
check('a stale page cannot move it again',   kay_booking_act($db, $manual, 'cancel', $adminId) !== null, true);
$manual = kay_booking_get($db, $made['id']);
check('it is completed',                     $manual['status'], 'completed');
check('a completed dive cannot be refunded', kay_booking_act($db, $manual, 'refund', $adminId) !== null, true);
check('it can be reopened',                  kay_booking_act($db, $manual, 'reopen', $adminId), null);
check('and goes back to confirmed, having never been paid online',
      kay_booking_get($db, $made['id'])['status'], 'confirmed');
$web = kay_uuid();
$db->prepare(
    "INSERT INTO bookings (id, product, dives, dive_date, divers, pickup, name, email, total_usd_cents, total_mxn_cents,
                           deposit_usd_cents, deposit_mxn_cents, status, paid_at, payment_id)
     VALUES (?, 'reef-cenote', 2, '2099-12-03', 1, 'meeting-point', 'Web Diver', ?, 20000, 320000, 6000, 96000, 'paid', NOW(), 'PAY-W')"
)->execute([$web, $testEmail]);
$webRow = kay_booking_get($db, $web);
check('an online deposit counts as received', kay_booking_money($webRow)['received'], 960);
kay_booking_act($db, $webRow, 'no_show', $adminId);
kay_booking_act($db, kay_booking_get($db, $web), 'reopen', $adminId);
check('reopening a paid booking puts it back to paid', kay_booking_get($db, $web)['status'], 'paid');
kay_booking_act($db, kay_booking_get($db, $web), 'refund', $adminId);
check('a refunded deposit no longer counts', kay_booking_money(kay_booking_get($db, $web))['received'], 0);

echo "\nEditing re-quotes only what sets the price\n";
$db->prepare("UPDATE bookings SET total_mxn_cents = 111100 WHERE id = ?")->execute([$made['id']]);
$row = kay_booking_get($db, $made['id']);
$form = ['item' => 'cenote-diving:3', 'date' => '2099-12-05', 'slot' => '0900', 'slotNote' => '', 'divers' => 2,
         'pickup' => 'tulum-town', 'cert' => 'Advanced Open Water', 'name' => 'Walk In Renamed', 'email' => $testEmail];
check('a new date and name are saved', kay_booking_edit($db, $row, $form, $adminId), null);
$row = kay_booking_get($db, $made['id']);
check('the date moved',               $row['dive_date'], '2099-12-05');
check('and the old price was kept',   (int) $row['total_mxn_cents'], 111100);
kay_booking_edit($db, $row, ['divers' => 3] + $form, $adminId);
$row = kay_booking_get($db, $made['id']);
check('one more diver re-quotes it',  (int) $row['total_mxn_cents'], (4000 * 3 + 320) * 100);
check('and the change is in the history',
      str_contains((string) kay_notes_for($db, null, $made['id'])[0]['body'], 'plongeurs 2 → 3'), true);

echo "\nEvery website booking finds its customer\n";
$lead = kay_uuid();
$db->prepare(
    "INSERT INTO bookings (id, product, dives, dive_date, divers, pickup, name, email, total_usd_cents, total_mxn_cents,
                           deposit_usd_cents, deposit_mxn_cents, status, certification, created_at)
     VALUES (?, 'snorkel', 0, '2099-12-04', 2, 'meeting-point', 'Lead Person', ?, 25000, 400000, 7500, 120000,
             'pending', 'Rescue / Divemaster', NOW() + INTERVAL 1 MINUTE)"
)->execute([$lead, $testEmail]);
kay_crm_sync($db);
$attached = $db->prepare('SELECT COUNT(DISTINCT customer_id) FROM bookings WHERE email = ?');
$attached->execute([$testEmail]);
check('all four bookings share one customer', (int) $attached->fetchColumn(), 1);
$customerId = (int) kay_booking_get($db, $lead)['customer_id'];
$customer = kay_customer_get($db, $customerId);
check('the email is the one Kay typed, lower-cased', $customer['email'], $testEmail);
check('the name typed in the admin is kept',          $customer['name'], 'Walk In');
check('the latest certification wins',                $customer['certification'], 'Rescue / Divemaster');
check('only bookings that went ahead count',          (int) $customer['bookings'], 2);
check('and only they count as value',                 (int) $customer['value_mxn_cents'], (4000 * 3 + 320) * 100 + 400000);
check('the next dive is the earliest ahead',          $customer['next_dive'], '2099-07-01');
check('a second sync changes nothing',                kay_crm_sync($db) >= 0 && (int) $db->query("SELECT COUNT(*) FROM customers WHERE email = '$testEmail'")->fetchColumn() === 1, true);
$found = kay_customers_find($db, ['q' => substr($testEmail, 0, 10), 'segment' => 'repeat']);
check('search and segment find them', $found['total'], 1);
check('the abandoned checkout is not listed: they booked since',
      in_array($lead, array_column(kay_abandoned_checkouts($db, 14, 50), 'id'), true), false);
check('tags are tidied', kay_tags_clean(' VIP, nitrox ,vip,, Groupe  Famille '), 'vip,nitrox,groupe famille');
check('a taken email is refused on another customer',
      kay_customer_update($db, $customerId + 100000, ['name' => 'X Y', 'email' => $testEmail]) !== null, true);

echo "\nFollow-ups appear until they are done\n";
$task = kay_note_add($db, $customerId, null, $adminId, 'task', 'Send the cenote map', '2000-01-01');
check('an open task is listed', in_array($task, array_map('intval', array_column(kay_tasks_open($db, 500), 'id')), true), true);
kay_task_done($db, $task);
check('a done one is not',      in_array($task, array_map('intval', array_column(kay_tasks_open($db, 500), 'id')), true), false);
check('an unknown kind is a plain note',
      $db->query('SELECT kind FROM crm_notes WHERE id = ' . kay_note_add($db, $customerId, null, null, 'hack', 'x'))->fetchColumn(), 'note');

echo "\nForgetting a customer keeps the accounts and nothing else\n";
kay_customer_forget($db, $customerId);
$gone = kay_customer_get($db, $customerId);
check('no email',   $gone['email'], null);
check('no name',    $gone['name'], 'Client anonymisé');
check('no notes',   (int) $db->query("SELECT COUNT(*) FROM crm_notes WHERE customer_id = $customerId")->fetchColumn(), 0);
check('no email on the bookings either',
      (int) $db->query("SELECT COUNT(*) FROM bookings WHERE customer_id = $customerId AND email <> ''")->fetchColumn(), 0);
check('but the money is still there', (int) $gone['value_mxn_cents'] > 0, true);

// Clean up everything this section made.
$ids = $db->query("SELECT id FROM bookings WHERE customer_id = $customerId")->fetchAll(PDO::FETCH_COLUMN);
foreach ($ids as $bid) {
    $db->prepare('DELETE FROM booking_payments WHERE booking_id = ?')->execute([$bid]);
    $db->prepare('DELETE FROM crm_notes WHERE booking_id = ?')->execute([$bid]);
    $db->prepare('DELETE FROM bookings WHERE id = ?')->execute([$bid]);
}
$db->prepare('DELETE FROM crm_notes WHERE customer_id = ?')->execute([$customerId]);
$db->prepare('DELETE FROM customers WHERE id = ?')->execute([$customerId]);
$db->prepare('DELETE FROM admin_sessions WHERE user_id = ?')->execute([$adminId]);
$db->prepare('DELETE FROM admin_users WHERE id = ?')->execute([$adminId]);
$cleanAuth();

echo "\nTraffic counts people without keeping anything that identifies them\n";
// Every beacon here is dated in 2001, so the reports below read only these.
$day1 = new DateTimeImmutable('2001-02-03 10:00:00', kay_tz());
$day2 = $day1->modify('+1 day');
$db->exec("DELETE FROM page_views WHERE day BETWEEN '2001-01-01' AND '2001-12-31'");
$chrome = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/120.0 Mobile/15E148 Safari/604.1';
$env = static fn(DateTimeImmutable $now, string $ip = '198.51.100.1', string $ua = ''): array =>
    ['ip' => $ip, 'ua' => $ua !== '' ? $ua : $chrome, 'host' => 'kaydiving.com', 'now' => $now];
$beacon = ['e' => 'pageview', 'p' => '/en/', 'r' => 'https://www.google.com/', 'tz' => 'America/Toronto', 'l' => 'en-CA'];

check('a page view is stored',     kay_track_event($db, $beacon, $env($day1)), 'stored');
check('a crawler is not',          kay_track_event($db, $beacon, $env($day1, '198.51.100.1', 'Googlebot/2.1')), 'ignored:bot');
check('a headless browser is not', kay_track_event($db, $beacon, $env($day1, '198.51.100.1', 'Mozilla/5.0 HeadlessChrome/120')), 'ignored:bot');
check('Kay reading his own site is not',
      kay_track_event($db, $beacon, ['notrack' => true] + $env($day1)), 'ignored:admin');
check('an invented event is not',  kay_track_event($db, ['e' => 'purchase'] + $beacon, $env($day1)), 'ignored:event');
check('the admin is never a page', kay_track_event($db, ['p' => '/admin/index.php'] + $beacon, $env($day1)), 'ignored:path');
check('nor is markup',             kay_track_event($db, ['p' => '/en/<script>'] + $beacon, $env($day1)), 'ignored:path');
check('a query string is dropped, not refused',
      kay_track_event($db, ['p' => '/en/?utm_source=x', 'r' => 'https://kaydiving.com/en/'] + $beacon, $env($day1)), 'stored');

$row = $db->query("SELECT * FROM page_views WHERE day = '2001-02-03' ORDER BY id LIMIT 1")->fetch();
check('no IP address anywhere in the row', str_contains(implode('|', $row), '198.51.100.1'), false);
check('the visitor is a 16-character hash', (bool) preg_match('/^[a-f0-9]{16}$/', $row['visitor']), true);
check('Toronto is Canada',                  $row['country'], 'CA');
check('Google is named',                    $row['source'], 'Google');
check('it is an arrival',                   (int) $row['entry'], 1);
check('the language is kept, not the region', $row['lang'], 'en');
check('an iPhone is a mobile',              [$row['device'], $row['os'], $row['browser']], ['mobile', 'iOS', 'Chrome']);
$second = $db->query("SELECT * FROM page_views WHERE day = '2001-02-03' ORDER BY id DESC LIMIT 1")->fetch();
check('the same person is the same visitor that day', $second['visitor'], $row['visitor']);
check('a click from our own page is not an arrival',  [(int) $second['entry'], $second['source']], [0, '']);
check('and its path has no query string',             $second['path'], '/en/');


check('the timezone gives the country',    [kay_country_from_tz('Europe/Paris'), kay_country_from_tz('America/Cancun')], ['FR', 'MX']);
check('UTC is no country',                 kay_country_from_tz('UTC'), '');
check('nonsense is no country',            kay_country_from_tz('Mars/Olympus'), '');
check('Instagram’s in-app link is Instagram', kay_source('l.instagram.com', ''), 'Instagram');
check('Google Maps is not Google',         kay_source('maps.google.com', ''), 'Google Maps');
check('google.com.mx is Google',           kay_source('www.google.com.mx', ''), 'Google');
check('a tag beats the referrer',          kay_source('www.google.com', 'IG'), 'Instagram');
check('an unknown site keeps its name',    kay_source('www.tulum-guide.example', ''), 'tulum-guide.example');
check('an iPad posing as a Mac is a tablet', kay_ua_parse('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 Version/17.0 Safari/605.1.15', true)['device'], 'tablet');

echo "\nThe traffic report adds up\n";
// Day 1: visitor A saw two pages and the form; B one page (a bounce); C
// opened the checkout. Day 2: the earlier visitor again.
kay_track_event($db, ['e' => 'booking-viewed'] + $beacon, $env($day1));
kay_track_event($db, ['r' => 'https://l.instagram.com/', 'tz' => 'Europe/Paris', 'l' => 'fr-FR'] + $beacon, $env($day1, '198.51.100.2'));
kay_track_event($db, ['r' => '', 'us' => 'newsletter', 'uc' => 'winter'] + $beacon, $env($day1, '198.51.100.3'));
kay_track_event($db, ['e' => 'checkout-opened', 'pr' => 'bull-sharks'] + $beacon, $env($day1, '198.51.100.3'));
kay_track_event($db, ['e' => 'checkout-opened', 'pr' => 'not-a-product'] + $beacon, $env($day1, '198.51.100.3'));

// Day 2, last: the first beacon of a new day destroys the previous salt.
kay_track_event($db, $beacon, $env($day2));
$next = $db->query("SELECT visitor FROM page_views WHERE day = '2001-02-04' LIMIT 1")->fetchColumn();
check('the next day, the same person cannot be linked', $next !== $row['visitor'], true);
check('and the first day’s salt no longer exists',
      (int) $db->query("SELECT COUNT(*) FROM traffic_salts WHERE day = '2001-02-03'")->fetchColumn(), 0);

$totals = kay_traffic_totals($db, '2001-02-03', '2001-02-04');
check('visitors are counted per day, added up', $totals['visitors'], 4);
check('page views',                            $totals['pageviews'], 5);
check('one visitor saw the booking form',      $totals['booking_views'], 1);
check('one opened the checkout',               $totals['checkouts'], 1);
// B and the day-2 visitor saw one page and never the form; C saw one page
// but opened the checkout, which is not bouncing.
check('bounces are single pages with no interaction', [$totals['visits'], $totals['bounces']], [4, 2]);
$series = kay_traffic_series($db, '2001-02-01', '2001-02-05', 'day');
check('every day is in the series, zeros too', count($series), 5);
check('the 3rd had three visitors',            $series['2001-02-03']['visitors'], 3);
check('the 1st had none',                      $series['2001-02-01']['visitors'], 0);
$sources = array_column(kay_traffic_top($db, '2001-02-03', '2001-02-04', 'source'), 'visitors', 'value');
ksort($sources);   // Instagram and Newsletter tie; their order is not the point
check('sources count arrivals',                $sources, ['Google' => 2, 'Instagram' => 1, 'Newsletter' => 1]);
check('countries',                             array_column(kay_traffic_top($db, '2001-02-03', '2001-02-04', 'country'), 'visitors', 'value'), ['CA' => 3, 'FR' => 1]);
check('the campaign is kept',                  array_column(kay_traffic_top($db, '2001-02-03', '2001-02-04', 'utm_campaign'), 'value'), ['winter']);
check('only a real product is recorded',       array_column(kay_traffic_top($db, '2001-02-03', '2001-02-04', 'product'), 'value'), ['bull-sharks']);
check('an unknown breakdown is refused',       kay_traffic_top($db, '2001-02-03', '2001-02-04', 'visitor; DROP TABLE x'), []);
check('live counts the last half hour',        kay_traffic_live($db, 30, $day1->modify('+10 minutes')), 3);
$r = kay_traffic_range('7d', new DateTimeImmutable('2001-02-10 12:00', kay_tz()));
check('seven days end today',                  [$r['from'], $r['to']], ['2001-02-04', '2001-02-10']);
check('and compare with the seven before',     [$r['prev_from'], $r['prev_to']], ['2001-01-28', '2001-02-03']);
check('an unknown range falls back to 30 days', kay_traffic_range('forever')['key'], '30d');

$db->exec("DELETE FROM page_views WHERE day BETWEEN '2001-01-01' AND '2001-12-31'");
$db->exec("DELETE FROM traffic_salts WHERE day < '2002-01-01'");

echo "\nThe admin's output cannot be turned against it\n";
$chart = kay_chart_columns([['label' => '<img src=x onerror=alert(1)>', 'value' => 3]], ['unit' => 'visiteurs', 'title' => 't']);
check('a chart label is escaped',          str_contains($chart, '<img'), false);
check('a spreadsheet formula is defused',  kay_csv_cell('=HYPERLINK("http://x")'), "'=HYPERLINK(\"http://x\")");
check('a phone number is not a formula',   kay_csv_cell('+52 55 5454 7479'), "'+52 55 5454 7479");
check('a negative balance stays a number', kay_csv_cell('-500'), '-500');
check('axis maxima are round',             [kay_nice_max(7), kay_nice_max(83), kay_nice_max(1234)], [10, 100, 2000]);
check('WhatsApp links are digits only',    kay_whatsapp_url('+52 (55) 5454-7479'), 'https://wa.me/525554547479');
check('a flag and a name for a country',   str_contains(kay_country('CA'), 'Canada'), true);

printf("\n%d passed, %d failed\n\n", $passed, $failed);
exit($failed === 0 ? 0 : 1);
