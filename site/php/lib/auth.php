<?php
declare(strict_types=1);

/**
 * Who may open the admin, and for how long.
 *
 * Sessions are rows in admin_sessions, not PHP's own session files. On a
 * shared host those files sit in a directory whose garbage collector runs on
 * other people's settings — the default logs you out after 24 idle minutes —
 * and a file cannot be listed or revoked from a page. A row can.
 *
 * The cookie carries 32 random bytes; the table keeps only their SHA-256, so
 * a copy of the database does not hand out live sessions. The cookie is
 * scoped to /admin/: the public site, and the payment endpoints under /api/,
 * never receive it.
 */

const KAY_ADMIN_COOKIE     = 'kay_admin';
const KAY_SESSION_IDLE     = 7 * 86400;     // a week without opening it
const KAY_SESSION_ABSOLUTE = 30 * 86400;    // and a fresh login every month regardless
const KAY_PASSWORD_MIN     = 10;
// Pinned rather than PASSWORD_DEFAULT's own: PHP 8.2 and 8.4 disagree on the
// default cost, and the decoy hash in kay_login_check() must cost the same
// as a real one or the timing says which emails exist.
const KAY_HASH = ['cost' => 12];

/* --------------------------------------------------------------- request -- */

function kay_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

/** Never stored raw: login throttling only needs to recognise the address. */
function kay_ip_hash(): string
{
    return hash('sha256', 'kay-login|' . ($_SERVER['REMOTE_ADDR'] ?? ''));
}

function kay_set_cookie(string $name, string $value, int $maxAge, string $path): void
{
    if (headers_sent()) {
        return;   // the test runner, which has already printed
    }
    setcookie($name, $value, [
        'expires'  => $maxAge > 0 ? time() + $maxAge : time() - 3600,
        'path'     => $path,
        'secure'   => kay_is_https(),
        'httponly' => true,
        // Lax, not Strict: a link to a booking from Kay's own inbox should
        // open the booking, not the login page. Every form carries a CSRF
        // token anyway, so Lax gives nothing away.
        'samesite' => 'Lax',
    ]);
}

/* -------------------------------------------------------------- sessions -- */

/** @return string the token, which only the cookie ever holds */
function kay_session_open(PDO $db, int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $db->prepare(
        'INSERT INTO admin_sessions (token_hash, user_id, csrf, user_agent, expires_at)
         VALUES (?, ?, ?, ?, NOW() + INTERVAL ? SECOND)'
    )->execute([
        hash('sha256', $token),
        $userId,
        bin2hex(random_bytes(32)),
        mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        KAY_SESSION_IDLE,
    ]);
    $db->prepare('UPDATE admin_users SET last_login_at = NOW() WHERE id = ?')->execute([$userId]);

    kay_set_cookie(KAY_ADMIN_COOKIE, $token, KAY_SESSION_ABSOLUTE, '/admin/');

    // Kay reading his own site should not show up in his own traffic. The
    // cookie goes only to /api/, where the beacon lands, and says nothing
    // but "do not count this browser".
    kay_set_cookie('kay_notrack', '1', 365 * 86400, '/api/');
    return $token;
}

/**
 * The signed-in admin, or null.
 *
 * @return array{id:int,email:string,name:string,csrf:string,token_hash:string}|null
 */
