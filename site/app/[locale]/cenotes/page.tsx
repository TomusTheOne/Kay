import { notFound } from "next/navigation";
import { isLocale, getDictionary, pathFor } from "@/lib/i18n";
import { guideMetadata } from "@/lib/guide";
import { PRODUCTS } from "@/content/products";
import { CENOTES, GUIDE_PUBLISHED } from "@/content/cenotes";
import GuideFrame from "@/components/GuideFrame";
import CenoteExplorer from "@/components/CenoteExplorer";

export async function generateMetadata({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  if (!isLocale(locale)) return {};
  const t = await getDictionary(locale);
  return guideMetadata(locale, "/cenotes/", t.cenotes.metaTitle, t.cenotes.metaDescription);
}

export default async function Guide({ params }: { params: Promise<{ locale: string }> }) {
  const { locale } = await params;
  if (!isLocale(locale)) notFound();
  const t = await getDictionary(locale);

  /* A photograph borrows the alt text of the product card it was taken for. */
  const alts = Object.fromEntries(CENOTES.filter((c) => c.photo).map((c) => {
    const p = PRODUCTS.find((x) => x.photo === c.photo);
    return [c.slug, p ? t.products.items[p.slug].alt : c.name];
  }));

  return (
    <GuideFrame t={t} locale={locale} path="/cenotes/">
      <section className="bay shell guide__head">
        <p className="tag">{t.cenotes.tag}</p>
        <h1 className="dsp dsp-lg" dangerouslySetInnerHTML={{ __html: t.cenotes.h1 }} />
        <p className="lede">{t.cenotes.intro}</p>
      </section>
      <section className="guide__explore" aria-label={t.cenotes.mapLabel}>
        <CenoteExplorer t={t.cenotes} base={pathFor(locale, "/cenotes/")} alts={alts} preview={!GUIDE_PUBLISHED} />
      </section>
    </GuideFrame>
  );
}
