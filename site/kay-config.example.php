<?php
/**
 * Copy this to kay-config.php and place it ONE LEVEL ABOVE www/, so Apache
 * can never serve it. On OVH the FTP account looks like:
 *
 *   /home/xxx/kay-config.php   ← here, not reachable by URL
 *   /home/xxx/www/             ← the web root, where the deploy uploads
 *
 * It is deliberately not in git: it holds live credentials.
 */
return [
    // MySQL, from the OVH control panel → Bases de données
    'db_host' => 'xxxxx.mysql.db',
    'db_name' => 'xxxxx',
    'db_user' => 'xxxxx',
    'db_pass' => '',

    // Mercado Pago → https://www.mercadopago.com.mx/developers/panel
    // Use the PRODUCTION access token, not the test one.
    'mp_access_token'   => '',
    // Webhook signing secret. Without it every notification is rejected.
    'mp_webhook_secret' => '',

    // No deposit rate and no exchange rate here. Both live in
    // content/products.json beside the prices they apply to, so the page and
    // the card are always computed from the same number.

    // No trailing slash.
    'site_url' => 'https://kaydiving.com',

    // ---------------------------------------------------------------- email
    // Confirmations are sent over a provider's HTTPS API, not PHP mail():
    // mail() leaves the message unsigned for kaydiving.com, so Gmail treats
    // a booking confirmation as spam. See docs/DEPLOY.md for the two DNS
    // records the provider asks for.
    //
    //   resend  100/day and 3000/month free, adds nothing to the message
    //   brevo   300/day free forever, adds a "Sent with Brevo" line
    //   off     no email at all; bookings still work
    'mail_provider'  => 'resend',
    'mail_api_key'   => '',
    // What the diver sees as the sender, and replies to. Must be an address
    // on the domain the provider authenticates, or nothing sends.
    'mail_from'      => 'contact@kaydiving.com',
    'mail_from_name' => 'Kay Diving Tulum',
    // Where a new booking lands. This is a different job from the one above:
    // whoever runs the day reads it, and that is often a personal inbox
    // rather than the address printed on the website. Any provider will do —
    // it only receives.
    'mail_to_shop'   => 'manager@example.com',

    // ---------------------------------------------------------------- admin
    // Lets /admin/setup.php create the first admin account, and later reset
    // a forgotten password. At least 20 random characters, e.g. the output of
    //   php -r 'echo bin2hex(random_bytes(16)), "\n";'
    // Leave it empty to switch the setup page off entirely.
    'admin_setup_token' => '',

    // "Today" for the day sheet, the dashboard and the traffic reports.
    'timezone' => 'America/Cancun',
];
