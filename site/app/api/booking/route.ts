import { NextResponse } from "next/server";
import { MercadoPagoConfig, Preference } from "mercadopago";
import { eq } from "drizzle-orm";
import { quote, validate, DEPOSIT_RATE, type BookingInput } from "@/lib/pricing";
import { getDb, bookings } from "@/db";

/**
 * Mercado Pago México settles in MXN. The site quotes USD because that is what
 * Tulum dive tourism runs on, so the deposit is converted here at a rate we
 * control. Swap for a live FX lookup when the volume justifies it.
 */
const USD_TO_MXN = Number(process.env.USD_TO_MXN ?? 17.5);

export async function POST(req: Request) {
  const token = process.env.MP_ACCESS_TOKEN;
  const db = getDb();
  if (!token || !db) {
    return NextResponse.json({ error: "payments not configured" }, { status: 503 });
  }

  let input: BookingInput;
  try { input = await req.json(); }
  catch { return NextResponse.json({ error: "bad json" }, { status: 400 }); }

  const bad = validate(input);
  if (bad) return NextResponse.json({ error: bad }, { status: 422 });

  const q = quote(input);
  if (!q) return NextResponse.json({ error: "unknown dive or pickup" }, { status: 422 });

  const depositMxn = Math.round(q.depositUsd * USD_TO_MXN);

  // The row goes in first. If checkout creation then fails we are left with an
  // abandoned pending booking, which is harmless; the reverse — a payment with
  // nothing to attach it to — is not.
  const [booking] = await db.insert(bookings).values({
    dive: q.dive.slug,
    diveDate: input.date,
    divers: q.divers,
    certification: input.cert ?? "",
    pickup: input.pickup,
    addons: input.addons ?? [],
    name: input.name.trim(),
    email: input.email.trim().toLowerCase(),
    locale: input.locale ?? "en",
    totalUsdCents: q.totalUsd * 100,
    depositUsdCents: q.depositUsd * 100,
    depositMxnCents: depositMxn * 100,
  }).returning();

  const origin = new URL(req.url).origin;
  const client = new MercadoPagoConfig({ accessToken: token });

  try {
    const pref = await new Preference(client).create({
      body: {
        items: [{
          id: q.dive.slug,
          title: `Kay Diving — ${q.dive.slug} (${q.divers})`,
          description: `Deposit ${Math.round(DEPOSIT_RATE * 100)}% · balance on the day`,
          quantity: 1,
          unit_price: depositMxn,
          currency_id: "MXN",
        }],
        payer: { name: booking.name, email: booking.email },
        // Our own id travels with the payment and comes back on the webhook.
        external_reference: booking.id,
        back_urls: {
          success: `${origin}/${booking.locale}/booking/thanks`,
          pending: `${origin}/${booking.locale}/booking/pending`,
          failure: `${origin}/${booking.locale}/booking/failed`,
        },
        auto_return: "approved",
        statement_descriptor: "KAY DIVING",
        notification_url: `${origin}/api/mp-webhook`,
      },
    });

    await db.update(bookings)
      .set({ preferenceId: pref.id })
      .where(eq(bookings.id, booking.id));

    return NextResponse.json({ checkoutUrl: pref.init_point, bookingId: booking.id });
  } catch (err) {
    console.error("mercadopago preference failed", err);
    await db.update(bookings)
      .set({ status: "cancelled" })
      .where(eq(bookings.id, booking.id));
    return NextResponse.json({ error: "checkout unavailable" }, { status: 502 });
  }
}

export const runtime = "nodejs";
