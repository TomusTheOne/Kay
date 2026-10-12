import type { Metadata } from "next";
import { LIVE_LOCALES, DEFAULT_LOCALE, OG_LOCALE, pathFor, type Locale } from "@/lib/i18n";
import { SHOP, ogCard } from "@/content/products";
import { GUIDE_PUBLISHED } from "@/content/cenotes";
import { geoMeta } from "@/lib/seo";

export interface GuideImage { url: string; alt: string; width: number; height: number }

/**
 * Metadata for a page of the cenote guide. The locale layout's canonical,
 * Open Graph and Twitter card are the home page's, so each guide page states
 * its own — and, until the guide is published, asks not to be indexed at all.
 * A cenote page passes its own card and position; the guide index keeps the
 * locale's card and Tulum's.
 */
export function guideMetadata(locale: Locale, path: string, title: string, description: string,
  { image, geo }: { image?: GuideImage; geo?: { lat: number; lon: number; place: string } } = {}): Metadata {
  const url = `${SHOP.domain}${pathFor(locale, path)}`;
  const card: GuideImage = image ?? {
    url: ogCard(locale), alt: "Kay Diving Tulum", width: 1200, height: 630,
  };
  return {
    title, description,
    alternates: {
      canonical: url,
      languages: {
        ...Object.fromEntries(LIVE_LOCALES.map((l) => [l, `${SHOP.domain}${pathFor(l, path)}`])),
        "x-default": `${SHOP.domain}${pathFor(DEFAULT_LOCALE, path)}`,
      },
    },
    openGraph: {
      type: "website", siteName: "Kay Diving Tulum", url, title, description,
      locale: OG_LOCALE[locale],
      alternateLocale: LIVE_LOCALES.filter((l) => l !== locale).map((l) => OG_LOCALE[l]),
      images: [{ ...card, type: "image/jpeg" }],
    },
    twitter: { card: "summary_large_image", title, description, images: [{ url: card.url, alt: card.alt }] },
    other: geoMeta(geo?.lat ?? SHOP.lat, geo?.lon ?? SHOP.lon, geo?.place),
    ...(GUIDE_PUBLISHED ? {} : { robots: { index: false, follow: false } }),
  };
}
