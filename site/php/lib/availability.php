<?php
declare(strict_types=1);

/**
 * Days Kay does not go out — a holiday, the boat in for repairs, a day
 * already full — and that the website must therefore refuse.
 *
 * Closing a day used to mean nothing at all: the form took any date from
 * tomorrow on. Now a tap on the admin's calendar closes it, and three things
 * follow from one table:
 *
 *   - booking.php refuses the date, whatever the browser sent;
 *   - /api/availability.php lists the closed days, so the form can say so
 *     before the diver reaches the payment page;
 *   - the admin's calendars show them.
 *
 * The payment path must not depend on the admin having been opened: until
 * the table exists every day is open, exactly as before.
 */

/** No closing more than this at once: a typo in a year should not close three. */
const KAY_CLOSE_MAX_DAYS = 400;

/** @return array<string,string> 'Y-m-d' => reason, for every closed day in the range */
function kay_closed_days(PDO $db, string $from, string $to): array
{
    $s = $db->prepare('SELECT day, reason FROM closed_days WHERE day BETWEEN ? AND ? ORDER BY day');
    $s->execute([$from, $to]);
    return array_column($s->fetchAll(), 'reason', 'day');
}

/**
 * Whether the website must refuse this date. A database without the table
 * yet — a deploy that has not been followed by a visit to the admin — has
 * no closed days, and booking goes on as it always did.
 */
function kay_date_closed(PDO $db, string $ymd): bool
{
    try {
        $s = $db->prepare('SELECT 1 FROM closed_days WHERE day = ?');
        $s->execute([$ymd]);
        return $s->fetchColumn() !== false;
    } catch (PDOException $e) {
        if ($e->getCode() === '42S02') {
            return false;
        }
        throw $e;
    }
}

/** 'Y-m-d' strings from $from to $to inclusive, or [] if the range is not one. */
function kay_day_range(string $from, string $to): array
{
    $a = DateTimeImmutable::createFromFormat('!Y-m-d', $from);
    $b = DateTimeImmutable::createFromFormat('!Y-m-d', $to);
    if ($a === false || $b === false || $a->format('Y-m-d') !== $from || $b->format('Y-m-d') !== $to) {
        return [];
    }
    if ($b < $a) {
        [$a, $b] = [$b, $a];
    }
    $days = [];
    for ($d = $a; $d <= $b && count($days) < KAY_CLOSE_MAX_DAYS; $d = $d->modify('+1 day')) {
        $days[] = $d->format('Y-m-d');
    }
    return $days;
}

/**
 * Closes every day of a range; a day already closed takes the new reason.
 *
 * @return int days in the range, or 0 if the dates were not a range
 */
function kay_close_days(PDO $db, string $from, string $to, string $reason, ?int $authorId): int
{
    $days = kay_day_range($from, $to);
    $s = $db->prepare(
        'INSERT INTO closed_days (day, reason, author_id) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE reason = VALUES(reason), author_id = VALUES(author_id)'
    );
    $reason = mb_substr(trim($reason), 0, 120);
    foreach ($days as $day) {
        $s->execute([$day, $reason, $authorId]);
    }
    return count($days);
}

/** @return int days reopened */
function kay_open_days(PDO $db, string $from, string $to): int
{
    $days = kay_day_range($from, $to);
    if ($days === []) {
        return 0;
    }
    $s = $db->prepare('DELETE FROM closed_days WHERE day BETWEEN ? AND ?');
    $s->execute([$days[0], $days[count($days) - 1]]);
    return $s->rowCount();
}

/**
 * One tap on the calendar: closed if it was open, open if it was closed.
 *
 * @return bool|null true if now closed, false if now open, null for a bad date
 */
function kay_toggle_day(PDO $db, string $day, ?int $authorId): ?bool
{
    if (kay_day_range($day, $day) === []) {
        return null;
    }
    if (kay_date_closed($db, $day)) {
        kay_open_days($db, $day, $day);
        return false;
    }
    kay_close_days($db, $day, $day, '', $authorId);
    return true;
}

/**
 * Consecutive closed days grouped into runs with the same reason, for the
 * list under the calendar: "1–14 Aug · holiday" rather than fourteen lines.
 *
 * @return array<int, array{from:string,to:string,reason:string,days:int}>
 */
function kay_closed_runs(PDO $db, string $from, string $to): array
{
    $runs = [];
    foreach (kay_closed_days($db, $from, $to) as $day => $reason) {
        $last = count($runs) - 1;
        if ($last >= 0
            && $runs[$last]['reason'] === $reason
            && (new DateTimeImmutable($runs[$last]['to']))->modify('+1 day')->format('Y-m-d') === $day) {
            $runs[$last]['to'] = $day;
            $runs[$last]['days']++;
        } else {
            $runs[] = ['from' => $day, 'to' => $day, 'reason' => (string) $reason, 'days' => 1];
        }
    }
    return $runs;
}

/**
 * The closed days the website needs to know about: from today, as far ahead
 * as anyone books. Dates only — the reason is Kay's and stays in the admin.
 *
 * @return string[]
 */
function kay_public_closed_days(PDO $db): array
{
    $today = kay_today();
    $until = (new DateTimeImmutable($today))->modify('+18 months')->format('Y-m-d');
    try {
        return array_keys(kay_closed_days($db, $today, $until));
    } catch (PDOException $e) {
        if ($e->getCode() === '42S02') {
            return [];
        }
        throw $e;
    }
}
