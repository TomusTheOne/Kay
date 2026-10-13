import { pathFor, type Dictionary, type Locale } from "@/lib/i18n";
import { GUIDE_PUBLISHED, cenoteBySlug } from "@/content/cenotes";
import { REVIEWS_URL, REVIEWS_RATING, REVIEWS_COUNT, ratingText, monthText,
         type GoogleReview } from "@/content/reviews";

/**
 * Kay's Google reviews: the home page's section, and the few that name one
 * cenote on that cenote's page. Server components, no client JavaScript.
 *
 * Each review keeps its own language — the quote carries its lang attribute,
 * so a screen reader reads a Spanish review in Spanish on the English page —
 * and the reader's own language comes first (reviewsFor). On a phone the
 * cards are a row to swipe, so they cost one screen, not six; from a tablet
 * up they fall into columns of uneven height, the way people write.
 */

function Stars({ n, label }: { n: number; label: string }) {
  return (
    <span className="rev__stars" role="img" aria-label={label}>
      {[1, 2, 3, 4, 5].map((i) => (
        <svg key={i} className={i <= n ? "on" : undefined} viewBox="0 0 12 12" width="13" height="13" aria-hidden="true">
          <path d="M6 .9l1.55 3.2 3.5.48-2.55 2.45.62 3.47L6 8.86 2.88 10.5l.62-3.47L.95 4.58l3.5-.48z" />
        </svg>
      ))}
    </span>
  );
}

export function ReviewCards({ reviews, t, locale, except }: {
  reviews: GoogleReview[]; t: Dictionary["reviews"]; locale: Locale;
  /** The cenote whose page this is: its own chip would link to itself. */
  except?: string;
}) {
  return (
    <div className="revs" role="region" aria-label={t.tag} tabIndex={0}>
      {reviews.map((r) => {
        const sites = (r.cenotes ?? []).filter((s) => s !== except).map((s) => cenoteBySlug(s)!);
        return (
          <figure className="rev" key={`${r.name}-${r.date}`}>
            <Stars n={r.stars} label={t.stars.replace("{n}", String(r.stars))} />
            <blockquote lang={r.lang}>
              {r.text.split(/\n\s*\n/).map((p, i) => <p key={i}>{p}</p>)}
            </blockquote>
            {sites.length > 0 && (
              <ul className="chips rev__sites">
                {sites.map((c) => (
                  <li key={c.slug}>
                    {GUIDE_PUBLISHED
                      ? <a className="chip chip--lit" href={pathFor(locale, `/cenotes/${c.slug}/`)}>{c.short}</a>
                      : <span className="chip">{c.short}</span>}
                  </li>
                ))}
              </ul>
            )}
            <figcaption>
              <span className="rev__who">{r.name}</span>
              <span className="data">{monthText(locale, r.date)} · Google</span>
            </figcaption>
          </figure>
        );
      })}
    </div>
  );
}

/** The link to the profile, where every review is, the bad ones included. */
export function AllReviews({ t }: { t: Dictionary["reviews"] }) {
  if (!REVIEWS_URL) return null;
  return (
    <a className="btn btn--ghost rev__all" href={REVIEWS_URL} target="_blank" rel="noopener">
      {t.all} ↗
    </a>
  );
}

export default function Reviews({ t, locale, reviews }: {
  t: Dictionary["reviews"]; locale: Locale; reviews: GoogleReview[];
}) {
  if (reviews.length === 0) return null;
  const h2 = REVIEWS_RATING === null ? t.h2Plain : t.h2.replace("{rating}", ratingText(locale));
  const intro = REVIEWS_COUNT === null ? t.introPlain
    : t.intro.replace("{count}", new Intl.NumberFormat(locale).format(REVIEWS_COUNT));
  return (
    <section className="bay shell" id="reviews">
      <div className="head2">
        <div data-rise>
          <p className="tag">{t.tag}</p>
          <h2 className="dsp dsp-lg" style={{ marginTop: "1.1rem" }} dangerouslySetInnerHTML={{ __html: h2 }} />
        </div>
        <div data-rise data-rise-d="1" style={{ maxWidth: "38ch" }}>
          <p className="muted">{intro}</p>
          <AllReviews t={t} />
        </div>
      </div>
      <div data-rise>
        <ReviewCards reviews={reviews} t={t} locale={locale} />
      </div>
    </section>
  );
}
