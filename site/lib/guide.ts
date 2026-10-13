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
 *
 * A product page passes its path per language, since its slug is translated,
 * and is indexed whether or not the guide is published.
 */
export function guideMetadata(locale: Locale, path: string | Record<Locale, string>, title: string,
  description: string,
  { image, geo, indexed = GUIDE_PUBLISHED }: {
    image?: GuideImage; geo?: { lat: number; lon: number; place: string }; indexed?: boolean;
  } = {}): Metadata {
  const at = (l: Locale) => `${SHOP.domain}${pathFor(l, typeof path === "string" ? path : path[l])}`;
  const url = at(locale);
  const card: GuideImage = image ?? {
    url: ogCard(locale), alt: "Kay Diving Tulum", width: 1200, height: 630,
  };
  return {
    title, description,
    alternates: {
      canonical: url,
      languages: {
        ...Object.fromEntries(LIVE_LOCALES.map((l) => [l, at(l)])),
        "x-default": at(DEFAULT_LOCALE),
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
    ...(indexed ? {} : { robots: { index: false, follow: false } }),
  };
}
