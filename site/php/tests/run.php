<?php
declare(strict_types=1);

/**
 * Covers the two things that can lose money or let someone dive free:
 * server-side pricing, and webhook idempotency. No framework — the whole
 * backend is four files, a test runner would outweigh it.
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

echo "\nPricing is recomputed from the menu, never taken from the client\n";

$base = ['product' => 'cenote-diving', 'option' => 3, 'date' => '2099-12-01',
         'cert' => 'Advanced', 'divers' => 3, 'name' => 'T', 'email' => 't@e.com', 'locale' => 'en'];

$q = kay_quote($base);
check('cenote diving, 3 dives, 3 divers', $q['total_usd'], 510);
check('deposit is 30%',                   $q['deposit_usd'], 153);

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

echo "\nValidation refuses a past date and a malformed email\n";
$ok = ['product' => 'reef-cenote', 'option' => 2, 'date' => '2099-01-01',
       'cert' => 'OW', 'divers' => 2, 'name' => 'Ana', 'email' => 'a@b.co', 'locale' => 'en'];
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
    'INSERT INTO bookings (id, product, dives, dive_date, divers, name, email,
                           total_usd_cents, deposit_usd_cents, deposit_mxn_cents)
     VALUES (?,?,?,?,?,?,?,?,?,?)'
)->execute([$id, 'cenote-diving', 3, '2099-12-01', 3, 'Test', 't@example.com', 51000, 15300, 267750]);

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

printf("\n%d passed, %d failed\n\n", $passed, $failed);
exit($failed === 0 ? 0 : 1);
