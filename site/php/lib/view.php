<?php
declare(strict_types=1);

/**
 * What the admin pages share: the frame, the formatting, the charts.
 *
 * The admin is plain server-rendered HTML with a strict Content-Security-
 * Policy — no inline script, no inline style, nothing from another origin —
 * because it shows every customer's name, email and phone. That is why the
 * charts below are HTML columns sized by class (.h-37) rather than inline
 * styles or a charting library: it keeps the policy strict, the text crisp
 * at any width, and the page usable with JavaScript off.
 */

/* ---------------------------------------------------------------- output -- */

function kay_h(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** A link to another admin page, query string built and escaped. */
function kay_url(string $page, array $query = []): string
{
    $query = array_filter($query, static fn($v): bool => $v !== null && $v !== '');
    return $page . ($query ? '?' . http_build_query($query) : '');
}

function kay_redirect(string $url): never
{
    header('Location: ' . $url, true, 303);
    exit;
}

/** Messages that survive the redirect after a form, by key — never free text in a URL. */
const KAY_FLASH = [
    'saved'      => 'Enregistré.',
    'created'    => 'Réservation créée.',
    'moved'      => 'Statut mis à jour.',
    'paid'       => 'Paiement enregistré.',
    'noted'      => 'Ajouté à l’historique.',
    'done'       => 'Tâche terminée.',
    'sent'       => 'E-mail de confirmation envoyé.',
    'notsent'    => 'L’e-mail n’est pas parti — voir le journal d’erreurs de l’hébergement.',
    'password'   => 'Mot de passe changé. Les autres appareils ont été déconnectés.',
    'user'       => 'Compte créé.',
    'removed'    => 'Compte supprimé.',
    'forgotten'  => 'Données personnelles effacées.',
    'signedout'  => 'Autres sessions déconnectées.',
    'lang'       => 'Langue changée.',
    'closed'     => 'Jour fermé : il ne peut plus être réservé sur le site.',
    'closedbusy' => 'Jour fermé. Attention : des réservations existent déjà ce jour-là, elles sont maintenues.',
    'opened'     => 'Jour rouvert aux réservations.',
    'rangeclosed' => 'Dates fermées.',
    'rangeopened' => 'Dates rouvertes.',
    'gearsent'   => 'Questionnaire des tailles envoyé.',
];

/* ------------------------------------------------------------ formatting --
   In the admin's language: Mexican Spanish writes 12,320.5 and French
   12 320,5. Money is in pesos, the currency the card is charged in and the
   till counts.                                                             */

function kay_num(int|float $n, int $decimals = 0): string
{
    return kay_lang() === 'fr'
        ? number_format((float) $n, $decimals, ',', "\u{202F}")
        : number_format((float) $n, $decimals, '.', ',');
}

/** 12,320 MXN, with a no-break space so the figure never wraps. */
function kay_mxn(int|float $pesos, bool $unit = true): string
{
    return kay_num($pesos) . ($unit ? "\u{00A0}MXN" : '');
}

function kay_usd(int|float $dollars): string
{
    return kay_num($dollars) . "\u{00A0}USD";
}

function kay_int(int|float $n): string
{
    return kay_num($n);
}

function kay_pct(float $ratio, int $decimals = 0): string
{
    return kay_num($ratio * 100, $decimals) . "\u{00A0}%";
}

/** "12.9 k" past ten thousand, for stat tiles. */
function kay_compact(int $n): string
{
    if ($n < 10000) {
        return kay_int($n);
    }
    return kay_num($n / 1000, $n % 1000 === 0 ? 0 : 1) . "\u{00A0}k";
}

/** Date patterns by name, per language: Spanish puts "de" where French puts nothing. */
const KAY_DATE_PATTERNS = [
    'fr' => [
        'short' => 'EEE d MMM', 'short_year' => 'EEE d MMM y', 'long' => 'EEEE d MMMM y',
        'month_year' => 'MMMM y', 'month' => 'MMM', 'weekday' => 'EEE', 'day' => 'd',
        'weekday_day' => 'EEE d', 'day_month' => 'd MMM', 'day_month_year' => 'd MMM y',
    ],
    'es' => [
        'short' => 'EEE d MMM', 'short_year' => 'EEE d MMM y', 'long' => "EEEE d 'de' MMMM 'de' y",
        'month_year' => "MMMM 'de' y", 'month' => 'MMM', 'weekday' => 'EEE', 'day' => 'd',
        'weekday_day' => 'EEE d', 'day_month' => 'd MMM', 'day_month_year' => 'd MMM y',
    ],
];

/** A date in the admin's language, by pattern name (see KAY_DATE_PATTERNS). */
function kay_fmt_date(string $ymd, string $name): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', substr($ymd, 0, 10), kay_tz());
    if ($date === false) {
        return $ymd;
    }
    $pattern = KAY_DATE_PATTERNS[kay_lang()][$name] ?? $name;
    if (class_exists(IntlDateFormatter::class)) {
        $fmt = new IntlDateFormatter(kay_intl_locale(), IntlDateFormatter::FULL, IntlDateFormatter::NONE,
            kay_tz(), null, $pattern);
        $out = $fmt->format($date);
        if (is_string($out) && $out !== '') {
            return $out;
        }
    }
    return $date->format('d/m/Y');
}

