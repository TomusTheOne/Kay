import { notFound } from "next/navigation";
import { isLocale, getDictionary, pathFor, mapCopy } from "@/lib/i18n";
import { guideMetadata, type GuideImage } from "@/lib/guide";
import { cenoteTitle, cenoteQa, cenoteFacts, guideBreadcrumb, planAlt, planCredit, siteNodes } from "@/lib/seo";
import { PRODUCTS, SHOP, ogCard } from "@/content/products";
import { CENOTES, GUIDE_PUBLISHED, GUIDE_UPDATED, cenoteBySlug, diveDepth, fromTulum, related, mapFile,
         type Cenote } from "@/content/cenotes";
import type { Dictionary, Locale } from "@/lib/i18n";
import GuideFrame from "@/components/GuideFrame";
import CenoteMap from "@/components/CenoteMap";
import CenoteCard from "@/components/CenoteCard";
import Plate from "@/components/Plate";
import CenotePlan from "@/components/CenotePlan";

export function generateStaticParams() {
  return CENOTES.map((c) => ({ slug: c.slug }));
}
export const dynamicParams = false;

type Params = { params: Promise<{ locale: string; slug: string }> };

/**
 * The card a link to this page travels with: the cenote's photograph, or its
 * dive map where there is no photograph — that one carries words, so it is
 * cut once per language. Built by scripts/og-cenotes.py. A cenote with
 * neither travels with the locale's own card.
 */
function socialCard(t: Dictionary, locale: Locale, c: Cenote): GuideImage {
  const g = t.cenotes;
  if (c.photo) return { url: `/assets/og/cenotes/${c.slug}.jpg`, width: 1200, height: 630,
                        alt: g.alts[c.slug] ?? c.name };
  if (c.plan) return { url: `/assets/og/cenotes/${c.slug}-${locale}.jpg`, width: 1200, height: 630,
                       alt: planAlt(t, locale, c) };
  return { url: ogCard(locale), width: 1200, height: 630, alt: t.hero.alt };
}

export async function generateMetadata({ params }: Params) {
  const { locale, slug } = await params;
  const c = cenoteBySlug(slug);
  if (!isLocale(locale) || !c) return {};
  const t = await getDictionary(locale);
  return guideMetadata(locale, `/cenotes/${slug}/`, cenoteTitle(t, c), t.cenotes.metas[slug],
    /* An approximate position is not published as a coordinate: the page
       keeps Tulum's until Kay has placed the cenote. */
    { image: socialCard(t, locale, c), geo: c.approx ? undefined : { lat: c.lat, lon: c.lon, place: c.name } });
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
  const qa = cenoteQa(t, locale, c);
  const card = socialCard(t, locale, c);
  const images = [
    ...(c.photo ? [`${SHOP.domain}/assets/photos/${c.photo}.webp`] : []),
    ...(c.plan ? [`${SHOP.domain}${mapFile(c, locale, c.plan.w * 2, "webp")}`] : []),
  ];
  /* One graph, as on the home page: the page, the place it is about, how it
     sits in the site, and the questions it answers — all tied by @id to the
     business the home page describes. */
  const ld = {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "WebPage",
        "@id": `${url}#page`,
        url,
        name: cenoteTitle(t, c),
        description: g.metas[slug],
        inLanguage: locale,
        isPartOf: { "@id": `${SHOP.domain}/#website` },
        about: { "@id": `${url}#cenote` },
        breadcrumb: { "@id": `${url}#breadcrumb` },
        dateModified: GUIDE_UPDATED,
        primaryImageOfPage: `${SHOP.domain}${card.url}`,
        publisher: { "@id": `${SHOP.domain}/#business` },
      },
      {
        "@type": "TouristAttraction",
        "@id": `${url}#cenote`,
        name: c.name,
        ...(c.aka ? { alternateName: c.aka } : {}),
        description: g.items[slug],
        url,
        ...(c.approx ? {} : { geo: { "@type": "GeoCoordinates", latitude: c.lat, longitude: c.lon } }),
        containedInPlace: { "@type": "City", name: "Tulum" },
        touristType: g.touristType,
        ...(images.length ? { image: images } : {}),
        ...(c.plan ? { hasMap: images[images.length - 1] } : {}),
        additionalProperty: cenoteFacts(t, c),
      },
      { ...guideBreadcrumb(t, locale, c), "@id": `${url}#breadcrumb` },
      ...siteNodes(locale),
      {
        "@type": "FAQPage",
        "@id": `${url}#faq`,
        mainEntity: qa.map((x) => ({
          "@type": "Question", name: x.q,
          acceptedAnswer: { "@type": "Answer", text: x.a },
        })),
      },
    ],
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

      {c.plan && (
        <section className="bay shell guide__plan" aria-labelledby="plan-tag">
          <h2 className="tag" id="plan-tag">{g.planTag}</h2>
          <figure>
            <CenotePlan c={c} plan={c.plan} locale={locale} open={g.planOpen}
                        alt={planAlt(t, locale, c)} />
            <figcaption className="data guide__plan-note">
              {g.planNote}
              {" "}{planCredit(t, locale, c.plan.credit)}
            </figcaption>
          </figure>
        </section>
      )}

      <section className="wide guide__map" aria-label={g.mapLabel}>
        <CenoteMap t={mapCopy(g)} base={guide} focus={slug} preview={!GUIDE_PUBLISHED} tall={false} />
      </section>

      <section className="bay shell guide__qa" aria-labelledby="qa-h">
        <p className="tag">{g.faqTag}</p>
        <h2 className="dsp dsp-md" id="qa-h" style={{ marginTop: "1rem" }}>{g.faqH2.replace("{name}", c.name)}</h2>
        <div className="faq" style={{ marginTop: "1.6rem" }}>
          {qa.map((x) => (
            <details key={x.q} open>
              <summary><h3>{x.q}</h3></summary>
              <p>{x.a}</p>
            </details>
          ))}
        </div>
      </section>

      <section className="bay shell">
        <h2 className="tag">{g.nearbyTag}</h2>
        <div className="dives cgrid" style={{ marginTop: "1.6rem" }}>
          {related(c).map((o) => (
            <CenoteCard key={o.slug} c={o} t={g} href={`${guide}${o.slug}/`} alt={photoAlt(o)} brief />
          ))}
        </div>
      </section>
    </GuideFrame>
  );
}
