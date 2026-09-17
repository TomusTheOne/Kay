import { NextResponse } from "next/server";
import crypto from "node:crypto";
import { and, eq } from "drizzle-orm";
import { MercadoPagoConfig, Payment } from "mercadopago";
import { getDb, bookings } from "@/db";

/**
 * Anyone can POST here, so the signature is checked before the payload is
 * believed — otherwise a stranger could mark any booking paid.
 *
 * x-signature: "ts=<unix>,v1=<hmac>" over "id:<data.id>;request-id:<rid>;ts:<ts>;"
 */
function verify(req: Request, dataId: string): boolean {
  const secret = process.env.MP_WEBHOOK_SECRET;
  if (!secret) return false;

  const parts = Object.fromEntries(
    (req.headers.get("x-signature") ?? "")
      .split(",")
      .map((p) => p.split("=").map((s) => s.trim()) as [string, string]),
  );
  const { ts, v1 } = parts;
  if (!ts || !v1) return false;

  const manifest = `id:${dataId};request-id:${req.headers.get("x-request-id") ?? ""};ts:${ts};`;
  const expected = crypto.createHmac("sha256", secret).update(manifest).digest("hex");

  const a = Buffer.from(expected, "hex");
  const b = Buffer.from(v1, "hex");
  return a.length === b.length && crypto.timingSafeEqual(a, b);
}

export async function POST(req: Request) {
  const url = new URL(req.url);
  let body: Record<string, any> = {};
  try { body = await req.clone().json(); } catch { /* MP also posts form bodies */ }

  const paymentId = url.searchParams.get("data.id") ?? body?.data?.id ?? "";
  if (!paymentId || !verify(req, String(paymentId))) {
    return NextResponse.json({ error: "bad signature" }, { status: 401 });
  }

  const type = body?.type ?? url.searchParams.get("type");
  if (type !== "payment") return NextResponse.json({ ok: true, ignored: type });

  const db = getDb();
  if (!db) return NextResponse.json({ error: "no database" }, { status: 503 });

  try {
    const client = new MercadoPagoConfig({ accessToken: process.env.MP_ACCESS_TOKEN! });
    // Ask Mercado Pago what happened rather than trusting the notification body.
    const payment = await new Payment(client).get({ id: String(paymentId) });

    const bookingId = payment.external_reference;
    if (!bookingId) return NextResponse.json({ ok: true, ignored: "no external_reference" });

    const next =
      payment.status === "approved" ? "paid" :
      payment.status === "refunded" || payment.status === "charged_back" ? "refunded" :
      payment.status === "rejected" || payment.status === "cancelled" ? "cancelled" :
      null;

    if (!next) return NextResponse.json({ ok: true, ignored: payment.status });

    // Idempotent: only a pending booking moves. Mercado Pago retries, and a
    // replay of an old notification must not resurrect a refunded booking.
    const updated = await db.update(bookings)
      .set({
        status: next,
        paymentId: String(paymentId),
        paidAt: next === "paid" ? new Date() : null,
      })
      .where(and(eq(bookings.id, bookingId), eq(bookings.status, "pending")))
      .returning({ id: bookings.id, status: bookings.status });

    if (updated.length === 0) {
      // Already handled, or the booking is no longer pending. Both are fine;
      // 200 stops Mercado Pago retrying.
      return NextResponse.json({ ok: true, alreadyHandled: true });
    }

    // TODO(email): confirmation to the diver and the day sheet line to Kay.
    console.info("booking settled", { bookingId, status: next, paymentId });
    return NextResponse.json({ ok: true, status: next });
  } catch (err) {
    console.error("mp-webhook failed", err);
    // Non-2xx makes Mercado Pago retry, which is what we want on a transient fault.
    return NextResponse.json({ error: "lookup failed" }, { status: 500 });
  }
}

export const runtime = "nodejs";
