<?php
declare(strict_types=1);

/**
 * The concierges and hotels Kay works with, for the "recommended by" field on
 * the booking form. Names only — no note, no commission, nothing internal.
 *
 * It is a list of businesses that already hand out Kay's link, so there is
 * nothing here a diver standing in a hotel lobby could not read off a desk.
 * Cached for an hour: the list changes when Kay signs a partner, not daily.
 */

require __DIR__ . '/lib/config.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/referrals.php';

if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) {
    kay_fail(405, 'method not allowed');
}

$list = array_map(
    static fn(array $p): array => ['slug' => $p['slug'], 'name' => $p['name']],
    kay_partners(kay_db(), true),
);

header('Cache-Control: public, max-age=3600');
kay_ok(['partners' => $list]);
