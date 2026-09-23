<?php
declare(strict_types=1);

/**
 * Traffic, counted on Kay's own server and shown in his own admin.
 *
 * The third-party providers in components/Analytics.tsx remain available,
 * and remain off by default. This is the part that is always on, because it
 * costs the visitor nothing a third party would take:
 *
 *   - No cookie and nothing in localStorage. Nothing is stored on the
 *     visitor's device, so there is nothing to consent to and no banner.
 *   - No IP address stored. A visitor is sha256(daily salt, IP, browser),
 *     cut to 16 characters. The salt is random, lives for one day and is then
 *     deleted, so today's hash cannot be linked to tomorrow's, and nobody —
 *     Kay included, database in hand — can turn it back into an address.
 *   - Country comes from the timezone the browser reports, not from the IP.
 *     Coarser (someone from Toronto already in Tulum counts as Mexico), and
 *     exactly why it identifies no one. It also needs no GeoIP database on a
 *     shared host.
 *
 * The cost of that design, stated rather than hidden: the same person on two
 * different days counts as two visitors. Every figure is "visitors per day,
 * added up", which is what the admin says beside it.
 */

const KAY_TRACK_EVENTS         = ['pageview', 'booking-viewed', 'checkout-opened'];
const KAY_TRACK_RETENTION_DAYS = 400;   // a year back, and the same month last year
const KAY_TRACK_DAILY_CAP      = 300;   // events per visitor per day; past that it is not a person

/**
 * Crawlers, link previews and headless browsers. Most never run the beacon
 * at all; the ones that execute JavaScript say who they are here.
 */
function kay_is_bot(string $ua): bool
{
    return $ua === '' || (bool) preg_match(
        '/bot|crawl|spider|slurp|mediapartners|headless|lighthouse|pagespeed|gtmetrix|pingdom|uptime'
        . '|preview|facebookexternalhit|embedly|whatsapp|telegram|discord|skype|vkshare|pinterest'
        . '|curl|wget|python|httpclient|java\/|go-http|okhttp|phantom|selenium|puppeteer|playwright/i',
        $ua,
    );
}

/** @return array{device:string,browser:string,os:string} */
function kay_ua_parse(string $ua, bool $touch = false): array
{
    $browser = match (true) {
        str_contains($ua, 'Instagram')                                  => 'Instagram',
        (bool) preg_match('/FBAN|FBAV|FB_IAB/', $ua)                     => 'Facebook',
        str_contains($ua, 'Edg/') || str_contains($ua, 'EdgiOS')        => 'Edge',
        (bool) preg_match('/OPR\/|Opera/', $ua)                         => 'Opera',
        str_contains($ua, 'SamsungBrowser')                             => 'Samsung',
        (bool) preg_match('/Firefox|FxiOS/', $ua)                       => 'Firefox',
        (bool) preg_match('/Chrome|CriOS|Chromium/', $ua)               => 'Chrome',
        str_contains($ua, 'Safari')                                     => 'Safari',
        // Stored as 'other' and named in the reader's language on display.
        default                                                         => 'other',
    };

    $mac = str_contains($ua, 'Macintosh');
    $os = match (true) {
        (bool) preg_match('/iPhone|iPad|iPod/', $ua) || ($mac && $touch) => 'iOS',
        str_contains($ua, 'Android')                                     => 'Android',
        str_contains($ua, 'Windows')                                     => 'Windows',
        $mac                                                             => 'macOS',
        str_contains($ua, 'CrOS')                                        => 'ChromeOS',
        str_contains($ua, 'Linux')                                       => 'Linux',
        default                                                          => 'other',
    };

    // iPadOS reports itself as a Mac; a Mac with a touch screen is an iPad.
    $device = match (true) {
        (bool) preg_match('/iPad|Tablet/', $ua)
            || (str_contains($ua, 'Android') && !str_contains($ua, 'Mobile'))
            || ($mac && $touch)                                          => 'tablet',
        (bool) preg_match('/Mobi|iPhone|iPod|Android/', $ua)             => 'mobile',
        default                                                          => 'desktop',
    };

    return ['device' => $device, 'browser' => $browser, 'os' => $os];
}

/**
 * Where a visit came from, named the way Kay would name it. Order matters:
 * Google Maps and Gemini are not "Google".
 */
