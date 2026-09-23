<?php
declare(strict_types=1);

/**
 * The schema, as numbered steps applied once each and recorded in
 * schema_migrations.
 *
 * Why not "run this SQL in phpMyAdmin" again: the admin space adds five
 * tables and changes a sixth, and every future change would be another
 * copy-paste for someone who is running a dive shop. The admin applies
 * whatever is missing the first time it is opened after a deploy, so the
 * database follows the code without anyone being asked to do anything.
 *
 * Rules every step follows, because MySQL commits each DDL statement on its
 * own and a half-applied step cannot be rolled back:
 *
 *   - one ALTER per table per step (InnoDB applies it as a unit), or
 *   - CREATE TABLE IF NOT EXISTS, which is safe to repeat.
 *
 * Nothing here ever drops or rewrites a column that holds bookings.
 *
 * The payment endpoints never call this. booking.php writes exactly the
 * columns schema.sql has always had, so a deploy that lands before anyone
 * opens the admin cannot cost a booking.
 */

/** @return array<int, string[]> version => statements */
function kay_migrations(): array
{
    return [
        // The table the site has always had. Already there on the live
        // database, where IF NOT EXISTS makes this a no-op; on a fresh one
        // it means the admin's setup page is the only step.
        1 => kay_sql_statements((string) file_get_contents(__DIR__ . '/../schema.sql')),

        // What the admin needs from a booking.
        //   confirmed  booked or confirmed by Kay himself — phone, WhatsApp,
        //              walk-in — rather than through Mercado Pago
        //   completed  the dive happened
        //   no_show    it did not, and the deposit stays
        // The webhook never moves a row into any of these, so none of them
        // can be reached, or undone, by a notification.
        2 => [
            "ALTER TABLE bookings
               MODIFY status ENUM('pending','paid','confirmed','completed','no_show','cancelled','refunded')
                      NOT NULL DEFAULT 'pending',
               ADD COLUMN customer_id INT UNSIGNED NULL,
               ADD COLUMN source      VARCHAR(16)  NOT NULL DEFAULT 'web',
               ADD COLUMN updated_at  DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
               ADD KEY bookings_customer_idx (customer_id),
               ADD KEY bookings_created_idx  (created_at)",
        ],

        // The CRM: one row per person, and everything said or planned about
        // them. Email is the natural key for anyone who booked online; it is
        // NULL for someone Kay only knows by WhatsApp, and a unique index
        // allows any number of NULLs.
        3 => [
            "CREATE TABLE IF NOT EXISTS customers (
               id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
               email          VARCHAR(190) NULL,
               name           VARCHAR(160) NOT NULL DEFAULT '',
               phone          VARCHAR(40)  NOT NULL DEFAULT '',
               country        CHAR(2)      NOT NULL DEFAULT '',
               locale         CHAR(2)      NOT NULL DEFAULT 'en',
               certification  VARCHAR(64)  NOT NULL DEFAULT '',
               tags           VARCHAR(255) NOT NULL DEFAULT '',
               notes          TEXT         NULL,
               created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
               updated_at     DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
               UNIQUE KEY customers_email_uq (email),
               KEY customers_name_idx (name)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // One timeline for notes, calls, messages, follow-ups and the
            // automatic log of what changed on a booking. A follow-up is a
            // row with a due date; it leaves the dashboard when done_at is set.
            "CREATE TABLE IF NOT EXISTS crm_notes (
               id           INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
               customer_id  INT UNSIGNED NULL,
               booking_id   CHAR(36)     NULL,
               author_id    INT UNSIGNED NULL,
               kind         VARCHAR(16)  NOT NULL DEFAULT 'note',
               body         TEXT         NOT NULL,
               due_on       DATE         NULL,
               done_at      DATETIME     NULL,
               created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
               KEY crm_notes_customer_idx (customer_id, created_at),
               KEY crm_notes_booking_idx  (booking_id),
               KEY crm_notes_due_idx      (kind, done_at, due_on)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // Money that did not come through Mercado Pago: the cash deposit
            // on a WhatsApp booking, the balance paid on the boat, a refund.
            // The online deposit stays where the webhook writes it, on the
            // booking; what a booking has received is that deposit, once
            // paid_at is set, plus the sum of these rows. A ledger rather
            // than a "balance paid" flag, because a flag cannot say how much,
            // how, or that it happened twice.
            "CREATE TABLE IF NOT EXISTS booking_payments (
               id                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
               booking_id        CHAR(36)     NOT NULL,
               kind              VARCHAR(16)  NOT NULL,
               amount_mxn_cents  INT          NOT NULL,
               method            VARCHAR(16)  NOT NULL DEFAULT '',
               author_id         INT UNSIGNED NULL,
               created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
               KEY booking_payments_booking_idx (booking_id),
               KEY booking_payments_created_idx (created_at)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ],

        // Who may open the admin. Sessions are rows rather than PHP's own
        // files: the shared host's session garbage collector would otherwise
        // log Kay out every 24 minutes, and a row can be revoked.
        4 => [
            "CREATE TABLE IF NOT EXISTS admin_users (
               id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
               email          VARCHAR(190) NOT NULL,
               name           VARCHAR(120) NOT NULL,
               password_hash  VARCHAR(255) NOT NULL,
               created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
               last_login_at  DATETIME     NULL,
               UNIQUE KEY admin_users_email_uq (email)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            // Only the SHA-256 of the cookie is stored, so a leaked database
            // dump does not hand out live sessions.
            "CREATE TABLE IF NOT EXISTS admin_sessions (
               token_hash    CHAR(64)     NOT NULL PRIMARY KEY,
               user_id       INT UNSIGNED NOT NULL,
               csrf          CHAR(64)     NOT NULL,
               user_agent    VARCHAR(255) NOT NULL DEFAULT '',
               created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
               last_seen_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
               expires_at    DATETIME     NOT NULL,
               KEY admin_sessions_user_idx (user_id),
               KEY admin_sessions_expiry_idx (expires_at)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS admin_login_attempts (
               id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
               ip_hash     CHAR(64)     NOT NULL,
               email       VARCHAR(190) NOT NULL DEFAULT '',
               created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
               KEY admin_attempts_ip_idx    (ip_hash, created_at),
               KEY admin_attempts_email_idx (email, created_at)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ],

        // Traffic. No IP address and no cookie: `visitor` is a hash of the
        // IP and browser with a salt that exists for one day and is then
        // deleted, so it can count people today and can never be traced back
        // to one — not tomorrow, and not by whoever holds the database.
        5 => [
            "CREATE TABLE IF NOT EXISTS page_views (
               id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
               day           DATE         NOT NULL,
               created_at    DATETIME     NOT NULL,
               visitor       CHAR(16)     NOT NULL,
               event         VARCHAR(32)  NOT NULL DEFAULT 'pageview',
               path          VARCHAR(191) NOT NULL,
               locale        CHAR(2)      NOT NULL DEFAULT '',
               entry         TINYINT(1)   NOT NULL DEFAULT 0,
               source        VARCHAR(100) NOT NULL DEFAULT '',
               referrer      VARCHAR(191) NOT NULL DEFAULT '',
               utm_medium    VARCHAR(100) NOT NULL DEFAULT '',
               utm_campaign  VARCHAR(100) NOT NULL DEFAULT '',
               country       CHAR(2)      NOT NULL DEFAULT '',
               lang          CHAR(2)      NOT NULL DEFAULT '',
               device        VARCHAR(8)   NOT NULL DEFAULT '',
               browser       VARCHAR(20)  NOT NULL DEFAULT '',
               os            VARCHAR(20)  NOT NULL DEFAULT '',
               product       VARCHAR(64)  NOT NULL DEFAULT '',
               KEY page_views_day_idx     (day, event),
               KEY page_views_visitor_idx (day, visitor),
               KEY page_views_created_idx (created_at)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "CREATE TABLE IF NOT EXISTS traffic_salts (
               day   DATE     NOT NULL PRIMARY KEY,
               salt  CHAR(64) NOT NULL
             ) ENGINE=InnoDB",
        ],

        // Days Kay does not go out: the website refuses them, the admin
        // shows them. One row per closed day, so closing a fortnight is
        // fourteen rows and reopening one day in the middle is one delete.
        // The reason is Kay's own note and never leaves the admin.
        //
        // And the admin's language, per account: Spanish unless someone
        // chooses French. The CREATE comes first: it is safe to repeat, so
        // if the ALTER after it fails, running the step again is harmless.
        6 => [
            "CREATE TABLE IF NOT EXISTS closed_days (
               day         DATE         NOT NULL PRIMARY KEY,
               reason      VARCHAR(120) NOT NULL DEFAULT '',
               author_id   INT UNSIGNED NULL,
               created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

            "ALTER TABLE admin_users ADD COLUMN locale CHAR(2) NOT NULL DEFAULT 'es'",
        ],
    ];
}

