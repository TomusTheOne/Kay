"use client";
import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { pathFor, type Locale } from "@/lib/i18n";

/**
 * The equipment questionnaire, opened from the link in the confirmation
 * email: one card per diver, in whichever units the diver thinks in. Feet and
 * pounds are converted here, so the server only ever stores centimetres and
 * kilograms — and Kay reads one system on the day sheet.
 */

const SIZES = ["XS", "S", "M", "L", "XL", "XXL"] as const;
const FIN_SIZES = ["XS", "S", "M", "L", "XL"] as const;
const SHOE_SYSTEMS = ["US", "EU", "MX", "UK"] as const;

type Units = "metric" | "imperial";
interface Diver {
  name: string;
  cm: string; ft: string; inch: string;
  kg: string; lb: string;
  shoeSystem: string; shoeSize: string;
  wetsuit: string; bcd: string; fins: string;
}
interface Booking {
  kind: "dive" | "snorkel";
  divers: number;
  name: string;
  date: string;
  product: string;
  answers: Array<{ diver: number; name: string; heightCm: number | null; weightKg: number | null;
                   shoe: string; wetsuit: string; bcd: string; fins: string }>;
}

/** The booking id and its token, from the link in the confirmation email. */
function readLink() {
  const q = new URLSearchParams(location.search);
  return { b: q.get("b") ?? "", t: q.get("t") ?? "" };
}

const blank = (shoeSystem: string): Diver => ({
  name: "", cm: "", ft: "", inch: "", kg: "", lb: "",
  shoeSystem, shoeSize: "", wetsuit: "?", bcd: "?", fins: "?",
});

