import Plate from "@/components/Plate";
import { diveDepth, type Cenote } from "@/content/cenotes";
import type { CardCopy } from "@/lib/i18n";

/**
 * One cenote in a grid: picture, type, depth, the card to show, and the start
 * of Kay's text. Plain markup — rendered by the server on the pages, and by
 * the filter on the client, from the same component. `level` follows the
 * page's outline: h2 on the guide, where the cards sit right under the h1;
 * h3 on a cenote page, under its "nearby" heading.
 */
export default function CenoteCard({ c, t, href, alt, level = 3 }: {
  c: Cenote; t: CardCopy; href: string; alt: string; level?: 2 | 3;
}) {
  const Name = level === 2 ? "h2" : "h3";
  return (
    <article className="dive ccard">
      <a className="ccard__hit" href={href} aria-label={c.name} />
      <div className="dive__media">
        <Plate photo={c.photo} art={c.art} alt={c.photo ? alt : t.artAlt.replace("{name}", c.name)} />
        <span className="dive__depth">{diveDepth(c, t.upTo).toUpperCase()}</span>
        {!c.photo && <span className="ccard__art">{t.illustration}</span>}
      </div>
      <div className="dive__body">
        <p className="tag tag--plain">{t.types[c.type]}</p>
        <Name className="dive__name">{c.name}</Name>
        {c.aka && <p className="data" style={{ marginTop: "-.3rem" }}>{c.aka}</p>}
        <p className="dive__txt ccard__txt">{t.items[c.slug]}</p>
        <dl className="spec">
          <div><dt>{t.levelTag}</dt><dd>{t.levels[c.level]}</dd></div>
          {c.snorkel && <div><dt>Snorkel</dt><dd>{t.snorkelToo}</dd></div>}
        </dl>
        <span className="dive__go">{t.see}
          <svg width="14" height="8" viewBox="0 0 14 8" fill="none" aria-hidden="true">
            <path d="M1 4h11M9 1l3 3-3 3" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" />
          </svg>
        </span>
      </div>
    </article>
  );
}
