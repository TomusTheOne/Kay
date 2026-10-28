import { REVIEWS } from "@/content/reviews";

/** One star, drawn rather than typed: a ★ glyph renders differently in every
    font on every platform, and five of them have to line up. */
const Star = () => (
  <svg viewBox="0 0 20 19" aria-hidden="true" focusable="false">
    <path d="M10 0l2.6 6.3 6.8.5-5.2 4.4 1.6 6.6L10 14.3 4.2 17.8l1.6-6.6L.6 6.8l6.8-.5z" />
  </svg>
);

export default function Reviews({ t }: { t: { stars: string; onGoogle: string } }) {
  return (
    <div className="revs">
      {REVIEWS.map((r, i) => (
        <figure className="rev" key={r.name} data-rise data-rise-d={String(i % 3)}>
          <div className="rev__stars" role="img" aria-label={t.stars.replace("{n}", String(r.stars))}>
            {Array.from({ length: r.stars }, (_, s) => <Star key={s} />)}
          </div>
          {/* lang, because the page may be in Spanish and the review in
              English: a screen reader that reads it with Spanish phonemes is
              unintelligible, and so is a browser translation that misses it. */}
          <blockquote className="rev__text" lang={r.lang}>{r.text}</blockquote>
          <figcaption className="rev__who">
            {/* The initial, not the photo: Google's avatars are hotlinked from
                their servers, which their terms do not allow and which would
                put a third-party request on every page load. */}
            <span className="rev__mark" aria-hidden="true">{r.name.slice(0, 1)}</span>
            <span>
              <b className="rev__name">{r.name}</b>
              <span className="data">{t.onGoogle} · {r.year}</span>
            </span>
          </figcaption>
        </figure>
      ))}
    </div>
  );
}
