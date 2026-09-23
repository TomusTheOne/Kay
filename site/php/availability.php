<?php
declare(strict_types=1);

/**
 * The days Kay closed in the admin, for the booking form: dates only, from
 * today on. The form reads this when it loads so a diver who picks a closed
 * day is told at once, not after the payment page — booking.php refuses the
 * date anyway, whatever the browser does.
 *
 * Read-only, and it says nothing private: no reason, no booking. Cached for
 * a few minutes, which is how long a day Kay has just closed can still be
 * offered — and then refused by booking.php.
 */

require __DIR__ . '/lib/config.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/availability.php';

if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) {
    kay_fail(405, 'method not allowed');
}

header('Cache-Control: public, max-age=300');
kay_ok(['closed' => kay_public_closed_days(kay_db())]);
