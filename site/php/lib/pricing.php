<?php
declare(strict_types=1);

/**
 * Prices are recomputed here from slugs alone, reading the SAME products.json
 * the website is built from. The browser sends choices, never amounts, so a
 * tampered payload cannot lower what is charged — and because both sides read
 * one file, the page can never quote a price the server does not honour.
 */

function kay_catalogue(): array
{
    static $data = null;
    if ($data === null) {
        $raw = file_get_contents(__DIR__ . '/../products.json');
        if ($raw === false) {
            error_log('kay: products.json unreadable');
            kay_fail(503, 'catalogue unavailable');
        }
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }
    return $data;
}

function kay_find_product(string $slug): ?array
{
    foreach (kay_catalogue()['products'] as $product) {
        if ($product['slug'] === $slug) {
            return $product;
        }
    }
    return null;
}

/**
 * @return array{product:array,option:array,pickup:array,divers:int,total_usd:int,deposit_usd:int}|null
 */
function kay_quote(array $input): ?array
{
    $product = kay_find_product((string) ($input['product'] ?? ''));
    if ($product === null) {
        return null;
    }

    $wanted = (int) ($input['option'] ?? -1);
    $option = null;
    foreach ($product['options'] as $candidate) {
        if ($candidate['dives'] === $wanted) {
            $option = $candidate;
            break;
        }
    }
    // A size this product is not sold in is refused, not silently substituted.
    if ($option === null) {
        return null;
    }

    $divers = (int) ($input['divers'] ?? 0);
    $divers = max(1, min(8, $divers));

    // Pickup is charged per booking, not per diver: it is one van, not one seat.
    $pickup = null;
    foreach (kay_catalogue()['pickups'] as $candidate) {
        if ($candidate['slug'] === ($input['pickup'] ?? '')) {
            $pickup = $candidate;
            break;
        }
    }
    if ($pickup === null) {
        return null;
    }

    $total    = $option['price'] * $divers + $pickup['price'];
    // Mercado Pago settles in pesos, and Kay quotes in pesos. Charging a
    // converted dollar figure would have billed 3500 MXN for a dive he sells
    // at 3200 — his rate is 16, not the 17.5 that was configured. The peso
    // price is carried in the catalogue beside the dollar one and charged as
    // it stands, so no rate sits between his price list and the card.
    $totalMxn = $option['priceMxn'] * $divers + $pickup['priceMxn'];
    $rate     = (float) kay_config()['deposit_rate'];

    return [
        'product'     => $product,
        'option'      => $option,
        'pickup'      => $pickup,
        'divers'      => $divers,
        'total_usd'   => $total,
        'total_mxn'   => $totalMxn,
        'deposit_usd' => (int) round($total * $rate),
        'deposit_mxn' => (int) round($totalMxn * $rate),
    ];
}

/** Returns a short reason string, or null when the input is acceptable. */
function kay_validate(array $input): ?string
{
    $email = trim((string) ($input['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'email';
    }
    if (mb_strlen(trim((string) ($input['name'] ?? ''))) < 2) {
        return 'name';
    }

    $date = (string) ($input['date'] ?? '');
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
        return 'date';
    }
    if ($parsed < new DateTimeImmutable('today')) {
        return 'date-past';
    }

    // A product with a season cannot be booked outside it. The bull sharks are
    // only off Playa del Carmen from November to March; without this the form
    // would happily take a deposit in July for a dive nobody can run.
    $product = kay_find_product((string) ($input['product'] ?? ''));
    if ($product !== null && !empty($product['season'])) {
        $month = (int) $parsed->format('n');
        $from  = (int) $product['season']['fromMonth'];
        $to    = (int) $product['season']['toMonth'];
        // The window wraps the year end, so it is a union, not a range.
        $inSeason = $from <= $to
            ? ($month >= $from && $month <= $to)
            : ($month >= $from || $month <= $to);
        if (!$inSeason) {
            return 'out-of-season';
        }
    }
    return null;
}
