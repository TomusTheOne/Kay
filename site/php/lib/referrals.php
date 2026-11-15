<?php
declare(strict_types=1);

/**
 * Who sent the diver.
 *
 * Kay works with concierges — hotels, agencies, individuals — who send people
 * and are paid a commission per tour. The booking form asks, but a free-text
 * answer cannot be paid out: by the end of a season "Jashita", "hotel
 * jashita" and "JASHITA Hotel" are one partner and three names, and totalling
 * them is an argument, not a report.
 *
 * So partners are rows with a slug, the booking keeps that slug, and the
 * report groups on it. What someone typed under "other" is kept beside it,
 * never instead of it: it is how a new partner gets noticed in the first
 * place, but it is not something to sum.
 */

/** A slug that can live in a URL: this is what ?ref= carries. */
function kay_partner_slug(string $raw): string
{
    $s = mb_strtolower(trim($raw));
    $s = strtr($s, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u', 'ç' => 'c',
        'â' => 'a', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u',
    ]);
    $s = (string) preg_replace('/[^a-z0-9]+/', '-', $s);
    return mb_substr(trim($s, '-'), 0, 64);
}

/**
 * The partners, newest-looking first by name.
 *
 * @return list<array{slug:string,name:string,note:string,active:int}>
 */
function kay_partners(PDO $db, bool $activeOnly = false): array
{
    try {
        $sql = 'SELECT slug, name, note, active FROM partners'
             . ($activeOnly ? ' WHERE active = 1' : '')
             . ' ORDER BY name';
        return $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable) {
        // Before the migration has run. The form must still work.
        return [];
    }
}

/** One partner, or null when the slug is not one of ours. */
function kay_partner(PDO $db, string $slug): ?array
{
    if ($slug === '') {
        return null;
    }
    try {
        $q = $db->prepare('SELECT slug, name, note, active FROM partners WHERE slug = ?');
        $q->execute([$slug]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    } catch (Throwable) {
        return null;
    }
}

function kay_partner_save(PDO $db, string $slug, string $name, string $note, bool $active): void
{
    $db->prepare(
        'INSERT INTO partners (slug, name, note, active) VALUES (?,?,?,?)
         ON DUPLICATE KEY UPDATE name = VALUES(name), note = VALUES(note), active = VALUES(active)'
    )->execute([$slug, mb_substr(trim($name), 0, 120), mb_substr(trim($note), 0, 190), $active ? 1 : 0]);
}

/**
 * What the booking form sent, reduced to what may be stored.
 *
 * An unknown ?ref= is NOT discarded: it is kept as text. A concierge handing
 * out a link with a typo would otherwise produce bookings that credit nobody,
 * and nobody would ever find out why.
 *
 * @return array{slug:string,text:string}
 */
function kay_referral_clean(PDO $db, mixed $ref, mixed $text): array
{
    $slug = kay_partner_slug(is_string($ref) ? $ref : '');
    $free = mb_substr(trim(is_string($text) ? $text : ''), 0, 120);

    if ($slug !== '' && kay_partner($db, $slug) !== null) {
        return ['slug' => $slug, 'text' => $free];
    }
    if ($slug !== '' && $free === '') {
        $free = mb_substr((string) (is_string($ref) ? trim($ref) : ''), 0, 120);
    }
    return ['slug' => '', 'text' => $free];
}

/**
 * Bookings grouped by who sent them, over a date range, by dive date.
 *
 * Only bookings that were actually paid count: a pending row is someone who
 * opened the payment page and walked away, and paying a commission on those
 * would be paying for nothing. Cancelled and refunded are out for the same
 * reason.
 *
 * @return array{rows:list<array<string,mixed>>,totals:array<string,int>}
 */
function kay_referral_report(PDO $db, string $from, string $to): array
{
    $q = $db->prepare(
        "SELECT b.referral_slug AS slug,
                COALESCE(p.name, '')        AS name,
                COUNT(*)                    AS bookings,
                SUM(b.divers)               AS divers,
                SUM(b.total_usd_cents)      AS usd_cents,
                SUM(b.total_mxn_cents)      AS mxn_cents
           FROM bookings b
           LEFT JOIN partners p ON p.slug = b.referral_slug
          WHERE b.status = 'paid'
            AND b.referral_slug <> ''
            AND b.dive_date BETWEEN ? AND ?
          GROUP BY b.referral_slug, p.name
          ORDER BY divers DESC, bookings DESC"
    );
    $q->execute([$from, $to]);
    $rows = $q->fetchAll(PDO::FETCH_ASSOC);

    $totals = ['bookings' => 0, 'divers' => 0, 'usd_cents' => 0, 'mxn_cents' => 0];
    foreach ($rows as $r) {
        foreach ($totals as $k => $_) {
            $totals[$k] += (int) $r[$k];
        }
    }
    return ['rows' => $rows, 'totals' => $totals];
}

/**
 * The ones that named nobody from the list — "other", and blank.
 *
 * Kept on the same page as the report rather than hidden: a name that keeps
 * turning up here is a partner Kay has not added yet, and that is exactly
 * the thing a commission report is supposed to surface.
 *
 * @return list<array<string,mixed>>
 */
function kay_referral_freetext(PDO $db, string $from, string $to): array
{
    $q = $db->prepare(
        "SELECT b.referral_text AS text, COUNT(*) AS bookings, SUM(b.divers) AS divers
           FROM bookings b
          WHERE b.status = 'paid'
            AND b.referral_slug = ''
            AND b.referral_text <> ''
            AND b.dive_date BETWEEN ? AND ?
          GROUP BY b.referral_text
          ORDER BY divers DESC"
    );
    $q->execute([$from, $to]);
    return $q->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * How a booking's referral should read on screen: the partner's current name
 * where the slug still matches one, otherwise whatever the diver typed.
 *
 * A partner Kay later deletes leaves bookings behind pointing at a slug with
 * no row. Those still have to say something, so the slug itself is the last
 * resort — never a blank where a name belongs.
 */
function kay_referral_name(PDO $db, string $slug, string $text): string
{
    if ($slug !== '') {
        $p = kay_partner($db, $slug);
        return $p !== null ? (string) $p['name'] : $slug;
    }
    return $text;
}
