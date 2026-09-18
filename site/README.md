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

## Where the content comes from

Every price, depth, location and inclusion in `content/products.ts` is taken from
Kay's 2026 menu PDF. Prose is theirs where they wrote it — the About and Why Us
paragraphs, the taglines — and generic where it is educational (halocline, cavern
versus cave, water temperature).

Three things in the menu were wrong or contradicted themselves, and Kay settled
each one:

- **Discover Scuba** — the blurb says "maximum depth of 7 metres / 25 ft", the spec
  says `DEPHTH: 30 FT`. Kay confirms the blurb; the site uses **7 m / 25 ft**.
- **Transport** — the menu prints hotel transport as included in every price. It is
  not. The meeting point is free, anywhere in Tulum town is +$20 for the booking,
  and outside town Kay can only do the drive back, +$10. The copy says so now.
- **Open Water Course** — the menu reuses the Discover Scuba paragraph word for word.
  The site has its own copy rather than repeating the duplication.

## Known gaps

- Confirmation emails are built and tested, but nothing is actually sent until
  `mail_api_key` is filled in and `kaydiving.com` is verified with Resend — see
  `docs/DEPLOY.md` §6. Until then the send is logged and the booking still works.
- No admin view. Kay reads bookings from the database until one exists.
- The payment flow cannot be exercised end to end without a public HTTPS origin
  for the webhook, so it is untested against the real Mercado Pago.
- Mercado Pago level 3 caps at roughly 10,000 UDIS (~87,000 MXN) a month. Raising
  it needs an RFC. Accepted for now; revisit when the cap is actually reached.
- The static maquettes under `../concepts/` still carry the invented dive names and
  prices from before the menu arrived. They are design references only — do not
  show them as the offer.
- **Only four photographs exist**, in `assets/source/`, and all four are on the
  page already: the hero, and the cards for Discover Scuba, Cenote Diving and
  the Open Water course. Reef & Cenote, Advanced and the Snorkel tour show the
  hand-drawn scene each one declares in `products.json`. The gallery is empty
  and its section does not render — a gallery re-showing the same four images
  would be padding. `scripts/photos.py` rebuilds every slot from the sources;
  add slots there and entries to `gallery` in `products.json` after a shoot.
