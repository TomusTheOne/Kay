import type { Metadata } from "next";
import { LIVE_LOCALES, DEFAULT_LOCALE, OG_LOCALE, pathFor, type Locale } from "@/lib/i18n";
import { SHOP } from "@/content/products";
import { GUIDE_PUBLISHED } from "@/content/cenotes";

/**
 * Metadata for a page of the cenote guide. The locale layout's canonical and
 * Open Graph URL are the home page's, so each guide page states its own —
 * and, until the guide is published, asks not to be indexed at all.
 */
export function guideMetadata(locale: Locale, path: string, title: string, description: string): Metadata {
  const url = `${SHOP.domain}${pathFor(locale, path)}`;
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
      images: [{ url: `/assets/og/${locale}.jpg`, width: 1200, height: 630, type: "image/jpeg" }],
    },
    ...(GUIDE_PUBLISHED ? {} : { robots: { index: false, follow: false } }),
  };
}
