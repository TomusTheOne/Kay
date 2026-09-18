"use client";
import { useMemo, useState } from "react";
import { PRODUCTS, PICKUPS, SCHEDULES, DEPOSIT_RATE, type Product } from "@/content/products";
import type { Dictionary } from "@/lib/i18n";

const money = (n: number) => "$" + n.toLocaleString("en-US");
const tomorrow = () => new Date(Date.now() + 864e5).toISOString().slice(0, 10);

export default function Booking({
  t, products, logistics, locale,
}: {
  t: Dictionary["book"]; products: Dictionary["products"];
  logistics: Dictionary["logistics"]; locale: string;
}) {
  const [product, setProduct] = useState<Product>(PRODUCTS[0]);
  const [dives, setDives] = useState<number>(PRODUCTS[0].options[0].dives);
  const [date, setDate] = useState("");
  const [slot, setSlot] = useState(SCHEDULES[0].slug);
  const [slotNote, setSlotNote] = useState("");
  const [cert, setCert] = useState(t.certs[0]);
  const [divers, setDivers] = useState(2);
  const [pickup, setPickup] = useState(PICKUPS[0].slug);
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [state, setState] = useState<"idle" | "sending" | "error">("idle");
  const [error, setError] = useState("");

  const option = product.options.find((o) => o.dives === dives) ?? product.options[0];
  const pick = PICKUPS.find((p) => p.slug === pickup)!;

  /* A seasonal product refuses a date outside its months, and the endpoint
     refuses it again — but being told after the payment page has opened is
     no good, so the form says so here and will not submit. The window wraps
     the year end, which makes it a union rather than a range. */
  const outOfSeason = useMemo(() => {
    if (!product.season || !date) return false;
    const month = Number(date.slice(5, 7));
    const { fromMonth: f, toMonth: to } = product.season;
    return !(f <= to ? month >= f && month <= to : month >= f || month <= to);
  }, [product, date]);

  // Pickup is per booking — one van, not one seat.
  const total = useMemo(() => option.price * divers + pick.price, [option, divers, pick]);

  /* Mercado Pago debits PESOS. Sending a diver to a checkout showing a number
     they have never seen — with a currency the page never mentioned — is how a
     booking turns into a chargeback, so the slate says it before they leave. */
  const deposit = useMemo(
    () => Math.round((option.priceMxn * divers + pick.priceMxn) * DEPOSIT_RATE),
    [option, divers, pick],
  );

  function choose(p: Product) {
    setProduct(p);
    setDives(p.options[0].dives);   // the old size may not exist on the new product
  }

  /* The endpoint names what it refused — "date", "email", "name" — and the
     form is noValidate, so the browser never catches these first. Showing one
     message for every refusal told a diver who forgot the date that the
     payment system was down, which is both wrong and unfixable by them. */
  const reasons: Record<string, string> = {
    date: t.errDate, "date-past": t.errDatePast,
    email: t.errEmail, name: t.errName, "out-of-season": t.errSeason,
  };

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    if (outOfSeason) return;
    setState("sending");
    try {
      const res = await fetch("/api/booking.php", {
        method: "POST",
        headers: { "content-type": "application/json" },
        body: JSON.stringify({
          product: product.slug, option: option.dives, date, slot, slotNote,
          cert, divers, pickup, name, email, locale,
        }),
      });
      if (!res.ok) {
        const body = await res.json().catch(() => ({}));
        // Anything unrecognised is a real fault on our side, not theirs.
        setError(reasons[body?.error] ?? t.error);
        setState("error");
        return;
      }
      const { checkoutUrl } = await res.json();
      // Mercado Pago hosts the card form: no card data ever touches this site.
      location.href = checkoutUrl;
    } catch {
      setError(t.error);
      setState("error");
    }
  }

  const fmtDate = date
    ? new Date(date + "T00:00:00").toLocaleDateString(locale === "en" ? "en-GB" : locale,
        { day: "2-digit", month: "short", year: "numeric" })
    : "—";

  const sizeLabel = (n: number) =>
    n === 0 ? products.halfDay : `${n} ${n === 1 ? products.dive : products.dives}`;

  const slotLabel = (s: (typeof SCHEDULES)[number]) =>
    s.start ? `${s.start}–${s.end}` : t.timeOther;

  return (
    <div className="slate-wrap">
      <form className="book" onSubmit={submit} noValidate>
        <div className="book__form">
          <div className="fgroup">
            <span className="flabel">{t.s1}</span>
            <div className="pills" role="radiogroup" aria-label={t.s1}>
              {PRODUCTS.map((p) => (
                <label className="pill" key={p.slug}>
                  <input type="radio" name="product" checked={product.slug === p.slug}
                         onChange={() => choose(p)} />
                  <span>{products.items[p.slug].name}</span>
                </label>
              ))}
            </div>
          </div>

          {product.options.length > 1 && (
            <div className="fgroup">
              <span className="flabel">{t.s2}</span>
              <div className="pills" role="radiogroup" aria-label={t.s2}>
                {product.options.map((o) => (
                  <label className="pill" key={o.dives}>
                    <input type="radio" name="option" checked={dives === o.dives}
                           onChange={() => setDives(o.dives)} />
                    <span>{sizeLabel(o.dives)} · ${o.price}</span>
                  </label>
                ))}
              </div>
            </div>
          )}

          <div className="row2">
            <div className="fgroup">
              <label className="flabel" htmlFor="date">{t.s3}</label>
              <input className="field" type="date" id="date" min={tomorrow()}
                     value={date} onChange={(e) => setDate(e.target.value)} />
            </div>
            <div className="fgroup">
              <label className="flabel" htmlFor="slot">{t.s4}</label>
              <select className="field" id="slot" value={slot} onChange={(e) => setSlot(e.target.value)}>
                {SCHEDULES.map((s) => <option key={s.slug} value={s.slug}>{slotLabel(s)}</option>)}
              </select>
            </div>
          </div>

          {slot === "other" && (
            <div className="fgroup">
              <label className="flabel" htmlFor="slotnote">{t.timeOther}</label>
              <input className="field" id="slotnote" value={slotNote}
                     onChange={(e) => setSlotNote(e.target.value)} placeholder="10:30?" />
            </div>
          )}

          <div className="row2">
            <div className="fgroup">
              <label className="flabel" htmlFor="cert">{t.s5}</label>
              <select className="field" id="cert" value={cert} onChange={(e) => setCert(e.target.value)}>
                {t.certs.map((c) => <option key={c}>{c}</option>)}
              </select>
            </div>
            <div className="fgroup">
              <span className="flabel">{t.s6}</span>
              <div className="step">
                <button type="button" aria-label="−" onClick={() => setDivers((n) => Math.max(1, n - 1))}>−</button>
                <output>{divers}</output>
                <button type="button" aria-label="+" onClick={() => setDivers((n) => Math.min(8, n + 1))}>+</button>
              </div>
            </div>
          </div>

          <div className="fgroup">
            <span className="flabel">{t.s7}</span>
            <div className="pills" role="radiogroup" aria-label={t.s7}>
              {PICKUPS.map((p) => (
                <label className="pill" key={p.slug}>
                  <input type="radio" name="pickup" checked={pickup === p.slug}
                         onChange={() => setPickup(p.slug)} />
                  <span>
                    {logistics.pickups[p.slug].name}
                    {p.price ? ` · +$${p.price}` : ""}
                  </span>
                </label>
              ))}
            </div>
          </div>

          <div className="row2">
            <div className="fgroup">
              <label className="flabel" htmlFor="name">{t.s8}</label>
              <input className="field" id="name" autoComplete="name" placeholder={t.namePh}
                     value={name} onChange={(e) => setName(e.target.value)} />
            </div>
            <div className="fgroup">
              <label className="flabel" htmlFor="mail">{t.s9}</label>
              <input className="field" id="mail" type="email" autoComplete="email" placeholder={t.mailPh}
                     value={email} onChange={(e) => setEmail(e.target.value)} />
            </div>
          </div>
        </div>

        <aside className="slate" aria-live="polite">
          <div className="slate__hd">
            <span className="slate__ttl">{t.slate}</span>
            <span className="slate__stamp">{t.stamp}</span>
          </div>
          <div className="slate__rows">
            <Row k={t.rProduct} v={products.items[product.slug].name} sm />
            <Row k={t.rOption}  v={sizeLabel(option.dives)} />
            <Row k={t.rDate}    v={fmtDate} />
            <Row k={t.rTime}    v={slotLabel(SCHEDULES.find((s) => s.slug === slot)!)} sm />
            {/* "Half day" only fits the snorkel tour. A dive whose depth Kay has
                not given yet shows a dash rather than borrowing that label. */}
            <Row k={t.rDepth}   v={product.maxDepthM ? `${product.maxDepthM} m`
                                   : product.kind === "snorkel" ? t.halfDayLabel : "—"} />
            {product.season && <Row k={t.rSeason} v={products.seasonValue[product.slug]} sm />}
            <Row k={t.rLevel}   v={products.level[product.level]} sm />
            <Row k={t.rDivers}  v={String(divers)} />
            <Row k={t.rPickup}  v={logistics.pickups[pickup].name} sm />
          </div>
          <div className="slate__total">
            {/* The currency is spelled out because a peso figure sits directly
                below it: "$280" over "$1,380 MXN" reads like the deposit costs
                more than the dive. */}
            <span className="k">{t.rTotal}</span>
            <span className="v">{money(total)}<i>USD</i></span>
          </div>
          <div className="slate__dep">
            <span className="k">{t.rDeposit}</span>
            <span className="v">{money(deposit)} MXN</span>
          </div>
          <p className="slate__fine">{t.fine}</p>
          {outOfSeason && (
            <p className="slate__fine" role="alert" style={{ color: "var(--turq)" }}>
              {t.outOfSeason.replace("{season}", products.seasonValue[product.slug])}
            </p>
          )}
          {state === "error" && (
            <p className="slate__fine" role="alert" style={{ color: "var(--turq)" }}>
              {error || t.error}
            </p>
          )}
          <button className="btn btn--lit" type="submit"
                  disabled={state === "sending" || outOfSeason}>
            {state === "sending" ? t.submitting : t.submit}
          </button>
        </aside>
      </form>
    </div>
  );
}

function Row({ k, v, sm }: { k: string; v: string; sm?: boolean }) {
  return (
    <div className="srow">
      <span className="srow__k">{k}</span>
      <span className={"srow__v" + (sm ? " sm" : "")}>{v}</span>
    </div>
  );
}
