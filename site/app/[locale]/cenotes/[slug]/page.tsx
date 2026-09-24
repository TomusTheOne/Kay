import { notFound } from "next/navigation";
import { isLocale, getDictionary, pathFor, mapCopy } from "@/lib/i18n";
import { guideMetadata } from "@/lib/guide";
import { PRODUCTS, SHOP } from "@/content/products";
import { CENOTES, GUIDE_PUBLISHED, cenoteBySlug, diveDepth, fromTulum, nearest, type Cenote } from "@/content/cenotes";
import GuideFrame from "@/components/GuideFrame";
import CenoteMap from "@/components/CenoteMap";
import CenoteCard from "@/components/CenoteCard";
import Plate from "@/components/Plate";

export function generateStaticParams() {
  return CENOTES.map((c) => ({ slug: c.slug }));
}
export const dynamicParams = false;

type Params = { params: Promise<{ locale: string; slug: string }> };

/** Kay's text, cut after its first sentence, for the meta description. */
const firstSentence = (s: string) => s.match(/^.+?[.!?](?=\s|$)/)?.[0] ?? s;

export async function generateMetadata({ params }: Params) {
  const { locale, slug } = await params;
  const c = cenoteBySlug(slug);
  if (!isLocale(locale) || !c) return {};
  const t = await getDictionary(locale);
  return guideMetadata(locale, `/cenotes/${slug}/`,
    `${c.name} — ${t.cenotes.tag} | Kay Diving`, firstSentence(t.cenotes.items[slug]));
}

export default async function CenotePage({ params }: Params) {
  const { locale, slug } = await params;
  const c = cenoteBySlug(slug);
  if (!isLocale(locale) || !c) notFound();
  const t = await getDictionary(locale);
  const g = t.cenotes;
  const away = fromTulum(c);
  const guide = pathFor(locale, "/cenotes/");
  const photoAlt = (o: Cenote) => {
    if (!o.photo) return g.artAlt.replace("{name}", o.name);
    const p = PRODUCTS.find((x) => x.photo === o.photo);
    return g.alts[o.slug] ?? (p ? t.products.items[p.slug].alt : o.name);
  };
  const url = `${SHOP.domain}${pathFor(locale, `/cenotes/${slug}/`)}`;
  const ld = {
    "@context": "https://schema.org",
    "@type": "TouristAttraction",
    "@id": `${url}#cenote`,
    name: c.name,
    ...(c.aka ? { alternateName: c.aka } : {}),
    description: g.items[slug],
    url,
    inLanguage: locale,
    geo: { "@type": "GeoCoordinates", latitude: c.lat, longitude: c.lon },
    containedInPlace: { "@type": "City", name: "Tulum" },
    touristType: "Scuba divers",
    ...(c.photo ? { image: `${SHOP.domain}/assets/photos/${c.photo}.webp` } : {}),
    provider: { "@id": `${SHOP.domain}/#business` },
  };

  return (
    <GuideFrame t={t} locale={locale} path={`/cenotes/${slug}/`}>
      {GUIDE_PUBLISHED && (
        <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(ld) }} />
      )}
      <article className="bay shell guide__cenote">
        <a className="guide__back data" href={guide}>← {g.back}</a>
        <div className="split guide__split">
          <div>
            <p className="tag">{g.types[c.type]}</p>
            <h1 className="dsp dsp-lg" style={{ marginTop: "1rem" }}>{c.name}</h1>
            {c.aka && <p className="data" style={{ marginTop: ".8rem" }}>{c.aka}</p>}
            <p className="lede" style={{ marginTop: "1.4rem" }}>{g.items[slug]}</p>

            <dl className="spec guide__spec">
              <div><dt>{g.diveDepth}</dt><dd>{diveDepth(c, g.upTo)}</dd></div>
              {c.depthM !== null && c.depthM > c.diveMaxM && (
                <div><dt>{g.cenoteDepth}</dt><dd>{c.depthM} m</dd></div>
              )}
              <div><dt>{g.levelTag}</dt><dd>{g.levels[c.level]}</dd></div>
              <div><dt>{g.fromTulum}</dt><dd>{away.km} km {g.compass[away.dir]}</dd></div>
              {c.snorkel && <div><dt>Snorkel</dt><dd>{g.snorkelToo}</dd></div>}
            </dl>

            <p className="tag tag--plain" style={{ marginTop: "1.8rem" }}>{g.divesTag}</p>
            <ul className="guide__dives">
              {c.products.map((p) => (
                <li key={p}><a href={`${pathFor(locale)}#products`}>{t.products.items[p].name}</a></li>
              ))}
            </ul>
            <a className="btn btn--lit" style={{ marginTop: "1.6rem" }} href={`${pathFor(locale)}#book`}>
              {t.nav.reserveLong}
            </a>
          </div>
          <figure className="plate guide__plate">
            <Plate photo={c.photo} art={c.art} alt={photoAlt(c)} eager />
            {!c.photo && <figcaption className="plate__note data">{g.illustration}</figcaption>}
          </figure>
        </div>
      </article>

      <section className="wide guide__map" aria-label={g.mapLabel}>
        <CenoteMap t={mapCopy(g)} base={guide} focus={slug} preview={!GUIDE_PUBLISHED} tall={false} />
      </section>

      <section className="bay shell">
        <p className="tag">{g.nearbyTag}</p>
        <div className="dives cgrid" style={{ marginTop: "1.6rem" }}>
          {nearest(c).map((o) => (
            <CenoteCard key={o.slug} c={o} t={g} href={`${guide}${o.slug}/`} alt={photoAlt(o)} />
          ))}
        </div>
      </section>
    </GuideFrame>
  );
}