/** Splits a .sql file into statements, dropping comment lines. */
function kay_sql_statements(string $sql): array
{
    $lines = array_filter(
        explode("\n", $sql),
        static fn(string $line): bool => !str_starts_with(ltrim($line), '--'),
    );
    return array_values(array_filter(
        array_map('trim', explode(';', implode("\n", $lines))),
        static fn(string $s): bool => $s !== '',
    ));
}

function kay_schema_version(PDO $db): int
{
    $db->exec(
        'CREATE TABLE IF NOT EXISTS schema_migrations (
           version     INT UNSIGNED NOT NULL PRIMARY KEY,
           applied_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
         ) ENGINE=InnoDB'
    );
    return (int) $db->query('SELECT COALESCE(MAX(version), 0) FROM schema_migrations')->fetchColumn();
}

/**
 * Applies every step newer than the database. Cheap when there is nothing to
 * do — one indexed read — so the admin calls it on every request.
 *
 * @return int[] the versions applied by this call
 */
function kay_migrate(PDO $db): array
{
    $all = kay_migrations();
    if (kay_schema_version($db) >= max(array_keys($all))) {
        return [];
    }

    // Two tabs opened after a deploy must not both run the same ALTER: the
    // second would fail on a column the first has just added.
    if ((int) $db->query("SELECT GET_LOCK('kay_migrate', 20)")->fetchColumn() !== 1) {
        throw new RuntimeException('another request is migrating the database');
    }

    $applied = [];
    try {
        // Re-read under the lock: the other tab may have finished meanwhile.
        $current = kay_schema_version($db);
        foreach ($all as $version => $statements) {
            if ($version <= $current) {
                continue;
            }
            foreach ($statements as $statement) {
                $db->exec($statement);
            }
            $db->prepare('INSERT INTO schema_migrations (version) VALUES (?)')->execute([$version]);
            error_log("kay: schema migrated to version $version");
            $applied[] = $version;
        }
    } finally {
        $db->query("SELECT RELEASE_LOCK('kay_migrate')");
    }
    return $applied;
}
