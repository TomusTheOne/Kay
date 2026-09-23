<?php
declare(strict_types=1);

/**
 * Loaded first by every admin page. Never served on its own: .htaccess
 * answers 404 for anything under /admin/ that starts with an underscore.
 *
 * Deployed, this directory is www/admin/ and the library is www/api/lib/,
 * shared with the payment endpoints. In the repository the library is
 * php/lib/, one level up. Same code either way.
 */

$kayLib = is_dir(__DIR__ . '/../api/lib') ? __DIR__ . '/../api/lib' : __DIR__ . '/../lib';
foreach (['config', 'pricing', 'db', 'mail', 'notify', 'mercadopago', 'migrate', 'i18n', 'auth',
          'availability', 'bookings', 'crm', 'traffic', 'view'] as $kayFile) {
    require_once "$kayLib/$kayFile.php";
}
unset($kayLib, $kayFile);

// This page lists every customer's name, email and phone. It is never
// cached, never indexed, never framed, and runs nothing it did not serve.
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; "
    . "img-src 'self' data:; connect-src 'self'; font-src 'self'; object-src 'none'; "
    . "base-uri 'none'; form-action 'self'; frame-ancestors 'none'");

// ?lang=fr or ?lang=es, from the links on the login and setup pages. Kept
// in a cookie so the next page speaks the same language; once someone signs
// in, their account's choice takes over.
$kayLang = (string) ($_GET['lang'] ?? '');
if (isset(KAY_ADMIN_LANGS[$kayLang])) {
    kay_lang($kayLang);
    kay_set_cookie('kay_lang', $kayLang, 365 * 86400, '/admin/');
}
unset($kayLang);

$db = kay_db();

// The schema follows the code: whatever a deploy added is applied the first
// time the admin is opened. One indexed read when there is nothing to do.
try {
    kay_migrate($db);
} catch (Throwable $e) {
    error_log('kay: migration failed: ' . $e->getMessage());
    http_response_code(503);
    kay_page_start(kay_t('Maintenance'), '', null);
    echo '<section class="auth"><h1>' . kay_th('Mise à jour de la base en cours') . '</h1>'
        . '<p>' . kay_th('Rechargez la page dans une minute. Si le message reste, le détail est dans le journal d’erreurs de l’hébergement (ligne commençant par « kay: »).')
        . '</p></section>';
    kay_page_end();
    exit;
}

/**
 * The signed-in admin, or a redirect to the login page. Also attaches the
 * latest website bookings to their customer, so the CRM is current on every
 * page without the payment path knowing it exists.
 */
function kay_admin(PDO $db): array
{
    $admin = kay_current_admin($db);
    if ($admin === null) {
        // Back to the page asked for, unless it was the dashboard anyway.
        $uri  = (string) ($_SERVER['REQUEST_URI'] ?? '');
        $path = (string) parse_url($uri, PHP_URL_PATH);
        $next = preg_match('#/[a-z-]+\.php$#', $path) && basename($path) !== 'index.php' ? $uri : '';
        kay_redirect(kay_url('login.php', ['next' => $next]));
    }
    kay_lang($admin['locale']);
    try {
        kay_crm_sync($db);
    } catch (Throwable $e) {
        error_log('kay: crm sync failed: ' . $e->getMessage());
    }
    return $admin;
}

/**
 * True for a POST that carries this session's CSRF token. A POST without
 * one stops here: it did not come from one of these pages.
 */
function kay_posted(array $admin): bool
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return false;
    }
    if (!kay_csrf_valid($admin, (string) ($_POST['_csrf'] ?? ''))) {
        http_response_code(400);
        kay_page_start(kay_t('Formulaire expiré'), '', $admin);
        echo '<section class="card"><h1>' . kay_th('Le formulaire a expiré') . '</h1>'
            . '<p>' . kay_th('Revenez en arrière, rechargez la page et recommencez.') . '</p></section>';
        kay_page_end();
        exit;
    }
    return true;
}
