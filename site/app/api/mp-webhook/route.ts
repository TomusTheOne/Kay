import { NextResponse } from "next/server";
import crypto from "node:crypto";
import { MercadoPagoConfig, Payment } from "mercadopago";

/**
 * Mercado Pago signs every notification. Anyone can POST to this URL, so the
 * signature is checked before the payload is believed — otherwise a stranger
 * could mark any booking paid.
 *
 * x-signature: "ts=<unix>,v1=<hmac>"  over  "id:<data.id>;request-id:<x-request-id>;ts:<ts>;"
 */
function verify(req: Request, dataId: string): boolean {
  const secret = process.env.MP_WEBHOOK_SECRET;
  if (!secret) return false;

  const sig = req.headers.get("x-signature") ?? "";
  const requestId = req.headers.get("x-request-id") ?? "";
  const parts = Object.fromEntries(
    sig.split(",").map((p) => p.split("=").map((s) => s.trim()) as [string, string]),
  );
  const { ts, v1 } = parts;
  if (!ts || !v1) return false;

  const manifest = `id:${dataId};request-id:${requestId};ts:${ts};`;
  const expected = crypto.createHmac("sha256", secret).update(manifest).digest("hex");

  const a = Buffer.from(expected, "hex");
  const b = Buffer.from(v1, "hex");
  return a.length === b.length && crypto.timingSafeEqual(a, b);
}

export async function POST(req: Request) {
  const url = new URL(req.url);
  const dataId = url.searchParams.get("data.id") ?? "";

  let body: any = {};
  try { body = await req.clone().json(); } catch { /* MP also sends form posts */ }
  const id = dataId || body?.data?.id || "";

  if (!id || !verify(req, id)) {
    return NextResponse.json({ error: "bad signature" }, { status: 401 });
  }

  const type = body?.type ?? url.searchParams.get("type");
  if (type !== "payment") return NextResponse.json({ ok: true, ignored: type });

  try {
    const client = new MercadoPagoConfig({ accessToken: process.env.MP_ACCESS_TOKEN! });
    const payment = await new Payment(client).get({ id });

    // Ask Mercado Pago what happened rather than trusting the notification body.
    if (payment.status === "approved") {
      // TODO(persistence): mark the booking paid, then send Kay and the diver
      // their confirmation. Must be idempotent — MP retries notifications.
      console.info("payment approved", { id, metadata: payment.metadata });
    }
    return NextResponse.json({ ok: true });
  } catch (err) {
    console.error("mp-webhook lookup failed", err);
    // A non-2xx makes Mercado Pago retry, which is what we want on a transient fault.
    return NextResponse.json({ error: "lookup failed" }, { status: 500 });
  }
}

export const runtime = "nodejs";