/** "mié 1 dic", with the year only when it is not this year. */
function kay_date_short(string $ymd): string
{
    return kay_fmt_date($ymd, substr($ymd, 0, 4) === kay_now()->format('Y') ? 'short' : 'short_year');
}

/** ucfirst() for UTF-8: "miércoles" → "Miércoles", "été" → "Été". */
function kay_ucfirst(string $s): string
{
    return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
}

function kay_date_long(string $ymd): string
{
    return kay_fmt_date($ymd, 'long');
}

/**
 * The database writes created_at and paid_at in its own timezone, which on
 * OVH is Paris, not Tulum. Its offset is asked once per request and every
 * timestamp is moved into Tulum time before anyone reads it.
 */
function kay_db_time(?string $sql): ?DateTimeImmutable
{
    static $offset = null;
    if ($sql === null || $sql === '') {
        return null;
    }
    if ($offset === null) {
        $offset = (int) kay_db()->query('SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), NOW())')->fetchColumn();
    }
    $utc = (new DateTimeImmutable($sql, new DateTimeZone('UTC')))->modify(-$offset . ' seconds');
    return $utc->setTimezone(kay_tz());
}

/** "3 oct a las 14:05", or "hoy a las 14:05". */
function kay_when(?string $sql): string
{
    $t = kay_db_time($sql);
    if ($t === null) {
        return '—';
    }
    $day = $t->format('Y-m-d');
    $label = match ($day) {
        kay_today()                                   => kay_t('aujourd’hui'),
        kay_now()->modify('-1 day')->format('Y-m-d')  => kay_t('hier'),
        default => kay_fmt_date($day, $t->format('Y') === kay_now()->format('Y') ? 'day_month' : 'day_month_year'),
    };
    return kay_t('{day} à {time}', ['{day}' => $label, '{time}' => $t->format('H:i')]);
}

/** The site's own product names, in the admin's language. */
function kay_product_name(string $slug): string
{
    return (string) (kay_messages(kay_lang())['products']['items'][$slug]['name'] ?? $slug);
}

function kay_dives_text(int $dives): string
{
    return $dives <= 0 ? kay_t('demi-journée') : kay_tn($dives, '{n} plongée', '{n} plongées');
}

function kay_pickup_name(string $slug): string
{
    return (string) (kay_messages(kay_lang())['logistics']['pickups'][$slug]['name'] ?? $slug);
}

function kay_slot_text(string $slot, string $note = ''): string
{
    foreach (kay_catalogue()['schedules'] as $schedule) {
        if ($schedule['slug'] === $slot && $schedule['start'] !== null) {
            return $schedule['start'];
        }
    }
    return $note === '' ? kay_t('Horaire à fixer') : kay_t('Horaire à fixer ({note})', ['{note}' => $note]);
}

