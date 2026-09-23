<?php
declare(strict_types=1);

/**
 * The admin's language. Spanish, because that is what the people running
 * the shop in Tulum read; French for anyone who picks it on the Account page.
 *
 * The French text in the code is the key and Spanish is looked up from
 * i18n-es.php — gettext's model, without gettext, which a shared host may
 * not have. A string with no translation shows in French rather than
 * breaking the page, and the test runner fails if any string in the admin
 * is missing from the dictionary, so that never ships.
 *
 * Only the admin goes through here. The website and the emails to divers
 * have their own copy, in messages/<locale>.json.
 */

const KAY_ADMIN_LANGS = ['es' => 'Español', 'fr' => 'Français'];

/**
 * The language in force, optionally setting it. Until something sets it —
 * the signed-in account's choice — it is the browser's last choice, kept in
 * a cookie so the login page speaks the same language, or Spanish.
 */
function kay_lang(?string $set = null): string
{
    static $lang = null;
    if ($set !== null && isset(KAY_ADMIN_LANGS[$set])) {
        $lang = $set;
    }
    if ($lang === null) {
        $cookie = (string) ($_COOKIE['kay_lang'] ?? '');
        $lang = isset(KAY_ADMIN_LANGS[$cookie]) ? $cookie : 'es';
    }
    return $lang;
}

/** For IntlDateFormatter and Locale: Mexico's Spanish, France's French. */
function kay_intl_locale(): string
{
    return kay_lang() === 'fr' ? 'fr_FR' : 'es_MX';
}

/** The dictionary, loaded once and only when Spanish is in use. */
function kay_es(): array
{
    static $es = null;
    return $es ??= require __DIR__ . '/i18n-es.php';
}

/**
 * Text in the admin's language. {placeholders} are filled after the lookup,
 * so a translation may put them in a different order.
 */
function kay_t(string $fr, array $vars = []): string
{
    $text = kay_lang() === 'es' ? (kay_es()[$fr] ?? $fr) : $fr;
    return $vars === [] ? $text : strtr($text, $vars);
}

/** The same, escaped for HTML. */
function kay_th(string $fr, array $vars = []): string
{
    return htmlspecialchars(kay_t($fr, $vars), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Singular or plural, with the count in {n}. French treats 0 as singular,
 * Spanish as plural: "0 réservation", "0 reservaciones".
 */
function kay_tn(int $n, string $one, string $many, array $vars = []): string
{
    $plural = kay_lang() === 'fr' ? $n > 1 : $n !== 1;
    return kay_t($plural ? $many : $one, ['{n}' => kay_int($n)] + $vars);
}