function kay_current_admin(PDO $db): ?array
{
    $token = (string) ($_COOKIE[KAY_ADMIN_COOKIE] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $s = $db->prepare(
        'SELECT u.id, u.email, u.name, s.csrf, s.token_hash,
                s.last_seen_at < NOW() - INTERVAL 5 MINUTE AS stale
           FROM admin_sessions s
           JOIN admin_users u ON u.id = s.user_id
          WHERE s.token_hash = ?
            AND s.expires_at > NOW()
            AND s.created_at > NOW() - INTERVAL ? SECOND'
    );
    $s->execute([hash('sha256', $token), KAY_SESSION_ABSOLUTE]);
    $row = $s->fetch();
    if (!is_array($row)) {
        return null;
    }

    // Sliding expiry, written at most every five minutes rather than on
    // every click.
    if ((int) $row['stale'] === 1) {
        $db->prepare(
            'UPDATE admin_sessions SET last_seen_at = NOW(), expires_at = NOW() + INTERVAL ? SECOND
              WHERE token_hash = ?'
        )->execute([KAY_SESSION_IDLE, $row['token_hash']]);
    }

    unset($row['stale']);
    $row['id'] = (int) $row['id'];
    return $row;
}

function kay_session_close(PDO $db): void
{
    $token = (string) ($_COOKIE[KAY_ADMIN_COOKIE] ?? '');
    if ($token !== '') {
        $db->prepare('DELETE FROM admin_sessions WHERE token_hash = ?')->execute([hash('sha256', $token)]);
    }
    kay_set_cookie(KAY_ADMIN_COOKIE, '', 0, '/admin/');
}

/** Every session but the current one — after a password change, say. */
function kay_sessions_revoke_others(PDO $db, int $userId, string $keepTokenHash = ''): int
{
    $s = $db->prepare('DELETE FROM admin_sessions WHERE user_id = ? AND token_hash <> ?');
    $s->execute([$userId, $keepTokenHash]);
    return $s->rowCount();
}

/* ------------------------------------------------------------------ csrf --
   SameSite already stops most cross-site posts; the token stops the rest,
   and the Origin check catches a browser that sends neither.               */

function kay_csrf_valid(array $admin, string $submitted): bool
{
    // Host and port together: HTTP_HOST carries the port whenever it is not
    // the default one, and so must the origin it is compared with.
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origin !== '') {
        $parts = parse_url($origin);
        $authority = strtolower(($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : ''));
        if ($authority !== strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''))) {
            return false;
        }
    }
    return $submitted !== '' && hash_equals((string) $admin['csrf'], $submitted);
}

/* ----------------------------------------------------------------- login -- */

/** Ten failures from one address, or five against one account, per 15 minutes. */
function kay_login_throttled(PDO $db, string $email): bool
{
    $s = $db->prepare(
        'SELECT
           SUM(ip_hash = ?) AS by_ip,
           SUM(email = ?)   AS by_email
         FROM admin_login_attempts
         WHERE created_at > NOW() - INTERVAL 15 MINUTE'
    );
    $s->execute([kay_ip_hash(), mb_strtolower(trim($email))]);
    $row = $s->fetch() ?: [];
    return (int) ($row['by_ip'] ?? 0) >= 10 || (int) ($row['by_email'] ?? 0) >= 5;
}

function kay_login_failed(PDO $db, string $email): void
{
    $db->prepare('INSERT INTO admin_login_attempts (ip_hash, email) VALUES (?, ?)')
       ->execute([kay_ip_hash(), mb_substr(mb_strtolower(trim($email)), 0, 190)]);
    // Housekeeping, while we are here: nothing older than a day is useful.
    $db->exec('DELETE FROM admin_login_attempts WHERE created_at < NOW() - INTERVAL 1 DAY');
}

/**
 * @return array{ok:bool,user?:array,error?:string}
 */
function kay_login_check(PDO $db, string $email, string $password): array
{
    $email = mb_strtolower(trim($email));
    if (kay_login_throttled($db, $email)) {
        return ['ok' => false, 'error' => 'throttled'];
    }

    $s = $db->prepare('SELECT id, email, name, password_hash FROM admin_users WHERE email = ?');
    $s->execute([$email]);
    $user = $s->fetch();

    // Verify against something even when the account does not exist, so the
    // response time does not say which emails have an account.
    $hash = is_array($user)
        ? (string) $user['password_hash']
        : '$2y$12$uJoFC3wa1CkMeU8Zdg94SuTqv36duslW5lg26s4RGhOENhSacWcG.';
    $good = password_verify($password, $hash) && is_array($user);

    if (!$good) {
        kay_login_failed($db, $email);
        return ['ok' => false, 'error' => 'invalid'];
    }

    if (password_needs_rehash($hash, PASSWORD_DEFAULT, KAY_HASH)) {
        $db->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')
           ->execute([password_hash($password, PASSWORD_DEFAULT, KAY_HASH), $user['id']]);
    }
    $db->prepare('DELETE FROM admin_login_attempts WHERE email = ?')->execute([$email]);
    $db->exec('DELETE FROM admin_sessions WHERE expires_at < NOW()');

    unset($user['password_hash']);
    $user['id'] = (int) $user['id'];
    return ['ok' => true, 'user' => $user];
}

