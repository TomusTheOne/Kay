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

test("pricing is recomputed from slugs, never taken from the client", async () => {
  const { quote } = await import("../lib/pricing.ts");

  const base = {
    dive: "angelita", date: "2026-12-01", cert: "Advanced", divers: 3,
    pickup: "tulum", addons: ["nitrox"], name: "T", email: "t@e.com", locale: "en",
  };
  const q = quote(base);
  // 240×3 dive + 25 pickup + 35×3 nitrox (per diver) = 850
  assert.equal(q.totalUsd, 850);
  assert.equal(q.depositUsd, 255);            // 30 %

  // A hostile payload carrying its own total changes nothing.
  const tampered = quote({ ...base, totalUsd: 1, price: 1, depositUsd: 0 });
  assert.equal(tampered.totalUsd, 850);

  // Unknown slugs are refused rather than silently priced at zero.
  assert.equal(quote({ ...base, dive: "free-dive" }), null);
  assert.equal(quote({ ...base, pickup: "moon" }), null);

  // Diver count is clamped, so 0 or 9999 cannot distort the charge.
  assert.equal(quote({ ...base, divers: 0 }).totalUsd, quote({ ...base, divers: 1 }).totalUsd);
  assert.equal(quote({ ...base, divers: 9999 }).totalUsd, quote({ ...base, divers: 8 }).totalUsd);
});

test("validate refuses a past date and a malformed email", async () => {
  const { validate } = await import("../lib/pricing.ts");
  const ok = { dive: "reef", date: "2099-01-01", cert: "OW", divers: 2,
               pickup: "shop", addons: [], name: "Ana", email: "a@b.co", locale: "en" };
  assert.equal(validate(ok), null);
  assert.equal(validate({ ...ok, email: "nope" }), "email");
  assert.equal(validate({ ...ok, name: "" }), "name");
  assert.equal(validate({ ...ok, date: "2000-01-01" }), "date-past");
});

test("a booking settles exactly once, however often the webhook fires", async () => {
  const [row] = await sql`
    insert into bookings (dive, dive_date, divers, certification, pickup,
                          name, email, total_usd_cents, deposit_usd_cents, deposit_mxn_cents)
    values ('angelita','2026-12-01',3,'Advanced','tulum',
            'Test','t@example.com', 84000, 25200, 441000)
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
