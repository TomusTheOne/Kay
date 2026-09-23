<?php
declare(strict_types=1);

/**
 * The traffic beacon. The page posts one small JSON body per view with
 * navigator.sendBeacon(); see lib/traffic.php for what is kept and what is
 * deliberately not.
 *
 * Whatever happens the answer is an empty 204: the browser does not read it,
 * and a stranger probing the endpoint learns nothing from it. A failure here
 * is logged and never becomes the visitor's problem.
 */

require __DIR__ . '/lib/config.php';
require __DIR__ . '/lib/pricing.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/traffic.php';

function kay_track_done(): never
{
    http_response_code(204);
    header('Cache-Control: no-store');
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit;
}

// A beacon is a few hundred bytes. Anything much larger is not one of ours.
$raw = file_get_contents('php://input', false, null, 0, 4096);
if ($raw === false || $raw === '' || strlen($raw) > 2048) {
    kay_track_done();
}
$input = json_decode($raw, true);
if (!is_array($input)) {
    kay_track_done();
}

try {
    kay_track_event(kay_db(), $input, [
        'ip'      => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        'ua'      => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
        'host'    => (string) ($_SERVER['HTTP_HOST'] ?? ''),
        // Set on login to the admin, scoped to /api/: Kay's own browsing.
        'notrack' => ($_COOKIE['kay_notrack'] ?? '') === '1',
    ]);
} catch (PDOException $e) {
    // 42S02, no such table: expected until the admin is first opened after
    // the deploy that added it, which is when the tables are created. Not
    // worth a line in the error log on every page view until then.
    if ($e->getCode() !== '42S02') {
        error_log('kay: traffic not recorded: ' . $e->getMessage());
    }
} catch (Throwable $e) {
    error_log('kay: traffic not recorded: ' . $e->getMessage());
}

kay_track_done();