/** Where a login may send you back to: a page of this admin, and nowhere else. */
function kay_safe_next(string $next): string
{
    return preg_match('#^/admin/[a-z-]+\.php(\?[^\s]*)?$#', $next) ? $next : 'index.php';
}

/* ----------------------------------------------------------------- users -- */

/** A short French reason, or null when the password is acceptable. */
function kay_password_problem(string $password, string $email = ''): ?string
{
    if (mb_strlen($password) < KAY_PASSWORD_MIN) {
        return 'Le mot de passe doit faire au moins ' . KAY_PASSWORD_MIN . ' caractères.';
    }
    if ($email !== '' && mb_strtolower($password) === mb_strtolower($email)) {
        return 'Le mot de passe ne peut pas être l’adresse e-mail.';
    }
    return null;
}

function kay_admin_count(PDO $db): int
{
    return (int) $db->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
}

function kay_admin_create(PDO $db, string $email, string $name, string $password): int
{
    $db->prepare('INSERT INTO admin_users (email, name, password_hash) VALUES (?, ?, ?)')
       ->execute([
           mb_strtolower(trim($email)),
           mb_substr(trim($name), 0, 120),
           password_hash($password, PASSWORD_DEFAULT, KAY_HASH),
       ]);
    return (int) $db->lastInsertId();
}

/** Also signs the account out everywhere else: that is usually why it changed. */
function kay_admin_set_password(PDO $db, int $userId, string $password, string $keepTokenHash = ''): void
{
    $db->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')
       ->execute([password_hash($password, PASSWORD_DEFAULT, KAY_HASH), $userId]);
    kay_sessions_revoke_others($db, $userId, $keepTokenHash);
}

/*
 * The phrase that lets someone create the first account, or reset a forgotten
 * password, from /admin/setup.php.
 *
 * It is read from a plain text file, kay-admin-token.txt, uploaded by FTP
 * beside kay-config.php — above the web root, where nothing is ever served.
 * A text file rather than a line in kay-config.php because the people who
 * set this up do it with an FTP client and a desktop text editor: one stray
 * curly quote or missing comma in kay-config.php stops PHP from reading it,
 * and with it every booking. Nothing typed into this file can break
 * anything; at worst the setup page says it found no phrase.
 *
 * Whoever can put a file there can already read the database password, so
 * the phrase gives away nothing they do not have. Delete the file and the
 * setup page is closed. 'admin_setup_token' in kay-config.php still works,
 * for anyone who prefers it.
 */
const KAY_SETUP_TOKEN_FILE = 'kay-admin-token.txt';

/**
 * Beside kay-config.php: the account root, one level above www/. With file
 * extensions hidden, Windows Notepad saves "kay-admin-token.txt" as
 * kay-admin-token.txt.txt, and nobody can see why it is not found — so that
 * name is accepted too.
 */
function kay_setup_token_path(): string
{
    $base = __DIR__ . '/../../../' . KAY_SETUP_TOKEN_FILE;
    return !is_file($base) && is_file($base . '.txt') ? $base . '.txt' : $base;
}

/**
 * True when the file was uploaded into www/ by mistake. There anyone could
 * download it, so it is refused rather than used, and the setup page says
 * where it belongs. (.htaccess answers 404 for it too, and the next deploy
 * would delete it, but neither is a reason to trust it.)
 */
function kay_setup_token_misplaced(): bool
{
    $inWebRoot = __DIR__ . '/../../' . KAY_SETUP_TOKEN_FILE;
    return is_file($inWebRoot) || is_file($inWebRoot . '.txt');
}

/**
 * What the file says, or '' when there is no file. A byte order mark and
 * surrounding blank lines — what Notepad and TextEdit add — are dropped, so
 * the phrase is exactly what was typed.
 */
function kay_setup_token_from_file(string $file): string
{
    if (!is_file($file) || !is_readable($file)) {
        return '';
    }
    $raw = (string) file_get_contents($file, false, null, 0, 1024);
    return trim((string) preg_replace('/^\xEF\xBB\xBF/', '', $raw));
}

/** The phrase, or null when there is none of at least 20 characters. */
function kay_setup_token(): ?string
{
    $token = trim((string) (kay_config()['admin_setup_token'] ?? ''));
    if ($token === '') {
        $token = kay_setup_token_from_file(kay_setup_token_path());
    }
    return mb_strlen($token) >= 20 ? $token : null;
}
