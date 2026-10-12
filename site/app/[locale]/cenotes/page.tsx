import { notFound } from "next/navigation";
import { isLocale, getDictionary, pathFor, cardCopy } from "@/lib/i18n";
import { guideMetadata } from "@/lib/guide";
import { guideBreadcrumb } from "@/lib/seo";
import { SHOP, ogCard } from "@/content/products";
import { PRODUCTS } from "@/content/products";
import { CENOTES, GUIDE_PUBLISHED, GUIDE_UPDATED } from "@/content/cenotes";
import GuideFrame from "@/components/GuideFrame";
import CenoteExplorer from "@/components/CenoteExplorer";

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  if (!isLocale(locale)) return {};
  const t = await getDictionary(locale);
  return guideMetadata(locale, "/cenotes/", t.cenotes.metaTitle, t.cenotes.metaDescription, {
    image: { url: ogCard(locale), alt: t.hero.alt, width: 1200, height: 630 },
  });
}

export default async function Guide({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  if (!isLocale(locale)) notFound();
  const t = await getDictionary(locale);

  /* A cenote's own photograph has its own alt text; failing that it borrows
     the one of the product card it was taken for. */
  const alts = Object.fromEntries(CENOTES.filter((c) => c.photo).map((c) => {
    const p = PRODUCTS.find((x) => x.photo === c.photo);
    return [c.slug, t.cenotes.alts[c.slug] ?? (p ? t.products.items[p.slug].alt : c.name)];
  }));

  /* The guide as a collection of the places it lists, each one the same @id
     its own page gives it, so the two pages describe one thing. */
  const url = `${SHOP.domain}${pathFor(locale, "/cenotes/")}`;
  const ld = {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "CollectionPage",
        "@id": `${url}#page`,
        url,
        name: t.cenotes.metaTitle,
        description: t.cenotes.metaDescription,
        inLanguage: locale,
        isPartOf: { "@id": `${SHOP.domain}/#website` },
        breadcrumb: { "@id": `${url}#breadcrumb` },
        mainEntity: { "@id": `${url}#list` },
        dateModified: GUIDE_UPDATED,
      },
      {
        "@type": "ItemList",
        "@id": `${url}#list`,
        numberOfItems: CENOTES.length,
        itemListElement: CENOTES.map((c, i) => {
          const page = `${SHOP.domain}${pathFor(locale, `/cenotes/${c.slug}/`)}`;
          return {
            "@type": "ListItem", position: i + 1, url: page,
            item: { "@type": "TouristAttraction", "@id": `${page}#cenote`, name: c.name, url: page,
                    ...(c.approx ? {} : { geo: { "@type": "GeoCoordinates", latitude: c.lat, longitude: c.lon } }) },
          };
        }),
      },
      { ...guideBreadcrumb(t, locale), "@id": `${url}#breadcrumb` },
    ],
  };

  return (
    <GuideFrame t={t} locale={locale} path="/cenotes/">
      {GUIDE_PUBLISHED && (
        <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(ld) }} />
      )}
      <section className="bay shell guide__head">
        <p className="tag">{t.cenotes.tag}</p>
        <h1 className="dsp dsp-lg" dangerouslySetInnerHTML={{ __html: t.cenotes.h1 }} />
        <p className="lede">{t.cenotes.intro}</p>
      </section>
      <section className="guide__explore" aria-label={t.cenotes.mapLabel}>
        <CenoteExplorer t={cardCopy(t.cenotes)} base={pathFor(locale, "/cenotes/")} alts={alts} preview={!GUIDE_PUBLISHED} />
      </section>
    </GuideFrame>
  );
}
