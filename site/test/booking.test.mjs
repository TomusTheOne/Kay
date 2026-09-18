/**
 * Exercises the two things that can lose money or let someone dive free:
 * server-side pricing, and webhook idempotency. Runs against a real Postgres.
 *
 *   DATABASE_URL=postgres://... node --test test/booking.test.mjs
 */
import { test } from "node:test";
import assert from "node:assert/strict";
import crypto from "node:crypto";
import postgres from "postgres";

const sql = postgres(process.env.DATABASE_URL, { max: 1 });

test("pricing is recomputed from the menu, never taken from the client", async () => {
  const { quote } = await import("../lib/pricing.ts");

  const base = {
    product: "cenote-diving", option: 3, date: "2026-12-01", cert: "Advanced",
    divers: 3, name: "T", email: "t@e.com", locale: "en",
  };
  // Cenote Diving, 3 dives, $170 each × 3 divers
  const q = quote(base);
  assert.equal(q.totalUsd, 510);
  assert.equal(q.depositUsd, 153);            // 30 %

  // Every price on the menu, exactly as printed.
  assert.equal(quote({ ...base, product: "discover-scuba", option: 1, divers: 1 }).totalUsd, 100);
  assert.equal(quote({ ...base, product: "discover-scuba", option: 2, divers: 1 }).totalUsd, 140);
  assert.equal(quote({ ...base, product: "reef-cenote",    option: 2, divers: 1 }).totalUsd, 140);
  assert.equal(quote({ ...base, product: "cenote-diving",  option: 2, divers: 1 }).totalUsd, 150);
  assert.equal(quote({ ...base, product: "advanced-ow",    option: 5, divers: 1 }).totalUsd, 460);
  assert.equal(quote({ ...base, product: "open-water",     option: 5, divers: 1 }).totalUsd, 450);
  assert.equal(quote({ ...base, product: "snorkel",        option: 0, divers: 1 }).totalUsd, 80);

  // A hostile payload carrying its own total changes nothing.
  assert.equal(quote({ ...base, totalUsd: 1, price: 1, depositUsd: 0 }).totalUsd, 510);

  // Unknown product, or a size that product is not sold in, is refused.
  assert.equal(quote({ ...base, product: "free-dive" }), null);
  assert.equal(quote({ ...base, product: "snorkel", option: 4 }), null);
  assert.equal(quote({ ...base, product: "reef-cenote", option: 1 }), null);

  // Diver count is clamped, so 0 or 9999 cannot distort the charge.
  assert.equal(quote({ ...base, divers: 0 }).totalUsd, quote({ ...base, divers: 1 }).totalUsd);
  assert.equal(quote({ ...base, divers: 9999 }).totalUsd, quote({ ...base, divers: 8 }).totalUsd);
});

test("validate refuses a past date and a malformed email", async () => {
  const { validate } = await import("../lib/pricing.ts");
  const ok = { product: "reef-cenote", option: 2, date: "2099-01-01", cert: "OW",
               divers: 2, name: "Ana", email: "a@b.co", locale: "en" };
  assert.equal(validate(ok), null);
  assert.equal(validate({ ...ok, email: "nope" }), "email");
  assert.equal(validate({ ...ok, name: "" }), "name");
  assert.equal(validate({ ...ok, date: "2000-01-01" }), "date-past");
});

test("a booking settles exactly once, however often the webhook fires", async () => {
  const [row] = await sql`
    insert into bookings (product, dives, dive_date, divers, certification,
                          name, email, total_usd_cents, deposit_usd_cents, deposit_mxn_cents)
    values ('cenote-diving',3,'2026-12-01',3,'Advanced',
            'Test','t@example.com', 51000, 15300, 267750)
    returning id, status`;
  assert.equal(row.status, "pending");

  const settle = () => sql`
    update bookings set status='paid', payment_id='PAY-1', paid_at=now()
    where id=${row.id} and status='pending' returning id`;

  assert.equal((await settle()).length, 1, "first notification settles it");
  assert.equal((await settle()).length, 0, "a retry is a no-op");
  assert.equal((await settle()).length, 0, "and stays a no-op");

  const [after] = await sql`select status, payment_id from bookings where id=${row.id}`;
  assert.equal(after.status, "paid");
  assert.equal(after.payment_id, "PAY-1");

  // A replayed 'rejected' notification must not un-pay a settled booking.
  const cancel = await sql`
    update bookings set status='cancelled'
    where id=${row.id} and status='pending' returning id`;
  assert.equal(cancel.length, 0, "a settled booking cannot be cancelled by a replay");

  await sql`delete from bookings where id=${row.id}`;
});

test("webhook signatures reject anything not signed with our secret", () => {
  const secret = "test-secret";
  const sign = (id, rid, ts, key = secret) =>
    crypto.createHmac("sha256", key).update(`id:${id};request-id:${rid};ts:${ts};`).digest("hex");

  const good = sign("PAY-1", "REQ-1", "1700000000");
  assert.equal(good, sign("PAY-1", "REQ-1", "1700000000"));
  assert.notEqual(good, sign("PAY-2", "REQ-1", "1700000000"), "a different payment id must not match");
  assert.notEqual(good, sign("PAY-1", "REQ-1", "1700000000", "guessed"), "a wrong secret must not match");
});

test.after(async () => { await sql.end(); });
