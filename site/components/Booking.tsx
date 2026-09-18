"use client";
import { useMemo, useState } from "react";
import { PRODUCTS, type Product } from "@/content/products";
import type { Dictionary } from "@/lib/i18n";

const money = (n: number) => "$" + n.toLocaleString("en-US");
const tomorrow = () => new Date(Date.now() + 864e5).toISOString().slice(0, 10);

export default function Booking({
  t, products, locale,
}: { t: Dictionary["book"]; products: Dictionary["products"]; locale: string }) {
  const [product, setProduct] = useState<Product>(PRODUCTS[0]);
  const [dives, setDives] = useState<number>(PRODUCTS[0].options[0].dives);
  const [date, setDate] = useState("");
  const [cert, setCert] = useState(t.certs[0]);
  const [divers, setDivers] = useState(2);
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [state, setState] = useState<"idle" | "sending" | "error">("idle");

  const option = product.options.find((o) => o.dives === dives) ?? product.options[0];
  const total = useMemo(() => option.price * divers, [option, divers]);

  function pick(p: Product) {
    setProduct(p);
    // The previous dive count may not exist on the new product.
    setDives(p.options[0].dives);
  }

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setState("sending");
    try {
      const res = await fetch("/api/booking", {
        method: "POST",
        headers: { "content-type": "application/json" },
        body: JSON.stringify({
          product: product.slug, option: option.dives,
          date, cert, divers, name, email, locale,
        }),
      });
      if (!res.ok) throw new Error(String(res.status));
      const { checkoutUrl } = await res.json();
      // Mercado Pago hosts the card form: no card data ever touches this site.
      location.href = checkoutUrl;
    } catch {
      setState("error");
    }
  }

  const fmtDate = date
    ? new Date(date + "T00:00:00").toLocaleDateString(locale === "en" ? "en-GB" : locale,
        { day: "2-digit", month: "short", year: "numeric" })
    : "—";

  const sizeLabel = (n: number) =>
    n === 0 ? products.halfDay : `${n} ${n === 1 ? products.dive : products.dives}`;

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
                         onChange={() => pick(p)} />
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
              <label className="flabel" htmlFor="cert">{t.s4}</label>
              <select className="field" id="cert" value={cert} onChange={(e) => setCert(e.target.value)}>
                {t.certs.map((c) => <option key={c}>{c}</option>)}
              </select>
            </div>
          </div>

          <div className="row2">
            <div className="fgroup">
              <span className="flabel">{t.s5}</span>
              <div className="step">
                <button type="button" aria-label="−" onClick={() => setDivers((n) => Math.max(1, n - 1))}>−</button>
                <output>{divers}</output>
                <button type="button" aria-label="+" onClick={() => setDivers((n) => Math.min(8, n + 1))}>+</button>
              </div>
            </div>
            <div className="fgroup">
              <label className="flabel" htmlFor="name">{t.s6}</label>
              <input className="field" id="name" autoComplete="name" placeholder={t.namePh}
                     value={name} onChange={(e) => setName(e.target.value)} />
            </div>
          </div>

          <div className="fgroup">
            <label className="flabel" htmlFor="mail">{t.s7}</label>
            <input className="field" id="mail" type="email" autoComplete="email" placeholder={t.mailPh}
                   value={email} onChange={(e) => setEmail(e.target.value)} />
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
            <Row k={t.rDepth}   v={product.maxDepthM ? `${product.maxDepthM} m` : t.halfDayLabel} />
            <Row k={t.rLevel}   v={products.level[product.level]} sm />
            <Row k={t.rDivers}  v={String(divers)} />
          </div>
          <div className="slate__total">
            <span className="k">{t.rTotal}</span><span className="v">{money(total)}</span>
          </div>
          <p className="slate__fine">{t.fine}</p>
          {state === "error" && (
            <p className="slate__fine" role="alert" style={{ color: "var(--turq)" }}>{t.error}</p>
          )}
          <button className="btn btn--lit" type="submit" disabled={state === "sending"}>
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
