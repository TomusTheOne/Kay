-- Run once against the MySQL database included with the OVH hosting.
-- phpMyAdmin is reachable from the OVH control panel.

CREATE TABLE IF NOT EXISTS bookings (
  id                 CHAR(36)     NOT NULL PRIMARY KEY,

  -- what was booked
  product            VARCHAR(64)  NOT NULL,
  dives              TINYINT      NOT NULL,   -- 0 for the half-day snorkel tour
  dive_date          DATE         NOT NULL,
  divers             TINYINT      NOT NULL,
  certification      VARCHAR(64)  NOT NULL DEFAULT '',
  pickup             VARCHAR(32)  NOT NULL DEFAULT 'meeting-point',
  -- one of the fixed departures, or 'other' when the diver proposed a time
  start_slot         VARCHAR(16)  NOT NULL DEFAULT '0800',
  start_note         VARCHAR(120) NOT NULL DEFAULT '',

  -- who
  name               VARCHAR(160) NOT NULL,
  email              VARCHAR(190) NOT NULL,
  locale             CHAR(2)      NOT NULL DEFAULT 'en',

  -- money, in cents so nothing is ever a float
  total_usd_cents    INT UNSIGNED NOT NULL,
  deposit_usd_cents  INT UNSIGNED NOT NULL,
  deposit_mxn_cents  INT UNSIGNED NOT NULL,

  -- Mercado Pago
  status             ENUM('pending','paid','cancelled','refunded') NOT NULL DEFAULT 'pending',
  preference_id      VARCHAR(64)  NULL,
  payment_id         VARCHAR(64)  NULL,

  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paid_at            DATETIME     NULL,

  KEY bookings_date_idx  (dive_date, status),   -- Kay's day sheet
  KEY bookings_email_idx (email),
  KEY bookings_pref_idx  (preference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
