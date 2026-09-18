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

    // Share taken at booking; the balance is settled on the day.
    'deposit_rate' => 0.30,
    // Mercado Pago México settles in MXN; the site quotes USD.
    'usd_to_mxn'   => 17.5,

    // No trailing slash.
    'site_url' => 'https://kaydiving.com',

    // ---------------------------------------------------------------- email
    // Confirmations are sent over a provider's HTTPS API, not PHP mail():
    // mail() leaves the message unsigned for kaydiving.com, so Gmail treats
    // a booking confirmation as spam. See docs/DEPLOY.md for the two DNS
    // records the provider asks for.
    //
    //   brevo   300/day free forever, adds a "Sent with Brevo" line
    //   resend  100/day and 3000/month free, adds nothing
    //   off     no email at all; bookings still work
    'mail_provider'  => 'brevo',
    'mail_api_key'   => '',
    // Must be an address on the authenticated domain.
    'mail_from'      => 'contact@kaydiving.com',
    'mail_from_name' => 'Kay Diving Tulum',
    // Where the day-sheet copy lands. Kay's own inbox, if it differs.
    'mail_to_shop'   => 'contact@kaydiving.com',
];
