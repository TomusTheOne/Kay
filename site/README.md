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
3. A date Kay closed in the admin is refused (`date-unavailable`). The form
   already knows: it reads `/api/availability.php` when it loads.
4. A `pending` row is written **before** checkout is created, so a payment always
   has a booking to attach to.
5. Mercado Pago Checkout Pro is created with `external_reference` = the booking id,
   and the diver is redirected. No card data ever reaches this site.
6. `POST /api/mp-webhook` verifies the `x-signature` HMAC, then asks Mercado Pago
   what the payment actually did rather than trusting the notification body.
7. `kay_settle_payment()` moves a booking once: a payment lands only on a
   booking never paid before (`paid_at IS NULL`), and a refusal or refund only
   on a pending one. Retries and replays are no-ops; a settled booking cannot be
   un-paid by a stale notification, and an approval that follows a refused card
   is not lost.

## Tests

```bash
DATABASE_URL=postgres://... npm test
```

Covers the two things that can lose money or let someone dive free: server-side
pricing, and webhook idempotency. Runs against a real Postgres.

## Admin, CRM and traffic

`/admin/` is a server-rendered PHP back office on the same host and database:
dashboard, day sheet, closed days, bookings (status, edits, cash payments,
manual bookings, CSV), customers with a notes-and-follow-ups timeline, and the
site's own cookieless traffic count. Spanish by default, French per account. Its tables are created by `php/lib/migrate.php`
the first time it is opened. Everything about it — setup, what the traffic
count keeps and does not keep, security — is in `../docs/ADMIN.md`.

## Adding a language

`lib/i18n.ts` holds the locale list; `messages/<locale>.json` holds the copy. The
`Dictionary` type is declared rather than inferred, so a missing key fails the
build instead of printing `undefined` to a visitor.

**All three languages are live.** Spanish is Mexican Spanish and uses `tú`;
French uses `vous`, as a business addressing a customer. PADI programme names
(Open Water, Advanced Open Water, Discover Scuba, Deep) and place names
(Tulum, Casa Cenote, Cenote Angelita, El Pit) stay in their original form,
which is how dive centres write them in both markets.

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
- The payment flow cannot be exercised end to end without a public HTTPS origin
  for the webhook, so it is untested against the real Mercado Pago.
- Mercado Pago level 3 caps at roughly 10,000 UDIS (~87,000 MXN) a month. Raising
  it needs an RFC. Accepted for now; revisit when the cap is actually reached.
- The static maquettes under `../concepts/` still carry the invented dive names and
  prices from before the menu arrived. They are design references only — do not
  show them as the offer.
- The brand kit lives in `assets/source/brand/` and `scripts/brand.mjs` builds
  what the site serves from it, re-tinting the kit's blue `#4D85C4` to the
  site's turquoise `#4FE0D2`. Changing `BRAND_TO` in that script re-tints the
  header mark, the favicon, the touch icons and the maskable icon in one run.
  The emblem is line art: below about 40px it stops reading as a diving helmet,
  which is why the header mark is 40px and the favicon is the kit's cropped
  helmet-and-goggles rather than the whole thing.
- **Twelve photographs** in `assets/source/`. **All six cards carry one**, plus
  the hero, the band and five gallery tiles — thirteen slots, each cut from a
  source with its own focal point by `scripts/photos.py`. The hand-drawn scenes
  in `products.json` are now a fallback nothing uses; leave them, they are what
  a card falls back to if a photograph is ever removed. `scripts/photos.py` rebuilds every slot from the
  sources with a focal point per slot; add slots there and entries to `gallery`
  after a shoot.
- **Prices are carried in both currencies** in `content/products.json`. Kay
  works at 16 pesos to the dollar everywhere except Discover Scuba's single
  dive, which he rounded to 2300 — so no single rate reproduces his list, and
  there is no rate in the code. The peso figure is what Mercado Pago charges.
- **The bull shark dive is seasonal**, November to March, and the window wraps
  the year end. `kay_validate()` refuses a date outside it and the booking form
  disables its own submit button, because being told after the payment page has
  opened is no use. Kay says the migration moves the dates from year to year,
  so the copy says the exact dates are confirmed when you book.
- **Kay has not given the depth of the shark dive itself** — only of the second
  dive, on the reef, at 8-20 m. `maxDepthM` is null and the card shows the
  season instead, rather than a guessed number.
