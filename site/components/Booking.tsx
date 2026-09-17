"use client";
import { useMemo, useState } from "react";
import { BOOKABLE, ADDONS, PICKUPS, type Dive } from "@/content/dives";

type T = Record<string, any>;

const money = (n: number) => "$" + n.toLocaleString("en-US");
const tomorrow = () => new Date(Date.now() + 864e5).toISOString().slice(0, 10);

export default function Booking({ t, locale }: { t: T; locale: string }) {
  const [dive, setDive] = useState<Dive>(BOOKABLE[0]);
  const [date, setDate] = useState("");
  const [cert, setCert] = useState<string>(t.certs[0]);
  const [divers, setDivers] = useState(2);
  const [pickup, setPickup] = useState(PICKUPS[0].slug);
  const [addons, setAddons] = useState<string[]>([]);
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [state, setState] = useState<"idle" | "sending" | "error">("idle");

  const total = useMemo(() => {
    const pick = PICKUPS.find((p) => p.slug === pickup)!.price;
    const extras = ADDONS
      .filter((a) => addons.includes(a.slug))
      .reduce((s, a) => s + a.price * (a.perDiver ? divers : 1), 0);
    return dive.price * divers + pick + extras;
  }, [dive, divers, pickup, addons]);

  const toggleAddon = (slug: string) =>
    setAddons((v) => (v.includes(slug) ? v.filter((s) => s !== slug) : [...v, slug]));

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setState("sending");
    try {
      const res = await fetch("/api/booking", {
        method: "POST",
        headers: { "content-type": "application/json" },
        body: JSON.stringify({
          dive: dive.slug, date, cert, divers, pickup, addons, name, email, locale,
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
    ? new Date(date + "T00:00:00").toLocaleDateString(locale === "en" ? "en-GB" : locale, {
        day: "2-digit", month: "short", year: "numeric",
      })
    : "—";

  return (
    <div className="slate-wrap" id="slateWrap">
      <form className="book" onSubmit={submit} noValidate>
        <div className="book__form">
          <div className="fgroup">
            <span className="flabel">{t.s1}</span>
            <div className="pills" role="radiogroup" aria-label={t.s1}>
              {BOOKABLE.map((d) => (
                <label className="pill" key={d.slug}>
                  <input type="radio" name="exp" checked={dive.slug === d.slug}
                         onChange={() => setDive(d)} />
                  <span>{t[d.slug].name} · ${d.price}</span>
                </label>
              ))}
            </div>
          </div>

          <div className="row2">
            <div className="fgroup">
              <label className="flabel" htmlFor="date">{t.s2}</label>
              <input className="field" type="date" id="date" min={tomorrow()}
                     value={date} onChange={(e) => setDate(e.target.value)} />
            </div>
            <div className="fgroup">
              <label className="flabel" htmlFor="cert">{t.s3}</label>
              <select className="field" id="cert" value={cert} onChange={(e) => setCert(e.target.value)}>
                {t.certs.map((c: string) => <option key={c}>{c}</option>)}
              </select>
            </div>
          </div>

          <div className="row2">
            <div className="fgroup">
              <span className="flabel">{t.s4}</span>
              <div className="step">
                <button type="button" aria-label="−" onClick={() => setDivers((n) => Math.max(1, n - 1))}>−</button>
                <output>{divers}</output>
                <button type="button" aria-label="+" onClick={() => setDivers((n) => Math.min(8, n + 1))}>+</button>
              </div>
            </div>
            <div className="fgroup">
              <label className="flabel" htmlFor="pickup">{t.s5}</label>
              <select className="field" id="pickup" value={pickup} onChange={(e) => setPickup(e.target.value)}>
                {PICKUPS.map((p) => (
                  <option key={p.slug} value={p.slug}>
                    {t.pickup[p.slug]}{p.price ? ` — $${p.price}` : ""}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="fgroup">
            <span className="flabel">{t.s6}</span>
            <div className="pills">
              {ADDONS.map((a) => (
                <label className="pill" key={a.slug}>
                  <input type="checkbox" checked={addons.includes(a.slug)}
                         onChange={() => toggleAddon(a.slug)} />
                  <span>{t.addons[a.slug]} · +${a.price}</span>
                </label>
              ))}
            </div>
          </div>

          <div className="row2">
            <div className="fgroup">
              <label className="flabel" htmlFor="name">{t.s7}</label>
              <input className="field" id="name" autoComplete="name" placeholder={t.namePh}
                     value={name} onChange={(e) => setName(e.target.value)} />
            </div>
            <div className="fgroup">
              <label className="flabel" htmlFor="mail">{t.s8}</label>
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
            <Row k={t.rDive}   v={t[dive.slug].name} sm />
            <Row k={t.rDate}   v={fmtDate} />
            <Row k={t.rDepth}  v={`${dive.maxDepth} m`} />
            <Row k={t.rLevel}  v={t.level?.[dive.level] ?? dive.level} sm />
            <Row k={t.rDivers} v={String(divers)} />
            <Row k={t.rPickup} v={t.pickup[pickup]} sm />
            <Row k={t.rExtras} v={addons.length ? addons.map((s) => t.addons[s]).join(", ") : t.none} sm />
          </div>
          <div className="slate__total">
            <span className="k">{t.rTotal}</span><span className="v">{money(total)}</span>
          </div>
          <p className="slate__fine">{t.fine}</p>
          {state === "error" && <p className="slate__fine" role="alert" style={{ color: "#F5C77E" }}>{t.error}</p>}
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