export default function GearForm({
  locale, t, productName,
}: {
  locale: Locale;
  t: Record<string, string>;
  productName: Record<string, string>;
}) {
  // Americans think in feet and pounds; Mexico and France do not.
  const [units, setUnits] = useState<Units>(locale === "en" ? "imperial" : "metric");
  const defaultShoe = locale === "en" ? "US" : locale === "es" ? "MX" : "EU";
  const [state, setState] = useState<"loading" | "ready" | "invalid" | "closed" | "error" | "sending" | "saved">("loading");
  const [booking, setBooking] = useState<Booking | null>(null);
  const [divers, setDivers] = useState<Diver[]>([]);
  const [problem, setProblem] = useState("");

  useEffect(() => {
    const { b, t: token } = readLink();
    const load = async () => {
      if (!b || !token) return setState("invalid");
      const res = await fetch(`/api/gear.php?${new URLSearchParams({ b, t: token })}`);
      if (res.status === 404) return setState("invalid");
      if (res.status === 410) return setState("closed");
      if (!res.ok) return setState("error");
      const data: Booking = await res.json();
      setBooking(data);
      setDivers(Array.from({ length: data.divers }, (_, i) => {
        const a = data.answers.find((x) => x.diver === i + 1);
        const d = blank(defaultShoe);
        if (i === 0) d.name = data.name;
        if (!a) return d;
        const [sys, size] = a.shoe.split(" ");
        const totalIn = a.heightCm ? a.heightCm / 2.54 : 0;
        return {
          ...d,
          name: a.name,
          cm: a.heightCm ? String(a.heightCm) : "",
          ft: a.heightCm ? String(Math.floor(totalIn / 12)) : "",
          inch: a.heightCm ? String(Math.round(totalIn % 12)) : "",
          kg: a.weightKg ? String(a.weightKg) : "",
          lb: a.weightKg ? String(Math.round(a.weightKg * 2.20462)) : "",
          shoeSystem: sys || defaultShoe, shoeSize: size || "",
          wetsuit: a.wetsuit || "?", bcd: a.bcd || "?", fins: a.fins || "?",
        };
      }));
      setState("ready");
    };
    load().catch(() => setState("error"));
  }, [defaultShoe]);

  const snorkel = booking?.kind === "snorkel";
  const who = (i: number) => divers[i]?.name.trim() || (snorkel ? t.person : t.diver).replace("{n}", String(i + 1));

  function update(i: number, patch: Partial<Diver>) {
    setDivers((all) => all.map((d, j) => (j === i ? { ...d, ...patch } : d)));
  }

  const payload = useMemo(() => divers.map((d) => {
    const heightCm = units === "metric"
      ? Number(d.cm)
      : (Number(d.ft) * 12 + Number(d.inch || 0)) * 2.54;
    const weightKg = units === "metric" ? Number(d.kg) : Number(d.lb) / 2.20462;
    return {
      name: d.name, heightCm: Math.round(heightCm), weightKg: Math.round(weightKg),
      shoeSystem: d.shoeSystem, shoeSize: d.shoeSize, wetsuit: d.wetsuit, bcd: d.bcd, fins: d.fins,
    };
  }), [divers, units]);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setState("sending");
    setProblem("");
    try {
      const res = await fetch("/api/gear.php", {
        method: "POST",
        headers: { "content-type": "application/json" },
        body: JSON.stringify({ ...readLink(), divers: payload }),
      });
      if (res.ok) {
        setState("saved");
        return;
      }
      const body = await res.json().catch(() => ({}));
      const n = typeof body?.diver === "number" ? body.diver - 1 : 0;
      const messages: Record<string, string> = { height: t.errHeight, weight: t.errWeight, shoe: t.errShoe };
      setProblem((messages[body?.error] ?? t.error).replace("{who}", who(n)));
      setState(res.status === 410 ? "closed" : "ready");
    } catch {
      setProblem(t.error);
      setState("ready");
    }
  }

  const fmtDate = booking
    ? new Date(booking.date + "T00:00:00").toLocaleDateString(locale === "en" ? "en-GB" : locale,
        { weekday: "long", day: "numeric", month: "long" })
    : "";

  const sizePills = (name: string, list: readonly string[], value: string, set: (v: string) => void) => (
    <div className="pills" role="radiogroup">
      {[...list, "?"].map((s) => (
        <label className="pill" key={s}>
          <input type="radio" name={name} checked={value === s} onChange={() => set(s)} />
          <span>{s === "?" ? t.notSure : s}</span>
        </label>
      ))}
    </div>
  );

  return (
    <main className="bay shell gear">
      <p className="tag">{t.tag}</p>
      <h1 className="dsp dsp-lg">{t.h1}</h1>
      {booking && (
        <p className="lede">{productName[booking.product] ?? booking.product} · {fmtDate}</p>
      )}

      {state === "loading" && <p className="lede">{t.loading}</p>}
      {(state === "invalid" || state === "closed" || (state === "error" && !booking)) && (
        <p className="lede" role="alert">{state === "invalid" ? t.invalid : state === "closed" ? t.closed : t.error}</p>
      )}

      {state === "saved" && (
        <>
          <p className="lede" role="status">{t.saved}</p>
          <Link className="btn btn--lit" href={pathFor(locale)}>{t.back}</Link>
        </>
      )}

      {booking && (state === "ready" || state === "sending") && (
        <form className="gear__form" onSubmit={submit} noValidate>
          <p className="gear__intro">{snorkel ? t.introSnorkel : t.intro}</p>

          {!snorkel && (
            <div className="fgroup">
              <span className="flabel">{t.units}</span>
              <div className="pills" role="radiogroup" aria-label={t.units}>
                {(["metric", "imperial"] as const).map((u) => (
                  <label className="pill" key={u}>
                    <input type="radio" name="units" checked={units === u} onChange={() => setUnits(u)} />
                    <span>{u === "metric" ? t.metric : t.imperial}</span>
                  </label>
                ))}
              </div>
            </div>
          )}

          {divers.map((d, i) => (
            <fieldset className="gear__card" key={i}>
              <legend className="flabel">{(snorkel ? t.person : t.diver).replace("{n}", String(i + 1))}</legend>

              <div className="fgroup">
                <label className="flabel" htmlFor={`name-${i}`}>{t.name}</label>
                <input className="field" id={`name-${i}`} value={d.name} autoComplete={i === 0 ? "name" : "off"}
                       onChange={(e) => update(i, { name: e.target.value })} />
              </div>

              {!snorkel && (
                <div className="row2">
                  <div className="fgroup">
                    <span className="flabel">{t.height}</span>
                    {units === "metric" ? (
                      <label className="gear__unit">
                        <input className="field" inputMode="numeric" value={d.cm} aria-label={`${t.height} (cm)`}
                               onChange={(e) => update(i, { cm: e.target.value })} /><span>cm</span>
                      </label>
                    ) : (
                      <div className="gear__pair">
                        <label className="gear__unit">
                          <input className="field" inputMode="numeric" value={d.ft} aria-label={`${t.height} (ft)`}
                                 onChange={(e) => update(i, { ft: e.target.value })} /><span>ft</span>
                        </label>
                        <label className="gear__unit">
                          <input className="field" inputMode="numeric" value={d.inch} aria-label={`${t.height} (in)`}
                                 onChange={(e) => update(i, { inch: e.target.value })} /><span>in</span>
                        </label>
                      </div>
                    )}
                  </div>
                  <div className="fgroup">
                    <span className="flabel">{t.weight}</span>
                    <label className="gear__unit">
                      <input className="field" inputMode="numeric" value={units === "metric" ? d.kg : d.lb}
                             aria-label={`${t.weight} (${units === "metric" ? "kg" : "lb"})`}
                             onChange={(e) => update(i, units === "metric" ? { kg: e.target.value } : { lb: e.target.value })} />
                      <span>{units === "metric" ? "kg" : "lb"}</span>
                    </label>
                  </div>
                </div>
              )}

              <div className="row2">
                <div className="fgroup">
                  <label className="flabel" htmlFor={`shoe-${i}`}>{t.shoe}</label>
                  <input className="field" id={`shoe-${i}`} inputMode="decimal" value={d.shoeSize}
                         onChange={(e) => update(i, { shoeSize: e.target.value })} />
                </div>
                <div className="fgroup">
                  <label className="flabel" htmlFor={`sys-${i}`}>{t.shoeSystem}</label>
                  <select className="field" id={`sys-${i}`} value={d.shoeSystem}
                          onChange={(e) => update(i, { shoeSystem: e.target.value })}>
                    {SHOE_SYSTEMS.map((s) => <option key={s} value={s}>{s}</option>)}
                  </select>
                </div>
              </div>

              {!snorkel && (
                <>
                  <div className="fgroup">
                    <span className="flabel">{t.wetsuit}</span>
                    {sizePills(`wetsuit-${i}`, SIZES, d.wetsuit, (v) => update(i, { wetsuit: v }))}
                  </div>
                  <div className="fgroup">
                    <span className="flabel">{t.bcd}</span>
                    {sizePills(`bcd-${i}`, SIZES, d.bcd, (v) => update(i, { bcd: v }))}
                  </div>
                  <div className="fgroup">
                    <span className="flabel">{t.fins}</span>
                    {sizePills(`fins-${i}`, FIN_SIZES, d.fins, (v) => update(i, { fins: v }))}
                  </div>
                </>
              )}
            </fieldset>
          ))}

          {!snorkel && <p className="slate__fine">{t.sizesHint}</p>}
          {problem && <p className="slate__fine gear__problem" role="alert">{problem}</p>}
          <button className="btn btn--lit" type="submit" disabled={state === "sending"}>
            {state === "sending" ? t.sending : t.submit}
          </button>
        </form>
      )}
    </main>
  );
}