function kay_status_badge(string $status): string
{
    return '<span class="badge badge--' . kay_h($status) . '">' . kay_th(KAY_STATUSES[$status] ?? $status) . '</span>';
}

/** "🇨🇦 Canadá" from 'CA'. The flag is two regional-indicator letters. */
function kay_country(string $cc): string
{
    if (!preg_match('/^[A-Z]{2}$/', $cc)) {
        return kay_t('Inconnu');
    }
    $name = class_exists(Locale::class) ? (string) Locale::getDisplayRegion('-' . $cc, kay_intl_locale()) : '';
    $flag = mb_chr(0x1F1E6 + ord($cc[0]) - 65) . mb_chr(0x1F1E6 + ord($cc[1]) - 65);
    return $flag . "\u{00A0}" . ($name !== '' && $name !== $cc ? $name : $cc);
}

/** Language names for the traffic page; translated on display. */
const KAY_LANG_NAMES = [
    'en' => 'Anglais', 'es' => 'Espagnol', 'fr' => 'Français', 'de' => 'Allemand', 'it' => 'Italien',
    'pt' => 'Portugais', 'nl' => 'Néerlandais', 'ru' => 'Russe', 'zh' => 'Chinois', 'ja' => 'Japonais',
    'ko' => 'Coréen', 'pl' => 'Polonais', 'sv' => 'Suédois', 'he' => 'Hébreu', 'ar' => 'Arabe',
];

/** The three languages a diver can be written to in. */
const KAY_SITE_LANGS = ['en' => 'Anglais', 'es' => 'Espagnol', 'fr' => 'Français'];

const KAY_DEVICE_NAMES = ['mobile' => 'Mobile', 'tablet' => 'Tablette', 'desktop' => 'Ordinateur'];

/** wa.me wants the number in international form, digits only. */
function kay_whatsapp_url(string $phone): ?string
{
    $digits = preg_replace('/\D+/', '', $phone) ?? '';
    return strlen($digits) >= 8 ? 'https://wa.me/' . $digits : null;
}

/* ------------------------------------------------------------------ frame -- */

const KAY_NAV = [
    'index.php'        => ['Tableau de bord', 'Accueil',  'M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z'],
    'planning.php'     => ['Planning',        'Planning', 'M4 6h16v14H4zM4 10h16M8 3v4M16 3v4'],
    'bookings.php'     => ['Réservations',    'Résas',    'M5 4h14v16H5zM9 9h6M9 13h6M9 17h3'],
    'availability.php' => ['Disponibilité',   'Dates',    'M4 6h16v14H4zM4 10h16M8 3v4M16 3v4M9 14l6 4M15 14l-6 4'],
    'customers.php'    => ['Clients',         'Clients',  'M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM2 21a7 7 0 0 1 14 0M17 11a3 3 0 1 0 0-6M22 20a5 5 0 0 0-4-5'],
    'traffic.php'      => ['Trafic',          'Trafic',   'M4 20V10M10 20V4M16 20v-7M22 20H2'],
];

function kay_icon(string $path, string $class = 'icon'): string
{
    return '<svg class="' . $class . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        . '<path d="' . $path . '"/></svg>';
}

/** Versioned by modification time: .htaccess caches CSS and JS for a year. */
function kay_asset(string $file): string
{
    $mtime = @filemtime(__DIR__ . '/../admin/' . $file) ?: @filemtime(__DIR__ . '/../../admin/' . $file) ?: 0;
    return $file . '?v=' . $mtime;
}

