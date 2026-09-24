"use client";
import { useState } from "react";
import CenoteMap from "@/components/CenoteMap";
import CenoteCard from "@/components/CenoteCard";
import { CENOTES, type Cenote } from "@/content/cenotes";
import { mapCopy, type CenoteCopy } from "@/lib/i18n";

type Filter = "all" | "open" | "cavern" | "deep" | "snorkel";
const FILTERS: Filter[] = ["all", "open", "cavern", "deep", "snorkel"];

const keep = (f: Filter) => (c: Cenote) =>
  f === "all" || (f === "snorkel" ? c.snorkel : c.type === f);

/** The guide's index: one row of filters driving both the map and the cards. */
export default function CenoteExplorer({ t, base, alts, preview }: {
  t: CenoteCopy;
  base: string;
  /** Alt text for the cenotes that have a real photograph, by slug */
  alts: Record<string, string>;
  preview: boolean;
}) {
  const [filter, setFilter] = useState<Filter>("all");
  /* A filter with nothing in it is not offered: today no cenote is snorkel-only. */
  const offered = FILTERS.filter((f) => CENOTES.some(keep(f)));
  const list = CENOTES.filter(keep(filter));

  return (
    <>
      <div className="shell cfilter">
        <div className="pills" role="radiogroup" aria-label={t.filter}>
          {offered.map((f) => (
            <label className="pill" key={f}>
              <input type="radio" name="cenote-filter" checked={filter === f} onChange={() => setFilter(f)} />
              <span>{f === "all" ? t.all : t.filters[f]}</span>
            </label>
          ))}
        </div>
        <p className="data" aria-live="polite">{t.count.replace("{n}", String(list.length))}</p>
      </div>

      <div className="wide">
        <CenoteMap t={mapCopy(t)} base={base} preview={preview}
                   visible={filter === "all" ? undefined : list.map((c) => c.slug)} />
      </div>

      <div className="shell dives cgrid">
        {list.map((c) => (
          <CenoteCard key={c.slug} c={c} t={t} href={`${base}${c.slug}/`} alt={alts[c.slug] ?? ""} />
        ))}
        {list.length === 0 && <p className="muted">{t.none}</p>}
      </div>
    </>
  );
}
