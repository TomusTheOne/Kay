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

/**
 * The share taken up front. It lives in products.json, beside the prices it
 * applies to, so the slate in the browser and the amount sent to Mercado Pago
 * are computed from one number. Putting a copy in kay-config.php would be the
 * same trap the exchange rate was: an edit here, a stale value on the host.
 */
function kay_deposit_rate(): float
{
    return (float) kay_catalogue()['depositRate'];
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

/* ------------------------------------------------------------- dive sites --
   Where each dive of a booking goes, chosen on the form. The cenotes come
   from content/cenotes.json — the guide — which already says which products
   dive each one, so the form, the guide and this check read one list.      */

/** The guide's cenotes, in the order the guide shows them. */
function kay_cenotes(): array
{
    static $cenotes = null;
    if ($cenotes === null) {
        $raw = file_get_contents(__DIR__ . '/../cenotes.json');
        if ($raw === false) {
            error_log('kay: cenotes.json unreadable');
            kay_fail(503, 'catalogue unavailable');
        }
        $cenotes = json_decode($raw, true, 512, JSON_THROW_ON_ERROR)['cenotes'];
    }
    return $cenotes;
}

/**
 * The cenotes a diver picks from for this product, keyed by slug. Empty when
 * there is nothing to choose: a course, the snorkel tour, the sea, or a dive
 * run at one place only (Discover Scuba, at Casa Cenote).
 *
 * @return array<string, array>
 */
function kay_site_choices(array $product): array
{
    if (($product['kind'] ?? '') !== 'dive') {
        return [];
    }
    $choices = [];
    foreach (kay_cenotes() as $cenote) {
        if (in_array($product['slug'], $cenote['products'] ?? [], true)) {
            $choices[$cenote['slug']] = $cenote;
        }
    }
    return count($choices) >= 2 ? $choices : [];
}

/**
 * Kay's rule: one site per dive. With three dives the first two are at the
 * product's base (Dos Ojos) and the diver picks one other cenote for the
 * third — never three different cenotes.
 *
 * Nothing given is accepted only when $required is false (the admin, who may
 * not know yet). A product with no choice ignores whatever was sent.
 *
 * @return array{0: string[], 1: ?string} the sites, and 'sites' when refused
 */
function kay_sites_check(array $product, int $dives, mixed $given, bool $required): array
{
    $choices = kay_site_choices($product);
    if ($choices === []) {
        return [[], null];
    }
    $sites = is_array($given)
        ? array_values(array_filter(array_map(static fn($s): string => is_string($s) ? $s : '', $given),
            static fn(string $s): bool => $s !== ''))
        : [];
    if ($sites === [] && !$required) {
        return [[], null];
    }
    if (count($sites) !== $dives) {
        return [[], 'sites'];
    }
    foreach ($sites as $site) {
        if (!isset($choices[$site])) {
            return [[], 'sites'];
        }
    }
    $base = (string) ($product['threeDiveBase'] ?? '');
    if ($dives === 3 && $base !== ''
        && ($sites[0] !== $base || $sites[1] !== $base || $sites[2] === $base)) {
        return [[], 'sites'];
    }
    return [$sites, null];
}

/** "Dos Ojos ×2 · Angelita": the sites of a booking, in dive order. */
function kay_sites_label(string $stored): string
{
    if ($stored === '') {
        return '';
    }
    $names = [];
    foreach (kay_cenotes() as $cenote) {
        $names[$cenote['slug']] = (string) $cenote['short'];
    }
    $parts = [];
    foreach (explode(',', $stored) as $slug) {
        $name = $names[$slug] ?? $slug;
        $last = count($parts) - 1;
        if ($last >= 0 && $parts[$last][0] === $name) {
            $parts[$last][1]++;
        } else {
            $parts[] = [$name, 1];
        }
    }
    return implode(' · ', array_map(
        static fn(array $p): string => $p[1] > 1 ? $p[0] . ' ×' . $p[1] : $p[0],
        $parts
    ));
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
    $rate     = kay_deposit_rate();

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

    // A dive with sites to choose from needs one per dive, by Kay's rule. An
    // option the product is not sold in is left to kay_quote() to refuse.
    if ($product !== null && in_array((int) ($input['option'] ?? -1), array_column($product['options'], 'dives'), true)) {
        [, $error] = kay_sites_check($product, (int) $input['option'], $input['sites'] ?? null, true);
        if ($error !== null) {
            return $error;
        }
    }
    return null;
}