function kay_page_start(string $title, string $current, ?array $admin): void
{
    $flash = KAY_FLASH[(string) ($_GET['m'] ?? '')] ?? null;
    echo '<!doctype html><html lang="' . kay_lang() . '"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">'
        . '<meta name="robots" content="noindex,nofollow">'
        . '<meta name="color-scheme" content="light dark">'
        . '<title>' . kay_h($title) . ' · Kay Diving admin</title>'
        . '<link rel="icon" href="/assets/brand/favicon.svg" type="image/svg+xml">'
        . '<link rel="stylesheet" href="' . kay_h(kay_asset('admin.css')) . '">'
        . '<script src="' . kay_h(kay_asset('admin.js')) . '" defer></script>'
        . '</head><body' . ($admin === null ? ' class="is-bare"' : '') . '>';

    if ($admin !== null) {
        echo '<a class="skip" href="#main">' . kay_th('Aller au contenu') . '</a>'
            . '<header class="side"><a class="brand" href="index.php">'
            . '<img src="/assets/brand/icon.svg" alt="" width="34" height="32">'
            . '<span>Kay Diving<small>Admin</small></span></a>'
            . '<nav class="nav" aria-label="' . kay_th('Sections') . '">';
        foreach (KAY_NAV as $page => [$label, $short, $icon]) {
            echo '<a href="' . $page . '"' . ($page === $current ? ' aria-current="page"' : '') . '>'
                . kay_icon($icon) . '<span class="nav__long">' . kay_th($label) . '</span>'
                . '<span class="nav__short">' . kay_th($short) . '</span></a>';
        }
        echo '</nav><div class="side__foot">'
            . '<a href="account.php" class="side__me"' . ($current === 'account.php' ? ' aria-current="page"' : '') . '>'
            . kay_icon('M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21a8 8 0 0 1 16 0')
            . '<span>' . kay_h($admin['name']) . '</span></a>'
            . '<a href="/" class="side__site" target="_blank" rel="noopener">' . kay_th('Voir le site ↗') . '</a>'
            . '</div></header>';
    }

    echo '<main id="main" class="main">';
    if ($flash !== null) {
        echo '<p class="flash" role="status">' . kay_th($flash) . '</p>';
    }
}

function kay_page_end(): void
{
    echo '</main><div class="tip" id="tip" role="tooltip" hidden></div></body></html>';
}

/** A POST form's hidden fields. */
function kay_csrf_field(array $admin): string
{
    return '<input type="hidden" name="_csrf" value="' . kay_h($admin['csrf']) . '">';
}

function kay_error_box(?string $error): string
{
    return $error === null ? '' : '<p class="alert" role="alert">' . kay_h($error) . '</p>';
}

/** « Anterior · 2 / 7 · Siguiente », keeping every other filter. */
function kay_pager(string $page, array $query, int $total, int $current, int $per): string
{
    $pages = (int) max(1, ceil($total / $per));
    if ($pages <= 1) {
        return '';
    }
    $out = '<nav class="pager" aria-label="Pages">';
    $out .= $current > 1
        ? '<a class="btn btn--ghost" href="' . kay_h(kay_url($page, ['p' => $current - 1] + $query)) . '">' . kay_th('← Précédent') . '</a>'
        : '<span></span>';
    $out .= '<span class="muted">' . kay_th('Page {n} / {total}', ['{n}' => (string) $current, '{total}' => (string) $pages]) . '</span>';
    $out .= $current < $pages
        ? '<a class="btn btn--ghost" href="' . kay_h(kay_url($page, ['p' => $current + 1] + $query)) . '">' . kay_th('Suivant →') . '</a>'
        : '<span></span>';
    return $out . '</nav>';
}

/* -------------------------------------------------------------------- csv --
   A BOM, and semicolons in French, commas in Spanish — what Excel expects
   on a computer set to each, so a double-click opens it in columns. Cells a spreadsheet would run as a formula are defused: the names
   come from a public form, and "=HYPERLINK(...)" is a name like any other. */

/**
 * Text a spreadsheet would run as a formula gets a leading quote. A plain
 * number — a negative balance — is left alone.
 */
function kay_csv_cell(mixed $cell): string
{
    $cell = (string) $cell;
    return !is_numeric($cell) && preg_match('/^[=+\-@\t\r]/', $cell) ? "'" . $cell : $cell;
}