const KAY_SOURCE_HOSTS = [
    ['maps.google.', 'Google Maps'], ['gemini.google.com', 'Gemini'],
    ['com.google.android.gm', 'Gmail'], ['mail.google.com', 'Gmail'],
    ['com.google.android.googlequicksearchbox', 'Google'], ['google.', 'Google'],
    ['bing.com', 'Bing'], ['duckduckgo.com', 'DuckDuckGo'], ['yahoo.', 'Yahoo'],
    ['ecosia.org', 'Ecosia'], ['qwant.com', 'Qwant'], ['yandex.', 'Yandex'], ['baidu.com', 'Baidu'],
    ['instagram.com', 'Instagram'], ['facebook.com', 'Facebook'], ['fb.me', 'Facebook'],
    ['messenger.com', 'Facebook'], ['whatsapp.com', 'WhatsApp'], ['wa.me', 'WhatsApp'],
    ['tripadvisor.', 'Tripadvisor'], ['t.co', 'X (Twitter)'], ['twitter.com', 'X (Twitter)'],
    ['x.com', 'X (Twitter)'], ['youtube.com', 'YouTube'], ['tiktok.com', 'TikTok'],
    ['pinterest.', 'Pinterest'], ['reddit.com', 'Reddit'], ['linkedin.com', 'LinkedIn'],
    ['chatgpt.com', 'ChatGPT'], ['chat.openai.com', 'ChatGPT'], ['perplexity.ai', 'Perplexity'],
    ['claude.ai', 'Claude'], ['copilot.microsoft.com', 'Copilot'],
    ['padi.com', 'PADI'], ['airbnb.', 'Airbnb'], ['booking.com', 'Booking.com'],
    ['getyourguide.', 'GetYourGuide'], ['viator.com', 'Viator'],
];

/** utm_source as people actually type it, folded onto the same names. */
const KAY_SOURCE_ALIASES = [
    'ig' => 'Instagram', 'instagram' => 'Instagram', 'fb' => 'Facebook', 'facebook' => 'Facebook',
    'google' => 'Google', 'gmb' => 'Google Maps', 'maps' => 'Google Maps', 'wa' => 'WhatsApp',
    'whatsapp' => 'WhatsApp', 'tripadvisor' => 'Tripadvisor', 'tiktok' => 'TikTok',
    'youtube' => 'YouTube', 'newsletter' => 'Newsletter', 'email' => 'E-mail',
];

function kay_host_clean(string $host): string
{
    return (string) preg_replace('/^(www\d?|m|l|lm|mobile|amp)\./', '', mb_strtolower(trim($host, '. ')));
}

function kay_source(string $referrerHost, string $utmSource): string
{
    $utm = mb_strtolower(trim($utmSource));
    if ($utm !== '') {
        return KAY_SOURCE_ALIASES[$utm] ?? mb_substr($utm, 0, 100);
    }

    $host = kay_host_clean($referrerHost);
    if ($host === '') {
        return '';
    }
    foreach (KAY_SOURCE_HOSTS as [$needle, $label]) {
        $hit = str_ends_with($needle, '.')
            ? str_starts_with($host, $needle) || str_contains($host, '.' . $needle)
            : $host === $needle || str_ends_with($host, '.' . $needle);
        if ($hit) {
            return $label;
        }
    }
    return mb_substr($host, 0, 100);
}

/** 'America/Toronto' → 'CA'. Empty when the zone is unknown or not a country (UTC). */
function kay_country_from_tz(string $tz): string
{
    if ($tz === '' || strlen($tz) > 64) {
        return '';
    }
    try {
        $location = (new DateTimeZone($tz))->getLocation();
    } catch (Exception) {
        return '';
    }
    $cc = is_array($location) ? (string) ($location['country_code'] ?? '') : '';
    return preg_match('/^[A-Z]{2}$/', $cc) ? $cc : '';
}

/**
 * Today's salt, created on the first visit of the day. Creating it is also
 * when yesterday's is deleted — from then on, yesterday's hashes are
 * irreversible.
 */
function kay_daily_salt(PDO $db, string $day): string
{
    $insert = $db->prepare('INSERT IGNORE INTO traffic_salts (day, salt) VALUES (?, ?)');
    $insert->execute([$day, bin2hex(random_bytes(32))]);
    if ($insert->rowCount() === 1) {
        $db->prepare('DELETE FROM traffic_salts WHERE day < ?')->execute([$day]);
    }
    $s = $db->prepare('SELECT salt FROM traffic_salts WHERE day = ?');
    $s->execute([$day]);
    return (string) $s->fetchColumn();
}

