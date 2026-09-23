<?php
declare(strict_types=1);

require __DIR__ . '/_boot.php';

// POST only, with the token: a link or an image elsewhere cannot sign Kay out.
$admin = kay_current_admin($db);
if ($admin !== null && kay_posted($admin)) {
    kay_session_close($db);
}
kay_redirect('login.php');
