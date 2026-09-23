<?php
declare(strict_types=1);

/**
 * Adds to a timeline, or ticks off a follow-up, then goes back to the page
 * the form was on. Shared by the dashboard, the customer and the booking.
 */

require __DIR__ . '/_boot.php';
$admin = kay_admin($db);

if (!kay_posted($admin)) {
    kay_redirect('index.php');
}

$back = (string) ($_POST['back'] ?? 'index.php');
if (!preg_match('#^[a-z-]+\.php(\?[A-Za-z0-9_=&%.\-]*)?$#', $back)) {
    $back = 'index.php';
}
$join = str_contains($back, '?') ? '&' : '?';

$action = (string) ($_POST['action'] ?? '');

if ($action === 'done' || $action === 'undo') {
    kay_task_done($db, (int) ($_POST['id'] ?? 0), $action === 'done');
    kay_redirect($back . $join . 'm=done');
}

if ($action === 'add') {
    $body = trim((string) ($_POST['body'] ?? ''));
    if ($body !== '') {
        $customerId = (int) ($_POST['customer_id'] ?? 0);
        $bookingId  = (string) ($_POST['booking_id'] ?? '');
        kay_note_add(
            $db,
            $customerId > 0 ? $customerId : null,
            preg_match('/^[a-f0-9-]{36}$/', $bookingId) ? $bookingId : null,
            $admin['id'],
            (string) ($_POST['kind'] ?? 'note'),
            $body,
            ($_POST['due_on'] ?? '') !== '' ? (string) $_POST['due_on'] : null,
        );
    }
    kay_redirect($back . $join . 'm=noted');
}

kay_redirect($back);