function kay_visitor_hash(string $salt, string $ip, string $ua): string
{
    return substr(hash('sha256', $salt . '|' . $ip . '|' . $ua), 0, 16);
}

/** Printable, single-line, bounded. What a stranger posts is data, never markup. */
function kay_track_text(mixed $value, int $max): string
{
    $text = is_scalar($value) ? (string) $value : '';
    $text = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $text);
    return mb_substr(trim($text), 0, $max);
}

/**
 * Records one beacon. $env is the request, passed in so the tests can play
 * any visitor on any day.
 *
 * @param array{e?:mixed,p?:mixed,r?:mixed,tz?:mixed,l?:mixed,t?:mixed,us?:mixed,um?:mixed,uc?:mixed,pr?:mixed} $in
 * @param array{ip:string,ua:string,host:string,notrack?:bool,now?:DateTimeImmutable} $env
 * @return string what happened: 'stored', or 'ignored:<why>'
 */
function kay_track_event(PDO $db, array $in, array $env): string
{
    if (!empty($env['notrack'])) {
        return 'ignored:admin';
    }
    $ua = kay_track_text($env['ua'] ?? '', 512);
    if (kay_is_bot($ua)) {
        return 'ignored:bot';
    }

    $event = (string) ($in['e'] ?? '');
    if (!in_array($event, KAY_TRACK_EVENTS, true)) {
        return 'ignored:event';
    }

    // Only the site's own pages: no query string, no fragment, and never the
    // admin or the endpoints, whatever someone posts by hand.
    $path = (string) strtok(kay_track_text($in['p'] ?? '', 400), '?#');
    if (!preg_match('#^/[A-Za-z0-9/_.\-]{0,180}$#', $path) || preg_match('#^/(api|admin)(/|$)#', $path)) {
        return 'ignored:path';
    }
    $segment = explode('/', trim($path, '/'))[0] ?? '';
    $locale  = in_array($segment, ['en', 'es', 'fr'], true) ? $segment : '';

    // A referrer from our own host is a click between two of Kay's pages,
    // not an arrival. Only arrivals carry a source.
    $own     = kay_host_clean((string) ($env['host'] ?? ''));
    $refHost = kay_host_clean((string) parse_url(kay_track_text($in['r'] ?? '', 500), PHP_URL_HOST));
    $utmSrc  = kay_track_text($in['us'] ?? '', 100);
    $internal = $refHost !== '' && $refHost === $own;
    // A tagged link counts once, on arrival: the utm_ parameters often stay
    // in the address bar for the rest of the visit.
    $entry    = $event === 'pageview' && !$internal;

    $product = '';
    if ($event === 'checkout-opened') {
        $wanted  = (string) ($in['pr'] ?? '');
        $product = kay_find_product($wanted) !== null ? $wanted : '';
    }

    $lang = strtolower(substr(kay_track_text($in['l'] ?? '', 35), 0, 2));
    $ua_  = kay_ua_parse($ua, !empty($in['t']));

    $now = ($env['now'] ?? new DateTimeImmutable('now'))->setTimezone(kay_tz());
    $day = $now->format('Y-m-d');
    $visitor = kay_visitor_hash(kay_daily_salt($db, $day), (string) ($env['ip'] ?? ''), $ua);

    $count = $db->prepare('SELECT COUNT(*) FROM page_views WHERE day = ? AND visitor = ?');
    $count->execute([$day, $visitor]);
    if ((int) $count->fetchColumn() >= KAY_TRACK_DAILY_CAP) {
        return 'ignored:cap';
    }

    $db->prepare(
        'INSERT INTO page_views
           (day, created_at, visitor, event, path, locale, entry, source, referrer,
            utm_medium, utm_campaign, country, lang, device, browser, os, product)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        $day, $now->format('Y-m-d H:i:s'), $visitor, $event, $path, $locale, $entry ? 1 : 0,
        $entry ? kay_source($refHost, $utmSrc) : '',
        $entry ? mb_substr($refHost, 0, 191) : '',
        $entry ? kay_track_text($in['um'] ?? '', 100) : '',
        $entry ? kay_track_text($in['uc'] ?? '', 100) : '',
        kay_country_from_tz(kay_track_text($in['tz'] ?? '', 64)),
        preg_match('/^[a-z]{2}$/', $lang) ? $lang : '',
        $ua_['device'], $ua_['browser'], $ua_['os'], $product,
    ]);

    // Retention, a little at a time rather than in one long job nobody runs.
    if (random_int(1, 500) === 1) {
        $db->prepare('DELETE FROM page_views WHERE day < ? LIMIT 5000')
           ->execute([$now->modify('-' . KAY_TRACK_RETENTION_DAYS . ' days')->format('Y-m-d')]);
    }
    return 'stored';
}

