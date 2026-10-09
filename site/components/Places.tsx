import { CENOTES, RUINS, INAH_VERIFIED, INAH_SOURCE, type Ruin } from "@/content/places";
import { pathFor, type Dictionary, type Locale } from "@/lib/i18n";

/**
 * The two guide sections: cenotes worth a day off, and the Maya sites within
 * reach. Both are server components with no client JavaScript — they are
 * reference material, and the page's job is still to load fast.
 *
 * They are deliberately quieter than the dive cards above them. A diver
 * scrolling past should be able to tell at a glance what Kay sells and what
 * he is merely telling you about; if these competed visually, the section
 * that earns money would lose.
 */

/** "5–7 km", or "47 km" when the guide gives one figure rather than a range. */
function range(lo: number | null, hi: number | null, unit: string): string | null {
  if (lo === null || hi === null) return null;
  const n = (v: number) => (Number.isInteger(v) ? String(v) : v.toFixed(1).replace(/\.0$/, ""));
  return lo === hi ? `${n(lo)} ${unit}` : `${n(lo)}–${n(hi)} ${unit}`;
}

/** "$105 national · $210 foreign", or just "$105" when the two are the same. */
function fee(national: number, foreign: number, nLabel: string, fLabel: string): string {
  return national === foreign
    ? `$${national}`
    : `$${national} ${nLabel} · $${foreign} ${fLabel}`;
}

/**
 * A photograph, when there is a real one of this place. No placeholder and no
 * stand-in: a generic cavern shot captioned "Taak Bi Ha" is a lie a visitor
 * only discovers on arrival, and the dive guide already carries one of those.
 */
function Shot({ photo, alt }: { photo: string | null; alt: string }) {
  if (!photo) return null;
  return (
    <div className="place__media">
      <picture>
        <source srcSet={`/assets/photos/${photo}.avif`} type="image/avif" />
        <source srcSet={`/assets/photos/${photo}.webp`} type="image/webp" />
        <img src={`/assets/photos/${photo}.webp`} alt={alt}
             loading="lazy" decoding="async" width={1200} height={800} />
      </picture>
    </div>
  );
}

function Row({ k, v }: { k: string; v: string }) {
  return (
    <div className="prow">
      <span className="prow__k">{k}</span>
      <span className="prow__v">{v}</span>
    </div>
  );
}

/* ------------------------------------------------------------------ cenotes */

export function Visit({ t, locale }: { t: Dictionary["visit"]; locale: Locale }) {
  return (
    <div className="places" data-rise>
      {CENOTES.map((c, i) => {
        const copy = t.items[c.slug];
        return (
          <article className="place" key={c.slug} data-rise data-rise-d={i % 3 || undefined}>
            <Shot photo={c.photo} alt={copy.name} />
            <div className="place__top">
              <h3 className="place__name">{copy.name}</h3>
              {/* The four that also appear in the dive guide. Rather than
                  describing the same cenote twice and leaving the reader to
                  notice, the card says so and links to the page that gives
                  the depths and the courses that go there. */}
              {c.dived && (
                <a className="place__dive" href={pathFor(locale, `/cenotes/${c.slug}/`)}>
                  {t.weDive}
                  <span className="place__diveCta">{t.weDiveCta}</span>
                </a>
              )}
            </div>

            <p className="chips">
              <span className="chip chip--lit">{t.typeLabel[c.type]}</span>
              {c.activities.map((a) => (
                <span className="chip" key={a}>{t.activityLabel[a]}</span>
              ))}
            </p>

            <p className="place__txt">{copy.blurb}</p>

            <div className="prows">
              <Row k={t.goodLabel} v={copy.good} />
              <Row k={t.tipLabel} v={copy.tip} />
            </div>
          </article>
        );
      })}
    </div>
  );
}

/* -------------------------------------------------------------------- ruins */

export function Ruins({ t }: { t: Dictionary["ruins"] }) {
  // Written out once, under the cards, rather than repeated on every price.
  const verified = t.priceNote.replace("{date}", INAH_VERIFIED);

  return (
    <>
      <div className="places" data-rise>
        {RUINS.map((r: Ruin, i) => {
          const copy = t.items[r.slug];
          const km = range(r.km[0], r.km[1], "km");
          const mins = range(r.minutes[0], r.minutes[1], "min");
          const stay = range(r.stayHours[0], r.stayHours[1], "h");

          return (
            <article className="place" key={r.slug} data-rise data-rise-d={i % 3 || undefined}>
              <Shot photo={r.photo} alt={copy.name} />
              <div className="place__top">
                <h3 className="place__name">{copy.name}</h3>
              </div>

              <p className="chips">
                <span className="chip chip--lit">{t.crowdLabel[r.crowd]}</span>
                <span className="chip">{t.walkLabel[r.walking]}</span>
                <span className="chip">{t.transitLabel[r.transit]}</span>
                {r.booking && <span className="chip chip--warn">{t.bookingRequired}</span>}
              </p>

              <p className="place__txt">{copy.blurb}</p>

              <div className="prows">
                {/* San Gervasio is a ferry away, so a distance in kilometres
                    would say nothing useful: the crossing is the journey. */}
                <Row k={t.fromLabel}
                     v={km ? `${km} · ${mins}` : `${mins} · ${t.transitLabel[r.transit]}`} />
                <Row k={t.stayLabel} v={stay ?? "—"} />
                <Row k={t.hoursLabel} v={`${r.hours} · ${t.lastEntryLabel} ${r.lastEntry}`} />
                <Row k={t.entryLabel} v={
                  fee(r.inah.national, r.inah.foreign, t.nationalLabel, t.foreignLabel)
                  + (r.extraPossible ? ` · ${t.extraPossible}` : "")
                } />
                {r.extra && (
                  <Row k={t.culturLabel} v={
                    fee(r.extra.national, r.extra.foreign, t.nationalLabel, t.foreignLabel)
                    + (r.extra.parking ? ` · ${t.parkingLabel} $${r.extra.parking}` : "")
                  } />
                )}
                <Row k={t.seeLabel} v={copy.see} />
                <Row k={t.combineLabel} v={copy.combine} />
              </div>

              {/* Not a Row: an empty label column left it indented under
                  "combine with", reading as part of that answer when it is
                  really how you get there at all. */}
              {r.transit === "ferry" && <p className="place__foot">{t.ferryNote}</p>}
            </article>
          );
        })}
      </div>

      <p className="places__note" data-rise>
        {verified}{" "}
        <a href={INAH_SOURCE} rel="noopener nofollow" target="_blank" className="tq">
          lugares.inah.gob.mx
        </a>
      </p>
    </>
  );
}