function kay_csv(string $filename, array $header, iterable $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^a-z0-9._-]/i', '-', $filename) . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\u{FEFF}");
    $sep = kay_lang() === 'fr' ? ';' : ',';
    fputcsv($out, $header, $sep, '"', '');
    foreach ($rows as $row) {
        fputcsv($out, array_map('kay_csv_cell', $row), $sep, '"', '');
    }
    fclose($out);
    exit;
}

/* ----------------------------------------------------------------- charts --
   One series, one hue (--mark, validated against both surfaces), columns
   growing from a single baseline with a rounded top, a 2px gap between them,
   hairline gridlines, and a data table under every chart so no value is
   reachable only by hovering.                                              */

/** A round axis maximum: 1, 2, 2.5 or 5 times a power of ten. */
function kay_nice_max(int $max): int
{
    if ($max <= 4) {
        return 4;
    }
    $power = 10 ** (int) floor(log10($max));
    foreach ([1, 2, 2.5, 5, 10] as $step) {
        if ($step * $power >= $max) {
            return (int) ($step * $power);
        }
    }
    return $max;
}

/**
 * @param array<int, array{label:string,short?:string,value:int,extra?:string}> $points
 * @param array{unit:string,title:string,mini?:bool,labels?:int,accent?:int} $o
 */
function kay_chart_columns(array $points, array $o): string
{
    $max   = kay_nice_max(max([1, ...array_column($points, 'value')]));
    $mini  = !empty($o['mini']);
    $count = count($points);
    $every = max(1, (int) ceil($count / ($o['labels'] ?? 7)));
    $unit  = $o['unit'];

    $cols = '';
    foreach (array_values($points) as $i => $p) {
        $h = (int) round($p['value'] / $max * 100);
        $tip = kay_int($p['value']) . ' ' . $unit;
        $showLabel = !$mini && ($i % $every === 0 || $i === $count - 1 && $count <= 12);
        $cols .= '<div class="chart__col' . (isset($o['accent']) && $o['accent'] === $i ? ' is-current' : '') . '"'
            . ' tabindex="0" data-tip-title="' . kay_h($p['label']) . '" data-tip-value="' . kay_h($tip) . '"'
            . (isset($p['extra']) ? ' data-tip-extra="' . kay_h($p['extra']) . '"' : '')
            . ' aria-label="' . kay_h($p['label'] . ' : ' . $tip) . '">'
            . '<span class="chart__bar h-' . max($p['value'] > 0 ? 1 : 0, $h) . '"></span>'
            . ($showLabel ? '<span class="chart__x">' . kay_h($p['short'] ?? $p['label']) . '</span>' : '')
            . '</div>';
    }

    $grid = '';
    if (!$mini) {
        foreach ([1, 0.75, 0.5, 0.25, 0] as $f) {
            $grid .= '<span><i>' . kay_h(kay_compact((int) round($max * $f))) . '</i></span>';
        }
    }

    $table = '';
    if (!$mini) {
        $table = '<details class="chart__data"><summary>' . kay_th('Voir les données') . '</summary><div class="table-wrap">'
            . '<table class="table table--compact"><thead><tr><th scope="col">' . kay_th('Période') . '</th>'
            . '<th scope="col" class="num">' . kay_h(ucfirst($unit)) . '</th></tr></thead><tbody>';
        foreach ($points as $p) {
            $table .= '<tr><td>' . kay_h($p['label']) . '</td><td class="num">' . kay_int($p['value'])
                . (isset($p['extra']) ? ' <span class="muted">· ' . kay_h($p['extra']) . '</span>' : '')
                . '</td></tr>';
        }
        $table .= '</tbody></table></div></details>';
    }

    return '<figure class="chart' . ($mini ? ' chart--mini' : '') . '">'
        . '<div class="chart__plot" role="group" aria-label="' . kay_h($o['title']) . '">'
        . ($mini ? '' : '<div class="chart__grid" aria-hidden="true">' . $grid . '</div>')
        . '<div class="chart__cols">' . $cols . '</div></div>'
        . $table . '</figure>';
}

