import { notFound } from "next/navigation";
import { isLocale, getDictionary, pathFor, type Locale } from "@/lib/i18n";
import { guideMetadata } from "@/lib/guide";
import { productQa, productNode, includesFor, priceLine, cenotePhotoAlt, siteNodes } from "@/lib/seo";
import { PRODUCTS, SHOP, CONTENT_UPDATED, ogCard, bookHref, type Product } from "@/content/products";
import { PRODUCT_PATHS, NEXT_PRODUCTS, productPath, productPaths, productBySegment } from "@/content/pages";
import { CENOTES, GUIDE_PUBLISHED } from "@/content/cenotes";
import { reviewsFor } from "@/content/reviews";
import GuideFrame from "@/components/GuideFrame";
import Plate from "@/components/Plate";
import CenoteCard from "@/components/CenoteCard";
import { ReviewCards, AllReviews } from "@/components/Reviews";

/**
 * One product's own page: /en/padi-open-water-course-tulum/,
 * /es/bautismo-de-buceo-tulum/… The home page lists all seven; this is the
 * page a search for one of them should land on, so it says everything about
 * that one — what it is, what it needs, what the price covers, where it goes,
 * the questions people ask — and ends, like every section of the site, at the
 * booking form, opened on this product.
 *
 * The slug is translated (content/pages.ts), so the language switcher and the
 * hreflang set are given every language's path.
 */
export function generateStaticParams({ params }: { params: { locale: string } }) {
  return PRODUCTS.map((p) => ({ page: PRODUCT_PATHS[p.slug][params.locale as "en"] }));
}
export const dynamicParams = false;

type Params = { params: Promise<{ locale: string; page: string }> };

/** The card a shared link travels with: the product's photograph, cut by scripts/og-cenotes.py. */
function socialCard(t: Awaited<ReturnType<typeof getDictionary>>, locale: Locale, p: Product) {
  return p.photo
    ? { url: `/assets/og/products/${p.slug}.jpg`, width: 1200, height: 630, alt: t.products.items[p.slug].alt }
    : { url: ogCard(locale), width: 1200, height: 630, alt: t.hero.alt };
}

export async function generateMetadata({ params }: Params) {
  const { locale, page } = await params;
  const p = isLocale(locale) ? productBySegment(locale, page) : undefined;
  if (!isLocale(locale) || !p) return {};
  const t = await getDictionary(locale);
  const copy = t.product.items[p.slug];
  return guideMetadata(locale, productPaths(p.slug), copy.title, copy.meta,
    { image: socialCard(t, locale, p), indexed: true });
}

/** The cenotes a product goes to, from the guide: the snorkel tour's are the ones that suit snorkelling. */
function placesFor(p: Product) {
  return p.kind === "snorkel" ? CENOTES.filter((c) => c.snorkel) : CENOTES.filter((c) => c.products.includes(p.slug));
}

const Arrow = () => (
  <svg width="14" height="8" viewBox="0 0 14 8" fill="none" aria-hidden="true">
    <path d="M1 4h11M9 1l3 3-3 3" stroke="currentColor" strokeWidth="1.4"
          strokeLinecap="round" strokeLinejoin="round" />
  </svg>
);

