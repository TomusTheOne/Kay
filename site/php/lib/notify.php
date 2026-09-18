<?php
declare(strict_types=1);

/**
 * The two emails that go out the moment a deposit clears: a confirmation to
 * the diver, and a day-sheet line to Kay.
 *
 * Every string comes from messages/<locale>.json — the same file the website
 * renders from — so a confirmation can never name a product differently from
 * the page that sold it, and translating the site translates the emails too.
 * Prices come from the booking row, which was written from the server-side
 * quote, never from anything the browser sent.
 */

/** The site's own copy, in the language the diver booked in. */
function kay_messages(string $locale): array
{
    static $cache = [];
    if (isset($cache[$locale])) {
        return $cache[$locale];
    }
    $path = __DIR__ . '/../messages/' . $locale . '.json';
    $raw  = is_readable($path) ? file_get_contents($path) : false;
    if ($raw === false) {
        // A missing translation must not cost someone their confirmation.
        return $cache[$locale] = ($locale === 'en' ? [] : kay_messages('en'));
    }
    return $cache[$locale] = json_decode($raw, true) ?? [];
}

/**
 * "Saturday 4 October 2026". Uses intl when the host has it — OVH's shared
 * PHP usually does — and falls back to English month names when it does not,
 * which is no worse than today, since es and fr are still English copies.
 */
function kay_format_date(string $ymd, string $locale): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd);
    if ($date === false) {
        return $ymd;
    }
    if (class_exists(IntlDateFormatter::class)) {
        $fmt = new IntlDateFormatter($locale, IntlDateFormatter::FULL, IntlDateFormatter::NONE);
        $fmt->setPattern('EEEE d MMMM y');
        $out = $fmt->format($date);
        if (is_string($out) && $out !== '') {
            return $out;
        }
    }
    return $date->format('l j F Y');
}

function kay_e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** "2 dives", "1 dive", or the snorkel tour's "Half day". */
function kay_dives_label(int $dives, array $c): string
{
    if ($dives <= 0) {
        return $c['halfDay'];
    }
    return $dives . ' ' . ($dives === 1 ? $c['dive'] : $c['dives']);
}

/** The chosen departure, or the diver's own suggestion when they asked for one. */
function kay_slot_label(string $slot, string $note, array $m): string
{
    foreach (kay_catalogue()['schedules'] as $schedule) {
        if ($schedule['slug'] === $slot && $schedule['start'] !== null) {
            return $schedule['start'] . ' – ' . $schedule['end'];
        }
    }
    $other = $m['emails']['common']['slotOther'];
    return $note === '' ? $other : $other . ' (' . $note . ')';
}

/* ------------------------------------------------------------------ shell --
   Tables and inline styles, because Outlook still discards a stylesheet.
   The palette is the site's: cream paper, deep ink, cenote turquoise.       */

function kay_mail_shell(string $preheader, string $inner): string
{
    return '<!doctype html><html><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '</head><body style="margin:0;padding:0;background:#EDE5D8;">'
        . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . kay_e($preheader) . '</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#EDE5D8;">'
        . '<tr><td align="center" style="padding:32px 16px;">'
        . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" '
        . 'style="width:100%;max-width:600px;background:#F7EFE3;border-radius:14px;overflow:hidden;">'
        . $inner
        . '</table></td></tr></table></body></html>';
}

/** The dark band at the top, so the email opens like the site does. */
function kay_mail_header(string $kicker, string $title): string
{
    return '<tr><td style="background:#03090E;padding:28px 32px;">'
        . '<div style="font:600 11px/1 Helvetica,Arial,sans-serif;letter-spacing:.18em;'
        . 'text-transform:uppercase;color:#4FE0D2;">' . kay_e($kicker) . '</div>'
        . '<div style="font:400 30px/1.15 Georgia,\'Times New Roman\',serif;color:#E6FBF6;'
        . 'margin-top:10px;">' . kay_e($title) . '</div>'
        . '</td></tr>';
}

function kay_mail_text(string $html): string
{
    return '<tr><td style="padding:22px 32px 0;font:400 15px/1.6 Helvetica,Arial,sans-serif;'
        . 'color:#06161F;">' . $html . '</td></tr>';
}

/** A labelled block of facts. $rows is [label => already-escaped value]. */
function kay_mail_facts(string $tag, array $rows): string
{
    $out = '<tr><td style="padding:24px 32px 0;">'
        . '<div style="font:600 10px/1 Helvetica,Arial,sans-serif;letter-spacing:.18em;'
        . 'text-transform:uppercase;color:#1FA8AE;padding-bottom:10px;">' . kay_e($tag) . '</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">';
    foreach ($rows as $label => $value) {
        $out .= '<tr>'
            . '<td style="padding:7px 12px 7px 0;font:400 13px/1.4 Helvetica,Arial,sans-serif;'
            . 'color:#5C6B72;border-top:1px solid #E0D5C4;white-space:nowrap;vertical-align:top;">'
            . kay_e((string) $label) . '</td>'
            . '<td style="padding:7px 0;font:600 14px/1.4 Helvetica,Arial,sans-serif;color:#06161F;'
            . 'border-top:1px solid #E0D5C4;text-align:right;">' . $value . '</td>'
            . '</tr>';
    }
    return $out . '</table></td></tr>';
}