/** A thin bar behind a table cell's label: share of the largest value. */
function kay_meter_cell(string $labelHtml, int $value, int $max): string
{
    $w = $max > 0 ? (int) round($value / $max * 100) : 0;
    return '<td class="meter"><span class="meter__bar w-' . $w . '" aria-hidden="true"></span>'
        . '<span class="meter__label">' . $labelHtml . '</span></td>';
}

/**
 * A stat tile: label, value, and optionally the change against the previous
 * period — signed, with an arrow and the words, never colour alone.
 */
function kay_tile(string $label, string $value, ?int $now = null, ?int $before = null, string $note = ''): string
{
    $delta = '';
    if ($now !== null && $before !== null) {
        if ($before === 0) {
            $delta = $now > 0 ? '<span class="delta delta--up">▲ ' . kay_th('nouveau') . '</span>' : '';
        } else {
            $change = ($now - $before) / $before;
            $class  = $change > 0.005 ? 'up' : ($change < -0.005 ? 'down' : 'flat');
            $arrow  = ['up' => '▲', 'down' => '▼', 'flat' => '■'][$class];
            $delta  = '<span class="delta delta--' . $class . '">' . $arrow . ' '
                . ($change > 0 ? '+' : '') . kay_pct($change) . '</span>';
        }
    }
    return '<div class="tile"><p class="tile__label">' . kay_h($label) . '</p>'
        . '<p class="tile__value">' . $value . '</p>'
        . ($delta !== '' || $note !== '' ? '<p class="tile__note">' . $delta . ($note !== '' ? ' <span>' . kay_h($note) . '</span>' : '') . '</p>' : '')
        . '</div>';
}

/* ---------------------------------------------------------- booking form --
   The same fields for a new booking and an edit. The price beside them is
   only a preview, computed by admin.js from the catalogue embedded below;
   the server re-quotes from products.json whatever the browser shows.     */

/**
 * @param array $v current values, keyed like kay_booking_input()'s clean array
 * @param bool  $withContact name, email, phone and language (a new booking)
 */
