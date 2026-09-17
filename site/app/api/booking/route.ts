import { NextResponse } from "next/server";
import { MercadoPagoConfig, Preference } from "mercadopago";
import { quote, validate, DEPOSIT_RATE, type BookingInput } from "@/lib/pricing";

/**
 * Mercado Pago México settles in MXN. The site quotes USD because that is what
 * Tulum dive tourism runs on, so the deposit is converted here at a rate we
 * control. Swap this for a live FX lookup when the volume justifies it.
 */
const USD_TO_MXN = Number(process.env.USD_TO_MXN ?? 17.5);

export async function POST(req: Request) {
  const token = process.env.MP_ACCESS_TOKEN;
  if (!token) {
    return NextResponse.json({ error: "payments not configured" }, { status: 503 });
  }

  let input: BookingInput;
  try { input = await req.json(); }
  catch { return NextResponse.json({ error: "bad json" }, { status: 400 }); }

  const bad = validate(input);
  if (bad) return NextResponse.json({ error: bad }, { status: 422 });

  const q = quote(input);
  if (!q) return NextResponse.json({ error: "unknown dive or pickup" }, { status: 422 });

  const origin = new URL(req.url).origin;
  const depositMxn = Math.round(q.depositUsd * USD_TO_MXN);

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
        payer: { name: input.name, email: input.email },
        back_urls: {
          success: `${origin}/${input.locale}/booking/thanks`,
          pending: `${origin}/${input.locale}/booking/pending`,
          failure: `${origin}/${input.locale}/booking/failed`,
        },
        auto_return: "approved",
        statement_descriptor: "KAY DIVING",
        notification_url: `${origin}/api/mp-webhook`,
        // Echoed back on the webhook so the booking can be reconciled.
        metadata: {
          dive: q.dive.slug, date: input.date, divers: q.divers,
          cert: input.cert, pickup: input.pickup, addons: input.addons ?? [],
          total_usd: q.totalUsd, deposit_usd: q.depositUsd, email: input.email, name: input.name,
        },
      },
    });

    // TODO(persistence): write a `pending` booking row keyed on pref.id before
    // returning, so the webhook has something to reconcile against.
    return NextResponse.json({ checkoutUrl: pref.init_point, preferenceId: pref.id });
  } catch (err) {
    console.error("mercadopago preference failed", err);
    return NextResponse.json({ error: "checkout unavailable" }, { status: 502 });
  }
}

export const runtime = "nodejs";