function kay_mail_footer(string $line): string
{
    return '<tr><td style="padding:26px 32px 30px;font:400 12px/1.6 Helvetica,Arial,sans-serif;'
        . 'color:#7A8A91;border-top:1px solid #E0D5C4;margin-top:20px;">' . kay_e($line) . '</td></tr>';
}

/* ------------------------------------------------------------- the emails --

   @param array $row a bookings row, as stored
   @return array{diver:array,shop:array} two kay_send_email() payloads        */

function kay_booking_emails(array $row): array
{
    $locale = in_array($row['locale'] ?? 'en', ['en', 'es', 'fr'], true) ? $row['locale'] : 'en';
    $m      = kay_messages($locale);
    $d      = $m['emails']['diver'];
    $s      = $m['emails']['shop'];
    $c      = $m['emails']['common'];

    $config  = kay_config();
    $site    = rtrim((string) $config['site_url'], '/');
    $shop    = kay_catalogue()['shop'];
    $product = kay_find_product((string) $row['product']);

    $productName = $product !== null && isset($m['products']['items'][$row['product']]['name'])
        ? (string) $m['products']['items'][$row['product']]['name']
        : (string) $row['product'];

    $pickupSlug  = (string) ($row['pickup'] ?? 'meeting-point');
    $pickupName  = (string) ($m['logistics']['pickups'][$pickupSlug]['name'] ?? $pickupSlug);
    $pickupPrice = (string) ($m['logistics']['pickups'][$pickupSlug]['price'] ?? '');

    $divers  = (int) $row['divers'];
    $dives   = (int) $row['dives'];
    $date    = kay_format_date((string) $row['dive_date'], $locale);
    $slot    = kay_slot_label((string) $row['start_slot'], (string) ($row['start_note'] ?? ''), $m);
    $cert    = trim((string) ($row['certification'] ?? '')) ?: $c['noCert'];

    $total   = (int) round($row['total_usd_cents'] / 100);
    $paid    = (int) round($row['deposit_usd_cents'] / 100);
    $balance = $total - $paid;

    $what = sprintf('%s · %s', $productName, kay_dives_label($dives, $c));
    $who  = $divers . ' ' . ($divers === 1 ? $c['diver'] : $c['divers']);

    $swap = static fn(string $t, array $vars): string => strtr($t, $vars);

    /* ----------------------------------------------------------- to the diver */

    $facts = [
        $d['whatLabel']   => kay_e($what),
        $d['dateLabel']   => kay_e($date),
        $d['timeLabel']   => kay_e($slot),
        $d['peopleLabel'] => kay_e($who),
        $d['certLabel']   => kay_e($cert),
        $d['meetLabel']   => kay_e((string) $shop['meetingPoint']),
        $d['pickupLabel'] => kay_e(trim($pickupName . ' ' . $pickupPrice)),
    ];
    $money = [
        $d['totalLabel']   => '$' . $total . ' USD',
        $d['paidLabel']    => '<span style="color:#1FA8AE;">$' . $paid . ' USD</span>',
        $d['balanceLabel'] => '$' . $balance . ' USD',
    ];

    $inner = kay_mail_header($d['kicker'], $productName)
        . kay_mail_text('<p style="margin:0 0 12px;">' . kay_e($swap($d['hi'], ['{name}' => (string) $row['name']])) . '</p>'
            . '<p style="margin:0;">' . kay_e($swap($d['lede'], ['{date}' => $date])) . '</p>')
        . kay_mail_facts($d['detailsTag'], $facts)
        . kay_mail_facts($d['moneyTag'], $money)
        . kay_mail_text('<p style="margin:0;font-size:13px;color:#5C6B72;">' . kay_e($d['balanceNote']) . '</p>')
        . kay_mail_text('<p style="margin:18px 0 6px;font-weight:700;">' . kay_e($d['nextTag']) . '</p>'
            . '<p style="margin:0;">' . kay_e($d['nextText']) . '</p>'
            . '<p style="margin:18px 0 6px;font-weight:700;">' . kay_e($d['bringTag']) . '</p>'
            . '<p style="margin:0;">' . kay_e($d['bringText']) . '</p>'
            . '<p style="margin:22px 0 0;">' . kay_e($d['signoff']) . '<br>'
            . '<strong>' . kay_e($d['team']) . '</strong><br>'
            . '<a href="tel:' . kay_e((string) $shop['phone']) . '" style="color:#1FA8AE;">'
            . kay_e((string) $shop['phoneDisplay']) . '</a></p>'
            . '<p style="margin:18px 0 0;font-size:12px;color:#7A8A91;">' . kay_e($d['refLabel'])
            . ': ' . kay_e((string) $row['id']) . '</p>')
        . kay_mail_footer($swap($d['footer'], ['{site}' => $site]));

    $diverText = implode("\n", [
        $swap($d['hi'], ['{name}' => (string) $row['name']]),
        '',
        $swap($d['lede'], ['{date}' => $date]),
        '',
        $d['whatLabel'] . ': ' . $what,
        $d['dateLabel'] . ': ' . $date,
        $d['timeLabel'] . ': ' . $slot,
        $d['peopleLabel'] . ': ' . $who,
        $d['certLabel'] . ': ' . $cert,
        $d['meetLabel'] . ': ' . $shop['meetingPoint'],
        $d['pickupLabel'] . ': ' . trim($pickupName . ' ' . $pickupPrice),
        '',
        $d['totalLabel'] . ': $' . $total . ' USD',
        $d['paidLabel'] . ': $' . $paid . ' USD',
        $d['balanceLabel'] . ': $' . $balance . ' USD',
        $d['balanceNote'],
        '',
        $d['nextTag'] . ' — ' . $d['nextText'],
        $d['bringTag'] . ' — ' . $d['bringText'],
        '',
        $d['signoff'],
        $d['team'] . ' · ' . $shop['phoneDisplay'],
        $d['refLabel'] . ': ' . $row['id'],
    ]);

    /* ------------------------------------------------------------- to the shop */

    $shopFacts = [
        $d['whatLabel']   => kay_e($what),
        $d['dateLabel']   => kay_e($date),
        $d['timeLabel']   => kay_e($slot),
        $d['peopleLabel'] => kay_e($who),
        $d['certLabel']   => kay_e($cert),
        $d['pickupLabel'] => kay_e(trim($pickupName . ' ' . $pickupPrice)),
        $d['paidLabel']   => '$' . $paid . ' USD',
        $d['balanceLabel'] => '$' . $balance . ' USD',
    ];
    $contact = [
        $s['nameLabel']   => kay_e((string) $row['name']),
        $s['emailLabel']  => '<a href="mailto:' . kay_e((string) $row['email']) . '" style="color:#1FA8AE;">'
                             . kay_e((string) $row['email']) . '</a>',
        $s['localeLabel'] => kay_e(strtoupper($locale)),
    ];

    $shopInner = kay_mail_header($swap($s['title'], ['{product}' => $productName]), $date)
        . kay_mail_facts($d['detailsTag'], $shopFacts)
        . kay_mail_facts($s['contactTag'], $contact)
        . kay_mail_text('<p style="margin:0 0 20px;">' . kay_e($s['action']) . '</p>'
            . '<p style="margin:0;font-size:12px;color:#7A8A91;">' . kay_e($d['refLabel'])
            . ': ' . kay_e((string) $row['id']) . '</p>')
        . kay_mail_footer($site);

    $shopSubject = $swap($s['subject'], [
        '{date}' => (string) $row['dive_date'], '{product}' => $productName, '{divers}' => (string) $divers,
    ]);

    return [
        'diver' => [
            'to'      => (string) $row['email'],
            'toName'  => (string) $row['name'],
            'subject' => $swap($d['subject'], ['{date}' => $date]),
            'html'    => kay_mail_shell($d['preheader'], $inner),
            'text'    => $diverText,
            // A reply goes to the shop, not into a no-reply void.
            'replyTo' => (string) ($config['mail_from'] ?? $shop['email']),
        ],
        'shop' => [
            'to'      => (string) ($config['mail_to_shop'] ?? $shop['email']),
            'toName'  => (string) $d['team'],
            'subject' => $shopSubject,
            'html'    => kay_mail_shell($shopSubject, $shopInner),
            'text'    => $shopSubject . "\n\n" . $diverText,
            // Kay hits reply and is already writing to the diver.
            'replyTo' => (string) $row['email'],
        ],
    ];
}

/**
 * Called from the webhook once a booking is marked paid. Failures are logged
 * and swallowed: the money is already taken, and a 500 here would only make
 * Mercado Pago retry a notification we have correctly handled.
 */
function kay_notify_booking(array $row): void
{
    try {
        $emails = kay_booking_emails($row);
    } catch (Throwable $e) {
        error_log('kay: could not render the confirmation for ' . ($row['id'] ?? '?') . ': ' . $e->getMessage());
        return;
    }
    foreach ($emails as $who => $message) {
        if (!kay_send_email($message)) {
            error_log("kay: $who confirmation not sent for " . $row['id']);
        }
    }
}