function kay_booking_fields(array $v, bool $withContact): string
{
    $cat    = kay_catalogue();
    $item   = ($v['product'] ?? '') . ':' . ($v['option'] ?? '');
    $prices = ['items' => [], 'pickups' => []];

    $out = '<div class="grid">';
    $out .= '<label class="field field--wide"><span>' . kay_th('Sortie') . '</span><select name="item" data-quote-item required>';
    foreach ($cat['products'] as $p) {
        $out .= '<optgroup label="' . kay_h(kay_product_name($p['slug'])) . '">';
        foreach ($p['options'] as $o) {
            $key = $p['slug'] . ':' . $o['dives'];
            $prices['items'][$key] = (int) $o['priceMxn'];
            $out .= '<option value="' . kay_h($key) . '"' . ($key === $item ? ' selected' : '') . '>'
                . kay_h(kay_product_name($p['slug']) . ' — ' . kay_dives_text((int) $o['dives'])
                    . ' · ' . kay_t('{price} / pers.', ['{price}' => kay_mxn((int) $o['priceMxn'])]))
                . '</option>';
        }
        $out .= '</optgroup>';
    }
    $out .= '</select></label>';

    $out .= '<label class="field"><span>' . kay_th('Date') . '</span><input type="date" name="date" value="'
        . kay_h($v['date'] ?? '') . '" required></label>';

    $out .= '<label class="field"><span>' . kay_th('Départ') . '</span><select name="slot">';
    foreach ($cat['schedules'] as $s) {
        $out .= '<option value="' . kay_h($s['slug']) . '"' . (($v['slot'] ?? '0800') === $s['slug'] ? ' selected' : '') . '>'
            . kay_h($s['start'] !== null ? $s['start'] . ' – ' . $s['end'] : kay_t('Autre horaire')) . '</option>';
    }
    $out .= '</select></label>';

    $out .= '<label class="field"><span>' . kay_th('Horaire souhaité') . ' <small>' . kay_th('(si autre)') . '</small></span>'
        . '<input name="slotNote" maxlength="120" value="' . kay_h($v['slotNote'] ?? '') . '"></label>';

    $out .= '<label class="field"><span>' . kay_th('Plongeurs') . '</span><input type="number" name="divers" min="1" max="8" value="'
        . (int) ($v['divers'] ?? 1) . '" data-quote-divers required></label>';

    $out .= '<label class="field"><span>' . kay_th('Transport') . '</span><select name="pickup" data-quote-pickup>';
    foreach ($cat['pickups'] as $p) {
        $prices['pickups'][$p['slug']] = (int) $p['priceMxn'];
        $out .= '<option value="' . kay_h($p['slug']) . '"' . (($v['pickup'] ?? 'meeting-point') === $p['slug'] ? ' selected' : '') . '>'
            . kay_h(kay_pickup_name($p['slug']) . ((int) $p['priceMxn'] > 0 ? ' (+' . kay_mxn((int) $p['priceMxn']) . ')' : ''))
            . '</option>';
    }
    $out .= '</select></label>';

    $out .= '<label class="field"><span>' . kay_th('Niveau') . '</span><input name="cert" list="certs" maxlength="64" value="'
        . kay_h($v['cert'] ?? '') . '"></label>' . kay_certs_datalist();

    $out .= '<label class="field"><span>' . kay_th('Nom du client') . '</span><input name="name" maxlength="160" value="'
        . kay_h($v['name'] ?? '') . '" required></label>';
    $out .= '<label class="field"><span>' . kay_th('E-mail') . ' <small>' . kay_th('(facultatif)') . '</small></span>'
        . '<input type="email" name="email" value="' . kay_h($v['email'] ?? '') . '"></label>';

    if ($withContact) {
        $out .= '<label class="field"><span>' . kay_th('Téléphone / WhatsApp') . '</span><input type="tel" name="phone" maxlength="40" value="'
            . kay_h($v['phone'] ?? '') . '" placeholder="+52 …"></label>';
        $out .= '<label class="field"><span>' . kay_th('Langue des e-mails') . '</span>' . kay_site_lang_select($v['locale'] ?? 'en') . '</label>';
    }
    $out .= '</div>';

    $out .= '<p class="quote" data-quote data-label="' . kay_th('Prix du catalogue :') . '" data-locale="'
        . (kay_lang() === 'fr' ? 'fr-FR' : 'es-MX') . '" aria-live="polite"></p>'
        . '<script type="application/json" id="kay-prices">'
        . json_encode($prices, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';
    return $out;
}

/** The certification names divers pick on the site, in the admin's language. */
function kay_certs_datalist(): string
{
    $out = '<datalist id="certs">';
    foreach ((array) (kay_messages(kay_lang())['book']['certs'] ?? []) as $cert) {
        $out .= '<option value="' . kay_h($cert) . '">';
    }
    return $out . '</datalist>';
}

/** English, Spanish or French: the language a diver's emails are written in. */
function kay_site_lang_select(string $current): string
{
    $out = '<select name="locale">';
    foreach (KAY_SITE_LANGS as $code => $label) {
        $out .= '<option value="' . $code . '"' . ($current === $code ? ' selected' : '') . '>' . kay_th($label) . '</option>';
    }
    return $out . '</select>';
}

/* -------------------------------------------------------------- timeline -- */

/** Notes, calls, follow-ups and the automatic log, newest first. */
function kay_timeline(array $notes, array $admin, string $back): string
{
    if ($notes === []) {
        return '<p class="empty">' . kay_th('Rien pour l’instant.') . '</p>';
    }
    $out = '<ol class="timeline">';
    foreach ($notes as $n) {
        $task = $n['kind'] === 'task';
        $done = $task && $n['done_at'] !== null;
        $out .= '<li class="timeline__item timeline__item--' . kay_h($n['kind']) . ($done ? ' is-done' : '') . '">'
            . '<p class="timeline__meta"><span class="tag tag--' . kay_h($n['kind']) . '">'
            . kay_th(KAY_NOTE_KINDS[$n['kind']] ?? $n['kind']) . '</span> '
            . kay_h(kay_when($n['created_at'])) . ($n['author'] ? ' · ' . kay_h($n['author']) : '')
            . ($task ? ' · ' . kay_th('pour le {date}', ['{date}' => kay_date_short((string) $n['due_on'])]) : '') . '</p>'
            . '<p class="timeline__body">' . nl2br(kay_h($n['body'])) . '</p>';
        if ($task) {
            $out .= '<form method="post" action="note.php">' . kay_csrf_field($admin)
                . '<input type="hidden" name="action" value="' . ($done ? 'undo' : 'done') . '">'
                . '<input type="hidden" name="id" value="' . (int) $n['id'] . '">'
                . '<input type="hidden" name="back" value="' . kay_h($back) . '">'
                . '<button class="btn btn--small ' . ($done ? 'btn--ghost">' . kay_th('Rouvrir') : 'btn--primary">' . kay_th('Fait'))
                . '</button></form>';
        }
        $out .= '</li>';
    }
    return $out . '</ol>';
}

/* --------------------------------------------------------- customer form -- */

/** Where Kay's divers mostly come from, for the country suggestions. */
const KAY_COMMON_COUNTRIES = ['US', 'CA', 'MX', 'FR', 'ES', 'GB', 'DE', 'IT', 'NL', 'BE', 'CH', 'AT',
                              'PT', 'IE', 'SE', 'DK', 'NO', 'PL', 'AR', 'BR', 'CO', 'CL', 'PE', 'AU', 'IL'];

function kay_customer_fields(array $v): string
{
    $input = static fn(string $name, string $label, string $type = 'text', string $extra = ''): string =>
        '<label class="field"><span>' . $label . '</span><input type="' . $type . '" name="' . $name . '" value="'
        . kay_h($v[$name] ?? '') . '"' . $extra . '></label>';

    $out = $input('name', kay_th('Nom'), 'text', ' maxlength="160" required')
        . $input('email', kay_th('E-mail'), 'email')
        . $input('phone', kay_th('Téléphone / WhatsApp'), 'tel', ' maxlength="40" placeholder="+1 …"')
        . $input('country', kay_th('Pays') . ' <small>' . kay_th('(code, ex. CA)') . '</small>', 'text',
                 ' maxlength="2" list="countries" autocapitalize="characters"')
        . '<datalist id="countries">';
    foreach (KAY_COMMON_COUNTRIES as $cc) {
        $out .= '<option value="' . $cc . '">' . kay_h(kay_country($cc)) . '</option>';
    }
    $out .= '</datalist><label class="field"><span>' . kay_th('Langue') . '</span>'
        . kay_site_lang_select((string) ($v['locale'] ?? 'en')) . '</label>'
        . $input('certification', kay_th('Niveau'), 'text', ' maxlength="64" list="certs"')
        . kay_certs_datalist()
        . '<label class="field"><span>' . kay_th('Étiquettes') . ' <small>' . kay_th('(séparées par des virgules)') . '</small></span>'
        . '<input name="tags" value="' . kay_h(str_replace(',', ', ', (string) ($v['tags'] ?? ''))) . '" placeholder="'
        . kay_th('vip, nitrox, groupe') . '"></label>'
        . '<label class="field"><span>' . kay_th('Notes permanentes') . '</span><textarea name="notes" rows="4" '
        . 'placeholder="' . kay_th('Taille de combinaison, allergies, préférences…') . '">' . kay_h($v['notes'] ?? '') . '</textarea></label>';
    return $out;
}

/** "Español · Français" on the pages seen before signing in. */
function kay_lang_links(): string
{
    $out = [];
    foreach (KAY_ADMIN_LANGS as $code => $name) {
        $out[] = $code === kay_lang()
            ? '<strong>' . kay_h($name) . '</strong>'
            : '<a href="?lang=' . $code . '" hreflang="' . $code . '" lang="' . $code . '">' . kay_h($name) . '</a>';
    }
    return '<p class="langs small">' . implode(' · ', $out) . '</p>';
}
