import type { MetadataRoute } from "next";
import { LIVE_LOCALES, pathFor } from "@/lib/i18n";
import { SHOP, CONTENT_UPDATED } from "@/content/products";

/** One entry per locale, each declaring its siblings so Google pairs them. */
export default function sitemap(): MetadataRoute.Sitemap {
  const languages = Object.fromEntries(
    LIVE_LOCALES.map((l) => [l, `${SHOP.domain}${pathFor(l)}`]),
  );

  return LIVE_LOCALES.map((locale) => ({
    url: `${SHOP.domain}${pathFor(locale)}`,
    lastModified: new Date(CONTENT_UPDATED),
    changeFrequency: "monthly" as const,
    priority: locale === "en" ? 1 : 0.8,
    alternates: { languages },
  }));
}

export const dynamic = "force-static";