export default async function ProductPage({ params }: Params) {
  const { locale, page } = await params;
  if (!isLocale(locale)) notFound();
  const p = productBySegment(locale, page);
  if (!p) notFound();
  const t = await getDictionary(locale);
  const g = t.product;
  const copy = g.items[p.slug];
  const card = t.products.items[p.slug];
  const home = pathFor(locale);
  const path = productPath(locale, p.slug);
  const url = `${SHOP.domain}${pathFor(locale, path)}`;
  const book = bookHref(home, p.slug);
  const qa = productQa(t, locale, p);
  const places = GUIDE_PUBLISHED ? placesFor(p) : [];
  const reviews = reviewsFor(locale, { product: p.slug });
  const others = NEXT_PRODUCTS[p.slug].map((s) => PRODUCTS.find((o) => o.slug === s)!);
  /* WhatsApp opens on a first line that already names the product. */
  const whatsapp = `https://wa.me/${SHOP.phone.replace(/\D/g, "")}?text=`
    + encodeURIComponent(g.whatsappText.replace("{the}", copy.the));

  const ld = {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "WebPage",
        "@id": `${url}#page`,
        url, name: copy.title, description: copy.meta, inLanguage: locale,
        isPartOf: { "@id": `${SHOP.domain}/#website` },
        about: { "@id": `${SHOP.domain}/#product-${p.slug}` },
        breadcrumb: { "@id": `${url}#breadcrumb` },
        ...(p.photo ? { primaryImageOfPage: `${SHOP.domain}/assets/photos/${p.photo}.webp` } : {}),
        dateModified: CONTENT_UPDATED,
      },
      productNode(t, locale, p),
      {
        "@type": "BreadcrumbList",
        "@id": `${url}#breadcrumb`,
        itemListElement: [
          { "@type": "ListItem", position: 1, name: t.cenotes.breadcrumbHome, item: `${SHOP.domain}${home}` },
          { "@type": "ListItem", position: 2, name: card.name, item: url },
        ],
      },
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
    <GuideFrame t={t} locale={locale} path={path} paths={productPaths(p.slug)} preview={false}>
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(ld) }} />
      <article className="bay shell guide__cenote">
        <a className="guide__back data" href={`${home}#products`}>← {g.back}</a>
        <div className="split guide__split">
          <div>
            <p className="tag">{g.kinds[p.kind]}</p>
            <h1 className="dsp dsp-lg" style={{ marginTop: "1rem" }}>{copy.h1}</h1>
            <p className="data" style={{ marginTop: ".8rem", color: "var(--turq)" }}>{card.tagline}</p>
            <p className="lede" style={{ marginTop: "1.4rem" }}>{copy.body[0]}</p>
            {copy.body.slice(1).map((b) => <p className="muted product__p" key={b}>{b}</p>)}

            <dl className="spec guide__spec">
              <div><dt>{t.products.needs}</dt><dd>{t.products.level[p.level]}</dd></div>
              {p.maxDepthM && <div><dt>{t.products.depth}</dt><dd>{p.maxDepthM} m · {p.maxDepthFt} ft</dd></div>}
              <div><dt>{t.products.where}</dt><dd>{p.locations.map((l) => t.products.places[l]).join(" · ")}</dd></div>
              {p.diveTime && <div><dt>{t.products.time}</dt><dd>{p.diveTime}</dd></div>}
              {p.season && <div><dt>{t.products.seasonTag}</dt><dd>{t.products.seasonValue[p.slug]}</dd></div>}
            </dl>

            <ul className="prices product__prices" aria-label={g.priceTag}>
              {p.options.map((o) => (
                <li key={o.dives}>
                  <span>{o.dives
                    ? `${o.dives} ${o.dives === 1 ? t.products.dive : t.products.dives}`
                    : t.products.halfDay}</span>
                  <b>${o.price}</b>
                </li>
              ))}
              <li className="prices__note"><span>{t.products.perDiver}</span></li>
            </ul>

            <div className="hero__cta">
              <a className="btn btn--lit" href={book}>{g.bookKind[p.kind]} <Arrow /></a>
              <a className="btn btn--ghost" href={whatsapp} rel="noopener">{g.ask}</a>
            </div>
          </div>
          <figure className="plate guide__plate">
            <Plate photo={p.photo} art={p.art} alt={card.alt} eager />
          </figure>
        </div>
      </article>

      <section className="bay shell" aria-labelledby="inc-h">
        <h2 className="tag" id="inc-h">{g.includedTag}</h2>
        <div className="courses" style={{ marginTop: "1.6rem" }}>
          {includesFor(p).map((k) => (
            <article className="course" key={k}>
              <h3 className="course__n">{t.included.items[k].name}</h3>
              <p className="course__d">{t.included.items[k].text}</p>
            </article>
          ))}
        </div>
      </section>

      {places.length > 0 && (
        <section className="bay shell" aria-labelledby="where-h">
          <h2 className="tag" id="where-h">{p.kind === "snorkel" ? g.snorkelWhere : g.whereTag}</h2>
          <div className="dives cgrid cgrid--fill" style={{ marginTop: "1.6rem" }}>
            {places.map((c) => (
              <CenoteCard key={c.slug} c={c} t={t.cenotes} href={pathFor(locale, `/cenotes/${c.slug}/`)}
                          alt={cenotePhotoAlt(t, c)} brief />
            ))}
          </div>
        </section>
      )}

      {reviews.length > 0 && (
        <section className="bay shell" aria-labelledby="rev-h">
          <p className="tag">{t.reviews.tag}</p>
          <h2 className="dsp dsp-md" id="rev-h" style={{ marginTop: "1rem" }}>{card.name}</h2>
          <div style={{ marginTop: "1.6rem" }}>
            <ReviewCards reviews={reviews} t={t.reviews} locale={locale} />
          </div>
          <AllReviews t={t.reviews} />
        </section>
      )}

      <section className="bay shell guide__qa" aria-labelledby="qa-h">
        <p className="tag">{g.faqTag}</p>
        <h2 className="dsp dsp-md" id="qa-h" style={{ marginTop: "1rem" }}>{g.faqH2}</h2>
        <div className="faq" style={{ marginTop: "1.6rem" }}>
          {qa.map((x) => (
            <details key={x.q} open>
              <summary><h3>{x.q}</h3></summary>
              <p>{x.a}</p>
            </details>
          ))}
        </div>
        <div className="hero__cta">
          <a className="btn btn--lit" href={book}>{g.bookKind[p.kind]} <Arrow /></a>
        </div>
      </section>

      <section className="bay shell" aria-labelledby="more-h">
        <h2 className="tag" id="more-h">{g.otherTag}</h2>
        <div className="dives cgrid cgrid--fill" style={{ marginTop: "1.6rem" }}>
          {others.map((o) => (
            <article className="dive ccard" key={o.slug}>
              <div className="dive__media">
                <Plate photo={o.photo} art={o.art} alt={t.products.items[o.slug].alt} full={960}
                       sizes="(max-width: 640px) 90vw, (max-width: 1000px) 45vw, 300px" />
              </div>
              <div className="dive__body">
                <p className="tag tag--plain">{g.kinds[o.kind]}</p>
                <h3 className="dive__name">
                  <a className="ccard__hit" href={pathFor(locale, productPath(locale, o.slug))}>
                    {t.products.items[o.slug].name}
                  </a>
                </h3>
                <p className="dive__txt">{t.products.items[o.slug].tagline}</p>
                <p className="data"><b>{priceLine(t, locale, o)}</b></p>
              </div>
            </article>
          ))}
        </div>
      </section>
    </GuideFrame>
  );
}