/* ------------------------------------------------------------- reporting -- */

const KAY_TRAFFIC_RANGES = [
    'today' => 'Aujourd’hui',
    '7d'    => '7 jours',
    '30d'   => '30 jours',
    '90d'   => '90 jours',
    '12m'   => '12 mois',
];

/**
 * A preset as dates, plus the period of equal length just before it, for the
 * deltas.
 *
 * @return array{key:string,from:string,to:string,prev_from:string,prev_to:string,bucket:string,days:int}
 */
function kay_traffic_range(string $key, ?DateTimeImmutable $now = null): array
{
    $key   = isset(KAY_TRAFFIC_RANGES[$key]) ? $key : '30d';
    $today = ($now ?? kay_now())->setTime(0, 0);
    $days  = ['today' => 1, '7d' => 7, '30d' => 30, '90d' => 90, '12m' => 365][$key];
    $from  = $today->modify('-' . ($days - 1) . ' days');

    return [
        'key'       => $key,
        'from'      => $from->format('Y-m-d'),
        'to'        => $today->format('Y-m-d'),
        'prev_from' => $from->modify("-$days days")->format('Y-m-d'),
        'prev_to'   => $from->modify('-1 day')->format('Y-m-d'),
        'bucket'    => match ($key) { 'today' => 'hour', '12m' => 'month', default => 'day' },
        'days'      => $days,
    ];
}

/**
 * @return array{visitors:int,pageviews:int,visits:int,bounces:int,booking_views:int,checkouts:int}
 */
function kay_traffic_totals(PDO $db, string $from, string $to): array
{
    $s = $db->prepare(
        "SELECT
           COUNT(DISTINCT CASE WHEN event = 'pageview' THEN CONCAT(day, visitor) END)        AS visitors,
           SUM(event = 'pageview')                                                          AS pageviews,
           COUNT(DISTINCT CASE WHEN event = 'booking-viewed' THEN CONCAT(day, visitor) END)  AS booking_views,
           COUNT(DISTINCT CASE WHEN event = 'checkout-opened' THEN CONCAT(day, visitor) END) AS checkouts
         FROM page_views WHERE day BETWEEN ? AND ?"
    );
    $s->execute([$from, $to]);
    $t = array_map('intval', $s->fetch() ?: []);

    // A bounce is a visit that saw one page and never reached the booking
    // form. The site is one long page per language, so "one page" alone would
    // call nearly every visit a bounce, including the ones that booked.
    $b = $db->prepare(
        "SELECT COUNT(*) AS visits, COALESCE(SUM(pv = 1 AND ev = 0), 0) AS bounces
           FROM (SELECT SUM(event = 'pageview') AS pv, SUM(event <> 'pageview') AS ev
                   FROM page_views WHERE day BETWEEN ? AND ?
                  GROUP BY day, visitor) v
          WHERE pv > 0"
    );
    $b->execute([$from, $to]);
    $v = array_map('intval', $b->fetch() ?: []);

    return [
        'visitors'      => $t['visitors'] ?? 0,
        'pageviews'     => $t['pageviews'] ?? 0,
        'visits'        => $v['visits'] ?? 0,
        'bounces'       => $v['bounces'] ?? 0,
        'booking_views' => $t['booking_views'] ?? 0,
        'checkouts'     => $t['checkouts'] ?? 0,
    ];
}

/**
 * Visitors and pageviews per hour, day or month, every bucket present even
 * when it is zero — a gap in the chart should be a zero, not a missing bar.
 *
 * @return array<string, array{visitors:int,pageviews:int}>
 */
