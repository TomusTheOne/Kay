# kaydiving.com

Next.js 16 · React 19 · TypeScript. The design is the approved concept A maquette,
ported verbatim — `app/globals.css` is the same hand-written stylesheet.

## Run it

```bash
npm install
cp .env.example .env.local     # fill in the values below
npm run db:migrate
npm run dev
```

## Environment

| Variable | What it is |
|---|---|
| `DATABASE_URL` | Postgres. Neon or Supabase both work; anything speaking the wire protocol will do. |
| `MP_ACCESS_TOKEN` | Mercado Pago access token — [developer panel](https://www.mercadopago.com.mx/developers/panel) |
| `MP_WEBHOOK_SECRET` | Webhook signing secret from the same panel. Without it every notification is rejected. |
| `DEPOSIT_RATE` | Share taken up front. `0.3` = 30 %, balance on the day. |
| `USD_TO_MXN` | The site quotes USD; Mercado Pago México settles MXN. |

The site builds and renders without any of these — only the booking routes need them.

## How a booking works

1. The form posts choices to `POST /api/booking`. **Never amounts.**
2. `lib/pricing.ts` recomputes the total from slugs, so a tampered payload cannot
   lower the charge. Unknown slugs are refused; diver count is clamped to 1–8.
3. A `pending` row is written **before** checkout is created, so a payment always
   has a booking to attach to.
4. Mercado Pago Checkout Pro is created with `external_reference` = the booking id,
   and the diver is redirected. No card data ever reaches this site.
5. `POST /api/mp-webhook` verifies the `x-signature` HMAC, then asks Mercado Pago
   what the payment actually did rather than trusting the notification body.
6. The settle is `WHERE id = ? AND status = 'pending'`, so retries and replays are
   no-ops. A settled booking cannot be un-paid by a stale notification.

## Tests

```bash
DATABASE_URL=postgres://... npm test
```

Covers the two things that can lose money or let someone dive free: server-side
pricing, and webhook idempotency. Runs against a real Postgres.

## Adding a language

`lib/i18n.ts` holds the locale list; `messages/<locale>.json` holds the copy. The
`Dictionary` type is declared rather than inferred, so a missing key fails the
build instead of printing `undefined` to a visitor.

**`es.json` and `fr.json` are currently English copies** flagged `_translated: false`.
The routing, metadata and `hreflang` are live; the words are not.

## Known gaps

- No confirmation email yet — marked `TODO(email)` in the webhook.
- No admin view. Kay reads bookings from the database until one exists.
- The payment flow cannot be exercised end to end without a public HTTPS origin
  for the webhook, so it is untested against the real Mercado Pago.
- Mercado Pago level 3 caps at roughly 10,000 UDIS (~87,000 MXN) a month. Raising
  it needs an RFC.
