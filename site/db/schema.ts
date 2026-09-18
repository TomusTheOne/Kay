import {
  pgTable, uuid, text, integer, date, timestamp, index, pgEnum,
} from "drizzle-orm/pg-core";

/**
 * A booking's life: it exists the moment someone opens checkout, so a payment
 * that lands always has a row to attach to. Mercado Pago decides the rest.
 */
export const bookingStatus = pgEnum("booking_status", [
  "pending",    // checkout opened, nothing paid
  "paid",       // deposit approved
  "cancelled",  // payment rejected, or cancelled by hand
  "refunded",
]);

export const bookings = pgTable("bookings", {
  id: uuid("id").primaryKey().defaultRandom(),

  // what was booked
  product: text("product").notNull(),
  /** the chosen size: number of dives, 0 for the half-day snorkel tour */
  dives: integer("dives").notNull(),
  diveDate: date("dive_date").notNull(),
  divers: integer("divers").notNull(),
  certification: text("certification").notNull(),

  // who
  name: text("name").notNull(),
  email: text("email").notNull(),
  locale: text("locale").notNull().default("en"),

  // money, in cents so nothing is ever a float
  totalUsdCents: integer("total_usd_cents").notNull(),
  depositUsdCents: integer("deposit_usd_cents").notNull(),
  depositMxnCents: integer("deposit_mxn_cents").notNull(),

  // Mercado Pago
  status: bookingStatus("status").notNull().default("pending"),
  // set once Mercado Pago has issued it — the row exists first, so a payment
  // can never land on a booking that was never written
  preferenceId: text("preference_id"),
  paymentId: text("payment_id"),

  createdAt: timestamp("created_at", { withTimezone: true }).notNull().defaultNow(),
  paidAt: timestamp("paid_at", { withTimezone: true }),
}, (t) => [
  // the webhook arrives knowing only the preference
  index("bookings_preference_idx").on(t.preferenceId),
  // Kay's day sheet: who is diving, when
  index("bookings_date_idx").on(t.diveDate, t.status),
  index("bookings_email_idx").on(t.email),
]);

export type Booking = typeof bookings.$inferSelect;
export type NewBooking = typeof bookings.$inferInsert;