function kay_traffic_series(PDO $db, string $from, string $to, string $bucket): array
{
    $out   = [];
    $start = new DateTimeImmutable($from);
    $end   = new DateTimeImmutable($to);

    if ($bucket === 'hour') {
        for ($h = 0; $h < 24; $h++) {
            $out[sprintf('%02d', $h)] = ['visitors' => 0, 'pageviews' => 0];
        }
        $key = "DATE_FORMAT(created_at, '%H')";
    } elseif ($bucket === 'month') {
        for ($m = $start->modify('first day of this month'); $m <= $end; $m = $m->modify('+1 month')) {
            $out[$m->format('Y-m')] = ['visitors' => 0, 'pageviews' => 0];
        }
        $key = "DATE_FORMAT(day, '%Y-%m')";
    } else {
        for ($d = $start; $d <= $end; $d = $d->modify('+1 day')) {
            $out[$d->format('Y-m-d')] = ['visitors' => 0, 'pageviews' => 0];
        }
        $key = "DATE_FORMAT(day, '%Y-%m-%d')";
    }

    $s = $db->prepare(
        "SELECT $key AS k,
                COUNT(DISTINCT CONCAT(day, visitor)) AS visitors,
                COUNT(*) AS pageviews
           FROM page_views
          WHERE day BETWEEN ? AND ? AND event = 'pageview'
          GROUP BY k"
    );
    $s->execute([$from, $to]);
    foreach ($s->fetchAll() as $row) {
        if (isset($out[$row['k']])) {
            $out[$row['k']] = ['visitors' => (int) $row['visitors'], 'pageviews' => (int) $row['pageviews']];
        }
    }
    return $out;
}

/** The breakdowns the traffic page offers. Column names are never taken from input. */
const KAY_TRAFFIC_DIMENSIONS = [
    'path'         => ['column' => 'path',         'event' => 'pageview',        'entry' => false],
    'source'       => ['column' => 'source',       'event' => 'pageview',        'entry' => true],
    'utm_campaign' => ['column' => 'utm_campaign', 'event' => 'pageview',        'entry' => true],
    'country'      => ['column' => 'country',      'event' => 'pageview',        'entry' => false],
    'locale'       => ['column' => 'locale',       'event' => 'pageview',        'entry' => false],
    'lang'         => ['column' => 'lang',         'event' => 'pageview',        'entry' => false],
    'device'       => ['column' => 'device',       'event' => 'pageview',        'entry' => false],
    'browser'      => ['column' => 'browser',      'event' => 'pageview',        'entry' => false],
    'os'           => ['column' => 'os',           'event' => 'pageview',        'entry' => false],
    'product'      => ['column' => 'product',      'event' => 'checkout-opened', 'entry' => false],
];

/**
 * The top values of one dimension, by visitors.
 *
 * @return array<int, array{value:string,visitors:int,events:int}>
 */
function kay_traffic_top(PDO $db, string $from, string $to, string $dimension, int $limit = 10): array
{
    $d = KAY_TRAFFIC_DIMENSIONS[$dimension] ?? null;
    if ($d === null) {
        return [];
    }
    $column = $d['column'];
    $extra  = $d['entry'] ? ' AND entry = 1' : '';
    // An empty campaign is "no campaign", not a campaign worth a row.
    if ($dimension === 'utm_campaign' || $dimension === 'product') {
        $extra .= " AND $column <> ''";
    }

    $s = $db->prepare(
        "SELECT $column AS value,
                COUNT(DISTINCT CONCAT(day, visitor)) AS visitors,
                COUNT(*) AS events
           FROM page_views
          WHERE day BETWEEN ? AND ? AND event = ?$extra
          GROUP BY $column
          ORDER BY visitors DESC, events DESC
          LIMIT " . max(1, min(100, $limit))
    );
    $s->execute([$from, $to, $d['event']]);
    return array_map(static fn(array $r): array => [
        'value' => (string) $r['value'], 'visitors' => (int) $r['visitors'], 'events' => (int) $r['events'],
    ], $s->fetchAll());
}

/** People on the site in the last $minutes minutes. */
function kay_traffic_live(PDO $db, int $minutes = 30, ?DateTimeImmutable $now = null): int
{
    $now   = ($now ?? kay_now())->setTimezone(kay_tz());
    $since = $now->modify("-$minutes minutes");
    $s = $db->prepare(
        'SELECT COUNT(DISTINCT visitor) FROM page_views
          WHERE day BETWEEN ? AND ? AND created_at BETWEEN ? AND ?'
    );
    $s->execute([$since->format('Y-m-d'), $now->format('Y-m-d'),
                 $since->format('Y-m-d H:i:s'), $now->format('Y-m-d H:i:s')]);
    return (int) $s->fetchColumn();
}
